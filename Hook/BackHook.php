<?php

namespace ChronopostLabel\Hook;

use ChronopostLabel\Form\ChronopostLabelConfigurationForm;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\DateTimeFormat;

class BackHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.in-top-menu-items' => [
                ['type' => 'back', 'method' => 'onInTopMenuItem'],
            ],
            'order-edit.delivery-module-bottom' => [
                ['type' => 'back', 'method' => 'orderEditBillTop'],
            ],
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    public function onInTopMenuItem(HookRenderEvent $event): void
    {
        $event->add($this->render('ChronopostLabel/hook/main-in-top-menu-items.html.twig', []));
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(ChronopostLabelConfigurationForm::getName());

        $event->add($this->render('ChronopostLabel/ChronopostLabelConfig.html.twig', [
            'form' => $form->createView()->getView(),
        ]));
    }

    public function orderEditBillTop(HookRenderEvent $event): void
    {
        $orderId = $event->getArgument('order_id');
        $order = OrderQuery::create()->findOneById($orderId);

        if (null === $order) {
            return;
        }

        $moduleCode = $order->getModuleRelatedByDeliveryModuleId()->getCode();
        $isChronopost = \in_array($moduleCode, ['ChronopostHomeDelivery', 'ChronopostPickupPoint'], true);

        $labelNumber = null;
        $deliveryType = null;

        if ('ChronopostHomeDelivery' === $moduleCode
            && class_exists(\ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery::class)) {
            $chronopostOrder = \ChronopostHomeDelivery\Model\ChronopostHomeDeliveryOrderQuery::create()
                ->findOneByOrderId($orderId);
            if (null !== $chronopostOrder) {
                $labelNumber = $chronopostOrder->getLabelNumber();
                $deliveryType = $chronopostOrder->getDeliveryType();
            }
        }

        if ('ChronopostPickupPoint' === $moduleCode
            && class_exists(\ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery::class)) {
            $chronopostOrder = \ChronopostPickupPoint\Model\ChronopostPickupPointOrderQuery::create()
                ->findOneByOrderId($orderId);
            if (null !== $chronopostOrder) {
                $labelNumber = $chronopostOrder->getLabelNumber();
                $deliveryType = $chronopostOrder->getDeliveryType();
            }
        }

        $createDate = null !== $order->getCreatedAt()
            ? $order->getCreatedAt()->format(DateTimeFormat::getInstance($this->getRequest())->getFormat())
            : null;

        $destination = '';
        $address = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        if (null !== $address) {
            $destination = trim(sprintf('%s %s %s', $address->getAddress1(), $address->getCity(), $address->getZipcode()));
        }

        $event->add($this->render('ChronopostLabel/hook/order-edit-bill-top.html.twig', [
            'is_chronopost' => $isChronopost,
            'order_id' => $orderId,
            'label_nbr' => $labelNumber,
            'delivery_type' => $deliveryType,
            'create_date' => $createDate,
            'destination' => $destination,
        ]));
    }
}
