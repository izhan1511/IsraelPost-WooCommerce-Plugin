<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

class Trus_SLParcels_Table_Parcels extends Trus_SLParcels_Table_AbstractTable
{
    protected $table_header = "israel Parcels";
    protected $items_per_page = 100;
    protected $table_name = "slparcels_parcels";
    protected $package = "slparcels_parcels";
    protected $id_field = 'id';

    public function __construct()
    {
        $this->columns = [
            'id' => __('Parcel ID', 'slparcels'),
            'order_number' => __('Order Number', 'slparcels'),
            'bag' => __('Bag', 'slparcels'),
            'sent_at' => __('Sent', 'slparcels'),
            'ship_mode' => __('Ship Mode', 'slparcels'),
            'items' => __('Items', 'slparcels'),
            'label' => __('Label', 'slparcels'),
            'invoice' => __('Invoice', 'slparcels'),
            'max_parcel_weight' => __('Max Parcel Weight', 'slparcels'),
            'tracking_code' => __('Tracking Code', 'slparcels'),
            'subtotal' => __('SubTotal', 'slparcels'),
            'coupon_code' => __('Coupon Code', 'slparcels'),
            'discount_amount' => __('Discount Amount', 'slparcels'),
            'total_price' => __('Total Price', 'slparcels'),
            'total_weight' => __('Total Weight', 'slparcels'),
            'pack_weight' => __('Pack Weight', 'slparcels'),
            'gross_weight' => __('Gross Weight', 'slparcels'),
            'total_quantity' => __('Total Quantity', 'slparcels'),
            'currency' => __('Currency', 'slparcels'),
            'customer_id' => __('Customer ID', 'slparcels'),
            'shipping_email' => __('Email', 'slparcels'),
            'shipping_first_name' => __('First Name', 'slparcels'),
            'shipping_last_name' => __('Last Name', 'slparcels'),
            'shipping_phone' => __('Phone', 'slparcels'),
            'shipping_company' => __('Company', 'slparcels'),
            'shipping_address_1' => __('Street/House', 'slparcels'),
            'shipping_address_2' => __('Apartment (Address 2)', 'slparcels'),
            'shipping_city' => __('City', 'slparcels'),
            'shipping_state' => __('State', 'slparcels'),
            'shipping_postcode' => __('Postcode', 'slparcels'),
            'shipping_country' => __('Country', 'slparcels'),
            'created_at' => __('Created', 'slparcels'),
            'updated_at' => __('Updated', 'slparcels'),
            'labeled_at' => __('Labeled', 'slparcels'),
            'invoiced_at' => __('Invoiced', 'slparcels'),
            'airport_at' => __('Airport', 'slparcels'),
            'arrived_at' => __('Arrived', 'slparcels'),
            'last_track_at' => __('Last Tracking Check', 'slparcels'),
        ];

        foreach ($this->columns as $key => $value) {
            $this->sortable_columns[$key] = $key;
        }

        $this->searchable_columns = [
            'id' => 'id',
            //'order_id' => 'order_id',
            'order_number' => 'order_number',
            'bag' => 'bag',
            'ship_mode' => 'ship_mode',
            'tracking_code' => 'tracking_code',
            //'customer_id' => 'customer_id',
            'shipping_email' => 'shipping_email',
            'shipping_first_name' => 'shipping_first_name',
            'shipping_last_name' => 'shipping_last_name',
            //'shipping_phone' => 'shipping_phone',
            //'shipping_company' => 'shipping_company',
            //'shipping_address_1' => 'shipping_address_1',
            //'shipping_address_2' => 'shipping_address_2',
            //'shipping_city' => 'shipping_city',
            //'shipping_state' => 'shipping_state',
            //'shipping_postcode' => 'shipping_postcode',
            //'shipping_country' => 'shipping_country',
            'sent_at' => 'sent_at',
        ];

        $this->bulk_actions = [
            'print_labels' => __('Print Labels', 'slparcels'),
            'print_invoices' => __('Print Invoices', 'slparcels'),
            'set_bag' => __('Set Bag', 'slparcels'),
            'send_bag' => __('Send Bag', 'slparcels'),
            'delete_order_parcels' => __('Delete Order Parcels', 'slparcels'),
        ];

        $this->items_per_page = empty($_GET['unsent_parcels_only']) ? 100 : 2000;

        parent::__construct([
            'singular' => 'wp_list_slparcels_parcel',
            'plural'   => 'wp_list_slparcels_parcels',
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return array_merge(
            ['cb' => '<input type="checkbox" />'],
            $this->columns
        );
    }

    protected function get_table_columns()
    {
        $columns = parent::get_table_columns();
        if (! in_array('order_id', $columns)) {
            $columns[] = 'order_id';
        }
        if (! in_array('label_error', $columns)) {
            $columns[] = 'label_error';
        }
        return $columns;
    }

    public function prepare_items()
    {
        global $wpdb;

        $this->process_bulk_action();

        if (! empty($_REQUEST['_wp_http_referer'])) {
            wp_redirect(remove_query_arg(array( '_wp_http_referer', '_wpnonce' ), wp_unslash($_SERVER['REQUEST_URI'])));
            exit;
        }

        $this->prepare_column_headers();

        if (empty($_GET['unsent_parcels_only'])) {
            $unsent_parcels_only_filter = "";
        } else {
            $unsent_parcels_only_filter = "(`bag`='' OR `bag` IS NULL) AND (`sent_at`='0000-00-00 00:00:00' OR `sent_at` IS NULL)";
        }

        $limit   = $this->get_items_query_limit();
        $offset  = $this->get_items_query_offset();
        $order   = $this->get_items_query_order();
        $where   = array_filter([
            $this->get_items_query_search(),
            $unsent_parcels_only_filter
        ]);
        $columns = '`' . implode('`, `', $this->get_table_columns()) . '`';

        if (! empty($where)) {
            $where = 'WHERE ('. implode(') AND (', $where) . ')';
        } else {
            $where = '';
        }

        $sql = "SELECT {$columns} FROM {$this->get_table_name()} {$where} {$order} {$limit} {$offset}";
        $this->set_items($wpdb->get_results($sql, ARRAY_A));

        $total_items = $wpdb->get_var("SELECT COUNT({$this->id_field}) FROM {$this->get_table_name()} {$where}");
        $per_page    = $this->get_items_per_page($this->package . '_items_per_page', $this->items_per_page);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $this->items_per_page,
            'total_pages' => ceil($total_items / $per_page),
        ));
    }

