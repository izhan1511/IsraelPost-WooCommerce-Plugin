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

spl_autoload_register(function ($className = '') {
    if (strpos($className, 'Trus_SLParcels_') !== 0) {
        return;
    }
    require_once __DIR__ . str_replace('_', DIRECTORY_SEPARATOR, substr($className, strlen('Trus_SLParcels'))) . '.php';
});
