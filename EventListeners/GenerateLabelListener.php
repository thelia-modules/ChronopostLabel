<?php

namespace ChronopostLabel\EventListeners;


use ChronopostLabel\Service\LabelService;
use Picking\Event\GenerateLabelEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Controller\Admin\BaseAdminController;

/**
 * Class GenerateLabelListener
 *
 * This class is used only when you have the Picking module
 *
 * @package ChronopostLabel\EventListeners
 */
class GenerateLabelListener extends BaseAdminController implements EventSubscriberInterface
{
    protected $service;

    /**
     * @param LabelService $service
     */
    public function __construct(LabelService $service)
    {
        $this->service = $service;
    }

    /**
     * @param GenerateLabelEvent $event
     */
    public function generateLabel(GenerateLabelEvent $event)
    {
        $deliveryModuleCode = $event->getOrder()->getModuleRelatedByDeliveryModuleId()->getCode();
        if ($deliveryModuleCode === "ChronopostHomeDelivery" || $deliveryModuleCode === "ChronopostPickupPoint") {
            $data = [];
            $orderId = $event->getOrder()->getId();
            $data['order_id'] = $orderId;
            $data['weight'] = $event->getWeight();
            $event->setResponse($this->service->generateLabel($data));
        }
    }

    public static function getSubscribedEvents()
    {
        $events = [];
        if (class_exists('Picking\Event\GenerateLabelEvent')){
            $events[GenerateLabelEvent::PICKING_GENERATE_LABEL] = ['generateLabel', 65];
        }
        return $events;
    }
}