    public function display_tablenav($which)
    {
        parent::display_tablenav($which);
        if ('top' === $which) {
            ?>
            <style>
                .table-top-scroller-wrapper {
                    height: 20px;
                    width: 100%;
                    overflow-x: scroll;
                    overflow-y:hidden;
                }
                .table-top-scroller {
                    width:100%;
                    height: 20px;
                }
            </style>
            <div class="table-top-scroller-wrapper">
              <div class="table-top-scroller"></div>
            </div>
            <?php
        }
    }

    public function display_unsent_parcels_only_filter()
    {
        $params = (array) $_GET;
        if (!empty($params['unsent_parcels_only'])) {
            unset($params['unsent_parcels_only']); ?>
            <a style="float:right;" class="button" href="?<?php echo http_build_query($params); ?>"><?php echo __('See all parcels', 'slparcels'); ?></a>
            <?php
        } else {
            $params['unsent_parcels_only'] = 1; ?>
            <a style="float:right;" class="button" href="?<?php echo http_build_query($params); ?>"><?php echo __('See all unsent parcels without bag', 'slparcels'); ?></a>
            <?php
        }
    }

    protected function display_reshimone_by_month_form()
    {
        $curr_month = date_i18n('m');
        $curr_year = date_i18n('Y'); ?>
        <span class="monthly-reshimone">
            <select id="monthly-reshimone-month" name="monthly_reshimone_month">
                <?php foreach (range(1, 12) as $month): ?>
                    <option vlaue="<?php echo $month; ?>" <?php echo $month === (int) $curr_month ? "selected" : "" ?>><?php echo $month; ?></option>
                <?php endforeach; ?>
            </select>
            <select id="monthly-reshimone-year" name="monthly_reshimone_year">
                <?php foreach (range(2022, $curr_year) as $year): ?>
                    <option vlaue="<?php echo $year; ?>" <?php echo $year === (int) $curr_year ? "selected" : "" ?>><?php echo $year; ?></option>
                <?php endforeach; ?>
            </select>
            <button id="monthly-reshimone-btn" class="button" title="<?php echo __('Get Reshimone by Month', 'slparcels') ?>"><?php echo __('Get Reshimone by Month', 'slparcels') ?></button>
            <a id="monthly-reshimone-link" style="display:none !important;" target="_blank"></a>
        </span>
        <script>
            jQuery(document).ready(function(){
                jQuery(document).on('click', 'button#monthly-reshimone-btn', function(e){
                    if (!window.open("?slparcels_get_reshimone&type=monthly&month=" + jQuery('#monthly-reshimone-month').val() + "&year=" + jQuery('#monthly-reshimone-year').val() + "&v=<?php echo time(); ?>")){
                        alert("Please disable any ad-blocker or popup-blocker, then try again.");
                    }
                });
            });
        </script>
        <?php
    }

