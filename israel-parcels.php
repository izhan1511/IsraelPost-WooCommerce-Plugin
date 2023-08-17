<?php
/**
 * Plugin Name: israel Parcels
 * Description: Parcels management with IL-Post for israel.
 * Author: Trus
 * Author URI: https://www.trus.co.il/
 * Copyright © 2022 Trus (private use only, distribution or selling is not allowed without the author's permission).
 * Version: 1.0.0
 * Requires at least: 5.2.3
 * WC requires at least: 5.8.2
 * Text Domain: slparcels
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TRUS_SLPARCELS_VERSION', get_plugin_data(__FILE__)['Version']);
define('TRUS_SLPARCELS_PATH', plugin_dir_path(__FILE__));
define('TRUS_SLPARCELS_ASSETS_URL', plugins_url('assets/', __FILE__));
define('TRUS_SLPARCELS_TEMPLATES_PATH', TRUS_SLPARCELS_PATH . 'templates/');

require_once(TRUS_SLPARCELS_PATH . 'vendor/autoload.php');
require_once(TRUS_SLPARCELS_PATH . 'includes/autoload.php');

/**
 * WooCommerce fallback notice.
 * @return string
 */

register_activation_hook(__FILE__, function () {
    Trus_SLParcels_DB::create_tables();
});

add_action('plugins_loaded', 'slparcels_init');

