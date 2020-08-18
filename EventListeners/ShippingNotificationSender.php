<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 21/07/2020
 * Time: 16:27
 */

namespace ChronopostLabel\EventListeners;


use ChronopostHomeDelivery\ChronopostHomeDelivery;
use ChronopostLabel\ChronopostLabel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;

class ShippingNotificationSender  extends BaseAction implements EventSubscriberInterface
{
    /** @var MailerFactory */
    protected $mailer;
    /** @var ParserInterface */
    protected $parser;

    public function __construct(ParserInterface $parser, MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ['sendShippingNotification', 128]
        ];
    }

    /**
     * @param OrderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function sendShippingNotification(OrderEvent $event)
    {
        if ($event->getOrder()->isSent() && (
            $event->getOrder()->getDeliveryModuleId() == ModuleQuery::create()->findOneByCode('ChronopostHomeDelivery')->getId() ||
            $event->getOrder()->getDeliveryModuleId() == ModuleQuery::create()->findOneByCode('ChronopostPickupPoint')->getId()
            )) {

            $contact_email = ConfigQuery::getStoreEmail();

            if ($contact_email) {
                $order = $event->getOrder();
                $customer = $order->getCustomer();

                $this->mailer->sendEmailToCustomer(
                    ChronopostLabel::CHRONOPOST_CONFIRMATION_MESSAGE_NAME,
                    $order->getCustomer(),
                    [
                        'order_id'      => $order->getId(),
                        'order_ref'     => $order->getRef(),
                        'customer_id'   => $customer->getId(),
                        'order_date'    => $order->getCreatedAt(),
                        'update_date'   => $order->getUpdatedAt(),
                        'package'       => $order->getDeliveryRef()
                    ]
                );
            }
        }
    }
}