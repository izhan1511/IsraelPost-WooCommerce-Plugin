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
 * Trus_SLParcels_DB class.
 */
class Trus_SLParcels_DB
{
    /**
     * Create Tables
     */
    public static function create_tables()
    {
        global $wpdb;

        if (version_compare(get_option("slparcels_db_version") ?: '0', TRUS_SLPARCELS_VERSION, '<')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $wpdb->get_charset_collate();

            //Create table: slparcels_ship_modes_countries_map
            dbDelta("CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}slparcels_ship_modes_countries_map` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
                `country_code` VARCHAR(2) NOT NULL COMMENT 'Country Code' ,
                `ship_mode` VARCHAR(10) NOT NULL COMMENT 'Ship Mode' ,
                `max_parcel_weight` DECIMAL(11,3) UNSIGNED NOT NULL COMMENT 'Max Parcel Weight' ,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At' ,
                `updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Updated At',
                PRIMARY KEY (`id`), CONSTRAINT COUNTRY_CODE_SHIP_MODE UNIQUE (`country_code`, `ship_mode`)
            ) ENGINE = InnoDB COMMENT = 'israel Parcels Ship Modes Countries Map' {$charset_collate};");

            //Create table: slparcels_ship_modes_pack_weight_map
            dbDelta("CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}slparcels_ship_modes_pack_weight_map` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
                `ship_mode` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
                `products_qty` int UNSIGNED NOT NULL,
                `pack_weight` decimal(11,3) UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At' ,
                `updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Updated At',
                PRIMARY KEY (`id`), CONSTRAINT SHIP_MODE_PRODUCTS_QTY UNIQUE (`ship_mode` , `products_qty`)
            ) ENGINE = InnoDB COMMENT = 'israel Parcels Ship Modes Pack Weight Map' {$charset_collate};");

            //Create table: slparcels_parcels
            dbDelta("CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}slparcels_parcels` (
                `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
                `order_id` bigint NOT NULL,
                `order_number` bigint NOT NULL,
                `bag` varchar(50) NOT NULL,
                `ship_mode` varchar(10) NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `invoice` VARCHAR(255) NOT NULL,
                `max_parcel_weight` decimal(11,3) UNSIGNED NOT NULL,
                `tracking_code` varchar(50) NOT NULL,
                `subtotal` decimal(11,3) UNSIGNED NOT NULL,
                `coupon_code` varchar(50) NOT NULL,
                `discount_amount` decimal(11,3) UNSIGNED NOT NULL,
                `total_price` decimal(11,3) UNSIGNED NOT NULL,
                `total_weight` decimal(11,3) UNSIGNED NOT NULL,
                `pack_weight` decimal(11,3) UNSIGNED NOT NULL,
                `gross_weight` decimal(11,3) UNSIGNED NOT NULL,
                `total_quantity` int NOT NULL,
                `currency` varchar(3) NOT NULL,
                `customer_id` bigint UNSIGNED DEFAULT NULL,
                `shipping_email` varchar(100) NOT NULL,
                `shipping_first_name` varchar(50) NOT NULL,
                `shipping_last_name` varchar(50) NOT NULL,
                `shipping_phone` varchar(50) NOT NULL,
                `shipping_company` varchar(50) NOT NULL,
                `shipping_address_1` varchar(100) NOT NULL,
                `shipping_address_2` varchar(100) NOT NULL,
                `shipping_city` varchar(50) NOT NULL,
                `shipping_state` varchar(50) NOT NULL,
                `shipping_postcode` varchar(20) NOT NULL,
                `shipping_country` varchar(10) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `labeled_at` timestamp NULL,
                `invoiced_at` timestamp NULL,
                `sent_at` timestamp NULL,
                `airport_at` timestamp NULL,
                `arrived_at` timestamp NULL,
                `last_track_at` timestamp NULL,
                `items` TEXT NOT NULL COMMENT 'JSON items',
                `label_error` TEXT NULL,
                PRIMARY KEY (`id`), INDEX (`order_id`), INDEX (`order_number`), INDEX (`bag`)
            ) ENGINE = InnoDB COMMENT = 'israel Parcels' {$charset_collate};");

            update_option('slparcels_db_version', TRUS_SLPARCELS_VERSION);
        }
    }

    /**
     * Upsert ship_modes/countries map
     * @param array $map
     * @return array
     */
    public static function upsert_slparcels_ship_modes_countries_map($map = [])
    {
        global $wpdb;

        $last_insert_id = null;
        foreach ($map as $line => &$row) {
            $sql = $wpdb->prepare(
                "INSERT INTO `{$wpdb->prefix}slparcels_ship_modes_countries_map` (`country_code`,`ship_mode`,`max_parcel_weight`) VALUES (%s,%s,%f) ON DUPLICATE KEY UPDATE ship_mode = VALUES(ship_mode), max_parcel_weight = VALUES(max_parcel_weight)",
                $row['country_code'],
                $row['ship_mode'],
                $row['max_parcel_weight']
            );
            $wpdb->query($sql);
            $row['id'] = $wpdb->insert_id;
            if ($row['id'] === $last_insert_id) {
                if ($wpdb->last_error) {
                    throw new \Exception("Error while inserting [ship_modes/countries] row #{$line}. Error: {$wpdb->last_error}");
                }
                throw new \Exception("Error while inserting row #{$line}");
            }
        }

        return $map;
    }

    /**
     * Upsert ship_modes/pack_weight map
     * @param array $map
     * @return array
     */
    public static function upsert_slparcels_ship_modes_pack_weight_map($map = [])
    {
        global $wpdb;

        $last_insert_id = null;
        foreach ($map as $line => &$row) {
            $sql = $wpdb->prepare(
                "INSERT INTO `{$wpdb->prefix}slparcels_ship_modes_pack_weight_map` (`ship_mode`,`products_qty`,`pack_weight`) VALUES (%s,%d,%f) ON DUPLICATE KEY UPDATE pack_weight = VALUES(pack_weight)",
                $row['ship_mode'],
                $row['products_qty'],
                $row['pack_weight']
            );
            $wpdb->query($sql);
            $row['id'] = $wpdb->insert_id;
            if ($row['id'] === $last_insert_id) {
                if ($wpdb->last_error) {
                    throw new \Exception("Error while inserting [ship_modes/pack_weight] row #{$line}. Error: {$wpdb->last_error}");
                }
                throw new \Exception("Error while inserting row #{$line}");
            }
        }

        return $map;
    }

    /**
     * @method get_ship_mode_info
     * @param  string                   $ship_mode
     * @param  string                   $country_code
     * @param  boolean                  $includePackWeight
     * @return array
     */
    public static function get_ship_mode_info($ship_mode, $country_code = '', $includePackWeight = true)
    {
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}slparcels_ship_modes_countries_map` WHERE `ship_mode` = '%s' AND (`country_code` = '%s' OR `country_code` = '') ORDER BY `country_code` DESC LIMIT 1",
            $ship_mode,
            trim($country_code)
        ), ARRAY_A);
        if (!empty($result['ship_mode']) && $includePackWeight) {
            $result["pack_weight_map"] = Trus_SLParcels_DB::get_ship_mode_pack_weight_map($result['ship_mode']);
        }
        return $result;
    }

    /**
     * @method get_ship_mode_by_country
     * @param  string                   $country_code
     * @param  boolean                  $includePackWeight
     * @return array|null
     */
    public static function get_ship_mode_by_country($country_code, $includePackWeight = true, $default_ship_mode = 'REGULAR')
    {
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}slparcels_ship_modes_countries_map` WHERE `country_code` = '%s' OR `ship_mode` = '%s' ORDER BY `country_code` DESC LIMIT 1",
            $country_code,
            $default_ship_mode
        ), ARRAY_A);
        if (!empty($result['ship_mode']) && $includePackWeight) {
            $result["pack_weight_map"] = Trus_SLParcels_DB::get_ship_mode_pack_weight_map($result['ship_mode']);
        }
        return $result;
    }

    /**
     * @method get_ship_mode_pack_weight_map
     * @param  string                   $ship_mode
     * @return array
     */
    public static function get_ship_mode_pack_weight_map($ship_mode)
    {
        global $wpdb;
        $result = [];
        if ($ship_mode) {
            $pack_weight_map = $wpdb->get_results($wpdb->prepare(
                "SELECT `products_qty`, `pack_weight` FROM `{$wpdb->prefix}slparcels_ship_modes_pack_weight_map` WHERE `ship_mode` = '%s' ORDER BY `products_qty` ASC",
                $ship_mode
            ), ARRAY_A);
            foreach ($pack_weight_map as $row) {
                $result[$row["products_qty"]] = $row["pack_weight"];
            }
        }
        return $result;
    }

    /**
     * Insert parcel
     * @param array $parcel
     * @return bool
     */
    public static function insert_parcel($parcel)
    {
        global $wpdb;
        foreach (['id', 'order_id', 'items'] as $field) {
            if (empty($parcel[$field])) {
                throw new \Exception("Error on insert_parcel. Parcel `{$field}` must not be empty.");
            }
        }
        if (is_array($parcel["items"]) || is_object($parcel["items"])) {
            $parcel["items"] = json_encode($parcel["items"]);
        }
        $wpdb->insert("{$wpdb->prefix}slparcels_parcels", $parcel);
        if ($wpdb->insert_id != $parcel["id"]) {
            if ($wpdb->last_error) {
                throw new \Exception("Error on insert_parcel. Error: {$wpdb->last_error}");
            }
            throw new \Exception("Error on insert_parcel.");
        }
        return true;
    }

    /**
     * Update parcel
     * @param array $parcel
     * @param int|null $parcel_id  Parcel ID, in case updating delta.
     * @return bool
     */
    public static function update_parcel($parcel, $parcel_id = null)
    {
        global $wpdb;
        if (!$parcel_id && empty($parcel["id"])) {
            throw new \Exception("Error on update_parcel. Parcel `id` must be set.");
        }
        $parcel_id = (int)$parcel_id ?: (int)$parcel["id"];
        if (!empty($parcel["items"]) && (is_array($parcel["items"]) || is_object($parcel["items"]))) {
            $parcel["items"] = json_encode($parcel["items"]);
        }
        $wpdb->update("{$wpdb->prefix}slparcels_parcels", $parcel, ['id' => $parcel_id]);
        if ($wpdb->last_error) {
            throw new \Exception("Error on update_parcel. Error: {$wpdb->last_error}");
        }
        return true;
    }

    /**
     * Update parcels
     * @param array $parcel_ids
     * @param array $data  Data to update
     * @return bool
     */
    public static function update_parcels($parcel_ids, $data)
    {
        global $wpdb;

        $data = (array) $data;
        if (isset($data['id'])) {
            throw new \Exception("Error on update_parcels. Can't set the same `id` on multiple rows.");
        }
        if (!empty($data["items"]) && (is_array($data["items"]) || is_object($data["items"]))) {
            $data["items"] = json_encode($data["items"]);
        }
        foreach ($data as $key => &$value) {
            $value = "`" . esc_sql(trim($key)) . "`='" . esc_sql(trim($value)) . "'";
        }

        foreach ($parcel_ids as &$parcel_id) {
            $parcel_id = (int) $parcel_id;
        }

        $wpdb->query("UPDATE `{$wpdb->prefix}slparcels_parcels` SET " . implode(", ", $data) .
            " WHERE `id` IN ('" . implode("','", $parcel_ids) . "')");

        if ($wpdb->last_error) {
            throw new \Exception("Error on update_parcels. Error: {$wpdb->last_error}");
        }
        return true;
    }

    /**
     * Delete parcel
     * @param bool
     */
    public static function delete_parcel($parcel_id)
    {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}slparcels_parcels", ['id' => $parcel_id]);
        if ($wpdb->last_error) {
            throw new \Exception("Error on delete_parcel. Error: {$wpdb->last_error}");
        }
        return true;
    }

    /**
     * @method delete_order_parcels
     * @param  int|false               $order_id  false will delete all!
     * @param  bool               $delete_sent_parcels
     * @return bool
     */
    public static function delete_order_parcels($order_id, $delete_sent_parcels = false)
    {
        global $wpdb;
        //$wpdb->delete("{$wpdb->prefix}slparcels_parcels", ['order_id' => $order_id]);
        $where_order = $order_id === false ? "" : "WHERE sp.`order_id` = '" . (int) $order_id ."'";
        $delete_sent_parcels = $delete_sent_parcels ? "" : "JOIN `{$wpdb->prefix}slparcels_parcels` AS sps ON sp.`order_id` = sps.`order_id` AND sps.`sent_at` IS NULL OR sps.`sent_at` = '0000-00-00 00:00:00'";
        $wpdb->query("DELETE sp, pm FROM `{$wpdb->prefix}slparcels_parcels` AS sp
            {$delete_sent_parcels}
            LEFT JOIN `{$wpdb->prefix}postmeta` AS pm ON sp.`order_id` = pm.`post_id`
            AND pm.`meta_key` IN (
                '_slparcels_ship_mode',
                '_slparcels_parcels_created',
                '_slparcels_parcels_created_error',
                '_slparcels_labels_created',
                '_slparcels_labels_created_error',
                '_slparcels_invoices_created',
                '_slparcels_invoices_created_error'
            )
            {$where_order}
        ;");
        if ($wpdb->last_error) {
            throw new \Exception("Error on delete_order_parcels({$order_id}). Error: {$wpdb->last_error}");
        }
        return true;
    }

    /**
     * @method delete_all_order_parcels
     * @param  bool                  $delete_sent_parcels
     * @return bool
     */
    public static function delete_all_order_parcels($delete_sent_parcels = false)
    {
        return self::delete_order_parcels(false, $delete_sent_parcels);
    }

    /**
     * @method get_order_parcels
     * @param  string            $key
     * @param  int|string        $val
     * @return array
     */
    public static function get_parcels_by($key, $val)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}slparcels_parcels` WHERE `" . esc_sql((string)$key) . "` = '%s'", $val), ARRAY_A);
    }


