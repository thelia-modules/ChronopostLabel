<?php

namespace ChronopostLabel\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;

class BackHook extends BaseHook
{
    public function onInTopMenuItem(HookRenderEvent $event)
    {
        $event->add($this->render('ChronopostLabel/hook/main-in-top-menu-items.html', []));
    }

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $event->add($this->render('ChronopostLabel/ChronopostLabelConfig.html'));
    }

    /**
     * @param HookRenderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function orderEditBillTop(HookRenderEvent $event)
    {
        $moduleCode = OrderQuery::create()->findOneById($event->getArgument("order_id"))->getModuleRelatedByDeliveryModuleId()->getCode();

        $event->add($this->render('ChronopostLabel/hook/order-edit-bill-top.html',
            [
                'delivery_module' => $moduleCode,
            ]
        ));
    }
}