<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_PayPal_Express_Request_AngellEYE {

    public $gateway;
    public $gateway_calculation;
    public $credentials;
    public $paypal;
    public $cart_param;
    public $paypal_request;
    public $paypal_response;
    public $response_helper;
    public $function_helper;
    public $confirm_order_id;
    public $order_param;
    public $user_email_address;

    public function __construct($gateway) {
        try {
            $this->gateway = $gateway;
            $this->skip_final_review = $this->gateway->get_option('skip_final_review', 'no');
            $this->billing_address = $this->gateway->get_option('billing_address', 'no');
            $this->disable_term = 'yes' === $this->gateway->get_option('disable_term', 'no');
            $this->credentials = array(
                'Sandbox' => $this->gateway->testmode == 'yes' ? TRUE : FALSE,
                'APIUsername' => $this->gateway->api_username,
                'APIPassword' => $this->gateway->api_password,
                'APISignature' => $this->gateway->api_signature,
                'Force_tls_one_point_two' => $this->gateway->Force_tls_one_point_two
            );
            $this->angelleye_load_paypal_class();
            if (!class_exists('WC_Gateway_Calculation_AngellEYE')) {
                require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-gateway-calculations-angelleye.php' );
            }
            $this->gateway_calculation = new WC_Gateway_Calculation_AngellEYE();
            if (!class_exists('WC_Gateway_PayPal_Express_Response_AngellEYE')) {
                require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/angelleye-includes/express-checkout/class-wc-gateway-paypal-express-response-angelleye.php' );
            }
            $this->response_helper = new WC_Gateway_PayPal_Express_Response_AngellEYE();
            if (!class_exists('WC_Gateway_PayPal_Express_Function_AngellEYE')) {
                require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/angelleye-includes/express-checkout/class-wc-gateway-paypal-express-function-angelleye.php' );
            }
            $this->function_helper = new WC_Gateway_PayPal_Express_Function_AngellEYE();
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_redirect() {
        unset(WC()->session->paypal_express_checkout);
        if (!is_ajax()) {
            wp_redirect(get_permalink(wc_get_page_id('cart')));
            exit;
        } else {
            $args = array(
                'result' => 'failure',
                'redirect' => get_permalink(wc_get_page_id('cart')),
            );
            if ($this->function_helper->ec_is_version_gte_2_4()) {
                wp_send_json($args);
            } else {
                echo '<!--WC_START-->' . json_encode($args) . '<!--WC_END-->';
            }
        }
    }

    public function angelleye_redirect_action($url) {
        if (!empty($url)) {
            if (!is_ajax()) {
                wp_redirect($url);
                exit;
            } else {
                $args = array(
                    'result' => 'success',
                    'redirect' => $url,
                );
                wp_send_json($args);
            }
        }
    }

    public function angelleye_set_express_checkout() {
        try {
            $this->angelleye_set_express_checkout_request();
            if ($this->response_helper->ec_is_response_success_or_successwithwarning($this->paypal_response)) {
                $this->angelleye_redirect_action($this->paypal_response['REDIRECTURL']);
                exit;
            } else {
                $this->angelleye_write_error_log_and_send_email_notification($paypal_action_name = 'SetExpressCheckout');
                $this->angelleye_redirect();
            }
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_get_express_checkout_details() {
        try {
            if (!isset($_GET['token'])) {
                // todo
                // need to display notice and redirect to cart page.
            }
            $token = esc_attr($_GET['token']);
            $this->paypal_response = $this->paypal->GetExpresscheckoutDetails($token);
            $this->angelleye_write_paypal_request_log($paypal_action_name = 'GetExpresscheckoutDetails');
            if ($this->response_helper->ec_is_response_success($this->paypal_response)) {
                WC()->session->paypal_express_checkout = array(
                    'token' => $token,
                    'shipping_details' => $this->response_helper->ec_get_shipping_details($this->paypal_response),
                    'order_note' => $this->response_helper->ec_get_note_text($this->paypal_response),
                    'payer_id' => $this->response_helper->ec_get_payer_id($this->paypal_response),
                    'ExpresscheckoutDetails' => $this->paypal_response
                );
                WC()->session->shiptoname = $this->paypal_response['FIRSTNAME'] . ' ' . $this->paypal_response['LASTNAME'];
                WC()->session->payeremail = $this->paypal_response['EMAIL'];
                WC()->session->chosen_payment_method = get_class($this->gateway);
                if ($this->angelleye_ec_is_skip_final_review()) {
                    wp_redirect(WC()->cart->get_checkout_url());
                    exit();
                }
                $this->angelleye_ec_load_customer_data_using_ec_details();
            } else {
                $this->angelleye_write_error_log_and_send_email_notification($paypal_action_name = 'GetExpresscheckoutDetails');
                $this->angelleye_redirect();
            }
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_do_express_checkout_payment() {
        try {
            if (!isset($_GET['order_id'])) {
                // todo need to redirect to cart page.
            }
            $this->confirm_order_id = esc_attr($_GET['order_id']);
            $order = new WC_Order($this->confirm_order_id);
            $this->angelleye_do_express_checkout_payment_request();
            $this->angelleye_add_order_note($order);
            $this->angelleye_add_extra_order_meta($order);
            if ($this->response_helper->ec_is_response_success_or_successwithwarning($this->paypal_response)) {
                $this->angelleye_ec_get_customer_email_address($this->confirm_order_id);
                $this->angelleye_ec_sellerprotection_handler($this->confirm_order_id);
                $is_sandbox = $this->gateway->testmode == 'yes' ? true : false;
                update_post_meta($order->id, 'is_sandbox', $is_sandbox);
                if ($this->paypal_response['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Completed') {
                    $order->payment_complete($this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']);
                } else {
                    $this->update_payment_status_by_paypal_responce($this->confirm_order_id, $this->paypal_response);
                    update_post_meta($order->id, '_transaction_id', $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']);
                    $order->reduce_order_stock();
                    WC()->cart->empty_cart();
                }
                update_post_meta($order->id, '_express_chekout_transactionid', isset($this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']) ? $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID'] : '');
                $order->add_order_note(sprintf(__('%s payment approved! Trnsaction ID: %s', 'paypal-for-woocommerce'), $this->gateway->title, $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']));
                $this->angelleye_ec_save_billing_agreement($order->id);
                WC()->cart->empty_cart();
                wc_clear_notices();
                wp_redirect($this->gateway->get_return_url($order));
                exit();
            } else {
                $this->angelleye_add_order_note_with_error($order, $paypal_action_name = 'DoExpressCheckoutPayment');
                $this->angelleye_write_error_log_and_send_email_notification($paypal_action_name = 'DoExpressCheckoutPayment');
                $this->angelleye_redirect();
            }
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_do_express_checkout_payment_request() {
        try {
            if (!empty($this->confirm_order_id)) {
                $order = new WC_Order($this->confirm_order_id);
                $invoice_number = preg_replace("/[^a-zA-Z0-9]/", "", $order->get_order_number());
                if ($order->customer_note) {
                    $customer_notes = wptexturize($order->customer_note);
                }
            } else {
                // error
            }
            $this->order_param = $this->gateway_calculation->order_calculation($this->confirm_order_id);
            $DECPFields = array(
                'token' => WC()->session->paypal_express_checkout['token'],
                'payerid' => (!empty(WC()->session->paypal_express_checkout['payer_id']) ) ? WC()->session->paypal_express_checkout['payer_id'] : null,
                'returnfmfdetails' => 1,
                'buyermarketingemail' => '',
                'allowedpaymentmethod' => ''
            );
            $Payments = array();
            $Payment = array(
                'amt' => AngellEYE_Gateway_Paypal::number_format($order->order_total),
                'currencycode' => $order->get_order_currency(),
                'shippingdiscamt' => '',
                'insuranceoptionoffered' => '',
                'handlingamt' => '',
                'desc' => '',
                'custom' => '',
                'invnum' => $this->gateway->invoice_id_prefix . $invoice_number,
                'notetext' => !empty($customer_notes) ? $customer_notes : '',
                'allowedpaymentmethod' => '',
                'paymentaction' => $this->gateway->payment_action,
                'paymentrequestid' => '',
                'sellerpaypalaccountid' => '',
                'sellerid' => '',
                'sellerusername' => '',
                'sellerregistrationdate' => '',
                'softdescriptor' => ''
            );
            if (isset($this->gateway->notifyurl) && !empty($this->gateway->notifyurl)) {
                $Payment['notifyurl'] = $this->gateway->notifyurl;
            }
            if ($this->gateway->send_items) {
                $Payment['order_items'] = $this->order_param['order_items'];
            } else {
                $Payment['order_items'] = array();
            }
            $Payment['taxamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['taxamt']);
            $Payment['shippingamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['shippingamt']);
            $Payment['itemamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['itemamt']);
            $REVIEW_RESULT = !empty(WC()->session->paypal_express_checkout['ExpresscheckoutDetails']) ? WC()->session->paypal_express_checkout['ExpresscheckoutDetails'] : array();
            $PaymentRedeemedOffers = array();
            if ((isset($REVIEW_RESULT) && !empty($REVIEW_RESULT)) && isset($REVIEW_RESULT['WALLETTYPE0'])) {
                $i = 0;
                while (isset($REVIEW_RESULT['WALLETTYPE' . $i])) {
                    $RedeemedOffer = array(
                        'redeemedoffername' => $REVIEW_RESULT['WALLETDESCRIPTION' . $i],
                        'redeemedofferdescription' => '',
                        'redeemedofferamount' => '',
                        'redeemedoffertype' => $REVIEW_RESULT['WALLETTYPE' . $i],
                        'redeemedofferid' => $REVIEW_RESULT['WALLETID' . $i],
                        'redeemedofferpointsaccrued' => '',
                        'cummulativepointsname' => '',
                        'cummulativepointsdescription' => '',
                        'cummulativepointstype' => '',
                        'cummulativepointsid' => '',
                        'cummulativepointsaccrued' => '',
                    );
                    $i = $i + 1;
                    array_push($PaymentRedeemedOffers, $RedeemedOffer);
                }
                $Payment['redeemed_offers'] = $PaymentRedeemedOffers;
                array_push($Payments, $Payment);
            } else {
                array_push($Payments, $Payment);
            }
            if (WC()->cart->needs_shipping()) {
                $shipping_first_name = $order->shipping_first_name;
                $shipping_last_name = $order->shipping_last_name;
                $shipping_address_1 = $order->shipping_address_1;
                $shipping_address_2 = $order->shipping_address_2;
                $shipping_city = $order->shipping_city;
                $shipping_state = $order->shipping_state;
                $shipping_postcode = $order->shipping_postcode;
                $shipping_country = $order->shipping_country;
                $Payment = array('shiptoname' => $shipping_first_name . ' ' . $shipping_last_name,
                    'shiptostreet' => $shipping_address_1,
                    'shiptostreet2' => $shipping_address_2,
                    'shiptocity' => wc_clean(stripslashes($shipping_city)),
                    'shiptostate' => $shipping_state,
                    'shiptozip' => $shipping_postcode,
                    'shiptocountrycode' => $shipping_country,
                    'shiptophonenum' => '',
                );
                array_push($Payments, $Payment);
            }
            $this->paypal_request = array(
                'DECPFields' => $DECPFields,
                'Payments' => $Payments
            );
            $this->paypal_response = $this->paypal->DoExpressCheckoutPayment($this->paypal_request);
            $this->angelleye_write_paypal_request_log($paypal_action_name = 'DoExpressCheckoutPayment');
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_load_paypal_class() {
        try {
            if (!class_exists('Angelleye_PayPal')) {
                require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/lib/angelleye/paypal-php-library/includes/paypal.class.php' );
            }
            $this->paypal = new Angelleye_PayPal($this->credentials);
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_set_express_checkout_request() {
        try {
            $cancel_url = !empty($this->gateway->cancel_page_id) ? get_permalink($this->gateway->cancel_page_id) : WC()->cart->get_cart_url();
            $this->cart_param = $this->gateway_calculation->cart_calculation();
            $SECFields = array(
                'maxamt' => '',
                'returnurl' => urldecode(add_query_arg('pp_action', 'get_express_checkout_details', WC()->api_request_url('WC_Gateway_PayPal_Express_AngellEYE'))),
                'cancelurl' => urldecode($cancel_url),
                'callback' => '',
                'callbacktimeout' => '',
                'callbackversion' => '',
                'reqconfirmshipping' => '',
                'noshipping' => '',
                'allownote' => 1,
                'addroverride' => '',
                'localecode' => ($this->gateway->use_wp_locale_code == 'yes' && get_locale() != '') ? get_locale() : '',
                'pagestyle' => $this->gateway->page_style,
                'hdrimg' => $this->gateway->checkout_logo_hdrimg,
                'logoimg' => $this->gateway->checkout_logo,
                'hdrbordercolor' => '',
                'hdrbackcolor' => '',
                'payflowcolor' => '',
                'skipdetails' => $this->gateway->skip_final_review == 'yes' ? '1' : '0',
                'email' => '',
                'channeltype' => '',
                'giropaysuccessurl' => '',
                'giropaycancelurl' => '',
                'banktxnpendingurl' => '',
                'brandname' => $this->gateway->brand_name,
                'customerservicenumber' => $this->gateway->customer_service_number,
                'buyeremailoptionenable' => '',
                'surveyquestion' => '',
                'surveyenable' => '',
                'totaltype' => '',
                'notetobuyer' => '',
                'buyerid' => '',
                'buyerusername' => '',
                'buyerregistrationdate' => '',
                'allowpushfunding' => '',
                'taxidtype' => '',
                'taxid' => ''
            );
            $usePayPalCredit = (!empty($_GET['use_paypal_credit']) && $_GET['use_paypal_credit'] == true) ? true : false;
            if ($usePayPalCredit) {
                $SECFields['solutiontype'] = 'Sole';
                $SECFields['landingpage'] = 'Billing';
                $SECFields['userselectedfundingsource'] = 'BML';
            } elseif (strtolower($this->gateway->paypal_account_optional) == 'yes' && strtolower($this->gateway->landing_page) == 'billing') {
                $SECFields['solutiontype'] = 'Sole';
                $SECFields['landingpage'] = 'Billing';
                $SECFields['userselectedfundingsource'] = 'CreditCard';
            } elseif (strtolower($this->gateway->paypal_account_optional) == 'yes' && strtolower($this->gateway->landing_page) == 'login') {
                $SECFields['solutiontype'] = 'Sole';
                $SECFields['landingpage'] = 'Login';
            }
            $Payments = array();
            $Payment = array(
                'amt' => AngellEYE_Gateway_Paypal::number_format(WC()->cart->total),
                'currencycode' => get_woocommerce_currency(),
                'custom' => apply_filters('ae_ppec_custom_parameter', ''),
                'notetext' => '',
                'paymentaction' => $this->gateway->payment_action,
            );
            if ($this->gateway->send_items) {
                $Payment['order_items'] = $this->cart_param['order_items'];
            } else {
                $Payment['order_items'] = array();
            }
            $Payment['taxamt'] = $this->cart_param['taxamt'];
            $Payment['shippingamt'] = $this->cart_param['shippingamt'];
            $Payment['itemamt'] = $this->cart_param['itemamt'];
            array_push($Payments, $Payment);
            $PayPalRequestData = array(
                'SECFields' => $SECFields,
                'Payments' => $Payments,
            );
            $this->paypal_request = $this->angelleye_add_billing_agreement_param($PayPalRequestData, $this->gateway->supports('tokenization'));
            $this->paypal_response = $this->paypal->SetExpressCheckout($this->paypal_request);
            $this->angelleye_write_paypal_request_log($paypal_action_name = 'SetExpressCheckout');
            return $this->paypal_response;
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_add_billing_agreement_param($PayPalRequestData, $tokenization) {
        try {
            if (sizeof(WC()->cart->get_cart()) != 0) {
                foreach (WC()->cart->get_cart() as $key => $value) {
                    $_product = $value['data'];
                    if (isset($_product->id) && !empty($_product->id)) {
                        $_paypal_billing_agreement = get_post_meta($_product->id, '_paypal_billing_agreement', true);
                        if ($_paypal_billing_agreement == 'yes' || ( isset(WC()->session->ec_save_to_account) && WC()->session->ec_save_to_account == 'on')) {
                            $BillingAgreements = array();
                            $Item = array(
                                'l_billingtype' => '',
                                'l_billingtype' => 'MerchantInitiatedBilling',
                                'l_billingagreementdescription' => '',
                                'l_paymenttype' => '',
                                'l_paymenttype' => 'Any',
                                'l_billingagreementcustom' => ''
                            );
                            array_push($BillingAgreements, $Item);
                            $PayPalRequestData['BillingAgreements'] = $BillingAgreements;
                            return $PayPalRequestData;
                        }
                    }
                }
            }
            return $PayPalRequestData;
        } catch (Exception $ex) {
            
        }
    }

    public function update_payment_status_by_paypal_responce($orderid, $result) {
        try {
            $order = wc_get_order($orderid);
            switch (strtolower($result['PAYMENTINFO_0_PAYMENTSTATUS'])) :
                case 'completed' :
                    if ($order->status == 'completed') {
                        break;
                    }
                    if (!in_array(strtolower($result['PAYMENTINFO_0_TRANSACTIONTYPE']), array('cart', 'instant', 'express_checkout', 'web_accept', 'masspay', 'send_money'))) {
                        break;
                    }
                    $order->add_order_note(__('Payment Completed via Express Checkout', 'paypal-for-woocommerce'));
                    $order->payment_complete($result['PAYMENTINFO_0_TRANSACTIONID']);
                    break;
                case 'pending' :
                    if (!in_array(strtolower($result['PAYMENTINFO_0_TRANSACTIONTYPE']), array('cart', 'instant', 'express_checkout', 'web_accept', 'masspay', 'send_money'))) {
                        break;
                    }
                    switch (strtolower($result['PAYMENTINFO_0_PENDINGREASON'])) {
                        case 'address':
                            $pending_reason = __('Address: The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set such that you want to manually accept or deny each of these payments. To change your preference, go to the Preferences section of your Profile.', 'paypal-for-woocommerce');
                            break;
                        case 'authorization':
                            $pending_reason = __('Authorization: The payment is pending because it has been authorized but not settled. You must capture the funds first.', 'paypal-for-woocommerce');
                            break;
                        case 'echeck':
                            $pending_reason = __('eCheck: The payment is pending because it was made by an eCheck that has not yet cleared.', 'paypal-for-woocommerce');
                            break;
                        case 'intl':
                            $pending_reason = __('intl: The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'paypal-for-woocommerce');
                            break;
                        case 'multicurrency':
                        case 'multi-currency':
                            $pending_reason = __('Multi-currency: You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'paypal-for-woocommerce');
                            break;
                        case 'order':
                            $pending_reason = __('Order: The payment is pending because it is part of an order that has been authorized but not settled.', 'paypal-for-woocommerce');
                            break;
                        case 'paymentreview':
                            $pending_reason = __('Payment Review: The payment is pending while it is being reviewed by PayPal for risk.', 'paypal-for-woocommerce');
                            break;
                        case 'unilateral':
                            $pending_reason = __('Unilateral: The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'paypal-for-woocommerce');
                            break;
                        case 'verify':
                            $pending_reason = __('Verify: The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'paypal-for-woocommerce');
                            break;
                        case 'other':
                            $pending_reason = __('Other: For more information, contact PayPal customer service.', 'paypal-for-woocommerce');
                            break;
                        case 'none':
                        default:
                            $pending_reason = __('No pending reason provided.', 'paypal-for-woocommerce');
                            break;
                    }
                    $order->add_order_note(sprintf(__('Payment via Express Checkout Pending. PayPal reason: %s.', 'paypal-for-woocommerce'), $pending_reason));
                    $order->update_status('on-hold');
                    break;
                case 'denied' :
                case 'expired' :
                case 'failed' :
                case 'voided' :
                    $order->update_status('failed', sprintf(__('Payment %s via Express Checkout.', 'paypal-for-woocommerce'), strtolower($result['PAYMENTINFO_0_PAYMENTSTATUS'])));
                    break;
                default:
                    break;
            endswitch;
            return;
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_add_extra_order_meta($order) {
        if (!empty($this->gateway->payment_action) && $this->gateway->payment_action != 'Sale') {
            $payment_order_meta = array('_transaction_id' => $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID'], '_payment_action' => $this->gateway->payment_action, '_express_checkout_token' => WC()->session->paypal_express_checkout['token'], '_first_transaction_id' => $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']);
            AngellEYE_Utility::angelleye_add_order_meta($order->id, $payment_order_meta);
        }
    }

    public function angelleye_add_order_note($order) {
        if (!empty(WC()->session->paypal_express_checkout['ExpresscheckoutDetails']['PAYERSTATUS'])) {
            $order->add_order_note(sprintf(__('Payer Status: %s', 'paypal-for-woocommerce'), '<strong>' . WC()->session->paypal_express_checkout['ExpresscheckoutDetails']['PAYERSTATUS'] . '</strong>'));
        }
        if (!empty(WC()->session->paypal_express_checkout['ExpresscheckoutDetails']['ADDRESSSTATUS'])) {
            $order->add_order_note(sprintf(__('Address Status: %s', 'paypal-for-woocommerce'), '<strong>' . WC()->session->paypal_express_checkout['ExpresscheckoutDetails']['ADDRESSSTATUS'] . '</strong>'));
        }
    }

    public function angelleye_add_order_note_with_error($order, $paypal_action_name = null) {
        if (!empty($this->paypal_response['L_ERRORCODE0'])) {
            $ErrorCode = urldecode($this->paypal_response['L_ERRORCODE0']);
        } else {
            $ErrorCode = '';
        }
        if (!empty($this->paypal_response['L_SHORTMESSAGE0'])) {
            $ErrorShortMsg = urldecode($this->paypal_response['L_SHORTMESSAGE0']);
        } else {
            $ErrorShortMsg = '';
        }
        if (!empty($this->paypal_response['L_LONGMESSAGE0'])) {
            $ErrorLongMsg = urldecode($this->paypal_response['L_LONGMESSAGE0']);
        } else {
            $ErrorLongMsg = '';
        }
        if (!empty($this->paypal_response['L_SEVERITYCODE0'])) {
            $ErrorSeverityCode = urldecode($this->paypal_response['L_SEVERITYCODE0']);
        } else {
            $ErrorSeverityCode = '';
        }
        $order->add_order_note(sprintf(__('PayPal %s API call failed:', 'woocommerce') . __('Detailed Error Message:', 'woocommerce') . PHP_EOL . __('Short Error Message:', 'woocommerce') . PHP_EOL . __('Error Code:', 'woocommerce') . PHP_EOL . __('Error Severity Code:', 'woocommerce'), $paypal_action_name, $ErrorLongMsg, $ErrorShortMsg, $ErrorCode, $ErrorSeverityCode));
    }

    public function angelleye_write_error_log_and_send_email_notification($paypal_action_name) {
        if (!empty($this->paypal_response['L_ERRORCODE0'])) {
            $ErrorCode = urldecode($this->paypal_response['L_ERRORCODE0']);
        } else {
            $ErrorCode = '';
        }
        if (!empty($this->paypal_response['L_SHORTMESSAGE0'])) {
            $ErrorShortMsg = urldecode($this->paypal_response['L_SHORTMESSAGE0']);
        } else {
            $ErrorShortMsg = '';
        }
        if (!empty($this->paypal_response['L_LONGMESSAGE0'])) {
            $ErrorLongMsg = urldecode($this->paypal_response['L_LONGMESSAGE0']);
        } else {
            $ErrorLongMsg = '';
        }
        if (!empty($this->paypal_response['L_SEVERITYCODE0'])) {
            $ErrorSeverityCode = urldecode($this->paypal_response['L_SEVERITYCODE0']);
        } else {
            $ErrorSeverityCode = '';
        }
        if ($this->gateway->error_email_notify) {
            $mailer = WC()->mailer();
            $error_email_notify_subject = apply_filters('ae_ppec_error_email_subject', 'PayPal Express Checkout Error Notification');
            $message = sprintf(__('PayPal %s API call failed:', 'woocommerce') . __('Detailed Error Message:', 'woocommerce') . PHP_EOL . __('Short Error Message:', 'woocommerce') . PHP_EOL . __('Error Code:', 'woocommerce') . PHP_EOL . __('Error Severity Code:', 'woocommerce'), $paypal_action_name, $ErrorLongMsg, $ErrorShortMsg, $ErrorCode, $ErrorSeverityCode);
            $message = apply_filters('ae_ppec_error_email_message', $message, $ErrorCode, $ErrorSeverityCode, $ErrorShortMsg, $ErrorLongMsg);
            $message = $mailer->wrap_message($error_email_notify_subject, $message);
            $mailer->send(get_option('admin_email'), strip_tags($error_email_notify_subject), $message);
        }
        if ($this->gateway->error_display_type == 'detailed') {
            $sec_error_notice = $ErrorCode . ' - ' . $ErrorLongMsg;
            $error_display_type_message = sprintf(__($sec_error_notice, 'paypal-for-woocommerce'));
        } else {
            $error_display_type_message = sprintf(__('There was a problem paying with PayPal.  Please try another method.', 'paypal-for-woocommerce'));
        }
        $error_display_type_message = apply_filters('ae_ppec_error_user_display_message', $error_display_type_message, $ErrorCode, $ErrorLongMsg);
        wc_add_notice($error_display_type_message, 'error');
    }

    public function angelleye_write_paypal_request_log($paypal_action_name) {
        if ($paypal_action_name == 'SetExpressCheckout') {
            WC_Gateway_PayPal_Express_AngellEYE::log('Redirecting to PayPal');
            WC_Gateway_PayPal_Express_AngellEYE::log(sprintf(__('PayPal for WooCommerce Version: %s', 'paypal-for-woocommerce'), VERSION_PFW));
            WC_Gateway_PayPal_Express_AngellEYE::log(sprintf(__('WooCommerce Version: %s', 'paypal-for-woocommerce'), WC_VERSION));
            WC_Gateway_PayPal_Express_AngellEYE::log('Test Mode: ' . $this->gateway->testmode);
            WC_Gateway_PayPal_Express_AngellEYE::log('Endpoint: ' . $this->gateway->API_Endpoint);
        }
        $PayPalRequest = isset($this->paypal_response['RAWREQUEST']) ? $this->paypal_response['RAWREQUEST'] : '';
        $PayPalResponse = isset($this->paypal_response['RAWRESPONSE']) ? $this->paypal_response['RAWRESPONSE'] : '';
        WC_Gateway_PayPal_Express_AngellEYE::log($paypal_action_name . ' Request: ' . print_r($this->paypal->NVPToArray($this->paypal->MaskAPIResult($PayPalRequest)), true));
        WC_Gateway_PayPal_Express_AngellEYE::log($paypal_action_name . ' Response: ' . print_r($this->paypal->NVPToArray($this->paypal->MaskAPIResult($PayPalResponse)), true));
    }

    public function angelleye_ec_load_customer_data_using_ec_details() {
        if (!empty($this->paypal_response['SHIPTOCOUNTRYCODE'])) {
            if (!array_key_exists($this->paypal_response['SHIPTOCOUNTRYCODE'], WC()->countries->get_allowed_countries())) {
                wc_add_notice(sprintf(__('We do not sell in your country, please try again with another address.', 'paypal-for-woocommerce')), 'error');
                wp_redirect(get_permalink(wc_get_page_id('cart')));
                exit;
            }
        }
        if (isset($this->paypal_response['FIRSTNAME'])) {
            WC()->customer->firstname = $this->paypal_response['FIRSTNAME'];
        }
        if (isset($this->paypal_response['LASTNAME'])) {
            WC()->customer->lastname = $this->paypal_response['LASTNAME'];
        }
        if (isset($this->paypal_response['SHIPTONAME'])) {
            WC()->customer->shiptoname = $this->paypal_response['SHIPTONAME'];
        }
        if (isset($this->paypal_response['SHIPTOSTREET'])) {
            WC()->customer->set_shipping_address($this->paypal_response['SHIPTOSTREET']);
        }
        if (isset($this->paypal_response['SHIPTOSTREET2'])) {
            WC()->customer->set_shipping_address_2($this->paypal_response['SHIPTOSTREET2']);
        }
        if (isset($this->paypal_response['SHIPTOCITY'])) {
            WC()->customer->set_shipping_city($this->paypal_response['SHIPTOCITY']);
        }
        if (isset($this->paypal_response['SHIPTOCOUNTRYCODE'])) {
            WC()->customer->set_shipping_country($this->paypal_response['SHIPTOCOUNTRYCODE']);
        }
        if (isset($this->paypal_response['SHIPTOSTATE'])) {
            WC()->customer->set_shipping_state($this->get_state_code($this->paypal_response['SHIPTOCOUNTRYCODE'], $this->paypal_response['SHIPTOSTATE']));
        }
        if (isset($this->paypal_response['SHIPTOZIP'])) {
            WC()->customer->set_shipping_postcode($this->paypal_response['SHIPTOZIP']);
        }

        if ($this->billing_address == 'yes') {
            if (isset($this->paypal_response['SHIPTOSTREET'])) {
                WC()->customer->set_address($this->paypal_response['SHIPTOSTREET']);
            }
            if (isset($this->paypal_response['SHIPTOSTREET2'])) {
                WC()->customer->set_address_2($this->paypal_response['SHIPTOSTREET2']);
            }
            if (isset($this->paypal_response['SHIPTOCITY'])) {
                WC()->customer->set_city($this->paypal_response['SHIPTOCITY']);
            }
            if (isset($this->paypal_response['SHIPTOCOUNTRYCODE'])) {
                WC()->customer->set_shipping_country($this->paypal_response['SHIPTOCOUNTRYCODE']);
            }
            if (isset($this->paypal_response['SHIPTOSTATE'])) {
                WC()->customer->set_shipping_state($this->get_state_code($this->paypal_response['SHIPTOCOUNTRYCODE'], $this->paypal_response['SHIPTOSTATE']));
            }
            if (isset($this->paypal_response['SHIPTOZIP'])) {
                WC()->customer->set_shipping_postcode($this->paypal_response['SHIPTOZIP']);
            }
        }
        WC()->customer->calculated_shipping(true);
    }

    public function get_state_code($country, $state) {
        // If not US address, then convert state to abbreviation
        if ($country != 'US') {
            if (isset(WC()->countries->states[WC()->customer->get_country()]) && !empty(WC()->countries->states[WC()->customer->get_country()])) {
                $local_states = WC()->countries->states[WC()->customer->get_country()];
                if (!empty($local_states) && in_array($state, $local_states)) {
                    foreach ($local_states as $key => $val) {
                        if ($val == $state) {
                            $state = $key;
                        }
                    }
                }
            }
        }
        return $state;
    }

    public function angelleye_ec_save_billing_agreement($order_id) {
        if (empty($this->paypal_response)) {
            return false;
        }
        $order = wc_get_order($order_id);
        //update_post_meta($order_id, '_express_checkout_token', $this->get_session('TOKEN'));
        update_post_meta($order_id, '_first_transaction_id', $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']);
        do_action('before_save_payment_token', $order_id);
        if (isset($this->paypal_response['BILLINGAGREEMENTID']) && !empty($this->paypal_response['BILLINGAGREEMENTID']) && is_user_logged_in()) {
            update_post_meta($order_id, 'billing_agreement_id', $this->paypal_response['BILLINGAGREEMENTID']);
            $customer_id = $order->get_user_id();
            $billing_agreement_id = $this->paypal_response['BILLINGAGREEMENTID'];
            $token = new WC_Payment_Token_CC();
            $token->set_user_id($customer_id);
            $token->set_token($billing_agreement_id);
            $token->set_gateway_id($this->gateway->id);
            $token->set_card_type('PayPal Billing Agreement');
            $token->set_last4(substr($billing_agreement_id, -4));
            $token->set_expiry_month(date('m'));
            $token->set_expiry_year(date('Y', strtotime('+20 years')));
            $save_result = $token->save();
            if ($save_result) {
                $order->add_payment_token($token);
            }
        }
        if (!empty($this->paypal_response['BILLINGAGREEMENTID'])) {
            update_post_meta($order_id, '_billing_agreement_id', $this->paypal_response['BILLINGAGREEMENTID']);
            update_post_meta($order_id, 'BILLINGAGREEMENTID', $this->paypal_response['BILLINGAGREEMENTID']);
        }
    }

    public function angelleye_ec_get_customer_email_address($order_id) {
        $this->user_email_address = '';
        if (is_user_logged_in()) {
            $userLogined = wp_get_current_user();
            $this->user_email_address = $userLogined->user_email;
            update_post_meta($order_id, '_billing_email', $userLogined->user_email);
            update_post_meta($order_id, '_customer_user', $userLogined->ID);
        } else {
            $_billing_email = get_post_meta($order_id, '_billing_email', true);
            if (!empty($_billing_email)) {
                $this->user_email_address = $_billing_email;
            } else {
                $this->user_email_address = WC()->session->payeremail;
                update_post_meta($order_id, '_billing_email', WC()->session->payeremail);
            }
        }
    }

    public function angelleye_ec_sellerprotection_handler($order_id) {
        $order = wc_get_order($order_id);
        if (AngellEYE_Gateway_Paypal::angelleye_woocommerce_sellerprotection_should_cancel_order($this, $this->paypal_response)) {
            $this->add_log('Order ' . $order_id . ' (' . $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID'] . ') did not meet our Seller Protection requirements. Cancelling and refunding order.');
            $order->add_order_note(__('Transaction did not meet our Seller Protection requirements. Cancelling and refunding order.', 'paypal-for-woocommerce'));
            $admin_email = get_option("admin_email");
            if ($this->email_notify_order_cancellations == true) {
                if (isset($this->user_email_address) && !empty($this->user_email_address)) {
                    $mailer = WC()->mailer();
                    $subject = __('PayPal Express Checkout payment declined due to our Seller Protection Settings', 'paypal-for-woocommerce');
                    $message = $mailer->wrap_message($subject, __('Order #', 'paypal-for-woocommerce') . $order_id);
                    $mailer->send($this->user_email_address, strip_tags($subject), $message);
                    $mailer->send($admin_email, strip_tags($subject), $message);
                }
            }
            update_post_meta($order_id, '_transaction_id', $this->paypal_response['PAYMENTINFO_0_TRANSACTIONID']);
            $this->process_refund($order_id, $order->order_total, __('There was a problem processing your order. Please contact customer support.', 'paypal-for-woocommerce'));
            $order->cancel_order();
            wc_add_notice(__('Thank you for your recent order. Unfortunately it has been cancelled and refunded. Please contact our customer support team.', 'paypal-for-woocommerce'), 'error');
            wp_redirect(get_permalink(wc_get_page_id('cart')));
            exit();
        }
    }

    public function DoReferenceTransaction($order_id) {
        $PayPalRequestData = array();
        $token_id = $_POST['wc-paypal_express-payment-token'];
        $token = WC_Payment_Tokens::get($token_id);
        if ($token->get_user_id() !== get_current_user_id()) {
            return;
        }
        $order = wc_get_order($order_id);
        if (sizeof(WC()->cart->get_cart()) == 0) {
            $ms = sprintf(__('Sorry, your session has expired. <a href=%s>Return to homepage &rarr;</a>', 'paypal-for-woocommerce'), '"' . home_url() . '"');
            $ec_confirm_message = apply_filters('angelleye_ec_confirm_message', $ms);
            wc_add_notice($ec_confirm_message, "error");
            wp_redirect(get_permalink(wc_get_page_id('cart')));
        }

        if (!empty($order_id)) {
            $order = new WC_Order($order_id);
            $invoice_number = preg_replace("/[^a-zA-Z0-9]/", "", $order->get_order_number());
            if ($order->customer_note) {
                $customer_notes = wptexturize($order->customer_note);
            } else {
                $customer_notes = '';
            }
        } else {
            $invoice_number = $order->id;
        }
        $DRTFields = array(
            'referenceid' => $token->get_token(),
            'paymentaction' => !empty($this->gateway->payment_action) ? $this->gateway->payment_action : 'Sale',
            'returnfmfdetails' => '1',
            'softdescriptor' => ''
        );
        $PayPalRequestData['DRTFields'] = $DRTFields;
        $PaymentDetails = array(
            'amt' => AngellEYE_Gateway_Paypal::number_format($order->order_total), // Required. Total amount of the order, including shipping, handling, and tax.
            'currencycode' => $order->get_order_currency(), // A three-character currency code.  Default is USD.
            'itemamt' => '', // Required if you specify itemized L_AMT fields. Sum of cost of all items in this order.
            'shippingamt' => '', // Total shipping costs for this order.  If you specify SHIPPINGAMT you mut also specify a value for ITEMAMT.
            'insuranceamt' => '',
            'shippingdiscount' => '',
            'handlingamt' => '', // Total handling costs for this order.  If you specify HANDLINGAMT you mut also specify a value for ITEMAMT.
            'taxamt' => '', // Required if you specify itemized L_TAXAMT fields.  Sum of all tax items in this order.
            'insuranceoptionoffered' => '', // If true, the insurance drop-down on the PayPal review page displays Yes and shows the amount.
            'desc' => '', // Description of items on the order.  127 char max.
            'custom' => '', // Free-form field for your own use.  256 char max.
            'invnum' => $this->gateway->invoice_id_prefix . $invoice_number, // Your own invoice or tracking number.  127 char max.
            'buttonsource' => ''     // URL for receiving Instant Payment Notifications
        );
        if (isset($this->gateway->notifyurl) && !empty($this->gateway->notifyurl)) {
            $PaymentDetails['notifyurl'] = $this->gateway->notifyurl;
        }
        if (WC()->cart->needs_shipping()) {
            $shipping_first_name = $order->shipping_first_name;
            $shipping_last_name = $order->shipping_last_name;
            $shipping_address_1 = $order->shipping_address_1;
            $shipping_address_2 = $order->shipping_address_2;
            $shipping_city = $order->shipping_city;
            $shipping_state = $order->shipping_state;
            $shipping_postcode = $order->shipping_postcode;
            $shipping_country = $order->shipping_country;
            $ShippingAddress = array('shiptoname' => $shipping_first_name . ' ' . $shipping_last_name, // Required if shipping is included.  Person's name associated with this address.  32 char max.
                'shiptostreet' => $shipping_address_1, // Required if shipping is included.  First street address.  100 char max.
                'shiptostreet2' => $shipping_address_2, // Second street address.  100 char max.
                'shiptocity' => wc_clean(stripslashes($shipping_city)), // Required if shipping is included.  Name of city.  40 char max.
                'shiptostate' => $shipping_state, // Required if shipping is included.  Name of state or province.  40 char max.
                'shiptozip' => $shipping_postcode, // Required if shipping is included.  Postal code of shipping address.  20 char max.
                'shiptocountrycode' => $shipping_country, // Required if shipping is included.  Country code of shipping address.  2 char max.
                'shiptophonenum' => '', // Phone number for shipping address.  20 char max.
            );
            $PayPalRequestData['ShippingAddress'] = $ShippingAddress;
        }
        $this->order_param = $this->gateway_calculation->order_calculation($order_id);
        if ($this->gateway->send_items) {
            $Payment['order_items'] = $this->order_param['order_items'];
        } else {
            $Payment['order_items'] = array();
        }
        $PaymentDetails['taxamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['taxamt']);
        $PaymentDetails['shippingamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['shippingamt']);
        $PaymentDetails['itemamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['itemamt']);
        $PayPalRequestData['PaymentDetails'] = $PaymentDetails;
        $this->paypal_response = $this->paypal->DoReferenceTransaction($PayPalRequestData);
        AngellEYE_Gateway_Paypal::angelleye_paypal_for_woocommerce_curl_error_handler($this->paypal_response, $methos_name = 'DoExpressCheckoutPayment', $gateway = 'PayPal Express Checkout', $this->gateway->error_email_notify);
        WC_Gateway_PayPal_Express_AngellEYE::log('Test Mode: ' . $this->gateway->testmode);
        WC_Gateway_PayPal_Express_AngellEYE::log('Endpoint: ' . $this->gateway->API_Endpoint);
        $PayPalRequest = isset($this->paypal_response['RAWREQUEST']) ? $this->paypal_response['RAWREQUEST'] : '';
        $PayPalResponse = isset($this->paypal_response['RAWRESPONSE']) ? $this->paypal_response['RAWRESPONSE'] : '';
        WC_Gateway_PayPal_Express_AngellEYE::log('Request: ' . print_r($this->paypal->NVPToArray($this->paypal->MaskAPIResult($PayPalRequest)), true));
        WC_Gateway_PayPal_Express_AngellEYE::log('Response: ' . print_r($this->paypal->NVPToArray($this->paypal->MaskAPIResult($PayPalResponse)), true));
        return $this->paypal_response;
    }

    public function angelleye_ec_is_skip_final_review() {
        $this->enable_guest_checkout = get_option('woocommerce_enable_guest_checkout') == 'yes' ? true : false;
        $this->must_create_account = $this->enable_guest_checkout || is_user_logged_in() ? false : true;
        $skip_final_review = true;
        if ($this->skip_final_review == 'no') {
            return $skip_final_review = false;
        }
        if ($this->must_create_account) {
            return $skip_final_review = false;
        }
        if (wc_get_page_id('terms') > 0 && apply_filters('woocommerce_checkout_show_terms', true)) {
            if (!$this->disable_term) {
                return $skip_final_review = false;
            }
        }
        return apply_filters('angelleye_ec_skip_final_review', $skip_final_review);
    }

}
