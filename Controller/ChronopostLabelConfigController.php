<?php

namespace ChronopostLabel\Controller;

use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostLabel\Form\ChronopostLabelConfigurationForm;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;

class ChronopostLabelConfigController extends BaseAdminController
{
    /**
     * Save the full Chronopost configuration (Chronopost informations + shipper informations).
     */
    #[Route('/admin/module/ChronopostLabel/config', name: 'chronopost.label.config.save', methods: ['POST'])]
    public function saveAction(): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ChronopostLabelConfigurationForm::getName());

        $configKeys = [
            ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT,
            ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD,
            ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR,
            ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE,
            ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS,
            ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS,
            ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL,
        ];

        try {
            $data = $this->validateForm($form)->getData();

            foreach ($configKeys as $key) {
                ChronopostLabel::setConfigValue($key, $data[$key]);
            }
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans('Error', [], ChronopostLabel::DOMAIN_NAME),
                $e->getMessage(),
                $form
            );

            return $this->generateRedirectFromRoute(
                'admin.module.configure',
                [],
                ['module_code' => 'ChronopostLabel']
            );
        }

        return $this->generateSuccessRedirect($form);
    }
}
