<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

class Trus_SLParcels_Table_ShipModesCountriesMap extends Trus_SLParcels_Table_AbstractTable
{
    protected $table_header = "Ship Modes / Countries Map";
    protected $items_per_page = 1000;
    protected $table_name = "slparcels_ship_modes_countries_map";
    protected $package = "slparcels_ship_modes_countries_map";
    protected $display_header_h = "2";

    public function __construct()
    {
        $this->columns = [
            'country_code'      => __('Country Code', 'slparcels'),
            'ship_mode'   => __('Ship Mode', 'slparcels'),
            'max_parcel_weight' => __('Max Parcel Weight', 'slparcels'),
        ];

        /*$this->sortable_columns = [
            'country_code' => 'country_code',
            'ship_mode' => 'ship_mode',
            'max_parcel_weight' => 'max_parcel_weight'
        ];

        $this->searchable_columns = [
            'country_code' => 'country_code',
            'ship_mode' => 'ship_mode'
        ];*/

        parent::__construct([
            'singular' => 'wp_list_slparcels_ship_modes_country',
            'plural'   => 'wp_list_slparcels_ship_modes_countries',
            'ajax'     => false
        ]);
    }
}
