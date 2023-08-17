<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Trus_SLParcels_Table_AbstractTable extends WP_List_Table
{
    protected $table_header = '';
    protected $items_per_page = 20;
    protected $table_name = '';
    protected $package = '';
    protected $id_field = 'id';
    protected $columns = [];
    protected $sortable_columns = [];
    protected $searchable_columns = [];
    protected $bulk_actions = [];
    protected $display_header_h = "1";


    public function no_items()
    {
        esc_html_e('No records found.', 'slparcels');
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

        $limit   = $this->get_items_query_limit();
        $offset  = $this->get_items_query_offset();
        $order   = $this->get_items_query_order();
        $where   = array_filter([
            $this->get_items_query_search(),
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

    protected function get_table_columns()
    {
        $columns = array_keys($this->columns);
        if (! in_array($this->id_field, $columns)) {
            $columns[] = $this->id_field;
        }
        return $columns;
    }

    protected function get_bulk_actions()
    {
        $actions = [];

        foreach ($this->bulk_actions as $action => $label) {
            if (! is_callable(array( $this, 'bulk_' . $action ))) {
                throw new RuntimeException("The bulk action $action does not have a callback method");
            }

            $actions[ $action ] = $label;
        }

        return $actions;
    }

    protected function bulk_actions($which = '')
    {
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

        echo "</select>\n";

        submit_button(__('Apply'), 'table_action', '', false, array( 'id' => "doaction$two" ));
        echo "\n";
    }

    public function current_action()
    {
        if (isset($_REQUEST['filter_action']) && ! empty($_REQUEST['filter_action'])) {
            return false;
        }

        if (isset($_REQUEST['table_action']) && -1 != $_REQUEST['table_action']) {
            return $_REQUEST['table_action'];
        }

        return false;
    }

    protected function process_bulk_action()
    {
        $action = $this->current_action();
        if (! $action) {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $method   = 'bulk_' . $action;
        if (array_key_exists($action, $this->bulk_actions) && is_callable(array( $this, $method )) && ! empty($_GET['id_field']) && is_array($_GET['id_field'])) {
            $this->{$method}($_GET['id_field']);
        }

        wp_redirect(remove_query_arg(
            array( '_wp_http_referer', '_wpnonce', 'id_field', 'table_action', 'action2', 'bulk_input' ),
            wp_unslash($_SERVER['REQUEST_URI'])
        ));
        exit;
    }

    protected function prepare_column_headers()
    {
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function get_sortable_columns()
    {
        $sort_by = [];
        foreach ($this->sortable_columns as $column) {
            $sort_by[$column] = [$column, true];
        }
        return $sort_by;
    }

    public function get_columns()
    {
        return $this->columns;
    }

    protected function get_table_name()
    {
        global $wpdb;
        return "{$wpdb->prefix}{$this->table_name}";
    }

    protected function get_items_query_limit()
    {
        global $wpdb;

        $per_page = $this->get_items_per_page($this->package . '_items_per_page', $this->items_per_page);
        return $wpdb->prepare('LIMIT %d', $per_page);
    }

    protected function get_items_offset()
    {
        $per_page = $this->get_items_per_page($this->package . '_items_per_page', $this->items_per_page);
        $current_page = $this->get_pagenum();
        if (1 < $current_page) {
            $offset = $per_page * ($current_page - 1);
        } else {
            $offset = 0;
        }

        return $offset;
    }

    protected function get_items_query_offset()
    {
        global $wpdb;
        return $wpdb->prepare('OFFSET %d', $this->get_items_offset());
    }

    protected function get_items_query_order()
    {
        if (empty($this->sortable_columns)) {
            return '';
        }

        $orderby = esc_sql($this->get_request_orderby());
        $order   = esc_sql($this->get_request_order());

        return "ORDER BY {$orderby} {$order}";
    }

    protected function get_request_orderby()
    {
        $valid_sortable_columns = array_values($this->sortable_columns);

        if (! empty($_GET['orderby']) && in_array($_GET['orderby'], $valid_sortable_columns)) {
            $orderby = sanitize_text_field($_GET['orderby']);
        } else {
            $orderby = $valid_sortable_columns[0];
        }

        return $orderby;
    }

    protected function get_request_order()
    {
        if (! empty($_GET['order']) && 'desc' === strtolower($_GET['order'])) {
            $order = 'DESC';
        } else {
            $order = 'ASC';
        }

        return $order;
    }

    protected function get_request_status()
    {
        $status = (! empty($_GET['status'])) ? $_GET['status'] : '';
        return $status;
    }

    protected function get_request_search_query()
    {
        $search_query = (! empty($_GET['s'])) ? $_GET['s'] : '';
        return $search_query;
    }

    protected function get_items_query_search()
    {
        global $wpdb;

        if (empty($_GET['s']) || empty($this->searchable_columns)) {
            return '';
        }
        $filter  = [];
        foreach ($this->searchable_columns as $column) {
            $filter[] = $wpdb->prepare("`{$column}` like '%s'", '%' . $wpdb->esc_like($_GET['s']) . '%');
        }
        return implode(' OR ', $filter);
    }

    protected function set_items(array $items)
    {
        $this->items = [];
        foreach ($items as $item) {
            $this->items[ $item[ $this->id_field ] ] = array_map('maybe_unserialize', $item);
        }
    }

    public function timestamp_column_format($row, $column, $format = 'm/d/Y H:i:s')
    {
        if (empty($row[$column]) || $row[$column] == '0000-00-00 00:00:00') {
            return '';
        }
        return date($format, strtotime($row[$column]));
    }

    public function column_created_at($row)
    {
        return $this->timestamp_column_format($row, 'created_at');
    }

    public function column_updated_at($row)
    {
        return $this->timestamp_column_format($row, 'updated_at');
    }

    public function column_cb($row)
    {
        return '<input name="id_field[]" type="checkbox" value="' . esc_attr($row[ $this->id_field ]) .'" />';
    }

    public function column_default($item, $column_name)
    {
        $column_html = esc_html($item[ $column_name ]);
        $column_html .= $this->maybe_render_actions($item, $column_name);
        return $column_html;
    }

    protected function display_header()
    {
        echo "<h{$this->display_header_h}>" . esc_attr(__($this->table_header, 'slparcels')) . "</h{$this->display_header_h}>";
        if ($this->get_request_search_query()) {
            echo '<span class="subtitle">' . esc_attr(sprintf(__('Search results for "%s"', 'slparcels'), $this->get_request_search_query())) . '</span>';
        }
    }

    public function display_table()
    {
        echo '<form id="' . esc_attr($this->_args['plural']) . '-filter" method="get">';
        foreach ($_GET as $key => $value) {
            if ('_' === $key[0] || 'paged' === $key) {
                continue;
            }
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
        }

        if (! empty($this->searchable_columns)) {
            echo $this->search_box($this->get_search_box_button_text(), 'slparcels');
        }

        parent::display();
        echo '</form>';
    }

    public function process_actions()
    {
        $this->process_bulk_action();

        if (! empty($_REQUEST['_wp_http_referer'])) {
            wp_redirect(remove_query_arg(array( '_wp_http_referer', '_wpnonce' ), wp_unslash($_SERVER['REQUEST_URI'])));
            exit;
        }
    }

    public function display_page()
    {
        $this->prepare_items();

        echo '<div class="wrap">';
        $this->display_header();
        $this->display_table();
        echo '</div>';
    }

    protected function get_search_box_button_text()
    {
        return __('Search', 'slparcels');
    }

    protected function get_search_box_placeholder()
    {
        return esc_html__('Search', 'slparcels');
    }
}
