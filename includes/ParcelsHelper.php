<?php
/**
 * Parcels management with IL-Post for https://woocommerce-587222-2661122.cloudwaysapps.com/
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
 * Trus_SLParcels_ParcelsHelper class.
 */
class Trus_SLParcels_ParcelsHelper
{
    /**
     * @method get_parcel_initial_values
     * @param  WC_Order                  $order
     * @param  array                     $ship_mode_info
     * @return array
     */
    public static function get_parcel_initial_values(WC_Order $order, $ship_mode_info)
    {
        return [
            "order_id" => $order->get_id(),
            "order_number" => $order->get_order_number(),
            "ship_mode" => $ship_mode_info['ship_mode'],
            "max_parcel_weight" => $ship_mode_info['max_parcel_weight'],
            "subtotal" => 0,
            "coupon_code" => implode(",", (array) $order->get_coupon_codes()),
            "discount_amount" => 0,
            "total_price" => 0,
            "gross_weight" => 0,
            "total_weight" => 0,
            "total_quantity" => 0,
            "currency" => $order->get_currency(),
            "customer_id" => $order->get_customer_id(),
            "shipping_email" => $order->get_billing_email(),
            "shipping_phone" => $order->get_billing_phone(),
            "shipping_first_name" => $order->get_shipping_first_name(),
            "shipping_last_name" => $order->get_shipping_last_name(),
            "shipping_company" => $order->get_shipping_company(),
            "shipping_address_1" => $order->get_shipping_address_1(),
            "shipping_address_2" => $order->get_shipping_address_2(),
            "shipping_city" => $order->get_shipping_city(),
            "shipping_state" => $order->get_shipping_state(),
            "shipping_postcode" => $order->get_shipping_postcode(),
            "shipping_country" => $order->get_shipping_country(),
            "items" => [],
        ];
    }

    /**
     * @method get_pack_weight_by_products_qty
     * @param  array                          $ship_mode_info
     * @param  int                            $products_qty
     * @return int|float
     */
    public static function get_pack_weight_by_products_qty($ship_mode_info, $products_qty)
    {
        if (isset($ship_mode_info["pack_weight_map"][$products_qty])) {
            return $ship_mode_info["pack_weight_map"][$products_qty];
        } elseif (isset($ship_mode_info["pack_weight_map"][count($ship_mode_info["pack_weight_map"])-1])) {
            return $ship_mode_info["pack_weight_map"][count($ship_mode_info["pack_weight_map"])-1];
        } else {
            return 0;
        }
    }

    /**
     * @method get_order_ship_mode
     * @param  WC_Order                 $order
     * @return array|null
     */
    public static function get_order_ship_mode(WC_Order $order)
    {
        $country_code = $order->get_shipping_country();
        if (!$country_code) {
            throw new \Exception("Order #{$order->get_id()} has no shipping country.");
        }
        $isEMS = false;
        foreach ($order->get_shipping_methods() as $shipping_method) {
            if (
                in_array('ems', array_map('strtolower', (array)preg_split('/\s+/', $shipping_method["name"]))) ||
                in_array('ems', array_map('strtolower', (array)preg_split('/\s+/', $shipping_method["method_title"])))
            ) {
                $isEMS = true;
                break;
            }
        }
        if ($isEMS) {
            //If EMS:
            return Trus_SLParcels_DB::get_ship_mode_info('EMS', '', true);
        }
        //If Free-Shipping (get by coutry):
        return Trus_SLParcels_DB::get_ship_mode_by_country($country_code, true, 'REGULAR');
    }

