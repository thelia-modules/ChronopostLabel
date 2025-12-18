<?php

namespace ChronopostLabel\Controller;


use ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery;
use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostLabel\Form\ChronopostLabelCreateForm;
use ChronopostLabel\Form\ChronopostLabelSelectForm;
use ChronopostLabel\Service\LabelService;
use ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/ChronopostLabel", name="chronopost-label")
 */

class ChronopostLabelController extends BaseAdminController
{
    /**
     * @Route("/labels", name="_show_labels", methods="GET")
     */
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

    /**
     * @Route("/saveLabel", name="_save_label", methods="GET")
     */
    public function saveLabel(RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }
        $orderId = $requestStack->getCurrentRequest()->get("orderId");

        if(!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
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
     * @Route("/getLabel/{orderId}", name="_get_label", methods="GET")
     */
    public function getLabel($orderId, LabelService $labelService)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        if(null == $chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        if(null == $fileName = $chronopostOrder->getLabelNumber()){
            $labelService->createLabel($chronopostOrder);
            $fileName = $chronopostOrder->getLabelNumber();
        }

        $file = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR) . $fileName;

        $response = new BinaryFileResponse($file);

        return $response;
    }


    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @Route("/deleteLabel", name="_delete_label", methods="GET")
     */
    public function deleteLabel(RequestStack $requestStack)
    {
        $orderId = $requestStack->getCurrentRequest()->get("orderId");
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

    /**
     * @Route("/generateLabel", name="_generate_label", methods="GET")
     */
    public function generateLabel(LabelService $labelService, RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $orderId = $requestStack->getCurrentRequest()?->get("orderId");

        if(!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)){
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }
        if (null === $chronopostOrder) {
            throw new \Exception("Commande Chronopost introuvable.");
        }

        $labelService->createLabel($chronopostOrder);

        return $this->generateRedirect('/admin/order/update/'.$orderId);

    }

    /**
     * @Route("/generateLabel", name="_generate_labels", methods="POST")
     */
    public function generateLabels(LabelService $labelService, RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ChronopostLabelCreateForm::getName());

        try {
            $data = $this->validateForm($form)->getData();
            $orderId = $data['order_id'];
            $weight = $data['weight'];
            $newStatusId = $data['new_status'];

            if (!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)) {
                $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
            }

            if (null === $chronopostOrder) {
                throw new FormValidationException("Commande Chronopost introuvable.");
            }

            $statusOption = ($newStatusId === 'no_change') ? null : 'other';

            $labelService->createLabel($chronopostOrder, (string)$statusOption, $newStatusId, $weight);

            return $this->generateRedirect('/admin/order/update/' . $orderId);

        } catch (FormValidationException $e) {
            $this->createStandardFormValidationErrorMessage($e);
            return $this->generateRedirect('/admin/order/update/' . $requestStack->getCurrentRequest()?->get('order_id'));
        } catch (\Exception $e) {
            $this->createStandardFormValidationErrorMessage(new FormValidationException($e->getMessage()));
            return $this->generateRedirect('/admin/order/update/' . $requestStack->getCurrentRequest()?->get('order_id'));
        }
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

    /**
     * @Route("/labels-zip/{base64EncodedZipFilename}", name="_labels_zip", methods="GET")
     */
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