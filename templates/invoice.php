<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */
ob_start(); ?>
<html>
<head>
    <meta content="text/html; charset=UTF-8" http-equiv="content-type">
    <style type="text/css">
        body{
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }
        header{
            margin-bottom: 25px;
        }
        footer{
            margin-top: 30px;
        }
        footer p{
            margin: 16px 0;
        }
        h1{
            text-align: center;
            font-size: 16px;
        }
        .align-right{
            text-align: right;
        }
        .align-left{
            text-align: left;
        }
        table{
            width: 100%;
            border: 0.5px solid #000;
            border-spacing: 0;
            margin-bottom:15px;
            text-align: center;
        }
        td.spacer{}
        table.addresses {
            border:none;
            line-height: 1.2;
            font-size: 12px;
        }
        table.bordered, table.bordered th, table.bordered td {
            border: 0.5px solid #000;
        }
        table.bordered th, table.bordered td {
            padding: 4px;
        }
        table.items thead th{
            background-color: #bfbfbf;
        }
        img.signature{
            width: 110px;
        }
    </style>
</head>
<body>
    <header>
        <h1>INVOICE: <?php echo esc_html($parcel['id']); ?></h1>
        <table class="addresses">
            <tbody>
                <tr>
                    <td class="align-left" colspan="1" rowspan="1">
                        <div><strong>From </strong></div>
                        <div>Swedish Tobacco LTD - 513942599</div>
                        <div>Yechezkel Hanavi 19</div>
                        <div>Bet Shemesh, 9913954 &ndash; Israel</div>
                        <div></div>
                    </td>
                    <td class="align-right" colspan="1" rowspan="1">
                        <div><strong>To</strong></div>
                        <div><?php echo esc_html("{$parcel['shipping_first_name']} {$parcel['shipping_last_name']}"); ?></div>
                        <div><?php echo esc_html("{$parcel['shipping_address_1']} {$parcel['shipping_address_2']}"); ?></div>
                        <div><?php echo esc_html("{$parcel['shipping_city']} {$parcel['shipping_postcode']}"); ?></div>
                        <div><?php echo esc_html($parcel['shipping_state'] . " " . WC()->countries->countries[$parcel['shipping_country']]); ?></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </header>
    <table class="global bordered">
        <thead>
            <tr>
                <th>Nb Packages</th>
                <th>Tracking Code</th>
                <th>Term of Delivery</th>
                <th>GROSS WEIGHT (g)</th>
                <th>Invoice Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="1" rowspan="1">
                    <div>1</div>
                </td>
                <td colspan="1" rowspan="1">
                    <div><?php echo esc_html($parcel['tracking_code']); ?></div>
                </td>
                <td colspan="1" rowspan="1">
                    <div>CIF</div>
                </td>
                <td colspan="1" rowspan="1">
                    <div><?php echo round($parcel['gross_weight'], 3); ?></div>
                </td>
                <td colspan="1" rowspan="1">
                    <div><?php echo esc_html(date('m/d/Y H:i', strtotime($invoice_date))); ?></div>
                </td>
            </tr>
        </tbody>
    </table>
    <table class="items bordered">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product name</th>
                <th>Quantity</th>
                <th>Products Group</th>
                <th>Country of Origin</th>
                <th>Weight (g)</th>
                <th>Total Net Weight (g)</th>
                <th>Unit Price &euro;</th>
                <th>Total Price &euro;</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $discount = 0;
            ?>
            <?php foreach ($parcel['items'] as $item): ?>
                <?php
                    if(empty((float) round($item['subtotal'], 3))) {
                        $current_product = wc_get_product($item['product_id']);
                        $item['unit_subtotal'] = $current_product->get_price();
                        $item['subtotal'] = $item['unit_subtotal'] * $item['quantity'];
                        $discount += $item['subtotal'];
                    }
                ?>
                <tr>
                    <td colspan="1" rowspan="1">
                        <div><?php echo $item['sku']; ?></div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div><?php echo esc_html(Trus_SLParcels_Config::clean_forbidden_words($item['name'])); ?></div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div><?php echo (int) $item['quantity']; ?></div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div>Chewing Tobacco</div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div>Sweden</div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div><?php echo round($item['unit_weight'] * 1000, 3) ?></div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div><?php echo round($item['total_weight'] * 1000, 3) ?></div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div><?php echo round($item['unit_subtotal'], 3) ?></div>
                    </td>
                    <td colspan="1" rowspan="1">
                        <div><?php echo round($item['subtotal'], 3) ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="9" rowspan="1" class="spacer">&nbsp;</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th class="align-right" colspan="8" rowspan="1">
                    <div>SubTotal &euro;</div>
                </th>
                <td colspan="2" rowspan="1">
                    <div><?php echo round($parcel['subtotal'] + $discount, 3); ?></div>
                </td>
            </tr>
            <tr>
                <th class="align-right" colspan="8" rowspan="1">
                    <div>Trade Discount<?php echo !empty($parcel['coupon_code']) ? esc_html(" (Coupon Code: {$parcel['coupon_code']}) ") : " " ?>&euro;</div>
                </th>
                <td colspan="2" rowspan="1">
                    <div><?php echo round($parcel['discount_amount'] + $discount, 3); ?></div>
                </td>
            </tr>
            <tr>
                <th class="align-right" colspan="8" rowspan="1">
                    <div>Total &euro;</div>
                </th>
                <td colspan="2" rowspan="1">
                    <div><?php echo round($parcel['total_price'], 3); ?></div>
                </td>
            </tr>
        </tfoot>
    </table>
    <footer>
        <?php if ($cc_last_4): ?>
            <p>Paid by Credit Card XXXX XXXX XXXX <?php echo esc_html(substr($cc_last_4, -4)); ?></p>
        <?php elseif (!empty($parcel['coupon_code']) && !$parcel['total_price']): ?>
            <p>Paid with the Coupon Code: <?php echo esc_html($parcel['coupon_code']); ?></p>
        <?php endif; ?>
        <?php if ($parcel['discount_amount'] > ($parcel['total_price'] * 0.25)): ?>
            <p>Value for Custom Only: <?php echo round($parcel['subtotal'], 3); ?></p>
        <?php endif; ?>
        <p class="align-right">
            <img class="signature" alt="Swedish Tobaco LTD - signature" title="Swedish Tobaco LTD - signature" src="<?php echo TRUS_SLPARCELS_ASSETS_URL; ?>images/swedish-tobaco-signature.png">
        </p>
    </footer>
</body>
</html>
<?php
return ob_get_clean();
