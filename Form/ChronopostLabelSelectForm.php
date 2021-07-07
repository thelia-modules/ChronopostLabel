<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 15/07/2020
 * Time: 13:30
 */

namespace ChronopostLabel\Form;


use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

class ChronopostLabelSelectForm extends BaseForm
{

    protected function buildForm()
    {
        $OrderStatus = OrderStatusQuery::create()->find();
        $choices = [];
        /** @var OrderStatus $status */
        foreach ($OrderStatus as $status){
            $choices[$status->getId()] = $status->getTitle();
        }

        $this->formBuilder
            ->add(
                'order_id',
                CollectionType::class,
                [
                    'required' => 'false',
                    'entry_type' => IntegerType::class,
                    'allow_add' => true,
                    'allow_delete' => true,
                ]
            )
            ->add(
                'choice_status',
                ChoiceType::class,
                [
                    'required'      => false,
                    'label'         => Translator::getInstance()->trans("After label generation change the order status to :"),
                    'label_attr'    => [
                        'for'           => 'choice_status',
                    ],
                    'choices' => [
                        'default'   => Translator::getInstance()->trans("The default status in configuration"),
                        'other'     => Translator::getInstance()->trans("Another status"),
                        'none'      => Translator::getInstance()->trans("Don't change status")
                    ]
                ]
            )
            ->add(
                'status_select',
                ChoiceType::class,
                [
                    'required'      => false,
                    'label'         => Translator::getInstance()->trans("Choose a status"),
                    'label_attr'    => [
                        'for'           => 'status_select',
                    ],
                    'choices' => $choices
                ]
            );
    }

    public static function getName()
    {
        return 'chronopost_label_select_form';
    }
}