    /**
     * @method get_separated_order_items
     * @param  WC_Order                 $order
     * @return array
     */
    public static function get_separated_order_items(WC_Order $order)
    {
        $order_items = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (!$item->get_product_id() || !$item->get_product()) {
                throw new \Exception("Error on get_separated_order_items(order-{$order->get_id()}): item-{$item->get_id()} product ID `{$item->get_product_id()}` not found!", 1);
            }
            if (has_term(399, 'product_cat', $item->get_product_id())) {
                continue;
            }
            if (has_term(440, 'product_cat', $item->get_product_id())) {
                continue;
            }
            $sku = $item->get_product()->get_sku();
            $item_weight = round(($item->get_product()->get_weight() ?: 0) / 1000, 3);
            $item_qty_refunded = (int) abs($order->get_qty_refunded_for_item($item->get_id()));
            $item_total_refunded = (float) $order->get_total_refunded_for_item($item->get_id());
            $quantity = $item->get_quantity() - $item_qty_refunded;
            $item_subtotal_incl_tax = $quantity ? round(($item->get_subtotal() + $item->get_subtotal_tax() - $item_total_refunded) / $quantity, 3) : 0;
            $item_total = $quantity ? round(($item->get_total() - $item_total_refunded) / $quantity, 3) : 0;
            $item_discount_amount = round(abs($item_subtotal_incl_tax - $item_total), 3);
            for ($i=0; $i < $quantity; $i++) {
                $order_items[] = [
                    "item_id" => $item->get_id(),
                    "product_id" => $item->get_product_id(),
                    "name" => $item->get_name(),
                    "sku" => $sku,
                    "quantity" => 1,
                    "unit_weight" => $item_weight,
                    "unit_subtotal" => $item_subtotal_incl_tax,
                    "unit_total" => $item_total,
                    "unit_discount_amount" => $item_discount_amount,
                    "total_weight" => $item_weight,
                    "subtotal" => $item_subtotal_incl_tax,
                    "total" => $item_total,
                    "discount_amount" => $item_discount_amount
                ];
            }
        }

