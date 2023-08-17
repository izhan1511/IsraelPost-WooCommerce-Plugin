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
     exit; // Exit if accessed directly.
 }

// if uninstall not called from WordPress exit
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (defined('WC_REMOVE_ALL_DATA') && true === WC_REMOVE_ALL_DATA) {
    // Delete options.
    $pluginDefinedOptions = ['slparcels_db_version'];
    foreach ($pluginDefinedOptions as $optionName) {
        delete_option($optionName);
    }
    //global $wpdb;
    //$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}slparcels_ship_modes_countries_map`");
    //$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}slparcels_ship_modes_pack_weight_map`");
    //$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}slparcels_parcels`");
}
