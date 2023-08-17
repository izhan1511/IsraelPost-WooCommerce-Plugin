<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

class Trus_SLParcels_Table_OrderParcels extends Trus_SLParcels_Table_AbstractTable
{
    protected $table_header = "israel Parcels";
    protected $items_per_page = 1000;
    protected $table_name = "slparcels_parcels";
    protected $package = "slparcels_parcels";
    protected $id_field = 'id';
    protected $order_id = null;
    protected $sent_parcels_count = 0;

    public function __construct()
    {
        $this->columns = [
            'id' => __('Parcel ID', 'slparcels'),
            //'order_number' => __('Order Number', 'slparcels'),
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

        $this->sortable_columns = [];
        $this->searchable_columns = [];

        $this->bulk_actions = [
            'print_labels' => __('Print Labels', 'slparcels'),
            'print_invoices' => __('Print Invoices', 'slparcels'),
            'set_bag' => __('Set Bag', 'slparcels'),
            'send_bag' => __('Send Bag', 'slparcels'),
            'delete_order_parcels' => __('Delete Order Parcels', 'slparcels'),
        ];

        parent::__construct([
            'singular' => 'wp_list_slparcels_parcel',
            'plural'   => 'wp_list_slparcels_parcels',
            'ajax'     => false
        ]);
    }

    public function set_order_id($order_id)
    {
        $this->order_id = $order_id;
        return $this;
    }

    protected function bulk_actions($which = '')
    {
        if ($which !== 'top') {
            return;
        }

        if (is_null($this->_actions)) {
            $this->_actions = $this->get_bulk_actions();

            $this->_actions = apply_filters("bulk_actions-{$this->screen->id}", $this->_actions); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

            $two = '';
        } else {
            $two = '2';
        }

        if (empty($this->_actions)) {
            return;
        }

        echo '<label for="bulk-action-selector-' . esc_attr($which) . '" class="screen-reader-text">' . __('Select bulk action') . '</label>';
        echo '<select name="table_action' . $two . '" id="bulk-action-selector-' . esc_attr($which) . "\">\n";
        echo '<option value="-1">' . __('Bulk actions') . "</option>\n";

        foreach ($this->_actions as $key => $value) {
            if ($key === 'delete_order_parcels' && $this->sent_parcels_count) {
                continue;
            }
            if (is_array($value)) {
                echo "\t" . '<optgroup label="' . esc_attr($key) . '">' . "\n";

                foreach ($value as $name => $title) {
                    $class = ('edit' === $name) ? ' class="hide-if-no-js"' : '';

                    echo "\t\t" . '<option value="' . esc_attr($name) . '"' . $class . '>' . $title . "</option>\n";
                }
                echo "\t" . "</optgroup>\n";
            } else {
                $class = ('edit' === $key) ? ' class="hide-if-no-js"' : '';

                echo "\t" . '<option value="' . esc_attr($key) . '"' . $class . '>' . $value . "</option>\n";
            }
        }

        echo "</select>\n"; ?>
        <button id="order-parcels-apply-btn" class="button" title="<?php echo __('Apply', 'slparcels') ?>"><?php echo __('Apply', 'slparcels') ?></button>
        <?php
    }

    protected function set_pagination_args($args)
    {
        $args = wp_parse_args(
            $args,
            array(
                'total_items' => 0,
                'total_pages' => 0,
                'per_page'    => 0,
            )
        );

        if (! $args['total_pages'] && $args['per_page'] > 0) {
            $args['total_pages'] = ceil($args['total_items'] / $args['per_page']);
        }

        $this->_pagination_args = $args;
    }

    public function prepare_items()
    {
        global $wpdb;

        $this->prepare_column_headers();

        $order   = $this->get_items_query_order();
        $where   = [
            $wpdb->prepare("`order_id` = '%d'", $this->order_id),
        ];
        $columns = '`' . implode('`, `', $this->get_table_columns()) . '`';

        if (! empty($where)) {
            $where = 'WHERE ('. implode(') AND (', $where) . ')';
        } else {
            $where = '';
        }

        $sql = "SELECT {$columns} FROM {$this->get_table_name()} {$where} {$order}";
        $this->set_items($wpdb->get_results($sql, ARRAY_A));
        $this->sent_parcels_count = Trus_SLParcels_DB::count_order_sent_parcels($this->order_id);
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

    public function display_tablenav($which)
    {
        //Parent method without the nonce field?>
        <div class="tablenav <?php echo esc_attr($which); ?>">
           <?php if ($this->has_items()) : ?>
           <div class="alignleft actions bulkactions">
               <?php $this->bulk_actions($which); ?>
           </div>
               <?php
           endif;
        $this->extra_tablenav($which);
        $this->pagination($which); ?>
           <br class="clear" />
        </div>
       <?php

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

    public function display_table()
    {
        ?>
        <style>
            .wp_list_slparcels_parcels.wp-list-table{
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .wp_list_slparcels_parcels.wp-list-table th:not(.check-column){
                padding: 8px;
            }
            .wp_list_slparcels_parcels.wp-list-table td:not(.check-column){
                padding: 8px 10px;
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
        </style>

       <?php $singular = $this->_args['singular']; ?>
       <?php $this->display_tablenav('top'); ?>

       <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
           <thead>
           <tr>
               <?php $this->print_column_headers(); ?>
           </tr>
           </thead>

           <tbody id="the-list" <?php echo $singular ? " data-wp-lists='list:$singular'" : "" ?>>
               <?php $this->display_rows_or_placeholder(); ?>
           </tbody>
       </table>

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
                jQuery(document).on('click', '.order_israel_parcels.wc-metaboxes-wrapper #order-parcels-apply-btn', function(e){
                    e.preventDefault();
                    var chosen_action = jQuery('.order_israel_parcels.wc-metaboxes-wrapper .bulkactions select[name="table_action"]').val();
                    var chosen_action_slug;
                    var parcel_ids_string = '';
                    jQuery(".order_israel_parcels.wc-metaboxes-wrapper input:checkbox:checked").map(function(){
                        parcel_ids_string += "&parcel_ids[]=" + jQuery(this).val();
                    });
                    var bulk_input = "&bulk_input=" + jQuery('.order_israel_parcels.wc-metaboxes-wrapper input[name="bulk_input"]').val();
                    var order_id = "&order_id=<?php echo $this->order_id; ?>";
                    switch (chosen_action) {
                        case 'set_bag':
                            window.location.href = "?slparcels_bulk_action=bulk_set_bag" + parcel_ids_string + order_id + bulk_input + "&v=<?php echo time(); ?>";
                            break;

                        case 'send_bag':
                            if(!confirm('<?php echo __('This action would change the "Sent At" date for the selected bags and will also update the notes on the corresponding orders with the tracking codes. This would also prevent parcels recreation and can not be changed. Are you sure you want to proceed?', 'slparcels'); ?>')){
                                return false;
                            }
                            window.location.href = "?slparcels_bulk_action=bulk_send_bag" + parcel_ids_string + order_id + bulk_input + "&v=<?php echo time(); ?>";
                            break;

                        case 'delete_order_parcels':
                            if(!confirm("<?php echo __("This action would remove all the parcels, labels and invoices for the selected parcels orders. It would then be automatically re-created for orders that have a 'Processing' status. Are you sure you want to proceed?", 'slparcels'); ?>")){
                                return false;
                            }
                            window.location.href = "?slparcels_bulk_action=bulk_delete_order_parcels" + parcel_ids_string + order_id + "&v=<?php echo time(); ?>";
                            break;

                        case 'print_labels':
                            if (!window.open("?download_slparcels_type=bulk_labels" + parcel_ids_string + "&open_in_new_tab=1&v=<?php echo time(); ?>")){
                                alert("Please disable any ad-blocker or popup-blocker, then try again.");
                            }
                            break;
                        case 'print_invoices':
                            if (!window.open("?download_slparcels_type=bulk_invoices" + parcel_ids_string + "&open_in_new_tab=1&v=<?php echo time(); ?>")){
                                alert("Please disable any ad-blocker or popup-blocker, then try again.");
                            }
                            break;

                    }
                    return false;
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
        return;
    }

    protected function bulk_send_bag($ids)
    {
        return;
    }

    protected function bulk_delete_order_parcels($ids)
    {
        return;
    }
}
