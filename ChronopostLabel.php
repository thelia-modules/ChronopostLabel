<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace ChronopostLabel;


use ChronopostLabel\Config\ChronopostLabelConst;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

class ChronopostLabel extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'chronopostLabel';

    const CHRONOPOST_CONFIRMATION_MESSAGE_NAME = 'chronopost_confirmation_message_name';

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        /** Default config values */
        $defaultConfig = [
            ChronopostLabelConst::CHRONOPOST_LABEL_CODE_CLIENT => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_PASSWORD => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR => THELIA_LOCAL_DIR . 'chronopost',
            ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_TYPE => "PDF",
            ChronopostLabelConst::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS => "N",
            ChronopostLabelConst::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS => 3,
            ChronopostLabelConst::CHRONOPOST_LABEL_EXPIRATION_DATE => null,

            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME1 => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_NAME2 => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS1 => ConfigQuery::read("store_address1"),
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ADDRESS2 => ConfigQuery::read("store_address2"),
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_COUNTRY => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CITY => ConfigQuery::read("store_city"),
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_ZIP => ConfigQuery::read("store_zipcode"),
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CIVILITY => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_PHONE => ConfigQuery::read("store_phone"),
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE => null,
            ChronopostLabelConst::CHRONOPOST_LABEL_SHIPPER_MAIL => ConfigQuery::read("store_email"),
        ];

        /** Set the default config values in the DB table if it doesn't exists yet */
        foreach ($defaultConfig as $key => $value) {
            if (null === self::getConfigValue($key, null)) {
                self::setConfigValue($key, $value);
            }
        }

        /** Check if the path given is a directory, creates it otherwise */
        $dir = self::getConfigValue(ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR, null);
        $fs = new Filesystem();
        if (!is_dir($dir)) {
            $fs->mkdir($dir);
        }

        if (null === MessageQuery::create()->findOneByName(self::CHRONOPOST_CONFIRMATION_MESSAGE_NAME)) {
            $message = new Message();

            $message
                ->setName(self::CHRONOPOST_CONFIRMATION_MESSAGE_NAME)
                ->setHtmlLayoutFileName('order_shipped.html')
                ->setTextLayoutFileName('order_shipped.txt')
                ->setLocale('en_US')
                ->setTitle('Order send confirmation')
                ->setSubject('Order send confirmation')

                ->setLocale('fr_FR')
                ->setTitle('Confirmation d\'envoi de commande')
                ->setSubject('Confirmation d\'envoi de commande')

                ->save()
            ;
        }

    }
}
