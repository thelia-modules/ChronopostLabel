<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 10/07/2020
 * Time: 10:35
 */

namespace ChronopostLabel\Form;


use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

class ChronopostLabelConfigurationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $config = ChronopostLabelConst::getConfig();
        $OrderStatus = OrderStatusQuery::create()->find();
        $statusChoices = [];
        /** @var OrderStatus $status */
        foreach ($OrderStatus as $status){
            $statusChoices[$status->getTitle()] = $status->getId();
        }

        $this->formBuilder

            ->add(
                ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT],
                    'label'         => Translator::getInstance()->trans("Chronopost client ID", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Your Chronopost client ID", [], ChronopostLabel::DOMAIN_NAME),
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD],
                    'label'         => Translator::getInstance()->trans("Chronopost password", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Your Chronopost password", [], ChronopostLabel::DOMAIN_NAME),
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR],
                    'label'         => Translator::getInstance()->trans("Directory where to save Chronopost labels", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => THELIA_LOCAL_DIR . 'chronopost',
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE,
                ChoiceType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE],
                    'label'         => Translator::getInstance()->trans("Label file type", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'level_field',
                    ],
                    'choices'       => [
                        "PDF label with proof of deposit laser printer"=> "PDF",
                        "PDF label without proof of deposit laser printer"=>"SPD",
                        "PDF label without proof of deposit for thermal printer"=> "THE",
                        "ZPL label with proof of deposit for thermal printer" => "Z2D",
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS,
                ChoiceType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS],
                    'label'         => Translator::getInstance()->trans("Default order status after label generation", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'status_select',
                    ],
                    'choices' => $statusChoices
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS,
                ChoiceType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS],
                    'label'         => Translator::getInstance()->trans("For the sending address, use :", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'level_field',
                    ],
                    'choices'       => [
                        "The shipper's one (Default value)" => "N",
                        "The customer's one (Do not use without knowing what it is)" => "Y",
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE,
                TextType::class,
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE],
                    'label'         => Translator::getInstance()->trans("Number of days before expiration date from the moment the order is in \"Processing\" status", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("5", [], ChronopostLabel::DOMAIN_NAME),
                    ],
                ]
            )

            /** Shipper Informations */
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1],
                    'label'         => Translator::getInstance()->trans("Company name 1", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Dupont & co", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2,
                TextType::class,
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2],
                    'label'         => Translator::getInstance()->trans("Company name 2", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("")
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1],
                    'label'         => Translator::getInstance()->trans("Address 1", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Les Gardelles", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2,
                TextType::class,
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2],
                    'label'         => Translator::getInstance()->trans("Address 2", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Route de volvic", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY],
                    'label'         => Translator::getInstance()->trans("Country (ISO ALPHA-2 format)", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("FR", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY],
                    'label'         => Translator::getInstance()->trans("City", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Paris", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP],
                    'label'         => Translator::getInstance()->trans("ZIP code", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("93000", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY],
                    'label'         => Translator::getInstance()->trans("Civility", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("E (Madam), L (Miss), M (Mister)", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME],
                    'label'         => Translator::getInstance()->trans("Contact name", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("Jean Dupont", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE,
                TextType::class,
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE],
                    'label'         => Translator::getInstance()->trans("Phone", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("0142080910", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE,
                TextType::class,
                [
                    'required'      => false,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE],
                    'label'         => Translator::getInstance()->trans("Mobile phone", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("0607080910", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
            ->add(ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL,
                TextType::class,
                [
                    'required'      => true,
                    'data'          => $config[ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL],
                    'label'         => Translator::getInstance()->trans("E-mail", [], ChronopostLabel::DOMAIN_NAME),
                    'label_attr'    => [
                        'for'           => 'title',
                    ],
                    'attr'          => [
                        'placeholder'   => Translator::getInstance()->trans("jeandupont@gmail.com", [], ChronopostLabel::DOMAIN_NAME)
                    ],
                ]
            )
        ;

        /** BUILDFORM END */
    }

    public static function getName(): string
    {
        return "chronopost_label_configuration_form";
    }

}
