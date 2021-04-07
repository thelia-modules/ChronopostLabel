<?php

namespace ChronopostLabel\Service;

use ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Log\Tlog;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
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

        $this->createLabel($chronopostOrder,'default', null, $weight);

        return new JsonResponse([
            'id' => $chronopostOrder->getLabelNumber(),
            'url' => URL::getInstance()->absoluteUrl('/admin/module/ChronopostLabel/getLabel/' . $chronopostOrder->getId()),
            'number' => $chronopostOrder->getRef(),
            'order' => [
                'id' => $chronopostOrder->getId(),
                'status' => [
                    'id' => $chronopostOrder->getOrderStatus()->getId()
                ]
            ]
        ]);
    }

    /**
     * @param $chronopostOrder
     * @param string $statusOption
     * @param null $otherStatus
     * @return null
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createLabel ($chronopostOrder, $statusOption = 'default', $otherStatus = null, $weight = null)
    {
        $order = OrderQuery::create()->findOneById($chronopostOrder->getOrderId());

        try {
            $APIDatas = [];

            $reference = $order->getRef();
            $config = ChronopostLabelConst::getConfig();


            $log = Tlog::getNewInstance();
            $log->setDestinations("\\Thelia\\Log\\Destination\\TlogDestinationFile");
            $log->setConfig("\\Thelia\\Log\\Destination\\TlogDestinationFile", 0, THELIA_ROOT . "log" . DS . "log-chronopost-label.txt");

            $log->notice("#CHRONOPOST // L'étiquette de la commande " . $reference . " est en cours de création.");

            if ($chronopostOrder) {
                $weight = ($weight == null) ? $order->getWeight() : $weight;
                $APIDatas[] = $this->writeAPIData($order, $chronopostOrder, $weight, 1, 1);
            } else {
                $log->error("#CHRONOPOST // Impossible de trouver la commande " . $reference . " dans la table des commandes Chronopost.");
                return null;
            }

            /** Send order informations to the Chronopost API */
            $soapClient = new \SoapClient(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPING_SERVICE_WSDL, array("trace" => 1, "exception" => 1));

            foreach ($APIDatas as $APIData) {

                $response = $soapClient->__soapCall('shippingV3', [$APIData]);

                if (0 != $response->return->errorCode) {
                    throw new \Exception($response->return->errorMessage, $response->return->errorCode);
                }

                /** Create the label accordingly */
                $label = $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR] . $response->return->skybillNumber . $this->getLabelExtension($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE]);

                if (false === @file_put_contents($label, $response->return->skybill)) {
                    $log->error("L'étiquette n'a pas pu être sauvegardée dans " . $label);
                } else {
                    $log->notice("L'étiquette Chronopost a été sauvegardée en tant que " . $response->return->skybillNumber . $this->getLabelExtension($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE]));
                    $chronopostOrder
                        ->setLabelNumber($response->return->skybillNumber . $this->getLabelExtension($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE]))
                        ->setLabelDirectory($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR])
                        ->save();

                    $order->setDeliveryRef($response->return->skybillNumber)->save();

                    /** Change the order status */
                    switch ($statusOption){
                        case 'default':
                            $order->setStatusId($config[ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS])->save();
                            break;

                        case 'other':
                            $order->setStatusId($otherStatus)->save();
                            break;
                    }
                }
            }

        } catch (\Exception $e) {
            Tlog::getInstance()->addError("#CHRONOPOST // Error when trying to create the label. Chronopost response : " . $e->getCode() . " - " .  $e->getMessage());
        }
    }

    /**
     * @param Order $order
     * @param $chronopostOrder
     * @param null $weight
     * @param int $idBox
     * @param int $skybillRank
     * @return mixed
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function writeAPIData(Order $order, $chronopostOrder, $weight = null, $idBox = 1, $skybillRank = 1)
    {
        $config = ChronopostLabelConst::getConfig();
        $customer = $order->getCustomer();

        $customerInvoiceAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        $customerDeliveryAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());

        $phone = $customerDeliveryAddress->getCellphone();

        if (null == $phone) {
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

        /** CUSTOMER INVOICE INFORMATIONS */
        $APIData["customerValue"] = [
            "customerCivility" => $this->getChronopostCivility($customer),
            "customerName" => $customerInvoiceAddress->getCompany(),
            "customerName2" => $invoiceCustomerName,
            "customerAdress1" => $customerInvoiceAddress->getAddress1(),
            "customerAdress2" => $customerInvoiceAddress->getAddress2(),
            "customerZipCode" => $customerInvoiceAddress->getZipcode(),
            "customerCity" => $customerInvoiceAddress->getCity(),
            "customerCountry" => $this->getCountryIso($customerInvoiceAddress->getCountryId()),
            "customerContactName" => $invoiceCustomerName,
            "customerEmail" => $customer->getEmail(),
            "customerPhone" => $customerInvoiceAddress->getPhone(),
            "customerMobilePhone" => $customerInvoiceAddress->getCellphone(),
            "customerPreAlert" => 0,
            "printAsSender" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS],
        ];

        /** CUSTOMER DELIVERY INFORMATIONS */
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
            "service" => "0",
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
     * @throws \Propel\Runtime\Exception\PropelException
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

}
