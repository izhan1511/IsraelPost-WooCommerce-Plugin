<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Trus_SLParcels_Logger
 */
class Trus_SLParcels_Logger
{
    public const WC_LOG_FILENAME = 'trus-israel-parcels';

    public static $logger;

    public static function log($message, $type = "debug", $data = [], $prefix = '[Slparcels] ')
    {
        if (! class_exists('WC_Logger')) {
            return;
        }

        if (empty(self::$logger)) {
            self::$logger = wc_get_logger();
        }

        if ($type !== 'debug' || Trus_SLParcels_Config::is_debug_enabled()) {
            $message = $prefix . date('Y-m-d H:i:s') . "\nType: " . $type . "\nMessage: " . print_r($message, true) . (!empty($data) ? "\nData: " . print_r($data, true) : "");
            self::$logger->log($type, $message, array( 'source' => self::WC_LOG_FILENAME ));
        }
    }
}