    public function display_table()
    {
        ?>
        <style>
            .wp_list_slparcels_parcels.wp-list-table{
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .wp_list_slparcels_parcels.wp-list-table th.sortable a span,
            .wp_list_slparcels_parcels.wp-list-table th.sorted a span {
                float: none;
                display: inline-block;
                vertical-align: text-top;
            }
            .wp_list_slparcels_parcels.wp-list-table.striped>tbody>tr:nth-of-type(1n+0){
                background-color: #f6f7f7;
            }
            .wp_list_slparcels_parcels.wp-list-table.striped>tbody>tr:nth-of-type(4n+0),
            .wp_list_slparcels_parcels.wp-list-table.striped>tbody>tr:nth-of-type(4n+3){
                background-color: #ffffff;
            }
            tr.parcel-items{
                display:none;
            }
            tr.parcel-items > td{
                padding: 2px 12px;
            }
            tr.parcel-items table, tr.parcel-items table th, tr.parcel-items table td{
                border: 1px solid #c3c4c7;
                border-collapse: collapse;
            }
            a.parcel-items-toggle{
                cursor:pointer;
                font-weight: bold;
            }
            ul.parcel-items-container{
                margin: 0;
            }
        </style>

        <p>
            <?php $this->display_reshimone_by_month_form(); ?>
            <?php $this->display_unsent_parcels_only_filter(); ?>
        </p>

        <?php parent::display_table(); ?>

        <script>
            jQuery(document).ready(function(){
                jQuery(document).on('click', 'a.parcel-items-toggle', function(e){
                    e.preventDefault();
                    var $this = jQuery(this);
                    var elem = jQuery('tr[data-toggle-parcel-id="' + $this.attr("data-toggle-parcel-id") + '"]');
                    elem.toggle( 'fast', function(){
                        if(elem.is(":visible")){
                            $this.html("Close Items ▲");
                        }else{
                            $this.html("See Items ▶");
                        }
                    });
                    return false;
                });
                jQuery(document).on('change', '.bulkactions select[name="table_action"]', function(e){
                    if(jQuery(this).parent('.bulkactions').find('input[name="bulk_input"]').length){
                        jQuery(this).parent('.bulkactions').find('input[name="bulk_input"]').remove();
                    }
                    if(jQuery(this).val() === 'set_bag' || jQuery(this).val() === 'send_bag'){
                        if(!jQuery(this).parent('.bulkactions').find('input[name="bulk_input"]').length){
                            switch (jQuery(this).val()) {
                                case 'set_bag':
                                    jQuery(this).after('<input type="text" id="slparcels-bult-input" name="bulk_input" placeholder="Bag #" value="">');
                                break;
                                case 'send_bag':
                                    jQuery(this).after('<input type="datetime-local" required id="slparcels-bult-input" name="bulk_input" placeholder="Sent at (date)">');
                                break;
                            }
                        }
                    }
                });
                jQuery(document).on('submit', 'form#wp_list_slparcels_parcels-filter', function(e){
                    var chosen_action = jQuery('.bulkactions select[name="table_action"]', this).val();
                    switch (chosen_action) {
                        case 'send_bag':
                            if(!confirm('<?php echo __('This action would change the "Sent At" date for the selected bags and will also update the notes on the corresponding orders with the tracking codes. This would also prevent parcels recreation and can not be changed. Are you sure you want to proceed?', 'slparcels'); ?>')){
                                e.preventDefault();
                                return false;
                            }
                            break;

                        case 'delete_order_parcels':
                            if(!confirm("<?php echo __("This action would remove all the parcels, labels and invoices for the selected parcels orders. It would then be automatically re-created for orders that have a 'Processing' status. Are you sure you want to proceed?", 'slparcels'); ?>")){
                                e.preventDefault();
                                return false;
                            }
                            break;

                        case 'print_labels':
                        case 'print_invoices':
                            e.preventDefault();
                            var chosen_action_slug = chosen_action === 'print_labels' ? 'bulk_labels' : 'bulk_invoices';
                            var parcel_ids_string = '';
                            jQuery("form#wp_list_slparcels_parcels-filter input:checkbox:checked").map(function(){
                                parcel_ids_string += "&parcel_ids[]=" + jQuery(this).val();
                            });
                            if (!window.open("?download_slparcels_type=" + chosen_action_slug + parcel_ids_string + "&open_in_new_tab=1&v=<?php echo time(); ?>")){
                                alert("Please disable any ad-blocker or popup-blocker, then try again.");
                            }
                            return false;
                            break;

                    }
                });
                jQuery(".table-top-scroller").width(jQuery(".wp-list-table.wp_list_slparcels_parcels tbody").width());
                jQuery(".wp-list-table.wp_list_slparcels_parcels").scroll(function(){
                    jQuery(".table-top-scroller-wrapper").scrollLeft(jQuery(".wp-list-table.wp_list_slparcels_parcels").scrollLeft());
                });
                jQuery(".table-top-scroller-wrapper").scroll(function(){
                    jQuery(".wp-list-table.wp_list_slparcels_parcels").scrollLeft(jQuery(".table-top-scroller-wrapper").scrollLeft());
                });
            });
        </script>
        <?php
    }

    public function single_row($item)
    {
        echo '<tr>';
        $this->single_row_columns($item);
        echo '</tr>';

        $items = (array) json_decode($item["items"], true);
        if (!$items) {
            return;
        } ?>
        <tr class="parcel-items" data-toggle-parcel-id="<?php echo $item["id"]; ?>">
            <td colspan="100%">
                <table class="fixed wp_list_slparcels_parcels_items">
                    <thead>
                        <tr>
                            <th><?php echo __('Product ID', 'slparcels'); ?></th>
                            <th><?php echo __('SKU', 'slparcels'); ?></th>
                            <th><?php echo __('Name', 'slparcels'); ?></th>
                            <th><?php echo __('Weight', 'slparcels'); ?></th>
                            <th><?php echo __('Quantity', 'slparcels'); ?></th>
                            <th><?php echo __('Unit Price', 'slparcels'); ?></th>
                            <th><?php echo __('Line Price', 'slparcels'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $_item): ?>
                            <tr>
                                <td><?php echo esc_html($_item["product_id"]); ?></td>
                                <td><?php echo esc_html($_item["sku"]); ?></td>
                                <td><?php echo esc_html($_item["name"]); ?></td>
                                <td><?php echo esc_html($_item["total_weight"]); ?></td>
                                <td><?php echo esc_html($_item["quantity"]); ?></td>
                                <td><?php echo esc_html($_item["unit_total"]); ?></td>
                                <td><?php echo esc_html($_item["total"]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </td>
        </tr>
        <?php
    }

    public function column_items($row)
    {
        $items = (array) json_decode($row["items"], true);
        if (!$items) {
            return '';
        }
        return "<a class='parcel-items-toggle' data-toggle-parcel-id='{$row["id"]}'>See Items ▶</a>";
    }

    public function column_order_number($row)
    {
        if (!$row['order_id'] || !$row['order_number']) {
            return '';
        }
        $url = admin_url('post.php?post=' . (int)$row['order_id'] . '&action=edit');
        return "<a href='{$url}' title='Open order edit page on a new tab.' target='_blank'>{$row['order_number']}</a>";
    }

    public function column_bag($row)
    {
        if (!$row['bag']) {
            return '';
        }
        return "<a href='?slparcels_get_reshimone&type=bag&bag={$row['bag']}&v=".time()."' title='Get Reshimone by Bag' target='_blank'>{$row['bag']}</a>";
    }

    public function column_label($row)
    {
        if (empty($row['label'])) {
            if (!empty($row['label_error'])) {
                return '<span style="color:red;white-space:normal;">' . esc_html($row['label_error']) . '</span>';
            }
            return '';
        }
        $url = trailingslashit(wp_upload_dir()['baseurl']) . 'israel-parcels-labels/' . $row['label'] . '?v=' . time();
        //return "<a href='{$url}' title='Get Label' target='_blank'>Get Label ▶</a>";
        return "<a href='?download_slparcels_type=label&parcel_id={$row['id']}&open_in_new_tab=1&v=".time()."' title='Get Label' target='_blank'>Get Label ▶</a>";
    }

    public function column_invoice($row)
    {
        if (!$row['invoice']) {
            return '';
        }
        //$url = trailingslashit(wp_upload_dir()['baseurl']) . 'israel-parcels-invoices/' . $row['invoice'] . '?v=' . time();
        //return "<a href='{$url}' title='Get Invoice' target='_blank'>Get Invoice ▶</a>";
        return "<a href='?download_slparcels_type=invoice&parcel_id={$row['id']}&open_in_new_tab=1&v=".time()."' title='Get Invoice' target='_blank'>Get Invoice ▶</a>";
    }

    public function column_sent_at($row)
    {
        return $this->timestamp_column_format($row, 'sent_at', 'm/d/Y H:i');
    }

    public function column_labeled_at($row)
    {
        return $this->timestamp_column_format($row, 'labeled_at');
    }

    public function column_invoiced_at($row)
    {
        return $this->timestamp_column_format($row, 'invoiced_at');
    }

    public function column_airport_at($row)
    {
        return $this->timestamp_column_format($row, 'airport_at');
    }

    public function column_arrived_at($row)
    {
        return $this->timestamp_column_format($row, 'arrived_at');
    }

    public function column_last_track_at($row)
    {
        return $this->timestamp_column_format($row, 'last_track_at');
    }

    protected function bulk_actions($which = '')
    {
        if ($which !== 'top') {
            return;
        }
        return parent::bulk_actions($which);
    }

    protected function bulk_print_labels($ids)
    {
        return;
    }

    protected function bulk_print_invoices($ids)
    {
        return;
    }

    protected function bulk_set_bag($ids)
    {
        Trus_SLParcels_ParcelsHelper::bulk_set_bag($ids, $_GET['bulk_input']);
    }

    protected function bulk_send_bag($ids)
    {
        Trus_SLParcels_ParcelsHelper::bulk_send_bag($ids, strtotime(wc_clean($_GET['bulk_input'])));
    }

    protected function bulk_delete_order_parcels($ids)
    {
        Trus_SLParcels_ParcelsHelper::bulk_delete_order_parcels_by_parcel_ids($ids);
    }
}
