<?php

namespace ChronopostLabel\Form;

use ChronopostLabel\ChronopostLabel;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Model\OrderStatusQuery;

class ChronopostLabelCreateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('order_id', HiddenType::class, [
                'constraints' => [new NotBlank()]
            ])
            ->add('weight', NumberType::class, [
                'label' => Translator::getInstance()->trans('Poids (kg)', [], ChronopostLabel::DOMAIN_NAME),
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan(0)
                ],
                'attr' => ['step' => '0.01']
            ])
            ->add('new_status', ChoiceType::class, [
                'label' => Translator::getInstance()->trans('Statut de commande après la création de l\'étiquette', [], ChronopostLabel::DOMAIN_NAME),
                'choices' => $this->getStatusChoices(),
                'required' => true,
            ]);
    }

    private function getStatusChoices(): array
    {
        $choices = [
            Translator::getInstance()->trans('Ne pas modifier', [], ChronopostLabel::DOMAIN_NAME) => 'no_change'
        ];

        $statuses = OrderStatusQuery::create()->find();
        foreach ($statuses as $status) {
            $choices[$status->setLocale(Translator::getInstance()->getLocale())->getTitle()] = $status->getId();
        }

        return $choices;
    }
}