function slparcels_init()
{
    load_plugin_textdomain('slparcels', false, plugin_basename(dirname(__FILE__)) . '/languages');

    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>' . sprintf(esc_html__('israel Parcels requires WooCommerce to be installed and active. You can download %s here.', 'slparcels'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
        });
        return;
    }

    if (class_exists('Trus_SLParcels')) {
        return;
    }

    class Trus_SLParcels
    {
        /**
         * @var Singleton The reference the *Singleton* instance of this class
         */
        private static $instance;

        private function __construct()
        {
            $this->init();
        }

        /**
         * Returns the *Singleton* instance of this class.
         *
         * @return Singleton The *Singleton* instance.
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Prevent cloning of the instance of the Singleton instance.
         *
         * @return void
         */
        private function __clone()
        {
        }

        /**
         * Prevent unserializing of the Singleton instance.
         *
         * @return void
         */
        private function __wakeup()
        {
        }

        /**
         * Init the plugin after plugins_loaded so environment variables are set.
         */
        public function init()
        {
            $this->maybe_download_files();

            // Install / Update
            if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX) && is_plugin_active(__FILE__)) {
                if (version_compare(get_option("slparcels_db_version") ?: '0', TRUS_SLPARCELS_VERSION, '<')) {
                    Trus_SLParcels_DB::create_tables();
                }
            }

            // Filters
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
            add_filter('woocommerce_get_settings_pages', [$this, 'get_settings_pages'], 50);
            add_filter('woocommerce_get_wp_query_args', [$this, 'add_orders_meta_query_support'], 10, 2);

            // Actions
            add_action('init', [$this, 'register_order_statuses']);
            add_action('add_meta_boxes', array( $this, 'add_meta_boxes' ), 30);
            add_action('init', [$this, 'maybe_get_reshimone']);
            add_action('init', [$this, 'maybe_bulk_action']);

            add_action('admin_menu', [$this, 'add_admin_menus']);

            Trus_SLParcels_Cron::init();
        }

        public function add_orders_meta_query_support($wp_query_args, $query_vars)
        {
            if (Trus_SLParcels_Config::is_enabled()) {
                if (isset($query_vars['meta_query'])) {
                    $meta_query = isset($wp_query_args['meta_query']) ? $wp_query_args['meta_query'] : [];
                    $wp_query_args['meta_query'] = array_merge($meta_query, $query_vars['meta_query']);
                }
                foreach (['cache_results', 'update_post_meta_cache', 'update_post_term_cache'] as $arg) {
                    if (isset($query_vars[$arg])) {
                        $wp_query_args[$arg] = $query_vars[$arg];
                    }
                }
            }
            return $wp_query_args;
        }

        /**
         * Adds plugin action links.
         */
        public function plugin_action_links($links)
        {
            $plugin_links = [
                '<a href="admin.php?page=wc-settings&tab=slparcels">' . esc_html__('Parcels Settings', 'slparcels') . '</a>',
            ];
            return array_merge($plugin_links, $links);
        }

        /**
         * Include the settings page classes.
         */
        public function get_settings_pages($settings)
        {
            $settings[] = new Trus_SLParcels_Settings();
            return $settings;
        }

        /**
         * register_order_statuses
         */
        public function register_order_statuses()
        {
            register_post_status('wc-slshipped ', [
                'label' => __('Shipped', 'woocommerce'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
            ]);

            add_filter('wc_order_statuses', function ($order_statuses) {
                $new_order_statuses = [];
                foreach ($order_statuses as $key => $status) {
                    $new_order_statuses[$key] = $status;
                    if ('wc-processing' === $key) {
                        $new_order_statuses['wc-slshipped'] = __('Shipped', 'woocommerce');
                    }
                }
                return $new_order_statuses;
            });

            add_filter('woocommerce_admin_order_actions', function ($actions, $order) {
                if ($order->has_status(array( 'on-hold', 'processing', 'pending' ))) {
                    $actions['slshipped'] = array(
                        'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=slshipped&order_id='.$order->get_id()), 'woocommerce-mark-order-status'),
                        'name'      => __('Shipped', 'woocommerce'),
                        'action'    => 'slshipped',
                    );
                }
                return $actions;
            }, 100, 2);

            add_action('admin_head', function () {
                ?>
                <style>.wc-action-button-slshipped::after {font-family: woocommerce !important; content: "\e029" !important;}</style>
                <?php
            });
        }

        /**
    	 * Add WC Meta boxes.
    	 */
        public function add_meta_boxes()
        {
            $screen    = get_current_screen();
            $screen_id = $screen ? $screen->id : '';

            // Orders.
            foreach (wc_get_order_types('order-meta-boxes') as $type) {
                $order_type_object = get_post_type_object($type);
                add_meta_box('israel-parcels', __('israel Parcels', 'slparcels'), 'Trus_SLParcels_MetaBox::order_output', $type, 'normal', 'default');
            }
        }

        /**
         * Add admin menus
         */
        public function add_admin_menus()
        {
            add_submenu_page(
                'woocommerce',
                'israel Parcels',
                'israel Parcels',
                'manage_options',
                'israel-parcels',
                [$this, 'render_admin_parcels_table_page'],
                //'dashicons-clipboard',
                50
            );
        }

        /**
         * Renders the Admin UI
         */
        public function render_admin_parcels_table_page()
        {
            $parcels_table = new Trus_SLParcels_Table_Parcels();
            $parcels_table->display_page();
        }

        public function maybe_get_reshimone()
        {
            if (isset($_GET['slparcels_get_reshimone']) && !empty($_GET['type'])) {
                $user = wp_get_current_user();
                if (!$user || !array_intersect(['editor', 'administrator', 'author', 'shop_manager'], $user->roles)) {
                    header('HTTP/1.0 403 Forbidden');
                    die('You are not allowed to access this file.');
                }

                $reshimone_type = $_GET['type'];
                switch ($reshimone_type) {
                    case 'bag':
                        if (empty($_GET['bag'])) {
                            return;
                        }
                        $bag = wc_clean((string) $_GET['bag']);
                        $reshimone = Trus_SLParcels_ParcelsHelper::get_reshimone_by_bag($bag);
                        if (file_exists($reshimone)) {
                            header('Content-Encoding: UTF-8');
                            header('Content-type: text/csv; charset=UTF-8');
                            header('Content-Disposition: attachment; filename=reshimone_for_bag_' . $bag . '.csv');
                            echo "\xEF\xBB\xBF"; // UTF-8 BOM
                            readfile($reshimone);
                            //fpassthru($reshimone);
                            die;
                        }
                        break;

                    case 'monthly':
                        if (empty($_GET['month']) || empty($_GET['year'])) {
                            return;
                        }
                        $month = wc_clean((int) $_GET['month']);
                        $year = wc_clean((int) $_GET['year']);
                        $reshimone = Trus_SLParcels_ParcelsHelper::get_reshimone_by_month($month, $year);
                        if (file_exists($reshimone)) {
                            header('Content-Type: application/zip');
                            header('Content-Disposition: attachment; filename=reshimone_for_month_' . $month . '_' . $year .'.zip');
                            header('Content-Length: ' . filesize($reshimone));
                            readfile($reshimone);
                            die;
                        }
                        break;

                    default:
                        header('HTTP/1.0 403 Forbidden');
                        die('Unsupported file type.');
                        break;
                }
            }
        }

        public function maybe_download_files()
        {
            if (!empty($_GET['download_slparcels_type']) && (!empty($_GET['parcel_id']) || !empty($_GET['parcel_ids']))) {
                $download_type = $_GET['download_slparcels_type'];
                switch ($download_type) {
                    case 'label':
                        $content_type = 'image/png';
                        $file_dir = trailingslashit(wp_upload_dir()['basedir']) . "israel-parcels-labels/";
                        break;

                    case 'invoice':
                        $content_type = 'application/pdf';
                        $file_dir = trailingslashit(wp_upload_dir()['basedir']) . "israel-parcels-invoices/";
                        break;

                    case 'bulk_labels':
                        $content_type = 'application/pdf';
                        break;

                    case 'bulk_invoices':
                        $content_type = 'application/pdf';
                        break;

                    default:
                        header('HTTP/1.0 403 Forbidden');
                        die('Unsupported file type.');
                        break;
                }
                $user = wp_get_current_user();
                if (!$user || !array_intersect(['editor', 'administrator', 'author', 'shop_manager'], $user->roles)) {
                    header('HTTP/1.0 403 Forbidden');
                    die('You are not allowed to access this file.');
                }
                if (!empty($_GET['parcel_id'])) {
                    $parcel_id = (int) $_GET['parcel_id'];
                    $parcel = Trus_SLParcels_DB::get_parcel_by_id($parcel_id);
                    if (!$parcel || empty($parcel[$download_type])) {
                        throw new \Exception("Requested parcel or {$download_type} doesn't exist.");
                    }
                    $file_path = $file_dir . $parcel[$download_type];
                } elseif (!empty($_GET['parcel_ids'])) {
                    $parcel_ids = (array) $_GET['parcel_ids'];
                    $delete_after = true;
                    switch ($download_type) {
                        case 'bulk_labels':
                            $file_path = Trus_SLParcels_ParcelsHelper::bulk_print_labels($parcel_ids);
                            break;

                        case 'bulk_invoices':
                            $file_path = Trus_SLParcels_ParcelsHelper::bulk_print_invoices($parcel_ids);
                            break;
                    }
                }
                if (!is_file($file_path)) {
                    throw new \Exception("Requested file doesn't exist.");
                }

                header('Content-Type: ' . $content_type);
                //header('Content-Type: application/octet-stream');
                if (empty($_GET['open_in_new_tab'])) {
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
                }
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file_path));
                flush();
                readfile($file_path);
                if (!empty($delete_after)) {
                    unlink($file_path);
                }
                die();
            }
        }

        public function maybe_bulk_action()
        {
            if (!empty($_GET['slparcels_bulk_action']) && (!empty($_GET['parcel_ids']) || !empty($_GET['order_id']))) {
                $user = wp_get_current_user();
                if (!$user || !array_intersect(['editor', 'administrator', 'author', 'shop_manager'], $user->roles)) {
                    header('HTTP/1.0 403 Forbidden');
                    die('You are not allowed to access this file.');
                }

                $_GET['bulk_input'] = isset($_GET['bulk_input']) ? $_GET['bulk_input'] : null;
                $bulk_action = $_GET['slparcels_bulk_action'];
                switch ($bulk_action) {
                    case 'bulk_set_bag':
                        Trus_SLParcels_ParcelsHelper::bulk_set_bag($_GET['parcel_ids'], $_GET['bulk_input']);
                        break;

                    case 'bulk_send_bag':
                        Trus_SLParcels_ParcelsHelper::bulk_send_bag($_GET['parcel_ids'], strtotime(wc_clean($_GET['bulk_input'])));
                        break;

                    case 'bulk_delete_order_parcels':
                        Trus_SLParcels_ParcelsHelper::delete_order_parcels((int)$_GET['order_id']);
                        break;

                    default:
                        header('HTTP/1.0 403 Forbidden');
                        die('Unsupported bulk action.');
                        break;
                }
                wp_redirect($_SERVER["HTTP_REFERER"]);
                die();
            }
        }
    }

    Trus_SLParcels::get_instance();
}
