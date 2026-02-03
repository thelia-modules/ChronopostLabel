<?php

namespace ChronopostLabel\Service;

use ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrder;
use ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostPickupPoint\Model\ChronopostPickupPointOrder;
use ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Log\Tlog;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\URL;

class LabelService
{
    protected $dispatcher;

    /**
     * UpdateDeliveryAddressListener constructor.
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    public function generateLabel($data)
    {
        $orderId = $data['order_id'];
        $weight = $data['weight'];

        if(!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        if ($chronopostOrder == null) {
            return new JsonResponse([
                'error' => "No order found with this id : ".$orderId
            ]);
        }
        /** @var Order $order */
        $order = $chronopostOrder->getOrder();

        $this->createLabel($chronopostOrder,'default', null, $weight);

        return new JsonResponse([
            'id' => $chronopostOrder->getLabelNumber(),
            'url' => URL::getInstance()->absoluteUrl('/admin/module/ChronopostLabel/getLabel/' . $order->getId()),
            'number' => $order->getRef(),
            'order' => [
                'id' => $order->getId(),
                'status' => [
                    'id' => $order->getOrderStatus()->getId()
                ]
            ]
        ]);
    }

    /**
     * @param $chronopostOrder
     * @param string|null $statusOption
     * @param null $otherStatus
     * @param null $weight
     * @return null
     * @throws PropelException
     * @throws \SoapFault
     */
    public function createLabel($chronopostOrder, ?string $statusOption = 'default', $otherStatus = null, $weight = null): void
    {
        $order = OrderQuery::create()->findOneById($chronopostOrder->getOrderId());
        if (null === $order) {
            throw new \Exception("Order not found for Chronopost order ID " . $chronopostOrder->getId());
        }

        $APIDatas = [];

        $reference = $order->getRef();
        $config = ChronopostLabelConst::getConfig();

        $log = Tlog::getInstance();
        $log->notice("#CHRONOPOST // L'étiquette de la commande " . $reference . " est en cours de création.");

        if ($chronopostOrder) {
            $weight = ($weight == null) ? $order->getWeight() : $weight;

            $APIDatas[] = $this->writeAPIData($order, $chronopostOrder, $weight, 1, 1);
        } else {
            $log->error("#CHRONOPOST // Impossible de trouver la commande " . $reference . " dans la table des commandes Chronopost.");
            return;
        }

        /** Send order informations to the Chronopost API */
        $soapClient = new \SoapClient(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPING_SERVICE_WSDL, array("trace" => 1, "exception" => 1));

        foreach ($APIDatas as $APIData) {

            $response = $soapClient->__soapCall('shippingV3', [$APIData]);

            if (0 !== (int) $response->return->errorCode) {
                throw new \Exception($response->return->errorMessage, $response->return->errorCode);
            }

            /** Create the label accordingly */
            $labelDir = $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR];
            $labelFilename = $response->return->skybillNumber . $this->getLabelExtension($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE]);
            $label = $labelDir . $labelFilename;

            $oldUmask = umask(0002);

            if (!is_dir($labelDir) && !mkdir($labelDir, 0775, true) && !is_dir($labelDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $labelDir));
            }
            if (false === @file_put_contents($label, $response->return->skybill)) {
                $log->error("L'étiquette n'a pas pu être sauvegardée dans " . $label);
            } else {
                @chmod($label, 0664);

                $log->notice("L'étiquette Chronopost a été sauvegardée en tant que " . $labelFilename);
                $chronopostOrder
                    ->setLabelNumber($labelFilename)
                    ->setLabelDirectory($labelDir)
                    ->save();

                $order->setDeliveryRef($response->return->skybillNumber)->save();

                $this->determineOrderStatus($config, $statusOption, $otherStatus, $order);
            }
            umask($oldUmask);
        }
    }

    /**
     * @param Order $order
     * @param $chronopostOrder
     * @param null $weight
     * @param int $idBox
     * @param int $skybillRank
     * @return mixed
     * @throws PropelException
     */
    public function writeAPIData(Order $order, $chronopostOrder, $weight = null, $idBox = 1, $skybillRank = 1)
    {
        $config = ChronopostLabelConst::getConfig();
        $customer = $order->getCustomer();

        $customerInvoiceAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        $customerDeliveryAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());

        $phone = $customerDeliveryAddress->getCellphone();

        if (null === $phone) {
            $phone = $customerDeliveryAddress->getPhone();
        }

        if (null === $weight) {
            //$weight = $this->pickingService->getOrderWeight($order->getId());
            $weight = 0;
        }

        $chronopostProductCode = $chronopostOrder->getDeliveryCode();
        $chronopostProductCode = str_pad($chronopostProductCode, 2, "0", STR_PAD_LEFT);

        $invoiceCustomerName = $this->getContactName($customerInvoiceAddress);
        $deliveryCustomerName = $this->getContactName($customerDeliveryAddress);

        /** START */

        /** HEADER */
        $APIData["headerValue"] = [
            "idEmit" => "CHRFR",
            "accountNumber" => (int)$config[ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT],
            "subAccount" => "",
        ];

        /** SHIPPER INFORMATIONS */
        $APIData["shipperValue"] = [
            "shipperCivility" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY],
            "shipperName" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1],
            "shipperName2" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2],
            "shipperAdress1" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1],
            "shipperAdress2" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2],
            "shipperZipCode" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP],
            "shipperCity" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY],
            "shipperCountry" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY],
            "shipperContactName" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME],
            "shipperEmail" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL],
            "shipperPhone" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE],
            "shipperMobilePhone" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE],
            "shipperPreAlert" => 0, // todo ?
        ];

        // Le customer chez Chronopost correspond au client de leur service, à savoir le donneur d'ordre/la société
        // possédant le site web, et non le client du site. ShipperValue sert dans le cas où un service logistique externe
        // est utilisé par cette entreprise pour l'envoi de ses colis
        /** CHRONOPOST CUSTOMER INFORMATIONS */
        $APIData["customerValue"] = [
            "customerCivility" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY],
            "customerName" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1],
            "customerName2" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2],
            "customerAdress1" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1],
            "customerAdress2" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2],
            "customerZipCode" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP],
            "customerCity" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY],
            "customerCountry" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY],
            "customerContactName" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME],
            "customerEmail" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL],
            "customerPhone" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE],
            "customerMobilePhone" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE],
            "customerPreAlert" => 0,
            "printAsSender" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS],
        ];

        /** WEBSITE CUSTOMER DELIVERY INFORMATIONS */
        $APIData["recipientValue"] = [
            "recipientName" => $customerDeliveryAddress->getCompany(),
            "recipientName2" => $deliveryCustomerName,
            "recipientAdress1" => $customerDeliveryAddress->getAddress1(),
            "recipientAdress2" => $customerDeliveryAddress->getAddress2(),
            "recipientZipCode" => $customerDeliveryAddress->getZipcode(),
            "recipientCity" => $customerDeliveryAddress->getCity(),
            "recipientCountry" => $this->getCountryIso($customerDeliveryAddress->getCountryId()),
            "recipientContactName" => $deliveryCustomerName,
            "recipientEmail" => $customer->getEmail(),
            "recipientPhone" => $phone,
            "recipientMobilePhone" => $customerDeliveryAddress->getCellphone(),
            "recipientPreAlert" => 0,
        ];

        /** RefValue */
        $APIData["refValue"] = [
            "shipperRef" => $order->getRef(),
            "recipientRef" => $customer->getRef(),
            "idRelais" => $this->getIdRelais($chronopostOrder),
        ];

        /** SKYBILL  (LABEL INFORMATIONS) */
        $APIData["skybillValue"] = [
            "bulkNumber" => $idBox,
            "skybillRank" => $skybillRank,
            "evtCode" => "DC",
            "productCode" => $chronopostProductCode,
            "shipDate" => date('c'),
            "shipHour" => (int)date('G'),
            "weight" => $weight,
            "weightUnit" => "KGM",
            "service" => $this->getServicebyDeliveryCode($chronopostOrder),
            "objectType" => "MAR", //Todo Change according to product ? Is any product a document instead of a marchandise ?
        ];

        /** SKYBILL PARAMETERS */
        $APIData["skybillParamsValue"] = [
            "mode" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE],
        ];

        /** OTHER PARAMETERS */
        $APIData["password"] = $config[ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD];
        $APIData["version"] = "2.0";

        /** EXPIRATION AND SELL-BY DATE (IN CASE OF FRESH PRODUCT) */
        if (in_array($chronopostProductCode, ["2R", "2P", "2Q", "2S", "3X", "3Y", "4V", "4W", "4X"])) {
            $APIData["scheduledValue"] = [
                "expirationDate" => date('c', mktime(0, 0, 0, date('m'), date('d') + (int)$config[ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE], date('Y'))),
                "sellByDate" => date('c'),
            ];
        }

        return $APIData;
    }

    /**
     * @param ChronopostHomeDeliveryOrder|ChronopostPickupPointOrder $chronopostOrder
     * @return string
     */
    private function getServicebyDeliveryCode(ChronopostHomeDeliveryOrder|ChronopostPickupPointOrder $chronopostOrder): string
    {
        $specificServiceCodes = ['5X', '5Y'];

        if (in_array($chronopostOrder->getDeliveryCode(), $specificServiceCodes, true)) {
            return '6';
        }

        return '0';
    }

    private function getIdRelais(ChronopostHomeDeliveryOrder|ChronopostPickupPointOrder $chronopostOrder): string
    {
        if (method_exists($chronopostOrder, 'getIdRelais')) {
            $idRelais = $chronopostOrder->getIdRelais();
            return $idRelais ?? '';
        }

        return '';
    }

    /**
     * Get the label file extension
     *
     * @param $labelType
     * @return string
     */
    private function getLabelExtension($labelType)
    {
        switch ($labelType) {
            case "SPD":
            case "THE":
            case "PDF":
                return ".pdf";
                break;
            case "Z2D":
                return ".zpl";
                break;
        }
        return ".pdf";
    }

    /**
     * @param Customer $customer
     * @return string
     * @throws PropelException
     */
    private function getChronopostCivility(Customer $customer)
    {
        $civ = $customer->getCustomerTitle()->getId();

        switch ($civ) {
            case 1:
                return 'M';
                break;
            case 2:
                return 'E';
                break;
            case 3:
                return 'L';
                break;
        }

        return 'M';
    }


    /**
     * @param $countryId
     * @return string
     */
    private function getCountryIso($countryId)
    {
        return CountryQuery::create()->findOneById($countryId)->getIsoalpha2();
    }


    /**
     * @param OrderAddress $address
     * @return string
     */
    private function getContactName(OrderAddress $address)
    {
        return $address->getFirstname() . " " . $address->getLastname();
    }

    /**
     * @throws PropelException
     */
    private function determineOrderStatus(
        array $config,
        ?string $statusOption,
        ?int $otherStatus,
        Order $order
    ): void
    {
        $statusId = null;

        if ($statusOption === 'default') {
            $statusId = (int) $config[ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS];
        } elseif ($statusOption === 'other' && $otherStatus !== null) {
            $statusId = $otherStatus;
        }
        if ($statusId === null) {
            return;
        }

        $order->setOrderStatus(
            OrderStatusQuery::create()->findOneById($statusId)
        );

        $this->dispatcher->dispatch(
            (new OrderEvent($order))->setStatus($statusId),
            TheliaEvents::ORDER_UPDATE_STATUS
        );

        $order->save();
    }

}
