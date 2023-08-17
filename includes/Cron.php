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

/**
 * Trus_SLParcels_Cron.
 */
class Trus_SLParcels_Cron
{
    public const CREATE_PARCELS_ORDERS_LIMIT = 100;
    public const CREATE_LABELS_ORDERS_LIMIT = 20;
    public const CREATE_INVOICES_ORDERS_LIMIT = 20;
    public const TRACK_PARCELS_LIMIT = 20;
    public const CREATE_PARCELS_STATUSES = ['wc-processing'];

    public static function init()
    {
        if (Trus_SLParcels_Config::is_enabled()) {
            // Add cron intervals
            add_filter('cron_schedules', function ($schedules) {
                $schedules['one_second'] = array(
                    'interval' => 1,
                    'display'  => esc_html__('Every Second'), );
                $schedules['five_seconds'] = array(
                    'interval' => 5,
                    'display'  => esc_html__('Every Five Seconds'), );
                return $schedules;
            });

            add_action('slparcels_cron_create_parcels', function () {
                Trus_SLParcels_Cron::create_parcels();
            });

            add_action('slparcels_cron_create_labels', function () {
                Trus_SLParcels_Cron::create_labels();
            });

            add_action('slparcels_cron_create_invoices', function () {
                Trus_SLParcels_Cron::create_invoices();
            });

            add_action('slparcels_cron_track_parcels', function () {
                Trus_SLParcels_Cron::track_parcels();
            });

            if (! wp_next_scheduled('slparcels_cron_create_parcels')) {
                wp_schedule_event(time(), 'one_second', 'slparcels_cron_create_parcels');
            }

            if (! wp_next_scheduled('slparcels_cron_create_labels')) {
                wp_schedule_event(time(), 'one_second', 'slparcels_cron_create_labels');
            }

            if (! wp_next_scheduled('slparcels_cron_create_invoices')) {
                wp_schedule_event(time(), 'one_second', 'slparcels_cron_create_invoices');
            }

            if (! wp_next_scheduled('slparcels_cron_track_parcels')) {
                wp_schedule_event(time(), 'hourly', 'slparcels_cron_track_parcels');
            }
        }
    }

    public static function clean_orders_cache()
    {
        WC_Cache_Helper::invalidate_cache_group('orders');
        wc_delete_shop_order_transients();
        wc_nocache_headers();
    }

    public static function get_nocache_order(WC_Order $order, $reload = true)
    {
        clean_post_cache($order->get_id());
        wc_delete_shop_order_transients($order);
        wp_cache_delete('order-items-' . $order->get_id(), 'orders');
        return $reload ? wc_get_order($order->get_id()) : $order;
    }

