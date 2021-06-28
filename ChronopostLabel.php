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
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

class ChronopostLabel extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'chronopostLabel';

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(ConnectionInterface $con = null): void
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

    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
