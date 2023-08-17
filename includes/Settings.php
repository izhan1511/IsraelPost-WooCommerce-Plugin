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
 * Trus_SLParcels_Settings class.
 */
class Trus_SLParcels_Settings extends WC_Settings_Page
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id    = 'slparcels';
        $this->label = __('Parcels', 'slparcels');
        add_action('woocommerce_admin_field_slparcels_file', [$this, 'render_admin_field_slparcels_file']);
        add_action('woocommerce_after_settings_slparcels', [$this, 'render_ship_modes_countries_map_table']);
        add_action('woocommerce_after_settings_slparcels', [$this, 'render_ship_modes_pack_weight_map_table']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        parent::__construct();
    }

    /*
     * CSS and JS for settings page on admin
     */
    public function admin_enqueue_scripts($hook)
    {
        if ($hook === 'woocommerce_page_wc-settings') {
            wp_enqueue_script('woocommerce_slparcels_settings', TRUS_SLPARCELS_ASSETS_URL . 'js/slparcels-settings.js', ['jquery']);
        }
    }


    /**
     * Output the settings.
     */
    public function output()
    {
        $settings = $this->get_settings();
        WC_Admin_Settings::output_fields($settings);
    }

    /**
     * Save settings.
     */
    public function save()
    {
        try {
            if (!empty($_POST['save'])) {
                if (empty($_REQUEST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'woocommerce-settings')) {
                    throw new \Exception("Save failed. Please try again.");
                }

                //Save settings fields
                WC_Admin_Settings::save_fields($this->get_settings());

                //Import slparcels_ship_modes_countries_map csv if needed
                if (!empty($_FILES["slparcels_ship_modes_countries_map"]['tmp_name'])) {
                    if ($_FILES["slparcels_ship_modes_countries_map"]["error"] > 0) {
                        throw new \Exception("Error on ship_modes_countries map upload: " . $_FILES["slparcels_ship_modes_countries_map"]["error"]);
                    } else {
                        $rows   = array_map('str_getcsv', file($_FILES['slparcels_ship_modes_countries_map']['tmp_name']));
                        $header = array_shift($rows);
                        $map    = [];
                        foreach ($rows as $line => $row) {
                            $row = array_combine($header, $row);
                            $row['country_code'] = trim($row['country_code']);
                            if (!in_array($row['ship_mode'], Trus_SLParcels_Config::get_supported_ship_modes())) {
                                throw new \Exception("Error on row #{$line}: `ship_mode` must be one of: ECOPOST/REGULAR/EMS/JEZ");
                            }
                            /*if (!empty($row['max_parcel_weight'])) {
                                $row['max_parcel_weight'] *= 1000;
                            }*/
                            $map[] = $row;
                        }
                        Trus_SLParcels_DB::upsert_slparcels_ship_modes_countries_map($map);
                    }
                }

                //Import slparcels_ship_modes_pack_weight_map csv if needed
                if (!empty($_FILES["slparcels_ship_modes_pack_weight_map"]['tmp_name'])) {
                    if ($_FILES["slparcels_ship_modes_pack_weight_map"]["error"] > 0) {
                        throw new \Exception("Error on ship_modes_pack_weight map upload: " . $_FILES["slparcels_ship_modes_pack_weight_map"]["error"]);
                    } else {
                        $rows   = array_map('str_getcsv', file($_FILES['slparcels_ship_modes_pack_weight_map']['tmp_name']));
                        $header = array_shift($rows);
                        $map    = [];
                        foreach ($rows as $line => $row) {
                            $row = array_combine($header, $row);
                            if (!in_array($row['ship_mode'], Trus_SLParcels_Config::get_supported_ship_modes())) {
                                throw new \Exception("Error on row #{$line}: `ship_mode` must be one of: ECOPOST/REGULAR/EMS/JEZ");
                            }
                            /*if (!empty($row['pack_weight'])) {
                                $row['pack_weight'] *= 1000;
                            }*/
                            $map[] = $row;
                        }
                        Trus_SLParcels_DB::upsert_slparcels_ship_modes_pack_weight_map($map);
                    }
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            die;
        }
    }

    /**
     * Render admin field slparcels file.
     */
    public function render_admin_field_slparcels_file($value)
    {
        $custom_attributes = array();
        if (! empty($value['custom_attributes']) && is_array($value['custom_attributes'])) {
            foreach ($value['custom_attributes'] as $attribute => $attribute_value) {
                $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }
        $field_description = WC_Admin_Settings::get_field_description($value);
        $description       = $field_description['description'];
        $tooltip_html      = $field_description['tooltip_html']; ?><tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?> <?php echo $tooltip_html; // WPCS: XSS ok.?></label>
            </th>
            <td class="forminp forminp-<?php echo esc_attr(sanitize_title($value['type'])); ?>">
                <input
                    name="<?php echo esc_attr($value['id']); ?>"
                    id="<?php echo esc_attr($value['id']); ?>"
                    type="file"
                    style="<?php echo esc_attr($value['css']); ?>"
                    class="<?php echo esc_attr($value['class']); ?>"
                    <?php echo implode(' ', $custom_attributes); // WPCS: XSS ok.?>
                    /><?php echo esc_html($value['suffix']); ?> <?php echo $description; // WPCS: XSS ok.?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render_ ship_modes_countries_map table
     */
    public function render_ship_modes_countries_map_table()
    {
        echo "<hr>";
        $methods_map_table = new Trus_SLParcels_Table_ShipModesCountriesMap();
        $methods_map_table->display_page();
    }

    /**
     * Render ship_modes_pack_weight_map table
     */
    public function render_ship_modes_pack_weight_map_table()
    {
        echo "<hr>";
        $methods_map_table = new Trus_SLParcels_Table_ShipModesPackWeightMap();
        $methods_map_table->display_page();
    }

    public function get_settings()
    {
        return apply_filters(
            'slparcels_settings',
            [
                'title' => [
                    'title' => __('israel Parcels Settings', 'woocommerce'),
                    'type'  => 'title',
                    'desc'  => __('Settings for israel parcels management', 'woocommerce'),
                    'id'    => 'israel_parcels_settings',
                ],
                'enabled' => [
                    'id'          => 'slparcels_enabled',
                    'title'		  => __('Enabled', 'slparcels'),
                    'type'        => 'select',
                    'default'     => 'no',
                    'options'     => [
                        'yes'  => __('Yes', 'slparcels'),
                        'no'   => __('No', 'slparcels'),
                    ],
                ],
                'debug' => [
                    'id'      => 'slparcels_debug',
                    'title'	  => __('Enable Debug Mode', 'slparcels'),
                    'type'    => 'select',
                    'default' => 0,
                    'options' => [
                        1   => __('Yes', 'slparcels'),
                        0   => __('No', 'slparcels'),
                    ],
                ],
                'slparcels_mode'  => [
                    'id'      => 'slparcels_mode',
                    'title'       => __('Mode', 'slparcels'),
                    'type'        => 'select',
                    'default'     => 'sandbox',
                    'options'     => [
                        'production'   => __('Live', 'slparcels'),
                        'sandbox'      => __('Sandbox', 'slparcels'),
                    ],
                ],
                'slparcels_identity_client' => [
                    'id'      => 'slparcels_identity_client',
                    'title' 	  => __('Identity Client', 'slparcels'),
                    'type' 		  => 'text',
                ],
                'slparcels_identity_secret' => [
                    'id'      => 'slparcels_identity_secret',
                    'title' 	  => __('Identity Secret', 'slparcels'),
                    'type' 		  => 'password',
                ],
                'slparcels_username' => [
                    'id'      => 'slparcels_username',
                    'title' => __('Username', 'slparcels'),
                    'type'  => 'text',
                ],
                'slparcels_password' => [
                    'id'      => 'slparcels_password',
                    'title' => __('Password', 'slparcels'),
                    'type'  => 'password'
                ],
                'slparcels_identity_client_sandbox' => [
                    'id'      => 'slparcels_identity_client_sandbox',
                    'title' 	  => __('Sandbox Identity Client', 'slparcels'),
                    'type' 		  => 'text',
                ],
                'slparcels_identity_secret_sandbox' => [
                    'id'      => 'slparcels_identity_secret_sandbox',
                    'title' 	  => __('Sandbox Identity Secret', 'slparcels'),
                    'type' 		  => 'password',
                ],
                'slparcels_username_sandbox' => [
                    'id'      => 'slparcels_username_sandbox',
                    'title' => __('Sandbox Username', 'slparcels'),
                    'type'  => 'text',
                ],
                'slparcels_password_sandbox' => [
                    'id'      => 'slparcels_password_sandbox',
                    'title' => __('Sandbox Password', 'slparcels'),
                    'type'  => 'password'
                ],
                'slparcels_order_tracking_note' => [
                    'id'      => 'slparcels_order_tracking_note',
                    'title' => __('Order Tracking Note', 'slparcels'),
                    'type'  => 'text',
                    'default' => 'Your order has been shipped.',
                ],
                'slparcels_ship_modes_countries_map' => [
                    'id'          => 'slparcels_ship_modes_countries_map',
                    'title' 	  => __('Countries / Ship Modes', 'slparcels'),
                    'desc'        => __('Upload a csv file that maps between the [country_code / ship_mode / max_parcel_weight]. *This would override the current saved map!', 'slparcels'),
                    'desc_tip'    => false,
                    'type' 		  => 'slparcels_file',
                ],
                'slparcels_ship_modes_pack_weight_map' => [
                    'id'          => 'slparcels_ship_modes_pack_weight_map',
                    'title' 	  => __('Ship Modes / Pack Weight', 'slparcels'),
                    'desc'        => __('Upload a csv file that maps between the [ship_mode / products_qty / pack_weight]. *This would override the current saved map!', 'slparcels'),
                    'desc_tip'    => false,
                    'type' 		  => 'slparcels_file',
                ],
                'sectionend' => [
                    'type' => 'sectionend',
                    'id'   => 'israel_parcels_settings',
                ]
            ]
        );
    }
}
