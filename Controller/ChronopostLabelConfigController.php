<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 10/07/2020
 * Time: 10:27
 */

namespace ChronopostLabel\Controller;


use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use ChronopostLabel\Form\ChronopostLabelConfigurationForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/ChronopostLabel", name="chronopost-label")
 */
class ChronopostLabelConfigController extends BaseAdminController
{
    /**
     * Save configuration form - Chronopost informations
     *
     * @return mixed|null|\Symfony\Component\HttpFoundation\Response|\Thelia\Core\HttpFoundation\Response
     * @Route("/config", name="_config_save", methods="POST")
     */
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ChronopostLabelConfigurationForm::getName());

        try {
            $data = $this->validateForm($form)->getData();

            /** Basic informations */

            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT, $data[ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD, $data[ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR, $data[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE, $data[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS, $data[ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS, $data[ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE, $data[ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE]);

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    "Error",
                    [],
                    ChronopostLabel::DOMAIN_NAME
                ),
                $e->getMessage(),
                $form
            );

            return $this->render(
                'module-configure',
                [
                    'module_code' => 'ChronopostLabel',
                ]
            );
        }

        return $this->generateSuccessRedirect($form);
    }

    /**
     * Save configuration form - Shipper informations
     *
     * @return mixed|null|\Symfony\Component\HttpFoundation\Response|\Thelia\Core\HttpFoundation\Response
     * @Route("/configShipper", name="_config_shipper_save", methods="POST")
     */
    public function saveActionShipper()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'ChronopostLabel', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ChronopostLabelConfigurationForm::getName());

        try {
            $data = $this->validateForm($form)->getData();

            /** Shipper informations */
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE]);
            ChronopostLabel::setConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL, $data[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL]);

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    "Error",
                    [],
                    ChronopostLabel::DOMAIN_NAME
                ),
                $e->getMessage(),
                $form
            );

            return $this->render(
                'module-configure',
                [
                    'module_code' => 'ChronopostLabel',
                ]
            );
        }

        return $this->generateSuccessRedirect($form);
    }

}