    /**
     * @method get_parcel_by_id
     * @param  int            $parcel_id
     * @return array
     */
    public static function get_parcel_by_id($parcel_id)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}slparcels_parcels` WHERE `id`='" . (int) $parcel_id . "' LIMIT 1", ARRAY_A);
    }

    /**
     * @method get_parcels_by_ids
     * @param  array            $parcel_ids
     * @return array
     */
    public static function get_parcels_by_ids($parcel_ids, $group_by = "")
    {
        global $wpdb;
        foreach ($parcel_ids as &$parcel_id) {
            $parcel_id = (int) $parcel_id;
        }
        if ($group_by) {
            $group_by = "GROUP BY " . esc_sql((string) $group_by);
        }
        return $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}slparcels_parcels` WHERE `id` IN ('" . implode("','", $parcel_ids) . "') {$group_by}", ARRAY_A);
    }

    /**
     * @method get_parcels_by_order_id
     * @param  int            $order_id
     * @return array
     */
    public static function get_parcels_by_order_id($order_id)
    {
        return self::get_parcels_by('order_id', (int) $order_id);
    }

    /**
     * @method get_parcels_by_bag
     * @param  string|int            $bag
     * @return array
     */
    public static function get_parcels_by_bag($bag)
    {
        return self::get_parcels_by('bag', $bag);
    }

    /**
     * @method get_parcels_by_month_and_year
     * @param  string|int            $month
     * @param  string|int            $year
     * @return array
     */
    public static function get_parcels_by_month_and_year($month, $year)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}slparcels_parcels`
            WHERE MONTH(`sent_at`) = '%s' AND YEAR(`sent_at`) = '%s'",
            $month,
            $year
        ), ARRAY_A);
    }

    /**
     * @method get_parcels_for_tracking
     * @param  integer                  $limit
     * @return array
     */
    public static function get_parcels_for_tracking($limit = 50)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}slparcels_parcels` WHERE `tracking_code` != '' AND (`airport_at` IS NULL OR `airport_at` = '0000-00-00 00:00:00' OR `arrived_at` IS NULL OR `arrived_at` = '0000-00-00 00:00:00') ORDER BY `last_track_at` ASC LIMIT " . (int) $limit, ARRAY_A);
    }

    /**
     * @method get_parcels_without_labels
     * @return array
     */
    public static function get_parcels_without_labels($orders_limit = 5)
    {
        global $wpdb;
        $orders_limit = (int) $orders_limit ? "LIMIT " . (int) $orders_limit : "";
        if ($orders_limit) {
            $order_ids = array_column($wpdb->get_results("SELECT `order_id` FROM `{$wpdb->prefix}slparcels_parcels` WHERE `label`='' GROUP BY `order_id` ORDER BY `created_at` ASC {$orders_limit}", ARRAY_A), "order_id");
            $order_ids = $order_ids ? "AND `order_id` IN ('" . implode("','", $order_ids) . "')" : "";
        }
        return $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}slparcels_parcels` WHERE `label`='' {$order_ids} ORDER BY `order_id` ASC, `created_at` ASC", ARRAY_A);
    }

    /**
     * @method count_order_unsent_parcels
     * @param  int                   $order_id
     * @return int
     */
    public static function count_order_unsent_parcels($order_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(`id`) FROM `{$wpdb->prefix}slparcels_parcels` WHERE `order_id` = '" . (int) $order_id . "' AND ( `sent_at` IS NULL OR `sent_at` IN ('', '0000-00-00 00:00:00'))");
    }

    /**
     * @method count_order_sent_parcels
     * @param  int                   $order_id
     * @return int
     */
    public static function count_order_sent_parcels($order_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(`id`) FROM `{$wpdb->prefix}slparcels_parcels` WHERE `order_id` = '" . (int) $order_id . "' AND ( `sent_at` IS NOT NULL AND `sent_at` NOT IN ('', '0000-00-00 00:00:00'))");
    }
}
