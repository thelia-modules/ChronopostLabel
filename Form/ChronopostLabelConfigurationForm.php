<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 10/07/2020
 * Time: 10:35
 */

namespace ChronopostLabel\Form;


use ChronopostLabel\Config\ChronopostLabelConst;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

class ChronopostLabelConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $config = ChronopostLabelConst::getConfig();
        $OrderStatus = OrderStatusQuery::create()->find();
        $statusChoices = [];
        /** @var OrderStatus $status */
        foreach ($OrderStatus as $status){
            $statusChoices[$status->getId()] = $status->getTitle();
        }

        $this->formBuilder

            ->add(
                ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT],
                    'label'         => Translator::getInstance()->trans("Chronopost client ID"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Your Chronopost client ID"),
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD],
                    'label'         => Translator::getInstance()->trans("Chronopost password"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Your Chronopost password"),
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR],
                    'label'         => Translator::getInstance()->trans("Directory where to save Chronopost labels"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => THELIA_LOCAL_DIR . 'chronopost',
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE,
                "choice",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE],
                    'label'         => Translator::getInstance()->trans("Label file type"),
                    'label_attr'    => [
                        'for'           => 'level_field',
                    ],
                    'choices'       => [
                        "PDF"           => "PDF label with proof of deposit laser printer",
                        "SPD"           => "PDF label without proof of deposit laser printer",
                        "THE"           => "PDF label without proof of deposit for thermal printer",
                        "Z2D"           => "ZPL label with proof of deposit for thermal printer",
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS,
                "choice",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS],
                    'label'         => Translator::getInstance()->trans("Default order status after label generation"),
                    'label_attr'    => [
                        'for'           => 'status_select',
                    ],
                    'choices' => $statusChoices
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS,
                "choice",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS],
                    'label'         => Translator::getInstance()->trans("For the sending address, use :"),
                    'label_attr'    => [
                        'for'           => 'level_field',
                    ],
                    'choices'       => [
                        "N"           => "The shipper's one (Default value)",
                        "Y"           => "The customer's one (Do not use without knowing what it is)",
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE,
                "text",
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE],
                    'label'         => Translator::getInstance()->trans("Number of days before expiration date from the moment the order is in \"Processing\" status"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("5"),
                    ],
                ]
            )

            /** Shipper Informations */
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1],
                    'label'         => Translator::getInstance()->trans("Company name 1"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Dupont & co")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2,
                "text",
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2],
                    'label'         => Translator::getInstance()->trans("Company name 2"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1],
                    'label'         => Translator::getInstance()->trans("Address 1"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Les Gardelles")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2,
                "text",
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2],
                    'label'         => Translator::getInstance()->trans("Address 2"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Route de volvic")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY],
                    'label'         => Translator::getInstance()->trans("Country (ISO ALPHA-2 format)"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("FR")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY],
                    'label'         => Translator::getInstance()->trans("City"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Paris")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP],
                    'label'         => Translator::getInstance()->trans("ZIP code"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("93000")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY],
                    'label'         => Translator::getInstance()->trans("Civility"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("E (Madam), L (Miss), M (Mister)")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME],
                    'label'         => Translator::getInstance()->trans("Contact name"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Jean Dupont")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE,
                "text",
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE],
                    'label'         => Translator::getInstance()->trans("Phone"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("0142080910")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE,
                "text",
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE],
                    'label'         => Translator::getInstance()->trans("Mobile phone"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("0607080910")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL,
                "text",
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL],
                    'label'         => Translator::getInstance()->trans("E-mail"),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("jeandupont@gmail.com")
                    ],
                ]
            )
        ;

        /** BUILDFORM END */
    }

    public function getName()
    {
        return "chronopost_label_configuration_form";
    }

}