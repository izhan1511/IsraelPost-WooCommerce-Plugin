<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

class Trus_SLParcels_Table_ShipModesPackWeightMap extends Trus_SLParcels_Table_AbstractTable
{
    protected $table_header = "Ship Modes / Pack Weight Map";
    protected $items_per_page = 1000;
    protected $table_name = "slparcels_ship_modes_pack_weight_map";
    protected $package = "slparcels_ship_modes_pack_weight_map";
    protected $display_header_h = "2";

    public function __construct()
    {
        $this->columns = [
            'ship_mode'   => __('Ship Mode', 'slparcels'),
            'products_qty'   => __('Products Quantity', 'slparcels'),
            'pack_weight' => __('Pack Weight', 'slparcels'),
        ];

        /*$this->sortable_columns = [
            'ship_mode'   => 'ship_mode',
            'products_qty'   => 'products_qty',
            'pack_weight' => 'pack_weight',
        ];

        $this->searchable_columns = [
            'ship_mode'   => 'ship_mode',
            'products_qty'   => 'products_qty',
            'pack_weight' => 'pack_weight',
        ];*/

        parent::__construct([
            'singular' => 'wp_list_slparcels_ship_modes_pack_weight',
            'plural'   => 'wp_list_slparcels_ship_modes_pack_weights',
            'ajax'     => false
        ]);
    }
}