    public static function create_parcels($orders_limit = Trus_SLParcels_Cron::CREATE_PARCELS_ORDERS_LIMIT)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_Cron::create_parcels().");
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() [STARTED]", 'debug');
            Trus_SLParcels_Cron::clean_orders_cache();
            $orders = wc_get_orders([
                'limit'  => $orders_limit,
                'type' => 'shop_order',
                'orderby' => 'modified',
                'order' => 'ASC',
                'cache_results' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'status' => Trus_SLParcels_Cron::CREATE_PARCELS_STATUSES,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'     => '_slparcels_parcels_created',
                        'compare'   => 'NOT EXISTS'
                    ],
                    [
                        'key'     => '_shipping_country',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => '_shipping_country',
                        'compare' => 'NOT IN',
                        'value'   => ['',null]
                    ],
                    [
                        'key'     => '_slparcels_nocache_' . time(),
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() Found " . count($orders) . " orders.", 'debug');
            foreach ($orders as $key => $order) {
                try {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() Order #{$order->get_id()} - Processing...", 'debug');
                    Trus_SLParcels_ParcelsHelper::create_parcels_for_order($order);
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() Order #{$order->get_id()} - Done.", 'debug');
                } catch (\Exception $e) {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() Order #{$order->get_id()} [EXCEPTION]", 'error', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() [DONE]", 'debug');
        } catch (\Exception $e) {
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_parcels() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public static function create_labels($orders_limit = Trus_SLParcels_Cron::CREATE_LABELS_ORDERS_LIMIT)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_Cron::create_labels().");
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() [STARTED]", 'debug');
            Trus_SLParcels_Cron::clean_orders_cache();
            $orders = wc_get_orders([
                'limit'  => $orders_limit,
                'type' => 'shop_order',
                'orderby' => 'modified',
                'order' => 'ASC',
                'cache_results' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'     => '_slparcels_parcels_created',
                        'compare' => '=',
                        'value'   => '1'
                    ],
                    [
                        'key'     => '_slparcels_labels_created',
                        'compare'   => 'NOT EXISTS'
                    ],
                    [
                        'key'     => '_slparcels_nocache_' . time(),
                        'compare' => 'NOT EXISTS'
                    ]
                ],
            ]);
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Found " . count($orders) . " orders.", 'debug');
            foreach ($orders as $order) {
                try {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Order #{$order->get_id()} - Processing...", 'debug');
                    $parcels = Trus_SLParcels_ParcelsHelper::get_order_parcels($order);
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Order #{$order->get_id()} - Found " . count($parcels) . " parcels.", 'debug');
                    foreach ($parcels as $parcel) {
                        Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Order #{$order->get_id()} - Processing parcel #{$parcel['id']} ...", 'debug');
                        $parcel = Trus_SLParcels_ParcelsHelper::generate_parcel_label($parcel);
                        if (empty($parcel['label'])) {
                            throw new \Exception("Couldn't create label for parcel #{$parcel['id']}. [SKIPPING]", 1);
                        }
                        Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Order #{$order->get_id()} - Processing parcel #{$parcel['id']} DONE.", 'debug');
                    }
                    $order->add_meta_data('_slparcels_labels_created', 1, true);
                    $order->delete_meta_data('_slparcels_labels_created_error');
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Order #{$order->get_id()} - Done.", 'debug');
                } catch (\Exception $e) {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() Order #{$order->get_id()} [EXCEPTION]", 'error', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $order->delete_meta_data('_slparcels_labels_created');
                    $order->add_meta_data('_slparcels_labels_created_error', $e->getMessage(), true);
                }
                $order->set_date_modified(current_time('mysql'));
                $order->save();
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() [DONE]", 'debug');
        } catch (\Exception $e) {
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public static function create_invoices($orders_limit = Trus_SLParcels_Cron::CREATE_INVOICES_ORDERS_LIMIT)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_Cron::create_invoices().");
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() [STARTED]", 'debug');
            Trus_SLParcels_Cron::clean_orders_cache();
            $orders = wc_get_orders([
                'limit'  => $orders_limit,
                'type' => 'shop_order',
                'orderby' => 'modified',
                'order' => 'ASC',
                'cache_results' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'     => '_slparcels_parcels_created',
                        'compare' => '=',
                        'value'   => '1'
                    ],
                    /*[
                        'key'     => '_reepay_masked_card',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => '_reepay_masked_card',
                        'compare' => 'NOT IN',
                        'value'   => ['', null]
                    ],*/
                    [
                        'key'     => '_slparcels_labels_created',
                        'compare' => '=',
                        'value'   => '1'
                    ],
                    [
                        'key'     => '_slparcels_invoices_created',
                        'compare'   => 'NOT EXISTS'
                    ],
                    [
                        'key'     => '_slparcels_nocache_' . time(),
                        'compare' => 'NOT EXISTS'
                    ]
                ],
            ]);
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Found " . count($orders) . " orders.", 'debug');
            foreach ($orders as $order) {
                try {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Order #{$order->get_id()} - Processing...", 'debug');
                    $order = Trus_SLParcels_Cron::get_nocache_order($order);
                    $parcels = Trus_SLParcels_ParcelsHelper::get_order_parcels($order);
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Order #{$order->get_id()} - Found " . count($parcels) . " parcels.", 'debug');
                    foreach ($parcels as $parcel) {
                        Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Order #{$order->get_id()} - Processing parcel #{$parcel['id']} ...", 'debug');
                        $parcel = Trus_SLParcels_ParcelsHelper::generate_parcel_invoice($parcel);
                        if (empty($parcel['invoice'])) {
                            throw new \Exception("Couldn't create invoice for parcel #{$parcel['id']}. [SKIPPING]", 1);
                        }
                        Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Order #{$order->get_id()} - Processing parcel #{$parcel['id']} DONE.", 'debug');
                    }
                    $order->add_meta_data('_slparcels_invoices_created', 1, true);
                    $order->delete_meta_data('_slparcels_invoices_created_error');
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Order #{$order->get_id()} - Done.", 'debug');
                } catch (\Exception $e) {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_invoices() Order #{$order->get_id()} [EXCEPTION]", 'error', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $order->delete_meta_data('_slparcels_invoices_created');
                    $order->add_meta_data('_slparcels_invoices_created_error', $e->getMessage(), true);
                }
                $order->set_date_modified(current_time('mysql'));
                $order->save();
                Trus_SLParcels_Cron::get_nocache_order($order, false);
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() [DONE]", 'debug');
        } catch (\Exception $e) {
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::create_labels() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public static function track_parcels($parcels_limit = Trus_SLParcels_Cron::TRACK_PARCELS_LIMIT)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_Cron::track_parcels().");
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() [STARTED]", 'debug');
            Trus_SLParcels_Cron::clean_orders_cache();
            $parcels = Trus_SLParcels_ParcelsHelper::get_parcels_for_tracking($parcels_limit);

            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() Found " . count($parcels) . " parcels.", 'debug');
            foreach ($parcels as $parcel) {
                try {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() Parcel #{$parcel['id']} - Processing...", 'debug');
                    $parcel = Trus_SLParcels_ParcelsHelper::update_parcel_tracking_info($parcel);
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() Parcel #{$parcel['id']} - Done.", 'debug');
                } catch (\Exception $e) {
                    Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() Parcel #{$parcel['id']} [EXCEPTION]", 'error', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() [DONE]", 'debug');
        } catch (\Exception $e) {
            Trus_SLParcels_Logger::log("Trus_SLParcels_Cron::track_parcels() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
