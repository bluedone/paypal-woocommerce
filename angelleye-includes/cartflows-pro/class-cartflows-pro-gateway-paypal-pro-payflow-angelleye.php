<?php

/**
 * Stripe Gateway.
 *
 * @package cartflows
 */

/**
 * Class Cartflows_Pro_Gateway_PayPal_Pro_PayFlow_AngellEYE.
 */
class Cartflows_Pro_Gateway_PayPal_Pro_PayFlow_AngellEYE {

    /**
     * Member Variable
     *
     * @var instance
     */
    private static $instance;

    /**
     * Key name variable
     *
     * @var key
     */
    public $key = 'paypal_pro_payflow';

    /**
     *  Initiator
     */
    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        
    }

    /**
     * Get WooCommerce payment geteways.
     *
     * @return array
     */
    public function get_wc_gateway() {

        global $woocommerce;

        $gateways = $woocommerce->payment_gateways->payment_gateways();

        return $gateways[$this->key];
    }

    /**
     * After payment process.
     *
     * @param array $order order data.
     * @param array $product product data.
     * @return array
     */
    public function process_offer_payment($order, $product) {
        try {
            $gateway = $this->get_wc_gateway();
            $gateway->angelleye_load_paypal_payflow_class(null, $this, $order);
            $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
            $description = sprintf(__('%1$s - Order %2$s - One Time offer', 'cartflows-pro'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $order->get_order_number());
            $old_wc = version_compare(WC_VERSION, '3.0', '<');

            $billing_address_1 = version_compare(WC_VERSION, '3.0', '<') ? $order->billing_address_1 : $order->get_billing_address_1();
            $billing_address_2 = version_compare(WC_VERSION, '3.0', '<') ? $order->billing_address_2 : $order->get_billing_address_2();
            $billing_city = version_compare(WC_VERSION, '3.0', '<') ? $order->billing_city : $order->get_billing_city();
            $billing_postcode = version_compare(WC_VERSION, '3.0', '<') ? $order->billing_postcode : $order->get_billing_postcode();
            $billing_country = version_compare(WC_VERSION, '3.0', '<') ? $order->billing_country : $order->get_billing_country();
            $billing_state = version_compare(WC_VERSION, '3.0', '<') ? $order->billing_state : $order->get_billing_state();
            $billing_email = version_compare(WC_VERSION, '3.0', '<') ? $billing_email : $order->get_billing_email();
            $customer_note_value = version_compare(WC_VERSION, '3.0', '<') ? wptexturize($order->customer_note) : wptexturize($order->get_customer_note());
            $customer_note = $customer_note_value ? substr(preg_replace("/[^A-Za-z0-9 ]/", "", $customer_note_value), 0, 256) : '';

            $PayPalRequestData = array(
                'tender' => 'C',
                'trxtype' => 'S',
                'amt' => AngellEYE_Gateway_Paypal::number_format($product['price'], $order),
                'currency' => version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency(),
                'comment1' => apply_filters('ae_pppf_custom_parameter', $customer_note, $order),
                'comment2' => apply_filters('ae_pppf_comment2_parameter', '', $order),
                'recurring' => '',
                'swipe' => '',
                'orderid' => $gateway->invoice_id_prefix . $order->get_order_number() . '-' . $product['step_id'],
                'orderdesc' => $description,
                'billtoemail' => $billing_email,
                'billtophonenum' => '',
                'billtostreet' => $billing_address_1 . ' ' . $billing_address_2,
                'billtocity' => $billing_city,
                'billtostate' => $billing_state,
                'billtozip' => $billing_postcode,
                'billtocountry' => $billing_country,
                'origid' => '',
                'custref' => '',
                'custcode' => '',
                'custip' => WC_Geolocation::get_ip_address(),
                'invnum' => $gateway->invoice_id_prefix . str_replace("#", "", $order->get_order_number()) . '-' . $product['step_id'],
                'ponum' => '',
                'starttime' => '',
                'endtime' => '',
                'securetoken' => '',
                'partialauth' => '',
                'authcode' => ''
            );

            /**
             * Shipping info
             */
            $shipping_first_name = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_first_name : $order->get_shipping_first_name();
            $shipping_last_name = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_last_name : $order->get_shipping_last_name();
            $shipping_address_1 = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_address_1 : $order->get_shipping_address_1();
            $shipping_address_2 = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_address_2 : $order->get_shipping_address_2();
            $shipping_city = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_city : $order->get_shipping_city();
            $shipping_postcode = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_postcode : $order->get_shipping_postcode();
            $shipping_country = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_country : $order->get_shipping_country();
            $shipping_state = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_state : $order->get_shipping_state();

            if ($shipping_address_1) {
                $PayPalRequestData['SHIPTOFIRSTNAME'] = $shipping_first_name;
                $PayPalRequestData['SHIPTOLASTNAME'] = $shipping_last_name;
                $PayPalRequestData['SHIPTOSTREET'] = $shipping_address_1 . ' ' . $shipping_address_2;
                $PayPalRequestData['SHIPTOCITY'] = $shipping_city;
                $PayPalRequestData['SHIPTOSTATE'] = $shipping_state;
                $PayPalRequestData['SHIPTOCOUNTRY'] = $shipping_country;
                $PayPalRequestData['SHIPTOZIP'] = $shipping_postcode;
            }

            $PayPalRequestData['origid'] = $order->get_transaction_id();
            $PayPalResult = $gateway->PayPal->ProcessTransaction($PayPalRequestData);

            wcf()->logger->log('PayFlow Endpoint: ' . $gateway->PayPal->APIEndPoint);
            wcf()->logger->log('PayFlow Response: ' . print_r($PayPalResult, true));

            if (empty($PayPalResult['RAWRESPONSE'])) {
                return false;
            }

            if (isset($PayPalResult['RESULT']) && ( $PayPalResult['RESULT'] == 0 || in_array($PayPalResult['RESULT'], $gateway->fraud_warning_codes))) {
                if (isset($PayPalResult['DUPLICATE']) && '2' == $PayPalResult['DUPLICATE']) {
                    $order->update_status('failed', __('Payment failed due to duplicate order ID', 'paypal-for-woocommerce'));
                    throw new Exception(__('Payment failed due to duplicate order ID', 'paypal-for-woocommerce'));
                }

                if (isset($PayPalResult['PPREF']) && !empty($PayPalResult['PPREF'])) {
                    add_post_meta($order_id, 'PPREF', $PayPalResult['PPREF']);
                    $order->add_order_note(sprintf(__('PayPal Pro Payflow payment completed (PNREF: %s) (PPREF: %s)', 'paypal-for-woocommerce'), $PayPalResult['PNREF'], $PayPalResult['PPREF']));
                } else {
                    $order->add_order_note(sprintf(__('PayPal Pro Payflow payment completed (PNREF: %s)', 'paypal-for-woocommerce'), $PayPalResult['PNREF']));
                }

                $avs_address_response_code = isset($PayPalResult['AVSADDR']) ? $PayPalResult['AVSADDR'] : '';
                $avs_zip_response_code = isset($PayPalResult['AVSZIP']) ? $PayPalResult['AVSZIP'] : '';
                $proc_avs_response_code = isset($PayPalResult['PROCAVS']) ? $PayPalResult['PROCAVS'] : '';
                $avs_response_order_note = __('Address Verification Result', 'paypal-for-woocommerce');
                $avs_response_order_note .= '<ul class="angelleye_avs_result">';
                $avs_response_order_note .= '<li>' . sprintf(__('AVS: %s', 'paypal-for-woocommerce'), $proc_avs_response_code) . '</li>';
                $avs_response_order_note .= '<ul class="angelleye_avs_result_inner">';
                $avs_response_order_note .= '<li>' . sprintf(__('Address Match: %s', 'paypal-for-woocommerce'), $avs_address_response_code) . '</li>';
                $avs_response_order_note .= '<li>' . sprintf(__('Postal Match: %s', 'paypal-for-woocommerce'), $avs_zip_response_code) . '</li>';
                $avs_response_order_note .= "<ul>";
                $avs_response_order_note .= '</ul>';

                if ($old_wc) {
                    update_post_meta($order_id, '_AVSADDR', $avs_address_response_code);
                    update_post_meta($order_id, '_AVSZIP', $avs_zip_response_code);
                    update_post_meta($order_id, '_PROCAVS', $avs_zip_response_code);
                } else {
                    update_post_meta($order->get_id(), '_AVSADDR', $avs_address_response_code);
                    update_post_meta($order->get_id(), '_AVSZIP', $avs_zip_response_code);
                    update_post_meta($order->get_id(), '_PROCAVS', $avs_zip_response_code);
                }

                $order->add_order_note($avs_response_order_note);
                $cvv2_response_code = isset($PayPalResult['CVV2MATCH']) ? $PayPalResult['CVV2MATCH'] : '';
                $cvv2_response_order_note = __('Card Security Code Result', 'paypal-for-woocommerce');
                $cvv2_response_order_note .= "\n";
                $cvv2_response_order_note .= sprintf(__('CVV2 Match: %s', 'paypal-for-woocommerce'), $cvv2_response_code);
                $order->add_order_note($cvv2_response_order_note);

                if ($gateway->fraud_management_filters == 'place_order_on_hold_for_further_review' && in_array($PayPalResult['RESULT'], $gateway->fraud_warning_codes)) {
                    $order->update_status('on-hold', $PayPalResult['RESPMSG']);
                    $old_wc = version_compare(WC_VERSION, '3.0', '<');
                    $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
                    if ($old_wc) {
                        if (!get_post_meta($order_id, '_order_stock_reduced', true)) {
                            $order->reduce_order_stock();
                        }
                    } else {
                        wc_maybe_reduce_stock_levels($order_id);
                    }
                } else {
                    if (isset($PayPalResult['PPREF']) && !empty($PayPalResult['PPREF'])) {
                        $order->add_order_note(sprintf(__('PayPal Pro Payflow payment completed (PNREF: %s) (PPREF: %s)', 'paypal-for-woocommerce'), $PayPalResult['PNREF'], $PayPalResult['PPREF']));
                    } else {
                        $order->add_order_note(sprintf(__('PayPal Pro Payflow payment completed (PNREF: %s)', 'paypal-for-woocommerce'), $PayPalResult['PNREF']));
                    }
                    if ($gateway->default_order_status == 'Completed') {
                        $order->update_status('completed');
                    } else {
                        $order->payment_complete($PayPalResult['PNREF']);
                    }
                    if ($old_wc) {
                        update_post_meta($order_id, '_transaction_id', $PayPalResult['PNREF']);
                    } else {
                        update_post_meta($order->get_id(), '_transaction_id', $PayPalResult['PNREF']);
                    }
                }
                return true;
            } else {
                $order->update_status('failed', __('PayPal Pro Payflow payment failed. Payment was rejected due to an error: ', 'paypal-for-woocommerce') . '(' . $PayPalResult['RESULT'] . ') ' . '"' . $PayPalResult['RESPMSG'] . '"');
                if ($gateway->error_email_notify) {
                    $admin_email = get_option("admin_email");
                    $message = __("PayFlow API call failed.", "paypal-for-woocommerce") . "\n\n";
                    $message .= __('Error Code: ', 'paypal-for-woocommerce') . $PayPalResult['RESULT'] . "\n";
                    $message .= __('Detailed Error Message: ', 'paypal-for-woocommerce') . $PayPalResult['RESPMSG'];
                    $message .= isset($PayPalResult['PREFPSMSG']) && $PayPalResult['PREFPSMSG'] != '' ? ' - ' . $PayPalResult['PREFPSMSG'] . "\n" : "\n";
                    $message .= __('User IP: ', 'paypal-for-woocommerce') . WC_Geolocation::get_ip_address() . "\n";
                    $message .= __('Order ID: ') . $order_id . "\n";
                    $message .= __('Customer Name: ') . $firstname . ' ' . $lastname . "\n";
                    $message .= __('Customer Email: ') . $billing_email . "\n";
                    $message = apply_filters('ae_pppf_error_email_message', $message);
                    $subject = apply_filters('ae_pppf_error_email_subject', "PayPal Pro Payflow Error Notification");
                    wp_mail($admin_email, $subject, $message);
                }
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

}

/**
 *  Prepare if class 'Cartflows_Pro_Gateway_PayPal_Pro_AngellEYE' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Pro_Gateway_PayPal_Pro_PayFlow_AngellEYE::get_instance();
