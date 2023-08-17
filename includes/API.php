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
 * Trus_SLParcels_API.
 */
class Trus_SLParcels_API
{
    public static function get_il_post_access_token()
    {
        if (!Trus_SLParcels_Config::is_enabled()) {
            throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_API::get_il_post_access_token().");
        }

        $access_token = Trus_SLParcels_Config::get_api_token();

        if (!$access_token || Trus_SLParcels_Config::get_api_token_expires() < time()) {
            try {
                $access_token = '';
                $url = Trus_SLParcels_Config::get_token_api_url();
                $args = [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode(Trus_SLParcels_Config::get_identity_client() . ':' . Trus_SLParcels_Config::get_identity_secret()),
                    ],
                    'body' => [
                        'grant_type' => 'password',
                        'username' => Trus_SLParcels_Config::get_username(),
                        'password' => Trus_SLParcels_Config::get_password(),
                        'scope' => 'read write',
                    ],
                    'timeout' => 120,
                ];

                Trus_SLParcels_Logger::log("get_il_post_access_token() Request", 'debug', [
                    'url' => $url,
                    'args' => $args,
                ]);

                $response = wp_remote_post($url, $args);

                if (is_wp_error($response)) {
                    throw new \Exception("[wp_error] " . $response->get_error_message());
                } else {
                    Trus_SLParcels_Logger::log("get_il_post_access_token() Response", 'debug', [
                        'status' => $response['response']['code'],
                        'response' => $response['body'],
                    ]);
                    if ($response['response']['code'] == 200) {
                        $body = json_decode($response['body'], true);
                        if (empty($body["access_token"])) {
                            throw new \Exception("Empty/Missing access_token");
                        }
                        $access_token = $body["access_token"];
                        Trus_SLParcels_Config::set_api_token($access_token);
                        Trus_SLParcels_Config::set_api_token_expires(time() + (int)$body["expires_in"] - 300);
                    } else {
                        throw new \Exception("Bad http response code: " . $response['response']['code']);
                    }
                }
            } catch (Exception $e) {
                Trus_SLParcels_Config::set_api_token('');
                Trus_SLParcels_Config::set_api_token_expires(time());
                Trus_SLParcels_Logger::log("get_il_post_access_token() [EXCEPTION]", 'error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'is_sandbox' => Trus_SLParcels_Config::is_sandbox_mode(),
                ]);
            }
        }

        return $access_token;
    }

    /**
     * @method generate_parcel_label
     * @param  array                $parcel
     * @return $parcel
     */
    public static function generate_parcel_label($parcel)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_API::generate_parcel_label().");
            }
            $parcel_id = $parcel["id"];
            $order_id = $parcel['order_id'];
            Trus_SLParcels_Logger::log("generate_parcel_label(parcel_id:{$parcel_id}) order_id:{$order_id} [START]", 'debug');

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



            $products = [];
            $use_generic_shipment_items_content = in_array($parcel["ship_mode"], ['ECOPOST','REGULAR']) && count($products);

            if ($use_generic_shipment_items_content) {
                $products[] = [
                    'Description' => Trus_SLParcels_Config::get_generic_shipment_postitem_desctiption(),
                    'Quantity' => (int) array_sum(array_column($parcel["items"], 'quantity')),
                    'CurrencyCode' => $parcel["currency"],
                    'OriginCountryCode' => 'IL',
					'HS_CODE' => '2404919000',
                    'ContentValue' => (float) round(array_sum(array_column($parcel["items"], 'subtotal')), 3),
                    'Weight' => (float) round(array_sum(array_column($parcel["items"], 'total_weight')), 3),
                ];
            } else {
                foreach ($parcel["items"] as $item) {
                    if(empty((float) round($item['subtotal'], 3))) {
                        $current_product = wc_get_product($item['product_id']);
                        $item['subtotal'] = $current_product->get_price() * $item['quantity'];
                    }

                    $products[] = [
                        'Description' => 'Nicotine Pouches ' . substr(wc_clean(Trus_SLParcels_Config::clean_forbidden_words($item['name'])), 0, 33),
                        'Quantity' => (int) $item['quantity'],
                        'CurrencyCode' => $parcel["currency"],
                        'OriginCountryCode' => 'IL',
						'HS_CODE' => '2404919000',
                        'ContentValue' => (float) round($item['subtotal'], 3),
                        'Weight' => (float) round($item['total_weight'], 3),
                    ];
                }
            }

            $shipment_body = [
                'Shipments' =>
                [
                    [
                        'ClientShipmentIdentifier' => $parcel_id, //uniqid("{$parcel_id}_{$parcel['order_number']}_"),
                        'Shipping' =>  array_merge(
                            //Sender Info:
                            wc_clean(Trus_SLParcels_Config::get_shipment_sender_address()),
                            //Recipient Info:
                            wc_clean($parcel["ship_mode"] === 'JEZ' ? Trus_SLParcels_Config::get_jez_recipient_address() : [
                                'RecipientName' => $parcel["shipping_first_name"] . " " . $parcel["shipping_last_name"],
                                'RecipientCompanyName' => $parcel["shipping_company"],
                                'RecipientCity' => $parcel["shipping_city"],
                                'RecipientZipCode' => $parcel["shipping_postcode"],
                                'RecipientCountry' => $parcel["shipping_country"],
                                'RecipientState' => $parcel["shipping_state"],
                                'RecipientAddress' => $parcel['shipping_address_2'] ? $parcel['shipping_address_1'] . ', ' . $parcel['shipping_address_2'] : $parcel['shipping_address_1'],
                                'RecipientHouseNumber' => substr($parcel['shipping_address_2'], 0, 10),
                                'RecipientEmail' => $parcel["shipping_email"],
                                'RecipientPhoneNumber' => $parcel["shipping_phone"],
                                'RecipientCellPhone' => $parcel["shipping_phone"],
                            ]),
                            //General Info:
                            [
                                'ShowComapnyIdInLabel' => true,
                                'ShippingTypeID' => Trus_SLParcels_ParcelsHelper::get_ship_mode_type_id($parcel["ship_mode"])
                            ]
                        ),
                        'PostItem' => [
                            'Weight' => (float) round($parcel['total_weight'], 3),
                            //'Weight' => (float) round(array_sum(array_column($products, 'Weight')), 3),
                            'Description' => $use_generic_shipment_items_content ? Trus_SLParcels_Config::get_generic_shipment_postitem_desctiption() : substr(implode(". ", array_column($products, "Description")), 0, 500),
                            'CustomsDeclarationID' => 1,
                            'Contents' => $products,
                        ],
                    ],
                ],
                'IsMergeLabels' => true,
                'MergedLabelsFileType' => 5, /* png */
            ];

            $access_token = Trus_SLParcels_Api::get_il_post_access_token();
            if (!$access_token) {
                throw new \Exception("[IL-Post API Error] No access token!");
            }

            $url = Trus_SLParcels_Config::get_shipments_api_url();
            $args = [
                'method' => 'POST',
                'redirection' => 10,
                'httpversion' => '1.0',
                'body' => json_encode($shipment_body),
                'timeout'     => 120,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ];

            Trus_SLParcels_Logger::log("generate_parcel_label() Request", 'debug', [
                'url' => $url,
                'args' => $args,
            ]);

            //$args['body'] = json_encode($args['body']);
            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                throw new \Exception("[wp_error] " . $response->get_error_message());
            } else {
                Trus_SLParcels_Logger::log("generate_parcel_label() Response", 'debug', [
                    'status' => $response['response']['code'],
                    'response' => $response['body'],
                ]);
                $response_code = !empty($response['response']['code']) ? $response['response']['code'] : null;
                $body = !empty($response['body'])? json_decode($response['body'], true) : [];
                $status_code = !empty($body["StatusCode"]) ? $body["StatusCode"] : $response_code;
                $status_message = !empty($body["Message"]) ? (string) $body["Message"] : "";
                $label = (!empty($body["Result"]) && !empty($body["Result"]["LabelsList"])) ? $body["Result"]["LabelsList"][0] : [];

                if ((int) $status_code !== 200) {
                    if ((int) $response_code !== 401 || (int) $status_code !== 401) {
                        Trus_SLParcels_Config::set_api_token('');
                        Trus_SLParcels_Config::set_api_token_expires(time());
                    }
                    $label_status_code = !empty($label['ShipmentRequestStatus']['StatusCode']) ? $label['ShipmentRequestStatus']['StatusCode'] : null;
                    $label_message = !empty($label['ShipmentRequestStatus']['Message']) ? $label['ShipmentRequestStatus']['Message'] : "";
                    throw new \Exception("[IL-Post API Error] ({$status_code},{$status_message}) {$label_message} ");
                }
                if(empty($label)){
                    throw new \Exception("[IL-Post API Error] No LabelsList. ");
                }
            }

            $tracking_number = $label["TrackingNumber"];
            //$shipment_num = $label["ShipmentNum"];

            $label_file_contents =  base64_decode($body["Result"]["MergedLabelsFile"]["FileByteString"]);

            //Save Label:
            $label_file = 'order_' . $parcel['order_number'] . '_parcel_' . $parcel['id'] . '_label.png';
            $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . 'israel-parcels-labels';
            wp_mkdir_p($uploads_dir);
            file_put_contents($uploads_dir . '/' . $label_file, $label_file_contents);

            //Update parcel:
            $parcel['tracking_code'] = wc_clean($tracking_number);
            $parcel['label'] = wc_clean($label_file);
            $parcel['labeled_at'] = wc_clean(date_i18n('Y-m-d H:i:s'));
            $parcel['label_error'] = null;
            Trus_SLParcels_DB::update_parcel($parcel);
            Trus_SLParcels_Logger::log("generate_parcel_label() Parcel", 'debug', $parcel);
            Trus_SLParcels_Logger::log("generate_parcel_label(parcel_id:{$parcel_id}) order_id:{$order_id} [DONE]", 'debug');
        } catch (Exception $e) {
            Trus_SLParcels_Logger::log("generate_parcel_label() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'is_sandbox' => Trus_SLParcels_Config::is_sandbox_mode(),
            ]);
            $parcel['label_error'] = $e->getMessage();
            Trus_SLParcels_DB::update_parcel($parcel);
        }

        return $parcel;
    }

    /**
     * @method update_parcel_tracking_info
     * @param  array                $parcel
     * @return $parcel
     */
    public static function update_parcel_tracking_info($parcel)
    {
        try {
            if (!Trus_SLParcels_Config::is_enabled()) {
                throw new \Exception("israel Parcels must be enabled & configured in order to call Trus_SLParcels_API::update_parcel_tracking_info().");
            }
            $parcel_id = $parcel["id"];
            $parcel['last_track_at'] = wc_clean(date_i18n('Y-m-d H:i:s'));

            if (empty($parcel["tracking_code"])) {
                throw new \Exception("Missing/empty tracking code for parcel #{$parcel['id']}");
            }

            $access_token = Trus_SLParcels_Api::get_il_post_access_token();
            if (!$access_token) {
                throw new \Exception("[IL-Post API Error] No access token!");
            }

            $url = Trus_SLParcels_Config::get_tracking_api_url($parcel["tracking_code"]);
            $args = [
                'method' => 'GET',
                'redirection' => 10,
                'httpversion' => '1.0',
                'timeout'     => 120,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                ],
            ];

            Trus_SLParcels_Logger::log("update_parcel_tracking_info() Request", 'debug', [
                'url' => $url,
                'args' => $args,
            ]);

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                throw new \Exception("[wp_error] " . $response->get_error_message());
            } else {
                Trus_SLParcels_Logger::log("update_parcel_tracking_info() Response", 'debug', [
                    'status' => $response['response']['code'],
                    'response' => $response['body'],
                ]);
                if ($response['response']['code'] == 200) {
                    $body = json_decode($response['body'], true);
                    $status_code = isset($body["StatusCode"]) ? $body["StatusCode"] : null;
                    if ((int) $status_code !== 200) {
                        throw new \Exception("Bad response StatusCode: " . $status_code);
                    }
                    if (!isset($body["Result"])) {
                        throw new \Exception("No `Result` field. ");
                    }
                } else {
                    if ($response['response']['code'] == 401) {
                        Trus_SLParcels_Config::set_api_token('');
                        Trus_SLParcels_Config::set_api_token_expires(time());
                    }
                    throw new \Exception("Bad http response code: " . $response['response']['code']);
                }
            }

            foreach($body["Result"] as $event){
                if(!isset($event["EventCharCode"]) || !isset($event["EventDate"])){
                    continue;
                }
                switch ($event["EventCharCode"]) {
                    case 'A':
                        //Label Printed (no need to update)
                        break;

                    case 'B':
                    case 'C':
                    case 'D':
                    case 'E':
                    case 'F':
                    case 'G':
                    case 'H':
                    case 'J':
                    case 'K':
                        // In tranzit
                        if($parcel['airport_at']){
                            continue;
                        }
                        $parcel['airport_at'] = wc_clean(date_i18n('Y-m-d H:i:s', strtotime($event["EventDate"])));
                        break;

                    case 'F':
                        //Out For Delivery
                        if($parcel['airport_at']){
                            continue;
                        }
                        $parcel['airport_at'] = wc_clean(date_i18n('Y-m-d H:i:s', strtotime($event["EventDate"])));
                        break;

                    case 'I':
                        //Delivered
                        if($parcel['arrived_at']){
                            continue;
                        }
                        $parcel['arrived_at'] = wc_clean(date_i18n('Y-m-d H:i:s', strtotime($event["EventDate"])));
                        break;

                    default:
                        continue;
                        break;
                }

            }

            Trus_SLParcels_DB::update_parcel($parcel);
            Trus_SLParcels_Logger::log("update_parcel_tracking_info() Parcel", 'debug', $parcel);
            Trus_SLParcels_Logger::log("update_parcel_tracking_info(parcel_id:{$parcel_id}) [DONE]", 'debug');
        } catch (Exception $e) {
            Trus_SLParcels_Logger::log("update_parcel_tracking_info() [EXCEPTION]", 'error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'is_sandbox' => Trus_SLParcels_Config::is_sandbox_mode(),
            ]);
            if(isset($response)){//Update "last_track_at"
                Trus_SLParcels_DB::update_parcel($parcel);
            }
        }

        return $parcel;
    }
}
