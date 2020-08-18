<?php

namespace ChronopostLabel\Controller;


use ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery;
use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Log\Tlog;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

class ChronopostLabelController extends BaseAdminController
{

    public function showLabels()
    {
        $homeDeliveryModule = ModuleQuery::create()->findOneByCode('ChronopostHomeDelivery')->getActivate();
        $pickupPointModule = ModuleQuery::create()->findOneByCode('ChronopostPickupPoint')->getActivate();
        $defaultLabel = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS);

        return $this->render('ChronopostLabel/ChronopostLabels',
            [
                'home_delivery_activate'    => "$homeDeliveryModule",
                'pickup_point_activate'     => "$pickupPointModule",
                'default_status'            => "$defaultLabel"
            ]
        );
    }


    public function saveLabel()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }

        if(!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($this->getRequest()->get("orderId"))){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($this->getRequest()->get("orderId"));
        }

        $labelNbr = $chronopostOrder->getLabelNumber();

        $labelDir = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR);

        $file = $labelDir .'/'. $labelNbr;

        if (file_exists($file) && $labelNbr != null) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
        } else {
            return $this->generateRedirect("/admin/module/ChronopostLabel/labels");
            // todo : Error message
        }

        return $this->render('ChronopostLabel/ChronopostLabels.html');
    }


    /**
     * @param $orderId
     * @return mixed|BinaryFileResponse
     */
    public function getLabel($orderId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        if(null == $chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        if(null == $fileName = $chronopostOrder->getLabelNumber()){
            $this->createLabel($chronopostOrder);
            $fileName = $chronopostOrder->getLabelNumber();
        }

        $file = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR) . $fileName;

        $response = new BinaryFileResponse($file);

        return $response;
    }


    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function deleteLabel()
    {
        $orderId = $this->getRequest()->get("orderId");
        $order = OrderQuery::create()->findOneById($orderId);

        if(!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }
        if(file_exists($chronopostOrder->getLabelDirectory() . $chronopostOrder->getLabelNumber())){
            unlink($chronopostOrder->getLabelDirectory() . $chronopostOrder->getLabelNumber());
            $chronopostOrder
                ->setLabelDirectory(null)
                ->setLabelNumber(null)
                ->save();

            $order
                ->setDeliveryRef(null)
                ->save();
        }

        return $this->generateRedirect($this->getRequest()->get("redirect_url"));
    }

    public function generateLabel()
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $orderId = $this->getRequest()->get("orderId");

        if(!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        $this->createLabel($chronopostOrder);

        return $this->generateRedirect('/admin/order/update/'.$orderId);

    }


    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generateLabels()
    {

        $chronopostDir = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR);
        $chronopostTmpDir = $chronopostDir . 'tmp' . DS;
        $fileSystem = new Filesystem();

        if (! $fileSystem->exists($chronopostTmpDir)){
            $fileSystem->mkdir($chronopostTmpDir, 0777);
        }

        $selectLabelForm = $this->createForm('chronopost_label_select_form');

        $form = $this->validateForm($selectLabelForm);

        $data = $form->getData();

        if (!$data['order_id']){
            return $this->generateRedirect("/admin/module/ChronopostLabel/labels");
        }

        $statusOption = $data['choice_status'];
        $otherStatus = $data['choice_status'] === 'other'? $data['status_select']:null;

        foreach ($data['order_id'] as $orderId) {

            if(null == $chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
                $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
            }

            if(null == $fileName = $chronopostOrder->getLabelNumber()){
                $this->createLabel($chronopostOrder, $statusOption, $otherStatus);
                $fileName = $chronopostOrder->getLabelNumber();
            }

            $fileSystem->copy($chronopostDir . $fileName, $chronopostTmpDir . $fileName);

        }

        $today = new \DateTime();
        $name = 'chronopost-label-'.$today->format('Y-m-d_H-i-s').'.zip';

        $zipPath = $chronopostDir.$name;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $this->folderToZip($chronopostTmpDir, $zip, strlen($chronopostTmpDir));
        $zip->close();

        $fileSystem->remove(ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR) . 'tmp' . DS);

        $params = [ 'zip' => base64_encode($zipPath)];

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/ChronopostLabel/labels', $params));

    }


    /**
     * @param $chronopostOrder
     * @param string $statusOption
     * @param null $otherStatus
     * @return null
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createLabel ($chronopostOrder, $statusOption = 'default', $otherStatus = null)
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
                $APIDatas[] = $this->writeAPIData($order, $chronopostOrder, $order->getWeight(), 1, 1);
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

        $name2 = "";
        if ($customerDeliveryAddress->getCompany()) {
            $name2 = $this->getContactName($customerDeliveryAddress);
        }
        $name3 = "";
        if ($customerInvoiceAddress->getCompany()) {
            $name3 = $this->getContactName($customerInvoiceAddress);
        }

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
            "customerName2" => $name3,
            "customerAdress1" => $customerInvoiceAddress->getAddress1(),
            "customerAdress2" => $customerInvoiceAddress->getAddress2(),
            "customerZipCode" => $customerInvoiceAddress->getZipcode(),
            "customerCity" => $customerInvoiceAddress->getCity(),
            "customerCountry" => $this->getCountryIso($customerInvoiceAddress->getCountryId()),
            "customerContactName" => $this->getContactName($customerInvoiceAddress),
            "customerEmail" => $customer->getEmail(),
            "customerPhone" => $customerInvoiceAddress->getPhone(),
            "customerMobilePhone" => $customerInvoiceAddress->getCellphone(),
            "customerPreAlert" => 0,
            "printAsSender" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS],
        ];

        /** CUSTOMER DELIVERY INFORMATIONS */
        $APIData["recipientValue"] = [
            "recipientName" => $customerDeliveryAddress->getCompany(),
            "recipientName2" => $name2,
            "recipientAdress1" => $customerDeliveryAddress->getAddress1(),
            "recipientAdress2" => $customerDeliveryAddress->getAddress2(),
            "recipientZipCode" => $customerDeliveryAddress->getZipcode(),
            "recipientCity" => $customerDeliveryAddress->getCity(),
            "recipientCountry" => $this->getCountryIso($customerDeliveryAddress->getCountryId()),
            "recipientContactName" => $this->getContactName($customerDeliveryAddress),
            "recipientEmail" => $customer->getEmail(),
            "recipientPhone" => $phone,
            "recipientMobilePhone" => $customerDeliveryAddress->getCellphone(),
            "recipientPreAlert" => 0,
        ];

        /** RefValue */
        $APIData["refValue"] = [
            "shipperRef" => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1],
            "recipientRef" => $customer->getId(),
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


    /**
     * @param $folder
     * @param \ZipArchive $zipFile
     * @param $exclusiveLength
     */
    private function folderToZip($folder,\ZipArchive &$zipFile, $exclusiveLength) {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f !== '.' && $f !== '..') {
                $filePath = "$folder/$f";
                $localPath = ltrim(str_replace('\\', '/', substr($filePath, $exclusiveLength)), '/');

                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    $zipFile->addEmptyDir($localPath);
                    $this->folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }

    public function getLabelZip($base64EncodedZipFilename)
    {
        $zipFilename = base64_decode($base64EncodedZipFilename);

        if (file_exists($zipFilename)) {
            return new StreamedResponse(
                function () use ($zipFilename) {
                    readfile($zipFilename);
                    @unlink($zipFilename);
                },
                200,
                [
                    'Content-Type' => 'application/zip',
                    'Content-disposition' => 'attachement; filename=chronopost-labels.zip',
                    'Content-Length' => filesize($zipFilename)
                ]
            );
        }

        return $this->generateRedirect("/admin/module/ChronopostLabel/labels");
    }
}