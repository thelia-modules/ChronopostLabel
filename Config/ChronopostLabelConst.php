<?php

namespace ChronopostLabel\Config;


use ChronopostLabel\ChronopostLabel;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\ConfigQuery;

class ChronopostLabelConst
{

    /** Chronopost shipper identifiers */
    const CHRONOPOST_LABEL_CODE_CLIENT                    = "chronopost_label_code";
    const CHRONOPOST_LABEL_PASSWORD                       = "chronopost_label_password";

    /** Chronopost label type (PDF,ZPL | With or without proof of deposit */
    const CHRONOPOST_LABEL_LABEL_TYPE                     = "chronopost_label_label_type";

    /** Directory where we save the label */
    const CHRONOPOST_LABEL_LABEL_DIR                      = "chronopost_label_label_dir";

    /** Send as customer status. */
    const CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS       = "chronopost_label_send_as_customer_status";

    /** ID of the order status to change during label creation */
    const CHRONOPOST_LABEL_CHANGE_ORDER_STATUS            = "chronopost_label_change_order_status";

    /** WSDL for the Chronopost Shipping Service */
    const CHRONOPOST_LABEL_SHIPPING_SERVICE_WSDL          = "https://ws.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl";
    //const CHRONOPOST_LABEL_RELAY_SEARCH_SERVICE_WSDL      = "https://ws.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl";
    const CHRONOPOST_LABEL_COORDINATES_SERVICE_WSDL       = "https://ws.chronopost.fr/rdv-cxf/services/CreneauServiceWS?wsdl";
    /** @TODO Add other WSDL config key */

    /** Days before fresh products expiration after processing */
    const CHRONOPOST_LABEL_EXPIRATION_DATE                = "chronopost_label_expiration_date";

    /** Shipper informations */
    const CHRONOPOST_LABEL_SHIPPER_NAME1          = "chronopost_label_shipper_name1";
    const CHRONOPOST_LABEL_SHIPPER_NAME2          = "chronopost_label_shipper_name2";
    const CHRONOPOST_LABEL_SHIPPER_ADDRESS1       = "chronopost_label_shipper_address1";
    const CHRONOPOST_LABEL_SHIPPER_ADDRESS2       = "chronopost_label_shipper_address2";
    const CHRONOPOST_LABEL_SHIPPER_COUNTRY        = "chronopost_label_shipper_country";
    const CHRONOPOST_LABEL_SHIPPER_CITY           = "chronopost_label_shipper_city";
    const CHRONOPOST_LABEL_SHIPPER_ZIP            = "chronopost_label_shipper_zipcode";
    const CHRONOPOST_LABEL_SHIPPER_CIVILITY       = "chronopost_label_shipper_civ";
    const CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME   = "chronopost_label_shipper_contact_name";
    const CHRONOPOST_LABEL_SHIPPER_PHONE          = "chronopost_label_shipper_phone";
    const CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE   = "chronopost_label_shipper_mobile_phone";
    const CHRONOPOST_LABEL_SHIPPER_MAIL           = "chronopost_label_shipper_mail";



    /** Local static config value, used to limit the number of calls to the DB  */
    protected static $config = null;

    public static function setConfig()
    {
        $config = [
            /** Chronopost basic informations */
            self::CHRONOPOST_LABEL_CODE_CLIENT                  => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_CODE_CLIENT),
            self::CHRONOPOST_LABEL_PASSWORD                     => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_PASSWORD),
            self::CHRONOPOST_LABEL_LABEL_DIR                    => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_LABEL_DIR),
            self::CHRONOPOST_LABEL_LABEL_TYPE                   => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_LABEL_TYPE),
            self::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS          => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_CHANGE_ORDER_STATUS),
            self::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS     => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_PRINT_AS_CUSTOMER_STATUS),
            self::CHRONOPOST_LABEL_EXPIRATION_DATE              => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_EXPIRATION_DATE),

            /** Shipper informations */
            self::CHRONOPOST_LABEL_SHIPPER_NAME1                => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_NAME1),
            self::CHRONOPOST_LABEL_SHIPPER_NAME2                => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_NAME2),
            self::CHRONOPOST_LABEL_SHIPPER_ADDRESS1             => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_ADDRESS1),
            self::CHRONOPOST_LABEL_SHIPPER_ADDRESS2             => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_ADDRESS2),
            self::CHRONOPOST_LABEL_SHIPPER_COUNTRY              => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_COUNTRY),
            self::CHRONOPOST_LABEL_SHIPPER_CITY                 => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_CITY),
            self::CHRONOPOST_LABEL_SHIPPER_ZIP                  => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_ZIP),
            self::CHRONOPOST_LABEL_SHIPPER_CIVILITY             => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_CIVILITY),
            self::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME         => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_CONTACT_NAME),
            self::CHRONOPOST_LABEL_SHIPPER_PHONE                => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_PHONE),
            self::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE         => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_MOBILE_PHONE),
            self::CHRONOPOST_LABEL_SHIPPER_MAIL                 => ChronopostLabel::getConfigValue(self::CHRONOPOST_LABEL_SHIPPER_MAIL),
            
            
        ];


        /** Add a / to the end of the path for the label directory if it wasn't added manually */
        if (substr($config[self::CHRONOPOST_LABEL_LABEL_DIR], -1) !== '/') {
            $config[self::CHRONOPOST_LABEL_LABEL_DIR] .= '/';
        }

        /** Check if the label directory exists, create it if it doesn't */
        if (!is_dir($config[self::CHRONOPOST_LABEL_LABEL_DIR])) {
            $fs = new Filesystem();

            $fs->mkdir($config[self::CHRONOPOST_LABEL_LABEL_DIR]);
        }

        /** Set the local static config value */
        self::$config = $config;
    }

    /**
     * Return the local static config value or the value of a given parameter
     *
     * @param null $parameter
     * @return array|mixed|null
     */
    public static function getConfig($parameter = null)
    {
        /** Check if the local config value is set, and set it if it's not */
        if (null === self::$config) {
            self::setConfig();
        }

        /** Return the value of the config parameter given, or null if it wasn't set */
        if (null !== $parameter) {
            return (isset(self::$config[$parameter])) ? self::$config[$parameter] : null;
        }

        /** Return the local static config value */
        return self::$config;
    }

}