        return $order_items;
    }

    /**
     * @method create_parcels_for_order
     * @param  WC_Order                 $order
     * @param  boolean                  $recreate
     * @return WC_Order
     */
    public static function create_parcels_for_order(WC_Order $order, $recreate = false)
    {
        Trus_SLParcels_Logger::log("create_parcels_for_order({$order->get_id()})", 'debug');

        if (!$recreate && $order->get_meta('_slparcels_parcels_created')) {
            Trus_SLParcels_Logger::log("create_parcels_for_order({$order->get_id()}) Parcels already created.", 'debug');
            return;
        }
        if ($recreate && $order->get_meta('_slparcels_parcels_created')) {
            Trus_SLParcels_Logger::log("create_parcels_for_order({$order->get_id()}) Recreating order parcels.", 'debug');
            Trus_SLParcels_DB::delete_order_parcels($order->get_id());
            //$order->delete_meta_data('_slparcels_parcels_created');
            //$order->save();
        }

        try {
            $ship_mode_info = self::get_order_ship_mode($order);
            Trus_SLParcels_Logger::log("create_parcels_for_order({$order->get_id()}) Ship Mode: {$ship_mode_info['ship_mode']}", 'debug');
            $order->add_meta_data('_slparcels_ship_mode', $ship_mode_info['ship_mode'], true);

            $order_items = self::get_separated_order_items($order);
            $parcels = [];
            $items_parcels = [];
            foreach ($order_items as $order_item) {
                $parcel = array_pop($parcels);
                if (!$parcel) {
                    $parcel = self::get_parcel_initial_values($order, $ship_mode_info);
                    $parcel["id"] = (int) ($order->get_order_number() . (count($parcels) + 1));
                }

                $total_weight = $order_item["total_weight"] + $parcel["total_weight"];
                $pack_weight = self::get_pack_weight_by_products_qty($ship_mode_info, $order_item["quantity"] + $parcel["total_quantity"]);
                $gross_weight = $total_weight + $pack_weight;
                //Check if can add to last parcel:
                if ($ship_mode_info['max_parcel_weight'] < $gross_weight) {
                    //If not - start a new parcel:
                    $parcels[] = $parcel;
                    $parcel = self::get_parcel_initial_values($order, $ship_mode_info);
                    $parcel["id"] = (int) ($order->get_order_number() . (count($parcels) + 1));
                    $total_weight = $order_item["total_weight"] + $parcel["total_weight"];
                    $pack_weight = self::get_pack_weight_by_products_qty($ship_mode_info, $order_item["quantity"] + $parcel["total_quantity"]);
                    $gross_weight = $total_weight + $pack_weight;
                }
                //Check again if can add to current parcel (in case it's a new one):
                if ($ship_mode_info['max_parcel_weight'] >= $gross_weight) {
                    $parcel["total_weight"] = $total_weight;
                    $parcel["pack_weight"] = $pack_weight;
                    $parcel["gross_weight"] = $gross_weight;
                    $parcel["total_quantity"] += $order_item["quantity"];
                    $parcel["subtotal"] += $order_item["subtotal"];
                    $parcel["discount_amount"] += $order_item["discount_amount"];
                    $parcel["total_price"] += $order_item["total"];
                    if (isset($parcel["items"][$order_item["item_id"]])) {
                        $parcel["items"][$order_item["item_id"]]["quantity"] += $order_item["quantity"];
                        $parcel["items"][$order_item["item_id"]]["total_weight"] += $order_item["total_weight"];
                        $parcel["items"][$order_item["item_id"]]["subtotal"] += $order_item["subtotal"];
                        $parcel["items"][$order_item["item_id"]]["total"] += $order_item["total"];
                        $parcel["items"][$order_item["item_id"]]["discount_amount"] += $order_item["discount_amount"];
                    } else {
                        $parcel["items"][$order_item["item_id"]] = $order_item;
                    }
                    $parcels[] = $parcel;
                    if (isset($items_parcels[$order_item["item_id"]])) {
                        $items_parcels[$order_item["item_id"]] = [$parcel["id"]];
                    } else {
                        $items_parcels[$order_item["item_id"]][] = $parcel["id"];
                    }
                } else {
                    throw new \Exception("Product unit weight ({$order_item["total_weight"]}) is higher than the parcel max weight ({$ship_mode_info['max_parcel_weight']}) for {$ship_mode_info['ship_mode']} to {$order->get_shipping_country()}.", 1);
                }
            }

            //Save Parcels:
            foreach ($parcels as $parcel) {
                Trus_SLParcels_DB::insert_parcel($parcel);
            }
            //Update Order & Items
            foreach ($order->get_items() as $item) {
                if (isset($items_parcels[$item->get_id()])) {
                    $item->add_meta_data('_slparcels_parcels', $items_parcels[$item->get_id()], true);
                } else {
                    $item->add_meta_data('_slparcels_parcels', [], true);
                }
                $item->save();
            }
            $order->add_meta_data('_slparcels_parcels_created', 1, true);
            $order->delete_meta_data('_slparcels_parcels_created_error');
        } catch (\Exception $e) {
            Trus_SLParcels_Logger::log("create_parcels_for_order({$order->get_id()}) [EXCEPTION]", 'error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            //$order->add_meta_data('_slparcels_parcels_created', 1, true);
            Trus_SLParcels_DB::delete_order_parcels($order->get_id());
            $order->add_meta_data('_slparcels_parcels_created_error', $e->getMessage(), true);
        }

        $order->set_date_modified(current_time('mysql'));
        $order->save();

        return $order;
    }

    /**
     * @method get_ship_mode_type_id
     * @param  string                 $ship_mode
     * @return int
     */
    public static function get_ship_mode_type_id($ship_mode)
    {
        switch (strtoupper(trim($ship_mode))) {
            case 'REGULAR':
                return 389;
                break;
            case 'EMS':
                return 4;
                break;
            case 'ECOPOST':
            case 'JEZ':
                return 6;
                break;
        }
        return 0;
    }

    /**
     * @method get_order_parcels
     * @param  int|WC_Order         $order_id
     * @return array
     */
    public static function get_order_parcels($order_id)
    {
        if (is_a($order_id, WC_Order::class)) {
            $order_id = $order_id->get_id();
        }
        return Trus_SLParcels_DB::get_parcels_by_order_id($order_id);
    }

    /**
     * @method get_parcels_for_tracking
     * @param  integer                  $limit
     * @return array
     */
    public static function get_parcels_for_tracking($limit = 50)
    {
        return Trus_SLParcels_DB::get_parcels_for_tracking($limit);
    }

    /**
     * @method generate_parcel_label
     * @param  array                $parcel
     * @param  bool                 $recreate
     * @return $parcel
     */
    public static function generate_parcel_label($parcel, $recreate = false)
    {
        if (!$recreate && !empty($parcel['label'])) {
            Trus_SLParcels_Logger::log("generate_parcel_label(parcel_id:{$parcel['id']}) Label already created.", 'debug');
            return $parcel;
        }
        if ($recreate && !empty($parcel['label'])) {
            Trus_SLParcels_Logger::log("generate_parcel_label(parcel_id:{$parcel['id']}) Recreating label.", 'debug');
        }

        return Trus_SLParcels_API::generate_parcel_label($parcel);
    }

    public static function get_order_payment_cc_last_4(WC_Order $order)
    {
        $payment_method = $order->get_payment_method();
        if (! in_array($payment_method, WC_ReepayCheckout::PAYMENT_METHODS)) {
            return;
        }
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        /** @var WC_Gateway_Reepay_Checkout $gateway */
        $gateway = 	$gateways[ $payment_method ];
        if (! $gateway) {
            return;
        }
        $order_data = $gateway->get_invoice_data($order);
        return WC_ReepayCheckout::formatCreditCard($order_data['transactions'][0]['card_transaction']['masked_card']);
    }

    /**
     * @method generate_parcel_invoice
     * @param  array                $parcel
     * @param  bool                 $recreate
     * @return $parcel
     */
    public static function generate_parcel_invoice($parcel, $recreate = false)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_API::generate_parcel_label().");
            }
            if (!$recreate && !empty($parcel['invoice'])) {
                Trus_SLParcels_Logger::log("generate_parcel_invoice(parcel_id:{$parcel['id']}) invoice already created.", 'debug');
                return $parcel;
            }
            if ($recreate && !empty($parcel['invoice'])) {
                Trus_SLParcels_Logger::log("generate_parcel_invoice(parcel_id:{$parcel['id']}) Recreating invoice.", 'debug');
            }

            $parcel_id = $parcel["id"];
            $order_id = $parcel['order_id'];
            Trus_SLParcels_Logger::log("generate_parcel_invoice(parcel_id:{$parcel_id}) order_id:{$order_id} [START]", 'debug');

            $order = wc_get_order($order_id);
            if (!$order || !$order->get_id()) {
                throw new \Exception("Couldn't load order for parcel #{$parcel['id']}");
            }

            if (!is_array($parcel["items"])) {
                $parcel["items"] = json_decode($parcel["items"], true);
            }
            if (!$parcel["items"]) {
                throw new \Exception("No items on parcel #{$parcel['id']}");
            }

            $invoice_date = date_i18n('Y-m-d H:i:s');
            $cc_last_4 = $order->get_meta('_reepay_masked_card') ?: self::get_order_payment_cc_last_4($order);
            //if (!$cc_last_4 && round($parcel['total_price'], 3) > 0) {
            //    throw new \Exception("No Reepay masked card on parcel #{$parcel['id']}");
            //}
            $html = include(TRUS_SLPARCELS_TEMPLATES_PATH . "invoice.php");
            $dompdf = new \Dompdf\Dompdf(['enable_remote' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            //$dompdf->stream("invoice.pdf", ["Attachment" => false]);
            $invoice_file_contents = $dompdf->output();

            //Save invoice:
            $invoice_file = 'order_' . $parcel['order_number'] . '_parcel_' . $parcel['id'] . '_invoice.pdf';
            $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-invoices';
            wp_mkdir_p($uploads_dir);
            file_put_contents($uploads_dir . '/' . $invoice_file, $invoice_file_contents);

            //Update parcel:
            $parcel['invoice'] = wc_clean($invoice_file);
            $parcel['invoiced_at'] = $invoice_date;
            Trus_SLParcels_DB::update_parcel($parcel);
            Trus_SLParcels_Logger::log("generate_parcel_invoice() Parcel", 'debug', $parcel);
            Trus_SLParcels_Logger::log("generate_parcel_invoice(parcel_id:{$parcel_id}) order_id:{$order_id} [DONE]", 'debug');
        } catch (Exception $e) {
            Trus_SLParcels_Logger::log("generate_parcel_invoice() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $parcel;
    }

    /**
     * @method bulk_print_labels
     * @param  array       $parcel_ids
     * @return bool
     */
    public static function bulk_print_labels($parcel_ids)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);//215X297
        $pdf->SetAutoPageBreak(false, 0);

        $parcels = Trus_SLParcels_DB::get_parcels_by_ids($parcel_ids);
        $parcels_length = count($parcels);
        foreach ($parcels as $i => $parcel) {
            $image_path = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-labels/' . $parcel['label'];
            if (!is_file($image_path)) {
                throw new \Exception("Label file for parcel #{$parcel['id']} doesn't exist.");
            }
            if ($i % 2 === 0) {
                $pdf->AddPage();
                $pdf->Image($image_path, 10, 7, 189);
            } else {
                $pdf->Image($image_path, 10, 156, 189);
            }
            if ($i % 2 !== 0 || $i+1 === $parcels_length) {
                $pdf->setPageMark();
            }
        }

        $file_path = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-labels/last_bulk_print_labels.pdf';
        try {
            $pdf->Output($file_path, 'F');
        } catch (\Exception $e) {
            unlink($file_path);
            throw $e;
        }
        return $file_path;
    }

    /**
     * @method bulk_print_invoices
     * @param  array       $parcel_ids
     * @return bool
     */
    public static function bulk_print_invoices($parcel_ids)
    {
        $pdf = new \Clegginabox\PDFMerger\PDFMerger();

        foreach (Trus_SLParcels_DB::get_parcels_by_ids($parcel_ids) as $parcel) {
            $url = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-invoices/' . $parcel['invoice'];
            if (!is_file($url)) {
                throw new \Exception("Invoice file for parcel #{$parcel['id']} doesn't exist.");
            }
            $pdf->addPDF($url, 'all', 'P');
        }

        $file_path = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-invoices/last_bulk_print_invoices.pdf';
        try {
            $pdf->merge('file', $file_path, 'P');
        } catch (\Exception $e) {
            unlink($file_path);
            throw $e;
        }
        return $file_path;
    }

    /**
     * @method delete_order_parcels
     * @param  int               $order_id
     * @param  bool              $delete_sent_parcels
     * @return bool
     */
    public static function delete_order_parcels($order_id, $delete_sent_parcels = false)
    {
        /*$order = wc_get_order((int)$order_id);
        if (!$order || !$order->get_id()) {
            throw new \Exception("Couldn't load order ID " . (int)$order_id);
        }*/
        if (!$delete_sent_parcels && Trus_SLParcels_DB::count_order_sent_parcels($order_id)) {
            return false;
        }
        Trus_SLParcels_DB::delete_order_parcels($order_id, $delete_sent_parcels);
        //$order->update_status('wc-processing');
        return true;
    }

    /**
     * @method bulk_delete_order_parcels_by_parcel_ids
     * @param  array       $parcel_ids
     * @param  bool        $delete_sent_parcels
     * @return bool
     */
    public static function bulk_delete_order_parcels_by_parcel_ids($parcel_ids, $delete_sent_parcels = false)
    {
        foreach (Trus_SLParcels_DB::get_parcels_by_ids($parcel_ids, "order_id") as $parcel) {
            if (!$delete_sent_parcels && Trus_SLParcels_DB::count_order_sent_parcels($parcel['order_id'])) {
                continue;
            }
            Trus_SLParcels_DB::delete_order_parcels($parcel['order_id'], $delete_sent_parcels);
            //$order = wc_get_order($parcel['order_id']);
            //if ($order && $order->get_id()) {
                //$order->update_status('wc-processing');
            //}
        }
        return true;
    }

    /**
     * @method bulk_set_bag
     * @param  array       $parcel_ids
     * @param  mixed       $bag
     * @return bool
     */
    public static function bulk_set_bag($parcel_ids, $bag)
    {
        foreach (Trus_SLParcels_DB::get_parcels_by_bag($bag) as $parcels) {
            if (!(empty($parcel['sent_at']) || $parcel['sent_at'] === '0000-00-00 00:00:00')) {
                //Can't add parcels to bag after it's already sent.
                return false;
                break;
            }
        }
        $prepared_parcel_ids = [];
        foreach (Trus_SLParcels_DB::get_parcels_by_ids($parcel_ids) as $parcel) {
            if (!(empty($parcel['sent_at']) || $parcel['sent_at'] === '0000-00-00 00:00:00') || empty($parcel['label']) || empty($parcel['invoice'])) {
                //Can't change bag on parcels after it's already sent. [skipping parcel]
                continue;
            }
            $prepared_parcel_ids[] = $parcel['id'];
        }
        if ($prepared_parcel_ids) {
            Trus_SLParcels_DB::update_parcels($prepared_parcel_ids, ['bag' => $bag]);
        }
        return true;
    }

    /**
     * @method bulk_send_bag
     * @param  array        $parcel_ids
     * @param  timestamp    $timestamp
     * @return bool
     */
    public static function bulk_send_bag($parcel_ids, $timestamp = null)
    {
        $timestamp = $timestamp === null ? time() : $timestamp;
        $bags = [];
        $skipped_bags = [];
        $orders_tracking_codes = [];
        foreach (array_values(array_column(Trus_SLParcels_DB::get_parcels_by_ids($parcel_ids, "bag"), 'bag')) as $i => $bag) {
            $bags[$bag] = Trus_SLParcels_DB::get_parcels_by_bag($bag);
            //Validate parcels
            foreach ($bags[$bag] as $parcel) {
                if (!(empty($parcel['sent_at']) || $parcel['sent_at'] === '0000-00-00 00:00:00') || empty($parcel['bag']) || empty($parcel['label']) || empty($parcel['invoice'])) {
                    $skipped_bags[$bag] = $bags[$bag];
                    unset($bags[$bag]);
                    break;
                }
            }
            foreach ($bags[$bag] as $parcel) {
                if (!isset($orders_tracking_codes[$parcel['order_id']])) {
                    $orders_tracking_codes[$parcel['order_id']] = [];
                }
                $orders_tracking_codes[$parcel['order_id']][] = $parcel['tracking_code'];
            }
        }
        $_bags = array_values($bags);
        $parcel_ids = array_unique(array_values(array_column(array_merge(...$_bags), 'id')));
        if ($parcel_ids) {
            Trus_SLParcels_DB::update_parcels($parcel_ids, ['sent_at' => wc_clean(date_i18n('Y-m-d H:i:s', $timestamp))]);
        }
        //Add tracking code notes to parcel orders
        foreach ($orders_tracking_codes as $order_id => $tracking_codes) {
            self::add_tracking_note_to_parcel_order($order_id, $tracking_codes);
        }
        if ($skipped_bags) {
            //throw new \Exception("Counldn't send bags: '" . implode("','", array_keys($skipped_bags)) . "' bacause they had some parcels with missing labels or invoices. Please make sure that nothing is missing, then try again.", 1);
        }
        return true;
    }

    public static function add_tracking_note_to_parcel_order($order_id, $tracking_codes)
    {
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_id()) {
            throw new \Exception("Couldn't load order ID {$order_id} for adding tracking notes.");
        }
        $note = Trus_SLParcels_Config::get_order_tracking_note();
        if (!empty($tracking_codes)) {
            $note .= sprintf(
                __('<br>Tracking Codes: %s.', 'slparcels'),
                implode(", ", array_map(function ($tracking_code) {
                    return sprintf(
                        '<a href="https://parcelsapp.com/en/tracking/%s" target="_blank" title="Check status by tracking number">%s</a>',
                        $tracking_code,
                        $tracking_code
                    );
                }, $tracking_codes))
            );
        }

        $order->add_order_note($note, true);
        if (!Trus_SLParcels_DB::count_order_unsent_parcels($order->get_id())) {
            $order->update_status('wc-slshipped');
        }
    }

    public static function array_2_csv_file($lines, $file_path)
    {
        //$buffer = fopen('php://temp', 'r+');
        $buffer = fopen($file_path, "wa+");
        foreach ($lines as $line) {
            fputcsv($buffer, $line);
        }
        fclose($buffer);
        return $file_path;
        /*rewind($buffer);
        return $buffer;*/
        /*$csv = fgets($buffer);
        fclose($buffer);
        return $csv;*/
    }

    public static function prepare_reshimone_by_parcels($parcels, $top_number, $file_path)
    {
        $reshimone = [
            ['Number of Parcels', '', '', '', '', '', $top_number],
            [count($parcels)    , '', '', '', '', '', ''  ],
            [''                 , '', '', '', '', '', ''  ],
        ];

        $reshimone[] = ['Order Number', 'Total Order', 'Currency', 'Products Description', 'Tracking Code', 'Products Origin', 'Export Reason'];

        $subtotal_price_sum = 0;
        foreach ($parcels as $parcel) {
            $subtotal_price_sum+=$parcel['subtotal'];
            $reshimone[] = [$parcel['id'], round($parcel['subtotal'], 3), $parcel['currency'], "Chewings Tobacco", $parcel['tracking_code'], 'Sweden', 'Sale'];
        }
        $reshimone[] = ['Total', round($subtotal_price_sum, 3), '', '', '', '', ''];
        $reshimone[] = ['', '',                         '', '', '', '', ''];

        return self::array_2_csv_file($reshimone, $file_path);
    }

    public static function get_reshimone_by_bag($bag)
    {
        $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-reshimones';
        wp_mkdir_p($uploads_dir);

        return self::prepare_reshimone_by_parcels(
            Trus_SLParcels_DB::get_parcels_by_bag($bag),
            $bag,
            $uploads_dir . '/' . 'reshimone_for_bag_' . $bag . '.csv'
        );
    }

    public static function get_reshimone_by_month($month, $year)
	
    {
        $reshimone_main_name = 'reshimone_for_month_' . $month . '_' . $year;
        $reshimones = [];
        foreach (Trus_SLParcels_DB::get_parcels_by_month_and_year($month, $year) as $parcel) {
			if($parcel['subtotal'] > 235) {
				continue;
			}
            $last_reshimone = (array) array_pop($reshimones);
            if (count($last_reshimone) && 8000 <= array_sum(array_column($last_reshimone, 'subtotal')) + $parcel['subtotal']) {
                $reshimones[] = $last_reshimone;
                $last_reshimone = [];
            }
            $last_reshimone[] = $parcel;
            $reshimones[] = $last_reshimone;
        }

        $invoices_dir = trailingslashit(wp_upload_dir()['basedir']) . "israel-parcels-invoices";
        $reshimones_dir = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-reshimones';
        wp_mkdir_p($reshimones_dir);

        foreach ($reshimones as $i => $parcels) {
            $reshimones[$i] = [
                "file" => self::prepare_reshimone_by_parcels(
                    $parcels,
                    $month . '_' . $year . '_' . ($i+1),
                    $reshimones_dir . '/' . $reshimone_main_name . '_' . ($i+1) . '.csv'
                ),
                "name" => $reshimone_main_name . '_' . ($i+1),
                "parcels" => $parcels,
            ];
        }

        $file_path = "{$reshimones_dir}/{$reshimone_main_name}.zip";
        $zip = new ZipArchive();
        $zip->open($file_path, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
        foreach ($reshimones as $i => $reshimone) {
            if (empty($reshimone['file']) || !file_exists($reshimone['file'])) {
                throw new \Exception("Error during monthly reshimone generation. Reshimone file is missing ({$reshimone['name']})", 1);
            }
            $zip->addEmptyDir($reshimone['name'] . '/');
            $zip->addFile($reshimone['file'], "{$reshimone['name']}/{$reshimone['name']}.csv");
            foreach ($reshimone['parcels'] as $parcel) {
                if (empty($parcel['invoice']) || !file_exists("{$invoices_dir}/{$parcel['invoice']}")) {
                    throw new \Exception("Error during monthly reshimone generation. Parcel #{$parcel['id']} is missing an invoice.", 1);
                }
                $zip->addFile("{$invoices_dir}/{$parcel['invoice']}", "{$reshimone['name']}/{$parcel['invoice']}");
            }
        }
        $zip->close();

        return $file_path;
    }

    /**
     * @method update_parcel_tracking_info
     * @param  array                      $parcel
     * @return array
     */
    public static function update_parcel_tracking_info($parcel)
    {
        return Trus_SLParcels_API::update_parcel_tracking_info($parcel);
    }
}
