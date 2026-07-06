<?php

namespace ChronopostLabel\Controller;

use ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery;
use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostLabel\Form\ChronopostLabelSelectForm;
use ChronopostLabel\Service\LabelService;
use ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Currency;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\DateTimeFormat;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;

class ChronopostLabelController extends BaseAdminController
{
    #[Route('/admin/module/ChronopostLabel/labels', name: 'chronopost.label.labels', methods: ['GET'])]
    public function showLabels(Request $request): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::VIEW)) {
            return $response;
        }

        $homeDeliveryModule = (bool) (ModuleQuery::create()->findOneByCode('ChronopostHomeDelivery')?->getActivate());
        $pickupPointModule = (bool) (ModuleQuery::create()->findOneByCode('ChronopostPickupPoint')?->getActivate());

        $locale = $request->hasSession()
            ? $request->getSession()->getAdminEditionLang()->getLocale()
            : (\Thelia\Model\LangQuery::create()->findOneByByDefault(true)?->getLocale() ?? 'en_US');
        $dateFormat = DateTimeFormat::getInstance($request)->getFormat();
        $currencySymbol = Currency::getDefaultCurrency()->getSymbol();

        $errors = $this->buildCheckRightsErrors();

        $selectForm = $this->createForm(ChronopostLabelSelectForm::getName());

        $homeOrders = [];
        if ($homeDeliveryModule && class_exists(ChronopostHomeDeliveryOrderQuery::class)) {
            $homeOrders = $this->buildExportRows(
                ChronopostHomeDeliveryOrderQuery::create()->orderById(Criteria::DESC)->find(),
                $locale,
                $dateFormat
            );
        }

        $pickupOrders = [];
        if ($pickupPointModule && class_exists(ChronopostPickupPointOrderQuery::class)) {
            $pickupOrders = $this->buildExportRows(
                ChronopostPickupPointOrderQuery::create()->orderById(Criteria::DESC)->find(),
                $locale,
                $dateFormat
            );
        }

        // Render through BaseAdminController so the active back-office template
        // engine is resolved automatically: Smarty (default -> .html) or
        // Twig (default-twig -> .html.twig). The Smarty template is self-sufficient
        // (its own loops/forms) and ignores the args below, which feed the Twig one.
        return $this->render('ChronopostLabel/ChronopostLabels', [
            'errors' => $errors,
            'home_delivery_activate' => $homeDeliveryModule,
            'pickup_point_activate' => $pickupPointModule,
            'currency_symbol' => $currencySymbol,
            'select_form' => $selectForm->getForm()->createView(),
            'home_orders' => $homeOrders,
            'pickup_orders' => $pickupOrders,
            'zip_hash' => $request->query->get('zip'),
        ]);
    }

    /**
     * @return array<int, array{message: string, file: string}>
     */
    private function buildCheckRightsErrors(): array
    {
        $config = ChronopostLabelConst::getConfig();
        $dir = $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR];
        $errors = [];

        if (!is_writable($dir)) {
            $errors[] = [
                'message' => Translator::getInstance()->trans("Can't write in the label directory", [], ChronopostLabel::DOMAIN_NAME),
                'file' => $dir,
            ];
        }
        if (!is_readable($dir)) {
            $errors[] = [
                'message' => Translator::getInstance()->trans("Can't read the label directory", [], ChronopostLabel::DOMAIN_NAME),
                'file' => $dir,
            ];
        }

        return $errors;
    }

    /**
     * Reproduces the chronopost export loops: one row per chronopost order, enriched with its
     * order reference, status, destination and total.
     *
     * @param iterable<object> $chronopostOrders
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildExportRows(iterable $chronopostOrders, string $locale, ?string $dateFormat): array
    {
        $rows = [];

        foreach ($chronopostOrders as $chronopostOrder) {
            $order = OrderQuery::create()->findPk($chronopostOrder->getOrderId());
            if (null === $order) {
                continue;
            }

            $status = OrderStatusQuery::create()->findPk($order->getStatusId());
            if (null !== $status) {
                $status->setLocale($locale);
            }

            $destination = '';
            $address = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
            if (null !== $address) {
                $destination = trim(sprintf('%s, %s %s', $address->getAddress1(), $address->getCity(), $address->getZipcode()));
            }

            $tax = 0;
            $total = $order->getTotalAmount($tax);

            $createdAt = $order->getCreatedAt();

            $rows[] = [
                'id' => $order->getId(),
                'ref' => $order->getRef(),
                'status_title' => null !== $status ? $status->getTitle() : '',
                'status_color' => null !== $status ? $status->getColor() : '',
                'delivery_type' => $chronopostOrder->getDeliveryType(),
                'create_date' => $createdAt instanceof \DateTimeInterface ? $createdAt->format($dateFormat) : '',
                'total' => round((float) $total, 2),
                'destination' => $destination,
                'label_nbr' => $chronopostOrder->getLabelNumber(),
            ];
        }

        return $rows;
    }

    #[Route('/admin/module/ChronopostLabel/saveLabel', name: 'chronopost.label.save_label', methods: ['GET'])]
    public function saveLabel(Request $request): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }
        $orderId = $request->query->get('orderId');

        if (!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)) {
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        $labelNbr = $chronopostOrder?->getLabelNumber();
        $labelDir = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR);
        $file = $labelDir.'/'.$labelNbr;

        if (null !== $labelNbr && file_exists($file)) {
            return new BinaryFileResponse(
                $file,
                200,
                ['Content-Type' => 'application/octet-stream'],
                false,
                'attachment'
            );
        }

        return $this->generateRedirect('/admin/module/ChronopostLabel/labels');
    }

    #[Route('/admin/module/ChronopostLabel/getLabel/{orderId}', name: 'chronopost.label.get_label', methods: ['GET'])]
    public function getLabel($orderId, LabelService $labelService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        if (null == $chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)) {
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        if (null === $chronopostOrder) {
            return $this->generateRedirect('/admin/module/ChronopostLabel/labels');
        }

        if (null == $fileName = $chronopostOrder->getLabelNumber()) {
            $labelService->createLabel($chronopostOrder);
            $fileName = $chronopostOrder->getLabelNumber();
        }

        $file = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR).$fileName;

        return new BinaryFileResponse($file);
    }

    #[Route('/admin/module/ChronopostLabel/deleteLabel', name: 'chronopost.label.delete_label', methods: ['POST'])]
    public function deleteLabel(Request $request, TokenProvider $tokenProvider): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->query->get('_token'));

        $orderId = $request->query->get('orderId');
        $order = OrderQuery::create()->findOneById($orderId);

        if (!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)) {
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        if (null !== $chronopostOrder
            && file_exists($chronopostOrder->getLabelDirectory().$chronopostOrder->getLabelNumber())) {
            unlink($chronopostOrder->getLabelDirectory().$chronopostOrder->getLabelNumber());
            $chronopostOrder
                ->setLabelDirectory(null)
                ->setLabelNumber(null)
                ->save();

            $order
                ?->setDeliveryRef(null)
                ->save();
        }

        return $this->generateRedirect($request->query->get('redirect_url'));
    }

    #[Route('/admin/module/ChronopostLabel/generateLabel', name: 'chronopost.label.generate_label', methods: ['POST'])]
    public function generateLabel(LabelService $labelService, Request $request, TokenProvider $tokenProvider): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->query->get('_token'));

        $orderId = $request->query->get('orderId');

        if (!$chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)) {
            $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
        }

        if (null === $chronopostOrder) {
            return $this->generateRedirect('/admin/order/update/'.$orderId);
        }

        $labelService->createLabel($chronopostOrder);

        return $this->generateRedirect('/admin/order/update/'.$orderId);
    }

    #[Route('/admin/module/ChronopostLabel/generate', name: 'chronopost.label.generate', methods: ['POST'])]
    public function generateLabels(LabelService $labelService): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }

        $chronopostDir = ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR);
        $chronopostTmpDir = $chronopostDir.'tmp'.DS;
        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($chronopostTmpDir)) {
            $fileSystem->mkdir($chronopostTmpDir, 0777);
        }

        $selectLabelForm = $this->createForm(ChronopostLabelSelectForm::getName());
        $form = $this->validateForm($selectLabelForm);
        $data = $form->getData();

        if (!$data['order_id']) {
            return $this->generateRedirect('/admin/module/ChronopostLabel/labels');
        }

        $statusOption = $data['choice_status'];
        $otherStatus = 'other' === $data['choice_status'] ? $data['status_select'] : null;

        foreach ($data['order_id'] as $orderId) {
            if (null == $chronopostOrder = ChronopostHomeDeliveryOrderQuery::create()->findOneByOrderId($orderId)) {
                $chronopostOrder = ChronopostPickupPointOrderQuery::create()->findOneByOrderId($orderId);
            }

            if (null == $fileName = $chronopostOrder->getLabelNumber()) {
                $labelService->createLabel($chronopostOrder, $statusOption, $otherStatus);
                $fileName = $chronopostOrder->getLabelNumber();
            }

            $fileSystem->copy($chronopostDir.$fileName, $chronopostTmpDir.$fileName);
        }

        $today = new \DateTime();
        $name = 'chronopost-label-'.$today->format('Y-m-d_H-i-s').'.zip';
        $zipPath = $chronopostDir.$name;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $this->folderToZip($chronopostTmpDir, $zip, \strlen($chronopostTmpDir));
        $zip->close();

        $fileSystem->remove($chronopostDir.'tmp'.DS);

        $params = ['zip' => base64_encode($zipPath)];

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/ChronopostLabel/labels', $params));
    }

    private function folderToZip($folder, \ZipArchive &$zipFile, $exclusiveLength): void
    {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ('.' !== $f && '..' !== $f) {
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

    #[Route('/admin/module/ChronopostLabel/labels-zip/{base64EncodedZipFilename}', name: 'chronopost.label.labels_zip', methods: ['GET'])]
    public function getLabelZip($base64EncodedZipFilename): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::VIEW)) {
            return $response;
        }

        $zipFilename = base64_decode($base64EncodedZipFilename);

        // Confine the decoded path to the configured label dir + chronopost-label- prefix (defeats arbitrary file read).
        $resolved = realpath($zipFilename);
        $labelDir = realpath((string) ChronopostLabel::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR));

        if (false === $resolved
            || false === $labelDir
            || !str_starts_with($resolved, $labelDir.DS)
            || !str_starts_with(basename($resolved), 'chronopost-label-')
        ) {
            return $this->generateRedirect('/admin/module/ChronopostLabel/labels');
        }

        $zipFilename = $resolved;

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
                    'Content-Length' => filesize($zipFilename),
                ]
            );
        }

        return $this->generateRedirect('/admin/module/ChronopostLabel/labels');
    }
}
