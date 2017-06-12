<?php

/**
 * @class       AngellEYE_Utility
 * @version	1.1.9.2
 * @package	paypal-for-woocommerce
 * @category	Class
 * @author      Angell EYE <service@angelleye.com>
 */
class AngellEYE_Utility {

    public $plugin_name;
    public $version;
    public $paypal;
    public $testmode;
    public $api_username;
    public $api_password;
    public $api_signature;
    public $ec_debug;
    public $payment_method;
    public $error_email_notify;
    public $angelleye_woocommerce_order_actions;
    public $total_Order;
    public $total_DoVoid;
    public $total_DoCapture;
    public $total_Pending_DoAuthorization;
    public $total_Completed_DoAuthorization;
    public $total_DoReauthorization;
    public $max_authorize_amount;
    public $remain_authorize_amount;
    public $payflow_transstate;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->load_dependencies();
        $this->payflow_transstate = array('0' => 'Account Verification', '1' => 'General error state', '3' => 'Authorization approved', '4' => 'Partial capture',
            '6' => 'Settlement pending', '7' => 'Settlement in progress', '8' => 'Settled successfully', '9' => 'Authorization captured', '10' => 'Capture failed',
            '11' => 'Failed to settle', '12' => 'Unsettled transaction because of incorrect account information', '14' => 'For various reasons, the batch containing this transaction failed settlement',
            '15' => 'Settlement incomplete due to a charge back', '16' => 'Merchant ACH settlement failed; (need to manually collect it)', '106' => 'Unknown Status Transaction - Transactions not settled',
            '206' => 'Transactions on hold pending customer intervention'
        );
    }

    public function add_ec_angelleye_paypal_php_library() {
        if (!class_exists('WC_Payment_Gateway')) {
            return false;
        }
        if (!class_exists('Angelleye_PayPal')) {
            require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/lib/angelleye/paypal-php-library/includes/paypal.class.php' );
        }


        if (empty($this->payment_method) || $this->payment_method == false) {
            $this->angelleye_set_payment_method();
        }
        if (empty($this->payment_method) || $this->payment_method == false) {
            return false;
        }

        if ($this->payment_method == 'paypal_express' || $this->payment_method == 'paypal_pro') {
            if ($this->payment_method == 'paypal_express') {
                $gateway_obj = new WC_Gateway_PayPal_Express_AngellEYE();
                $this->testmode = 'yes' === $gateway_obj->get_option('testmode', 'yes');
            } else if ($this->payment_method == 'paypal_pro') {
                $gateway_obj = new WC_Gateway_PayPal_Pro_AngellEYE();
                $this->testmode = 'yes' === $gateway_obj->get_option('testmode', 'no');
            } else {
                return false;
            }
            if ($this->testmode == false) {
                $this->testmode = AngellEYE_Utility::angelleye_paypal_for_woocommerce_is_set_sandbox_product();
            }
            if ($this->testmode == true) {
                $this->api_username = $gateway_obj->get_option('sandbox_api_username');
                $this->api_password = $gateway_obj->get_option('sandbox_api_password');
                $this->api_signature = $gateway_obj->get_option('sandbox_api_signature');
            } else {
                $this->api_username = $gateway_obj->get_option('api_username');
                $this->api_password = $gateway_obj->get_option('api_password');
                $this->api_signature = $gateway_obj->get_option('api_signature');
            }
            $this->error_email_notify = $gateway_obj->get_option('error_email_notify');
            $this->ec_debug = $gateway_obj->get_option('debug');
            $PayPalConfig = array(
                'Sandbox' => $this->testmode,
                'APIUsername' => $this->api_username,
                'APIPassword' => $this->api_password,
                'APISignature' => $this->api_signature
            );
            $this->paypal = new Angelleye_PayPal($PayPalConfig);
        } elseif ($this->payment_method == 'paypal_pro_payflow') {
            $gateway_obj = new WC_Gateway_PayPal_Pro_PayFlow_AngellEYE();
            $this->ec_debug = $gateway_obj->get_option('debug');
            $this->Force_tls_one_point_two = get_option('Force_tls_one_point_two', 'no');
            $this->testmode = 'yes' === $gateway_obj->get_option('testmode', 'no');
            $this->paypal_partner = $gateway_obj->get_option('paypal_partner', 'PayPal');
            $this->paypal_vendor = $gateway_obj->get_option('paypal_vendor');
            $this->paypal_user = $gateway_obj->get_option('paypal_user', $this->paypal_vendor);
            $this->paypal_password = $gateway_obj->get_option('paypal_password');
            if ($this->testmode == true) {
                $this->paypal_vendor = $gateway_obj->get_option('sandbox_paypal_vendor');
                $this->paypal_partner = $gateway_obj->get_option('sandbox_paypal_partner', 'PayPal');
                $this->paypal_password = $gateway_obj->get_option('sandbox_paypal_password');
                $this->paypal_user = $gateway_obj->get_option('sandbox_paypal_user', $this->paypal_vendor);
            }
            if (!class_exists('Angelleye_PayPal_PayFlow')) {
                require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/lib/angelleye/paypal-php-library/includes/paypal.payflow.class.php' );
            }
            $PayPalConfig = array(
                'Sandbox' => $this->testmode,
                'APIUsername' => $this->paypal_user,
                'APIPassword' => trim($this->paypal_password),
                'APIVendor' => $this->paypal_vendor,
                'APIPartner' => $this->paypal_partner,
                'Force_tls_one_point_two' => $this->Force_tls_one_point_two
            );
            $this->paypal = new Angelleye_PayPal_PayFlow($PayPalConfig);
        }
    }

    public function load_dependencies() {
        add_action('init', array($this, 'paypal_for_woocommerce_paypal_transaction_history'), 5);
        if (is_admin() && !defined('DOING_AJAX')) {
            add_action('add_meta_boxes', array($this, 'angelleye_paypal_for_woocommerce_order_action_meta_box'), 10, 2);
            $hook_name = '';
            $payment_action_with_gateway = array('paypal_express' => array('DoAuthorization', 'DoCapture', 'DoVoid', 'DoReauthorization'), 'paypal_pro_payflow' => array('DoAuthorization', 'DoCapture', 'DoVoid'), 'paypal_pro' => array('DoAuthorization', 'DoCapture', 'DoVoid'));
            foreach ($payment_action_with_gateway as $payment_method_name => $payment_action_name) {
                foreach ($payment_action_name as $action_name) {
                    $hook_name = 'wc_' . $payment_method_name . '_' . strtolower($action_name);
                    add_action('woocommerce_order_action_' . $hook_name, array($this, 'angelleye_' . $hook_name));
                }
            }
            add_filter('woocommerce_payment_gateway_supports', array($this, 'angelleye_woocommerce_payment_gateway_supports'), 10, 3);
        }

        add_action('woocommerce_process_shop_order_meta', array($this, 'save'), 50, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'angelleye_paypal_for_woocommerce_billing_agreement_details'), 10, 1);
    }

    public function angelleye_woocommerce_order_actions($order_actions = array()) {
        global $post;
        $order_id = $post->ID;
        if (empty($post->ID)) {
            return false;
        }
        if ($post->post_type != 'shop_order') {
            return false;
        }
        if (!is_object($order_id)) {
            $order = wc_get_order($order_id);
        }
        $paypal_payment_action = array();
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $this->payment_method = $old_wc ? get_post_meta($order_id, '_payment_method', true) : get_post_meta($order->get_id(), '_payment_method', true);
        $payment_action = $old_wc ? get_post_meta($order_id, '_payment_action', true) : get_post_meta($order->get_id(), '_payment_action', true);
        if ((isset($this->payment_method) && !empty($this->payment_method)) && (isset($payment_action) && !empty($payment_action)) && !$this->has_authorization_expired($post->ID)) {
            switch ($this->payment_method) {
                case 'paypal_express': {
                        $paypal_payment_action = array();
                        $this->total_Order = self::get_total('Order', 'Pending', $order_id);
                        $this->total_DoVoid = self::get_total('DoVoid', '', $order_id);
                        $this->total_DoCapture = self::get_total('DoCapture', 'Completed', $order_id);
                        $this->total_Pending_DoAuthorization = self::get_total('DoAuthorization', 'Pending', $order_id);
                        $this->total_Completed_DoAuthorization = self::get_total('DoAuthorization', 'Completed', $order_id);
                        $this->total_DoReauthorization = self::get_total('DoReauthorization', '', $order_id);
                        switch ($payment_action) {
                            case ($payment_action == 'Order'):
                                $this->angelleye_max_authorize_amount($order_id);
                                $this->angelleye_remain_authorize_amount();
                                if ($this->max_authorize_amount == $this->total_DoVoid || $this->max_authorize_amount == $this->total_DoCapture) {
                                    return $paypal_payment_action;
                                } else {
                                    $paypal_payment_action = array('DoCapture' => 'Capture Authorization', 'DoVoid' => 'Void Authorization', 'DoAuthorization' => 'Authorization');
                                    if ($this->total_Completed_DoAuthorization == $this->total_Pending_DoAuthorization || $this->total_Pending_DoAuthorization == 0 || $this->total_Pending_DoAuthorization == $this->total_DoCapture || $this->total_DoCapture == $order->get_total() - $order->get_total_refunded()) {
                                        unset($paypal_payment_action['DoCapture']);
                                    }
                                    if ($this->total_Pending_DoAuthorization == 0 && $this->total_Completed_DoAuthorization > 0 || $this->total_Pending_DoAuthorization == $this->total_DoCapture) {
                                        unset($paypal_payment_action['DoVoid']);
                                    }
                                    if ($this->max_authorize_amount == self::round($this->total_Pending_DoAuthorization + $this->total_Completed_DoAuthorization)) {
                                        unset($paypal_payment_action['DoAuthorization']);
                                    }
                                    return $paypal_payment_action;
                                }
                                break;
                            case ($payment_action == 'Authorization'):
                                $paypal_payment_action = array('DoCapture' => 'Capture Authorization', 'DoReauthorization' => 'Authorization', 'DoVoid' => 'Void Authorization');
                                $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
                                if (!$this->has_authorization_inside_honor_period($transaction_id)) {
                                    unset($paypal_payment_action['DoReauthorization']);
                                }
                                if (!is_object($order_id)) {
                                    $order = wc_get_order($order_id);
                                }
                                if ($this->total_DoCapture > 0) {
                                    unset($paypal_payment_action['DoVoid']);
                                }
                                if ($order->get_total() == $this->total_DoVoid || $this->total_Completed_DoAuthorization == $order->get_total() || $order->get_total() == $this->total_DoCapture || $this->total_DoCapture == $order->get_total() - $order->get_total_refunded()) {
                                    unset($paypal_payment_action['DoCapture']);
                                   // unset($paypal_payment_action['DoVoid']);
                                }
                                return $paypal_payment_action;
                        }
                    }
                case 'paypal_pro': {
                        $paypal_payment_action = array();
                        $this->total_Order = self::get_total('Order', 'Pending', $order_id);
                        $this->total_DoVoid = self::get_total('DoVoid', '', $order_id);
                        $this->total_DoCapture = self::get_total('DoCapture', 'Completed', $order_id);
                        $this->total_Pending_DoAuthorization = self::get_total('DoAuthorization', 'Pending', $order_id);
                        $this->total_Completed_DoAuthorization = self::get_total('DoAuthorization', 'Completed', $order_id);
                        $this->total_DoReauthorization = self::get_total('DoReauthorization', '', $order_id);
                        switch ($payment_action) {
                            case ($payment_action == 'Authorization'):
                                $this->angelleye_max_authorize_amount($order_id);
                                $this->angelleye_remain_authorize_amount();
                                if ($this->max_authorize_amount == $this->total_DoVoid || $this->max_authorize_amount == $this->total_DoCapture) {
                                    return $paypal_payment_action;
                                } else {
                                    $paypal_payment_action = array('DoCapture' => 'Capture Authorization', 'DoVoid' => 'Void Authorization');
                                    if ($this->total_Completed_DoAuthorization == $this->total_Pending_DoAuthorization || $this->total_Pending_DoAuthorization == 0 || $this->total_Pending_DoAuthorization == $this->total_DoCapture || $this->total_DoCapture == $order->get_total() - $order->get_total_refunded()) {
                                        
                                    }
                                    if ($this->total_DoCapture == ($order->get_total() - $order->get_total_refunded() - $this->total_DoVoid)) {
                                        unset($paypal_payment_action['DoCapture']);
                                    }
                                    if ($this->total_DoCapture > 0 || $this->total_DoVoid > 0) {
                                        unset($paypal_payment_action['DoVoid']);
                                    }
                                    
                                    if ($this->total_Order == self::round($this->total_Pending_DoAuthorization + $this->total_Completed_DoAuthorization)) {
                                        unset($paypal_payment_action['DoAuthorization']);
                                    }
                                    return $paypal_payment_action;
                                }
                                break;
                        }
                    }
                case 'paypal_pro_payflow': {
                        $paypal_payment_action = array();
                        $this->total_Order = self::get_total('Order', 'Pending', $order_id);
                        $this->total_DoVoid = self::get_total('DoVoid', '', $order_id);
                        $this->total_DoCapture = self::get_total('DoCapture', 'Completed', $order_id);
                        $this->total_Pending_DoAuthorization = self::get_total('DoAuthorization', 'Pending', $order_id);
                        $this->total_Completed_DoAuthorization = self::get_total('DoAuthorization', 'Completed', $order_id);
                        $this->total_DoReauthorization = self::get_total('DoReauthorization', '', $order_id);
                        switch ($payment_action) {
                            case ($payment_action == 'Authorization'):
                                $this->angelleye_max_authorize_amount($order_id);
                                $this->angelleye_remain_authorize_amount();
                                if ($this->max_authorize_amount == $this->total_DoVoid || $this->max_authorize_amount == $this->total_DoCapture) {
                                    return $paypal_payment_action;
                                } else {
                                    $paypal_payment_action = array('DoCapture' => 'Capture Authorization', 'DoVoid' => 'Void Authorization');
                                    if ($this->total_Completed_DoAuthorization == $this->total_Pending_DoAuthorization || $this->total_Pending_DoAuthorization == 0 || $this->total_Pending_DoAuthorization == $this->total_DoCapture || $this->total_DoCapture == $order->get_total() - $order->get_total_refunded()) {
                                        
                                    }
                                    if ($this->total_DoCapture == ($order->get_total() - $order->get_total_refunded() - $this->total_DoVoid)) {
                                        unset($paypal_payment_action['DoCapture']);
                                    }
                                    if ($this->total_DoCapture > 0 || $this->total_DoVoid > 0) {
                                        unset($paypal_payment_action['DoVoid']);
                                    }
                                    
                                    if ($this->total_Order == self::round($this->total_Pending_DoAuthorization + $this->total_Completed_DoAuthorization)) {
                                        unset($paypal_payment_action['DoAuthorization']);
                                    }
                                    return $paypal_payment_action;
                                }
                                break;
                        }
                    }
            }
        }
        if (isset($paypal_payment_action) && !empty($paypal_payment_action)) {
            foreach ($paypal_payment_action as $key => $value) {
                $order_actions['wc_' . $this->payment_method . '_' . strtolower($value)] = _x($value, $value, $this->plugin_name);
            }
        }
        return $order_actions;
    }

    /**
     * $_transaction_id, $payment_action, $gateway_name
     * @param type $order_id
     */
    public static function angelleye_add_order_meta($order_id, $payment_order_meta) {
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order = wc_get_order($order_id);
        foreach ($payment_order_meta as $key => $value) {
            if ($old_wc) {
                update_post_meta($order_id, $key, $value);
            } else {
                update_post_meta($order->get_id(), $key, $value);
            }
        }
        if ($old_wc) {
            update_post_meta($order_id, '_trans_date', current_time('mysql'));
        } else {
            update_post_meta($order->get_id(), '_trans_date', current_time('mysql'));
        }
    }

    /**
     *
     * @param type $order_id
     * @return type
     */
    public function has_authorization_expired($order_id) {
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order = wc_get_order($order_id);
        $transaction_time = $old_wc ? get_post_meta($order_id, '_trans_date', true) : get_post_meta($order->get_id(), '_trans_date', true);
        return floor(( time() - strtotime($transaction_time) ) / 3600) > 720;
    }

    public function has_authorization_inside_honor_period($transaction_id) {
        $transaction_post_is = $this->get_post_by_title($transaction_id);
        $transaction_time = get_post_meta($transaction_post_is, '_trans_date', true);
        return floor(( time() - strtotime($transaction_time) ) / 3600) > 72;
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_express_docapture($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        // ensure the authorization is still valid for capture
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if ($this->has_authorization_expired($order_id)) {
            return;
        }
        if (isset($_POST['angelleye_paypal_capture_transaction_dropdown']) && !empty($_POST['angelleye_paypal_capture_transaction_dropdown'])) {
            $transaction_id = $_POST['angelleye_paypal_capture_transaction_dropdown'];
        } else {
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        }
        remove_action('woocommerce_order_action_wc_paypal_express_docapture', array($this, 'angelleye_wc_paypal_express_docapture'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->pfw_do_capture($order, $transaction_id, null);
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_pro_docapture($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        // ensure the authorization is still valid for capture
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if ($this->has_authorization_expired($order_id)) {
            return;
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        remove_action('woocommerce_order_action_wc_paypal_pro_docapture', array($this, 'angelleye_wc_paypal_pro_docapture'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->pfw_do_capture($order, $transaction_id, null);
    }

    public function pfw_do_capture($order, $transaction_id = null, $capture_total = null) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('DoCapture API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if( !empty($_POST['_regular_price'])) {
            $AMT = self::number_format($_POST['_regular_price']);
        } elseif ($capture_total == null) {
            $AMT = $this->get_amount_by_transaction_id($transaction_id);
        } else {
            $AMT = $capture_total;
        }
        $DataArray = array(
            'AUTHORIZATIONID' => $transaction_id,
            'AMT' => $AMT,
            'CURRENCYCODE' => version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency(),
            'COMPLETETYPE' => 'NotComplete',
        );
        $PayPalRequest = array(
            'DCFields' => $DataArray
        );
        $do_capture_result = $this->paypal->DoCapture($PayPalRequest);
        $this->angelleye_write_request_response_api_log($do_capture_result);
        $ack = strtoupper($do_capture_result["ACK"]);
        if ($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
            $order->add_order_note(__('PayPal DoCapture', 'paypal-for-woocommerce') .
                    ' ( Response Code: ' . $do_capture_result["ACK"] . ", " .
                    ' DoCapture TransactionID: ' . $do_capture_result['TRANSACTIONID'] . ' )' .
                    ' Authorization ID: ' . $do_capture_result['AUTHORIZATIONID'] . ' )'
            );
            $order->add_order_note('Payment Action: DoCapture');
            $payerstatus_note = __('Payment Status: ', 'paypal-for-woocommerce');
            $payerstatus_note .= ucfirst($do_capture_result['PAYMENTSTATUS']);
            $order->add_order_note($payerstatus_note);
            if ($do_capture_result['PAYMENTSTATUS'] == 'Completed') {
                $AUTHORIZATIONID = $this->get_post_by_title($transaction_id);
                if ($AUTHORIZATIONID != null) {
                    update_post_meta($AUTHORIZATIONID, 'PAYMENTSTATUS', $do_capture_result['PAYMENTSTATUS']);
                }
            }
            $payment_order_meta = array('_transaction_id' => $do_capture_result['TRANSACTIONID']);
            self::angelleye_add_order_meta($order_id, $payment_order_meta);
            self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_capture_result, $order, 'DoCapture');
            $this->angelleye_paypal_for_woocommerce_order_status_handler($order);
        } else {
            $ErrorCode = urldecode($do_capture_result["L_ERRORCODE0"]);
            $ErrorShortMsg = urldecode($do_capture_result["L_SHORTMESSAGE0"]);
            $ErrorLongMsg = urldecode($do_capture_result["L_LONGMESSAGE0"]);
            $ErrorSeverityCode = urldecode($do_capture_result["L_SEVERITYCODE0"]);
            $this->ec_add_log(__('PayPal DoCapture API call failed. ', 'paypal-for-woocommerce'));
            $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
            $this->ec_add_log(__('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg);
            $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
            $this->ec_add_log(__('Error Severity Code: ', 'paypal-for-woocommerce') . $ErrorSeverityCode);
            $order->add_order_note(__('PayPal DoCapture API call failed. ', 'paypal-for-woocommerce') .
                    ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                    ' Short Error Message: ' . $ErrorShortMsg . ' )' .
                    ' Error Code: ' . $ErrorCode . ' )' .
                    ' Error Severity Code: ' . $ErrorSeverityCode . ' )'
            );
            $this->call_error_email_notifications($subject = 'DoCapture failed', $method_name = 'DoCapture', $resArray = $do_capture_result);
        }
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_express_dovoid($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        // ensure the authorization is still valid for capture
        if ($this->has_authorization_expired($order_id)) {
            return;
        }
        remove_action('woocommerce_order_action_wc_paypal_express_dovoid', array($this, 'angelleye_wc_paypal_express_dovoid'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_do_void($order);
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_pro_dovoid($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        // ensure the authorization is still valid for capture
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if ($this->has_authorization_expired($order_id)) {
            return;
        }
        remove_action('woocommerce_order_action_wc_paypal_express_dovoid', array($this, 'angelleye_wc_paypal_express_dovoid'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_do_void($order);
    }

    public function call_do_void($order) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('DoVoid API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if (isset($_POST['angelleye_paypal_dovoid_transaction_dropdown']) && !empty($_POST['angelleye_paypal_dovoid_transaction_dropdown'])) {
            $transaction_id = $_POST['angelleye_paypal_dovoid_transaction_dropdown'];
        } else {
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        }
        if (isset($transaction_id) && !empty($transaction_id)) {
            $DVFields = array(
                'authorizationid' => $transaction_id,
                'note' => '',
                'msgsubid' => ''
            );
            $PayPalRequestData = array('DVFields' => $DVFields);
            $do_void_result = $this->paypal->DoVoid($PayPalRequestData);
            $this->angelleye_write_request_response_api_log($do_void_result);
            $ack = strtoupper($do_void_result["ACK"]);
            if ($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $order->add_order_note(__('PayPal DoVoid', 'paypal-for-woocommerce') .
                        ' ( Response Code: ' . $do_void_result["ACK"] . ", " .
                        ' DoVoid AUTHORIZATIONID: ' . $do_void_result['AUTHORIZATIONID'] . ' )'
                );
                $this->angelleye_get_transactionDetails($do_void_result['AUTHORIZATIONID']);
                $payment_order_meta = array('_transaction_id' => $do_void_result['AUTHORIZATIONID']);
                self::angelleye_add_order_meta($order_id, $payment_order_meta);
                self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_void_result, $order, 'DoVoid');
                $this->angelleye_paypal_for_woocommerce_order_status_handler($order);
            } else {
                $ErrorCode = urldecode($do_void_result["L_ERRORCODE0"]);
                $ErrorShortMsg = urldecode($do_void_result["L_SHORTMESSAGE0"]);
                $ErrorLongMsg = urldecode($do_void_result["L_LONGMESSAGE0"]);
                $ErrorSeverityCode = urldecode($do_void_result["L_SEVERITYCODE0"]);
                $this->ec_add_log(__('PayPal DoVoid API call failed. ', 'paypal-for-woocommerce'));
                $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
                $this->ec_add_log(__('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg);
                $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
                $this->ec_add_log(__('Error Severity Code: ', 'paypal-for-woocommerce') . $ErrorSeverityCode);
                $order->add_order_note(__('PayPal DoVoid API call failed. ', 'paypal-for-woocommerce') .
                        ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                        ' Short Error Message: ' . $ErrorShortMsg . ' )' .
                        ' Error Code: ' . $ErrorCode . ' )' .
                        ' Error Severity Code: ' . $ErrorSeverityCode . ' )'
                );
                $this->call_error_email_notifications($subject = 'DoVoid failed', $method_name = 'DoVoid', $resArray = $do_void_result);
            }
        }
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_express_doreauthorization($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $this->payment_method = $old_wc ? get_post_meta($order_id, '_payment_method', true) : get_post_meta($order->get_id(), '_payment_method', true);
        remove_action('woocommerce_order_action_wc_paypal_express_doreauthorization', array($this, 'angelleye_wc_paypal_express_doreauthorization'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_do_reauthorization($order);
    }

    public function call_do_reauthorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('DoReauthorization API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if (isset($_POST['angelleye_paypal_doreauthorization_transaction_dropdown']) && !empty($_POST['angelleye_paypal_doreauthorization_transaction_dropdown'])) {
            $transaction_id = $_POST['angelleye_paypal_doreauthorization_transaction_dropdown'];
        } else {
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        }
        $AMT = $this->get_amount_by_transaction_id($transaction_id);
        if (isset($transaction_id) && !empty($transaction_id)) {
            $DRFields = array(
                'authorizationid' => $transaction_id, // Required. The value of a previously authorized transaction ID returned by PayPal.
                'amt' => self::number_format($AMT), // Required. Must have two decimal places.  Decimal separator must be a period (.) and optional thousands separator must be a comma (,)
                'currencycode' => version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency(), // Three-character currency code.
                'msgsubid' => ''      // A message ID used for idempotence to uniquely identify a message.
            );
            $PayPalRequestData = array('DRFields' => $DRFields);
            $do_reauthorization_result = $this->paypal->DoReauthorization($PayPalRequestData);
            $this->angelleye_write_request_response_api_log($do_reauthorization_result);
            $ack = strtoupper($do_reauthorization_result["ACK"]);
            if ($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $order->add_order_note(__('PayPal DoReauthorization', 'paypal-for-woocommerce') .
                        ' ( Response Code: ' . $do_reauthorization_result["ACK"] . ", " .
                        ' DoReauthorization AUTHORIZATIONID: ' . $do_reauthorization_result['AUTHORIZATIONID'] . ' )'
                );
                $payment_order_meta = array('_transaction_id' => $do_reauthorization_result['AUTHORIZATIONID']);
                self::angelleye_add_order_meta($order_id, $payment_order_meta);
                self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_reauthorization_result, $order, 'DoReauthorization');
            } else {
                $ErrorCode = urldecode($do_reauthorization_result["L_ERRORCODE0"]);
                $ErrorShortMsg = urldecode($do_reauthorization_result["L_SHORTMESSAGE0"]);
                $ErrorLongMsg = urldecode($do_reauthorization_result["L_LONGMESSAGE0"]);
                $ErrorSeverityCode = urldecode($do_reauthorization_result["L_SEVERITYCODE0"]);
                $this->ec_add_log(__('PayPal DoReauthorization API call failed. ', 'paypal-for-woocommerce'));
                $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
                $this->ec_add_log(__('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg);
                $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
                $this->ec_add_log(__('Error Severity Code: ', 'paypal-for-woocommerce') . $ErrorSeverityCode);
                $order->add_order_note(__('PayPal DoReauthorization API call failed. ', $this->plugin_name) .
                        ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                        ' Short Error Message: ' . $ErrorShortMsg . ' )' .
                        ' Error Code: ' . $ErrorCode . ' )' .
                        ' Error Severity Code: ' . $ErrorSeverityCode . ' )'
                );
                $this->call_error_email_notifications($subject = 'DoReauthorization failed', $method_name = 'DoReauthorization', $resArray = $do_reauthorization_result);
            }
        }
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_pro_doreauthorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        remove_action('woocommerce_order_action_wc_paypal_pro_doreauthorization', array($this, 'angelleye_wc_paypal_pro_doreauthorization'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_do_reauthorization($order);
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_express_doauthorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        remove_action('woocommerce_order_action_wc_paypal_express_doauthorization', array($this, 'angelleye_wc_paypal_express_doauthorization'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_do_authorization($order);
    }
    
    public function angelleye_wc_paypal_pro_doauthorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        remove_action('woocommerce_order_action_wc_paypal_pro_doauthorization', array($this, 'angelleye_wc_paypal_pro_doauthorization'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_do_authorization($order);
    }

    public function call_do_authorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('DoAuthorization API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        if (isset($transaction_id) && !empty($transaction_id)) {
            $DRFields = array(
                'TRANSACTIONID' => $transaction_id, // Required. The value of a previously authorized transaction ID returned by PayPal.
                'AMT' => self::number_format($_POST['_regular_price']), // Required. Must have two decimal places.  Decimal separator must be a period (.) and optional thousands separator must be a comma (,)
                'CURRENCYCODE' => version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency()
            );
            $PayPalRequestData = array('DAFields' => $DRFields);
            $do_authorization_result = $this->paypal->DoAuthorization($PayPalRequestData);
            $this->angelleye_write_request_response_api_log($do_authorization_result);
            $ack = strtoupper($do_authorization_result["ACK"]);
            if ($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $order->add_order_note(__('PayPal authorization', 'paypal-for-woocommerce') .
                        ' ( Response Code: ' . $do_authorization_result["ACK"] . ", " .
                        ' DoAuthorization AUTHORIZATIONID: ' . $do_authorization_result['TRANSACTIONID'] . ' )'
                );
                $payment_order_meta = array('_transaction_id' => $do_authorization_result['TRANSACTIONID']);
                self::angelleye_add_order_meta($order_id, $payment_order_meta);
                self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_authorization_result, $order, 'DoAuthorization');
            } else {
                $ErrorCode = urldecode($do_authorization_result["L_ERRORCODE0"]);
                $ErrorShortMsg = urldecode($do_authorization_result["L_SHORTMESSAGE0"]);
                $ErrorLongMsg = urldecode($do_authorization_result["L_LONGMESSAGE0"]);
                $ErrorSeverityCode = urldecode($do_authorization_result["L_SEVERITYCODE0"]);
                $this->ec_add_log(__('PayPal DoAuthorization API call failed. ', 'paypal-for-woocommerce'));
                $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
                $this->ec_add_log(__('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg);
                $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
                $this->ec_add_log(__('Error Severity Code: ', 'paypal-for-woocommerce') . $ErrorSeverityCode);
                $order->add_order_note(__('PayPal DoAuthorization API call failed. ', 'paypal-for-woocommerce') .
                        ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                        ' Short Error Message: ' . $ErrorShortMsg . ' )' .
                        ' Error Code: ' . $ErrorCode . ' )' .
                        ' Error Severity Code: ' . $ErrorSeverityCode . ' )'
                );
                $this->call_error_email_notifications($subject = 'DoAuthorization failed', $method_name = 'DoAuthorization', $resArray = $do_authorization_result);
            }
        }
    }

    public function ec_add_log($message, $level = 'info') {
        if ($this->ec_debug == 'yes') {
            if (empty($this->log)) {
                $this->log = wc_get_logger();
            }
            $this->log->log($level, $message, array('source' => $this->payment_method));
        }
    }

    public function call_error_email_notifications($subject = null, $method_name = null, $resArray = null) {
        if ((isset($resArray["L_ERRORCODE0"]) && !empty($resArray["L_ERRORCODE0"])) && ( isset($resArray["L_SHORTMESSAGE0"]) && !empty($resArray["L_SHORTMESSAGE0"]))) {
            $ErrorCode = urldecode($resArray["L_ERRORCODE0"]);
            $ErrorShortMsg = urldecode($resArray["L_SHORTMESSAGE0"]);
            $ErrorLongMsg = urldecode($resArray["L_LONGMESSAGE0"]);
            $ErrorSeverityCode = urldecode($resArray["L_SEVERITYCODE0"]);
            $this->ec_add_log(__($method_name . ' API call failed. ', 'paypal-for-woocommerce'));
            $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
            $this->ec_add_log(__('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg);
            $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
            $this->ec_add_log(__('Error Severity Code: ', 'paypal-for-woocommerce') . $ErrorSeverityCode);
            $message = '';
            if ($this->error_email_notify) {
                $admin_email = get_option("admin_email");
                $message .= __($method_name, "paypal-for-woocommerce") . "\n\n";
                $message .= __('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode . "\n";
                $message .= __('Error Severity Code: ', 'paypal-for-woocommerce') . $ErrorSeverityCode . "\n";
                $message .= __('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg . "\n";
                $message .= __('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg . "\n";
                $ofw_error_email_notify_mes = apply_filters('angelleye_error_email_notify_message', $message, $ErrorCode, $ErrorSeverityCode, $ErrorShortMsg, $ErrorLongMsg);
                $ofw_error_email_notify_subject = apply_filters('angelleye_error_email_notify_subject', $subject);
                wp_mail($admin_email, $ofw_error_email_notify_subject, $ofw_error_email_notify_mes);
            }
        }
        if ((isset($resArray["Errors"][0]['ErrorID']) && !empty($resArray["Errors"][0]['ErrorID'])) && ( isset($resArray["Errors"][0]['Message']) && !empty($resArray["Errors"][0]['Message']))) {
            $ErrorCode = $resArray["Errors"][0]['ErrorID'];
            $ErrorShortMsg = $resArray["Errors"][0]['Message'];
            $this->ec_add_log(__($method_name . ' API call failed. ', 'paypal-for-woocommerce'));
            $this->ec_add_log(__('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg);
            $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
            $message = '';
            if ($this->error_email_notify) {
                $admin_email = get_option("admin_email");
                $message .= __($method_name, "paypal-for-woocommerce") . "\n\n";
                $message .= __('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode . "\n";
                $message .= __('Short Error Message: ', 'paypal-for-woocommerce') . $ErrorShortMsg . "\n";
                $ofw_error_email_notify_mes = apply_filters('angelleye_error_email_notify_message', $message, $ErrorCode, $ErrorShortMsg);
                $ofw_error_email_notify_subject = apply_filters('angelleye_error_email_notify_subject', $subject);
                wp_mail($admin_email, $ofw_error_email_notify_subject, $ofw_error_email_notify_mes);
            }
        }
    }

    public function angelleye_woocommerce_payment_gateway_supports($boolean, $feature, $current) {
        global $post;
        if (empty($post->ID)) {
            return $boolean;
        }
        if ($post->post_type != 'shop_order') {
            return $boolean;
        }
        $order_id = $post->ID;
        if (!is_object($order_id)) {
            $order = wc_get_order($order_id);
        }
        $payment_action = '';
        if ($current->id == 'paypal_express' || $current->id == 'paypal_pro') {
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $payment_action = $old_wc ? get_post_meta($order_id, '_payment_action', true) : get_post_meta($order->get_id(), '_payment_action', true);
            if ($payment_action == 'Sale' || $payment_action == 'DoCapture' || empty($payment_action)) {
                return $boolean;
            } else {
                return false;
            }
        } else {
            return $boolean;
        }
    }

    public function angelleye_write_request_response_api_log($PayPalResult) {
        if ($this->payment_method != 'paypal_pro_payflow') {
            $PayPalRequest = isset($PayPalResult['RAWREQUEST']) ? $PayPalResult['RAWREQUEST'] : '';
            $PayPalResponse = isset($PayPalResult['RAWRESPONSE']) ? $PayPalResult['RAWRESPONSE'] : '';
            $this->ec_add_log('Request: ' . print_r($this->paypal->NVPToArray($this->paypal->MaskAPIResult($PayPalRequest)), true));
            $this->ec_add_log('Response: ' . print_r($this->paypal->NVPToArray($this->paypal->MaskAPIResult($PayPalResponse)), true));
        } else {
            $this->ec_add_log('LOG: ' . print_r($PayPalResult, true));
        }
    }

    public static function angelleye_paypal_credit_card_rest_setting_fields() {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Credit Card (REST)', 'paypal-for-woocommerce'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'paypal-for-woocommerce'),
                'default' => __('PayPal Credit Card (REST)', 'paypal-for-woocommerce')
            ),
            'description' => array(
                'title' => __('Description', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'paypal-for-woocommerce'),
                'default' => __('PayPal Credit Card (REST) description', 'paypal-for-woocommerce')
            ),
            'testmode' => array(
                'title' => __('Test Mode', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Sandbox/Test Mode', 'paypal-for-woocommerce'),
                'default' => 'yes',
                'description' => sprintf(__('Place the payment gateway in development mode. Sign up for a developer account <a href="%s" target="_blank">here</a>', 'paypal-for-woocommerce'), 'https://developer.paypal.com/'),
            ),
            'rest_client_id_sandbox' => array(
                'title' => __('Sandbox Client ID', 'paypal-for-woocommerce'),
                'type' => 'password',
                'description' => 'Enter your Sandbox PayPal Rest API Client ID',
                'default' => ''
            ),
            'rest_secret_id_sandbox' => array(
                'title' => __('Sandbox Secret ID', 'paypal-for-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your Sandbox PayPal Rest API Secret ID.', 'paypal-for-woocommerce'),
                'default' => ''
            ),
            'rest_client_id' => array(
                'title' => __('Live Client ID', 'paypal-for-woocommerce'),
                'type' => 'password',
                'description' => 'Enter your PayPal Rest API Client ID',
                'default' => ''
            ),
            'rest_secret_id' => array(
                'title' => __('Live Secret ID', 'paypal-for-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your PayPal Rest API Secret ID.', 'paypal-for-woocommerce'),
                'default' => ''
            ),
            'enable_tokenized_payments' => array(
                'title' => __('Enable Tokenized Payments', 'paypal-for-woocommerce'),
                'label' => __('Enable Tokenized Payments', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'description' => __('Allow buyers to securely save payment details to their account for quick checkout / auto-ship orders in the future.', 'paypal-for-woocommerce'),
                'default' => 'no',
                'class' => 'enable_tokenized_payments'
            ),
            'invoice_prefix' => array(
                'title' => __('Invoice Prefix', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'paypal-for-woocommerce'),
                'default' => 'WC-PCCR',
                'desc_tip' => true,
            ),
            'card_icon' => array(
                'title' => __('Card Icon', 'paypal-for-woocommerce'),
                'type' => 'text',
                'default' => plugins_url('/assets/images/cards.png', plugin_basename(dirname(__FILE__))),
                'class' => 'button_upload'
            ),
            'softdescriptor' => array(
                'title' => __('Credit Card Statement Name', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('The value entered here will be displayed on the buyer\'s credit card statement.', 'paypal-for-woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug Log', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'paypal-for-woocommerce'),
                'default' => 'no',
                'description' => sprintf(__('Log PayPal events, such as Secured Token requests, inside <code>%s</code>', 'paypal-for-woocommerce'), wc_get_log_file_path('paypal_credit_card_rest')),
            ),
            'is_encrypt' => array(
                'title' => __('', 'paypal-for-woocommerce'),
                'label' => __('', 'paypal-for-woocommerce'),
                'type' => 'hidden',
                'default' => 'yes',
                'class' => ''
            )
        );
    }

    public static function card_type_from_account_number($account_number) {
        $types = array(
            'visa' => '/^4/',
            'mastercard' => '/^5[1-5]/',
            'amex' => '/^3[47]/',
            'discover' => '/^(6011|65|64[4-9]|622)/',
            'diners' => '/^(36|38|30[0-5])/',
            'jcb' => '/^35/',
            'maestro' => '/^(5018|5020|5038|6304|6759|676[1-3])/',
            'laser' => '/^(6706|6771|6709)/',
        );
        foreach ($types as $type => $pattern) {
            if (1 === preg_match($pattern, $account_number)) {
                return $type;
            }
        }
        return null;
    }

    public static function is_express_checkout_credentials_is_set() {
        $pp_settings = get_option('woocommerce_paypal_express_settings');
        $testmode = isset($pp_settings['testmode']) ? $pp_settings['testmode'] : 'yes';
        $enabled = (isset($pp_settings['enabled']) && $pp_settings['enabled'] == 'yes') ? 'yes' : 'no';
        if ($testmode == 'yes') {
            $api_username = isset($pp_settings['sandbox_api_username']) ? $pp_settings['sandbox_api_username'] : '';
            $api_password = isset($pp_settings['sandbox_api_password']) ? $pp_settings['sandbox_api_password'] : '';
            $api_signature = isset($pp_settings['sandbox_api_signature']) ? $pp_settings['sandbox_api_signature'] : '';
        } else {
            $api_username = isset($pp_settings['api_username']) ? $pp_settings['api_username'] : '';
            $api_password = isset($pp_settings['api_password']) ? $pp_settings['api_password'] : '';
            $api_signature = isset($pp_settings['api_signature']) ? $pp_settings['api_signature'] : '';
        }
        if ('yes' != $enabled) {
            return false;
        }
        if (!$api_username || !$api_password || !$api_signature) {
            return false;
        }
        return true;
    }

    public function paypal_for_woocommerce_paypal_transaction_history() {

        if (post_type_exists('paypal_transaction')) {
            return;
        }

        do_action('paypal_for_woocommerce_register_post_type');

        register_post_type('paypal_transaction', apply_filters('paypal_for_woocommerce_register_post_type_paypal_transaction_history', array(
            'labels' => array(
                'name' => __('PayPal Transaction', 'paypal-for-woocommerce'),
                'singular_name' => __('PayPal Transaction', 'paypal-for-woocommerce'),
                'menu_name' => _x('PayPal Transaction', 'Admin menu name', 'paypal-for-woocommerce'),
                'add_new' => __('Add PayPal Transaction', 'paypal-for-woocommerce'),
                'add_new_item' => __('Add New PayPal Transaction', 'paypal-for-woocommerce'),
                'edit' => __('Edit', 'paypal-for-woocommerce'),
                'edit_item' => __('View PayPal Transaction', 'paypal-for-woocommerce'),
                'new_item' => __('New PayPal Transaction', 'paypal-for-woocommerce'),
                'view' => __('View PayPal Transaction', 'paypal-for-woocommerce'),
                'view_item' => __('View PayPal Transaction', 'paypal-for-woocommerce'),
                'search_items' => __('Search PayPal Transaction', 'paypal-for-woocommerce'),
                'not_found' => __('No PayPal Transaction found', 'paypal-for-woocommerce'),
                'not_found_in_trash' => __('No PayPal Transaction found in trash', 'paypal-for-woocommerce'),
                'parent' => __('Parent PayPal Transaction', 'paypal-for-woocommerce')
            ),
            'description' => __('This is where you can add new PayPal Transaction to your store.', 'paypal-for-woocommerce'),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => false, // Removes support for the "Add New" function
            ),
            'map_meta_cap' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'hierarchical' => false, // Hierarchical causes memory issues - WP loads all records!
            'rewrite' => array('slug' => 'paypal_ipn'),
            'query_var' => true,
            'supports' => array('', ''),
            'has_archive' => true,
            'show_in_nav_menus' => FALSE
                        )
                )
        );
    }

    public function angelleye_paypal_for_woocommerce_order_action_meta_box($post_type, $post) {
        if (isset($post->ID) && !empty($post->ID) && $post_type == 'shop_order') {
            if ($this->angelleye_is_display_paypal_transaction_details($post->ID)) {
                add_meta_box('angelleye-pw-order-action', __('PayPal Transaction History', 'paypal-for-woocommerce'), array($this, 'angelleye_paypal_for_woocommerce_order_action_callback'), 'shop_order', 'normal', 'high', null);
            }
        }
    }

    public function angelleye_paypal_for_woocommerce_order_action_callback($post) {

        $args = array(
            'post_type' => 'paypal_transaction',
            'posts_per_page' => -1,
            'meta_key' => 'order_id',
            'meta_value' => $post->ID,
            'order' => 'ASC',
            'post_status' => 'any'
        );
        $posts_array = get_posts($args);
        foreach ($posts_array as $post_data):
            $payment_status = get_post_meta($post_data->ID, 'PAYMENTSTATUS', true);
            if (isset($post->post_title) && !empty($post_data->post_title) && $payment_status != 'Completed') {
                $this->angelleye_get_transactionDetails($post_data->post_title);
            }
        endforeach;
        $order = wc_get_order($post->ID);

            $payment_method = get_post_meta($post->ID, '_payment_method', true);
            $payment_action = get_post_meta($post->ID, '_payment_action', true);
            
             
            
        if (empty($this->angelleye_woocommerce_order_actions)) {
            $this->angelleye_woocommerce_order_actions = $this->angelleye_woocommerce_order_actions();
        }
        ?>
        <div class='wrap'>
            <?php
            if (isset($this->angelleye_woocommerce_order_actions) && !empty($this->angelleye_woocommerce_order_actions)) {
                ?>
                <select name="angelleye_payment_action" id="angelleye_payment_action">
                    <?php
                    $i = 0;
                    foreach ($this->angelleye_woocommerce_order_actions as $k => $v) :
                        if ($i == 0) {
                            echo '<option value="" >Select Action</option>';
                        }
                        ?>
                        <option value="<?php echo esc_attr($k); ?>" ><?php echo esc_html($v); ?></option>
                        <?php
                        $i = $i + 1;
                    endforeach;
                    ?>
                </select>
            
                <div class="angelleye_authorization_box" style="display: none;">
                    <?php
                    $payment_method = get_post_meta($post->ID, '_payment_method', true);
                    $remain_authorize_amount_text = '';
                    if ($payment_method == 'paypal_express') {
                        if (isset($this->remain_authorize_amount)) {
                            $remain_authorize_amount_text = 'less than ' . $this->remain_authorize_amount;
                        } else {
                            $remain_authorize_amount_text = '';
                        }
                    }
                    ?>
                    <input type="text" placeholder="Enter amount <?php echo $remain_authorize_amount_text; ?>" id="_regular_price" name="_regular_price" class="short wc_input_price text-box" style="width: 220px">
                </div>
                <?php $this->angelleye_express_checkout_transaction_capture_dropdownbox($post->ID); ?>
                <input type="submit" id="angelleye_payment_submit_button" value="Submit" name="save" class="button button-primary" style="display: none">
                <br/><br/><br/>
                <script>
                    (function ($) {
                        "use strict";

                        //Asking confirm for the capture
                        $('#angelleye_payment_submit_button').on('click', function () {
                            return confirm('Are you sure?');
                        })

                        MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
                        var observer = new MutationObserver(function (mutations, observer) {
                            var currency_symbol = window.woocommerce_admin_meta_boxes.currency_format_symbol;

                            for (var i = 0, len = mutations.length; i < len; i++) {
                                //Updating the total order action table field
                                if (mutations[i].target.className == 'inside' && mutations[i].addedNodes.length > 0) {
                                    //var new_amt_with_curr = $('.wc-order-refund-items .wc-order-totals tr td.total .amount:last').text();
                                    //Adjusting price with paypal-for-woocommerce amount format
                                    // new_amt_with_curr = currency_symbol + new_amt_with_curr.replace(currency_symbol, '');
                                    //$('.angelleye_order_action_table:first tr:first td:last').text(new_amt_with_curr);
                                }
                            }
                        });

                        //Setting an observer to know about total new total amount
                        $(document).ready(function () {
                            var target = document.getElementById('woocommerce-order-items').getElementsByClassName('inside')[0];
                            observer.observe(target, {
                                childList: true,
                            });
                        });
                    })(jQuery);
                </script>
                <?php
            }
            
            ?>
            <table class="widefat angelleye_order_action_table" style="width: 190px;float: right;">
                <tbody>
                    <tr>
                        <td><?php echo __('Order Total:', 'paypal-for-woocommerce'); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo __('Total Capture:', 'paypal-for-woocommerce'); ?></td>
                        <td><?php echo get_woocommerce_currency_symbol() . '' . $this->total_DoCapture ?></td>
                    </tr>
                </tbody>
            </table>
            <br/><br/>
            <table class="widefat angelleye_order_action_table">
                <thead>
                    <tr>
                        <th><?php echo __('Transaction ID', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Date', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Amount', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Payment Status', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Payment Action', 'paypal-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th><?php echo __('Transaction ID', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Date', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Amount', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Payment Status', 'paypal-for-woocommerce'); ?></th>
                        <th><?php echo __('Payment Action', 'paypal-for-woocommerce'); ?></th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    foreach ($posts_array as $post):
                        ?>
                        <tr>
                            <td><?php echo $post->post_title; ?></td>
                            <td>
                                <?php
                                $time = get_post_meta($post->ID, 'TIMESTAMP', true);
                                if (!empty($time)) {
                                    echo esc_attr($time);
                                } else {
                                    $time = get_post_meta($post->ID, 'TRANSTIME', true);
                                    if (!empty($time)) {
                                        echo esc_attr($time);
                                    }
                                }
                                ?></td>
                            <td><?php echo get_woocommerce_currency_symbol() . '' . esc_attr(get_post_meta($post->ID, 'AMT', true)); ?></td>
                            <?php
                            $PENDINGREASON = esc_attr(get_post_meta($post->ID, 'PENDINGREASON', true));
                            if (empty($PENDINGREASON)) {
                                $TRANSSTATE = esc_attr(get_post_meta($post->ID, 'TRANSSTATE', true));
                                if (!empty($this->payflow_transstate[$TRANSSTATE])) {
                                    $PENDINGREASON = $this->payflow_transstate[$TRANSSTATE];
                                }
                            }
                            ?>
                            <td <?php echo ($PENDINGREASON) ? sprintf('title="%s"', $PENDINGREASON) : ""; ?> >
                                <?php
                                $PAYMENTSTATUS = get_post_meta($post->ID, 'PAYMENTSTATUS', true);
                                if (!empty($PAYMENTSTATUS)) {
                                    echo esc_attr($PAYMENTSTATUS);
                                }
                                ?>
                            </td>
                            <td><?php echo esc_attr(get_post_meta($post->ID, 'payment_action', true)); ?> </td>
                        </tr>
                        <?php
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function angelleye_paypal_for_woocommerce_add_paypal_transaction($response, $order, $payment_action) {
        if ($payment_action == 'Authorization') {
            $payment_action = 'DoAuthorization';
        }
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $payment_method = get_post_meta($order_id, '_payment_method', true);
        $TRANSACTIONID = '';
        if (isset($response['PAYMENTINFO_0_TRANSACTIONID']) && !empty($response['PAYMENTINFO_0_TRANSACTIONID'])) {
            $TRANSACTIONID = $response['PAYMENTINFO_0_TRANSACTIONID'];
        } elseif (isset($response['TRANSACTIONID']) && !empty($response['TRANSACTIONID'])) {
            $TRANSACTIONID = $response['TRANSACTIONID'];
        } elseif (isset($response['AUTHORIZATIONID'])) {
            $TRANSACTIONID = $response['AUTHORIZATIONID'];
        } elseif (isset($response['PNREF'])) {
            $TRANSACTIONID = $response['PNREF'];
        }
        $insert_paypal_transaction = array(
            'ID' => '',
            'post_type' => 'paypal_transaction',
            'post_status' => $payment_action,
            'post_title' => $TRANSACTIONID,
            'post_parent' => $order_id
        );
        unset($response['ERRORS']);
        unset($response['REQUESTDATA']);
        unset($response['RAWREQUEST']);
        unset($response['RAWRESPONSE']);
        unset($response['PAYMENTS']);
        $post_id = wp_insert_post($insert_paypal_transaction);
        $response['order_id'] = $order_id;
        $response['payment_action'] = $payment_action;
        $response['_trans_date'] = current_time('mysql');
        if ($payment_method == 'paypal_pro_payflow') {
            $response['PAYMENTSTATUS'] = 'Pending';
        }
        update_post_meta($post_id, 'paypal_transaction', $response);
        foreach ($response as $metakey => $metavalue) {
            $metakey = str_replace('PAYMENTINFO_0_', '', $metakey);
            update_post_meta($post_id, $metakey, $metavalue);
        }
        $post_id_value = self::get_post_id_by_meta_key_and_meta_value('TRANSACTIONID', $TRANSACTIONID);
        if (!empty($post_id_value)) {
            $AMT = get_post_meta($post_id_value, 'AMT', true);
            update_post_meta($post_id, 'AMT', $AMT);
        }
    }

    public static function get_post_id_by_meta_key_and_meta_value($key, $value) {
        global $wpdb;
        $post_id_value = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE `meta_key` LIKE '%s' AND `meta_value` LIKE '%s'", $key, $value));
        return $post_id_value;
    }

    public function save($post_id, $post) {
        if (empty($post->ID)) {
            return false;
        }
        if ($post->post_type != 'shop_order') {
            return false;
        }
        $order = wc_get_order($post_id);
        if (empty($this->payment_method)) {
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
            $this->payment_method = $old_wc ? get_post_meta($order_id, '_payment_method', true) : get_post_meta($order->get_id(), '_payment_method', true);
        }
        if (!empty($_POST['angelleye_payment_action'])) {
            $action = wc_clean($_POST['angelleye_payment_action']);
            $hook_name = 'wc_' . $this->payment_method . '_' . strtolower($action);
            if (!did_action('woocommerce_order_action_' . sanitize_title($hook_name))) {
                do_action('woocommerce_order_action_' . sanitize_title($hook_name), $order);
            }
        }
    }

    public static function get_total($action, $status, $order_id) {
        global $wpdb;
        if ($action == 'DoVoid') {
            $total = $wpdb->get_var($wpdb->prepare("
			SELECT SUM( postmeta.meta_value )
			FROM $wpdb->postmeta AS postmeta
			INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'paypal_transaction' AND posts.post_status LIKE '%s' AND post_parent = %d )
			WHERE postmeta.meta_key = 'AMT'
			AND postmeta.post_id = posts.ID LIMIT 0, 99
		", $action, $order_id));
        } else {
            if ($action == 'DoCapture') {
                $total = $wpdb->get_var($wpdb->prepare("
                            SELECT SUM( postmeta.meta_value )
                            FROM $wpdb->postmeta AS postmeta
                            JOIN $wpdb->postmeta pm2 ON pm2.post_id = postmeta.post_id
                            INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'paypal_transaction' AND posts.post_status LIKE '%s' AND post_parent = %d )
                            WHERE postmeta.meta_key = 'AMT' AND pm2.meta_key = 'PAYMENTSTATUS' AND (pm2.meta_value LIKE '%s' OR pm2.meta_value LIKE 'Pending')
                            AND postmeta.post_id = posts.ID LIMIT 0, 99
                    ", $action, $order_id, $status));
            } else {
                $total = $wpdb->get_var($wpdb->prepare("
                            SELECT SUM( postmeta.meta_value )
                            FROM $wpdb->postmeta AS postmeta
                            JOIN $wpdb->postmeta pm2 ON pm2.post_id = postmeta.post_id
                            INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'paypal_transaction' AND posts.post_status LIKE '%s' AND post_parent = %d )
                            WHERE postmeta.meta_key = 'AMT' AND pm2.meta_key = 'PAYMENTSTATUS' AND pm2.meta_value LIKE '%s'
                            AND postmeta.post_id = posts.ID LIMIT 0, 99
                    ", $action, $order_id, $status));
            }
        }
        if ($total == NULL) {
            $total = 0;
        }
        return self::number_format($total);
    }

    public function angelleye_paypal_for_woocommerce_order_status_handler($order) {
        $this->angelleye_woocommerce_order_actions = $this->angelleye_woocommerce_order_actions();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $_first_transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        if (empty($_first_transaction_id)) {
            return false;
        }
        $this->angelleye_get_transactionDetails($_first_transaction_id);
        $_payment_action = $old_wc ? get_post_meta($order_id, '_payment_action', true) : get_post_meta($order->get_id(), '_payment_action', true);
        if (isset($_payment_action) && !empty($_payment_action) && $_payment_action == 'Order') {
            if (($this->max_authorize_amount <= $this->total_DoVoid) || ($this->total_Pending_DoAuthorization == 0 && $this->total_Completed_DoAuthorization == 0 && $this->total_DoVoid == $order->get_total())) {
                $order->update_status('cancelled');
            }
            if ($order->get_total() - $order->get_total_refunded() <= $this->total_Completed_DoAuthorization && $this->total_Pending_DoAuthorization == 0) {
                do_action('woocommerce_order_status_pending_to_processing', $order_id);
                $order->payment_complete($_first_transaction_id);
                do_action('woocommerce_checkout_order_processed', $order_id, $posted = array());
                if ($old_wc) {
                    $order->reduce_order_stock();
                } else {
                    wc_reduce_stock_levels($order->get_id());
                }
            }
        }

        if ($order->get_total() == $this->total_DoVoid) {
            $order->update_status('cancelled');
        }
    }

    public function angelleye_express_checkout_transaction_capture_dropdownbox($post_id) {
        global $wpdb;
        $order = wc_get_order($post_id);
        if (empty($order)) {
            return false;
        }

        wp_reset_postdata();
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $payment_action = $old_wc ? get_post_meta($order_id, '_payment_action', true) : get_post_meta($order->get_id(), '_payment_action', true);
        if ($this->total_DoCapture == 0 && $this->total_Pending_DoAuthorization == 0) {
            if ('Order' == $payment_action) {
                $post_status = 'Order';
            } else {
                $post_status = 'DoAuthorization';
            }
        } else {
            $post_status = 'DoAuthorization';
        }
        if ($this->total_Completed_DoAuthorization < $this->total_Order || $this->total_Pending_DoAuthorization > 0) {
            $posts = $wpdb->get_results($wpdb->prepare("SELECT $wpdb->posts.ID, $wpdb->posts.post_title FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 AND $wpdb->posts.post_status LIKE '%s' AND $wpdb->posts.post_parent = %d AND ( ( $wpdb->postmeta.meta_key = 'PAYMENTSTATUS' AND CAST($wpdb->postmeta.meta_value AS CHAR) = 'Pending' ) ) AND $wpdb->posts.post_type = 'paypal_transaction' GROUP BY $wpdb->posts.ID ORDER BY $wpdb->posts.post_date DESC LIMIT 0, 99", $post_status, $order_id), ARRAY_A);
            if (empty($posts)) {
                return false;
            }
            ?>
            <select name="angelleye_paypal_capture_transaction_dropdown" id="angelleye_paypal_capture_transaction_dropdown" style="display: none">
                <?php
                $i = 0;
                foreach ($posts as $post):
                    if ($i == 0) {
                        echo '<option value="" >Select Transaction ID</option>';
                    }
                    ?>
                    <option value="<?php echo esc_attr($post['post_title']); ?>" ><?php echo esc_html($post['post_title']); ?></option>
                    <?php
                    $i = $i + 1;
                endforeach;
                ?>
            </select>
            <?php
        }
        if (($this->total_Completed_DoAuthorization == $this->total_DoCapture && $this->total_DoCapture > 0) || $this->total_Pending_DoAuthorization >= 0) {
            ?>
            <select name="angelleye_paypal_dovoid_transaction_dropdown" id="angelleye_paypal_dovoid_transaction_dropdown" style="display: none">
                <?php
                $i = 0;
                if (empty($posts)) {
                    $posts = $wpdb->get_results($wpdb->prepare("SELECT $wpdb->posts.ID, $wpdb->posts.post_title FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 AND $wpdb->posts.post_status LIKE '%s' AND $wpdb->posts.post_parent = %d AND ( ( $wpdb->postmeta.meta_key = 'PAYMENTSTATUS' AND CAST($wpdb->postmeta.meta_value AS CHAR) = 'Pending' ) ) AND $wpdb->posts.post_type = 'paypal_transaction' GROUP BY $wpdb->posts.ID ORDER BY $wpdb->posts.post_date DESC LIMIT 0, 99", $post_status, $order_id), ARRAY_A);
                }
                foreach ($posts as $post):
                    if ($i == 0) {
                        echo '<option value="" >Select Transaction ID</option>';
                    }
                    ?>
                    <option value="<?php echo esc_attr($post['post_title']); ?>" ><?php echo esc_html($post['post_title']); ?></option>
                    <?php
                    $i = $i + 1;
                endforeach;
                ?>
            </select>
            <?php
        }
        if (($this->total_Completed_DoAuthorization == $this->total_DoCapture && $this->total_DoCapture > 0) || $this->total_Pending_DoAuthorization >= 0) {
            ?>
            <select name="angelleye_paypal_doreauthorization_transaction_dropdown" id="angelleye_paypal_doreauthorization_transaction_dropdown" style="display: none">
                <?php
                $i = 0;
                if (empty($posts)) {
                    $posts = $wpdb->get_results($wpdb->prepare("SELECT $wpdb->posts.ID, $wpdb->posts.post_title FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 AND $wpdb->posts.post_status LIKE '%s' AND $wpdb->posts.post_parent = %d AND ( ( $wpdb->postmeta.meta_key = 'PAYMENTSTATUS' AND CAST($wpdb->postmeta.meta_value AS CHAR) = 'Pending' ) ) AND $wpdb->posts.post_type = 'paypal_transaction' GROUP BY $wpdb->posts.ID ORDER BY $wpdb->posts.post_date DESC LIMIT 0, 99", $post_status, $order_id), ARRAY_A);
                }
                foreach ($posts as $post):
                    if ($i == 0) {
                        echo '<option value="" >Select Transaction ID</option>';
                    }
                    ?>
                    <option value="<?php echo esc_attr($post['post_title']); ?>" ><?php echo esc_html($post['post_title']); ?></option>
                    <?php
                    $i = $i + 1;
                endforeach;
                ?>
            </select>
            <?php
        }
    }

    public function angelleye_get_transactionDetails($transaction_id) {
        if (empty($this->payment_method) && $this->payment_method == false) {
            $this->angelleye_set_payment_method_using_transaction_id($transaction_id);
        }
        $this->add_ec_angelleye_paypal_php_library();


        if ($this->payment_method != 'paypal_pro_payflow') {
            $GTDFields = array(
                'transactionid' => $transaction_id
            );
            $PayPalRequestData = array('GTDFields' => $GTDFields);
            $get_transactionDetails_result = $this->paypal->GetTransactionDetails($PayPalRequestData);
            //$this->angelleye_write_request_response_api_log($get_transactionDetails_result);
            $ack = strtoupper($get_transactionDetails_result["ACK"]);
            if ($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $AUTHORIZATIONID = $this->get_post_by_title($transaction_id);
                if ($AUTHORIZATIONID != null) {
                    update_post_meta($AUTHORIZATIONID, 'PAYMENTSTATUS', $get_transactionDetails_result['PAYMENTSTATUS']);
                }
            }
        } else {
            $get_transactionDetails_result = $this->inquiry_transaction($transaction_id);
            $this->angelleye_write_request_response_api_log($get_transactionDetails_result);
            if ($get_transactionDetails_result['RESULT'] == 0 && ($get_transactionDetails_result['RESPMSG'] == 'Approved' || $get_transactionDetails_result['RESPMSG'] == 'Verified')) {
                if ($get_transactionDetails_result['TRANSSTATE'] == 3) {
                    $get_transactionDetails_result['PAYMENTSTATUS'] = 'Pending';
                } elseif ($get_transactionDetails_result['TRANSSTATE'] > 1000) {
                    $get_transactionDetails_result['PAYMENTSTATUS'] = 'Voided';
                } elseif ($get_transactionDetails_result['TRANSSTATE'] == 9) {
                    $get_transactionDetails_result['PAYMENTSTATUS'] = 'Completed';
                } elseif ($get_transactionDetails_result['TRANSSTATE'] == 6 || $get_transactionDetails_result['TRANSSTATE'] == 7 || $get_transactionDetails_result['TRANSSTATE'] == 0) {
                    $get_transactionDetails_result['PAYMENTSTATUS'] = 'Completed';
                } elseif($get_transactionDetails_result['TRANSSTATE'] == 4 ) {
                    $get_transactionDetails_result['PAYMENTSTATUS'] = 'Partial Capture';
                }

                $AUTHORIZATIONID = $this->get_post_by_title($transaction_id);
                if (!empty($get_transactionDetails_result['AMT'])) {
                    update_post_meta($AUTHORIZATIONID, 'AMT', $get_transactionDetails_result['AMT']);
                }

                if (!empty($get_transactionDetails_result['PAYMENTSTATUS'])) {
                    update_post_meta($AUTHORIZATIONID, 'PAYMENTSTATUS', $get_transactionDetails_result['PAYMENTSTATUS']);
                }
                update_post_meta($AUTHORIZATIONID, 'TRANSSTATE', $get_transactionDetails_result['TRANSSTATE']);
            }
        }
    }

    function get_post_by_title($page_title) {
        global $wpdb;
        $post = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='paypal_transaction'", $page_title));
        if ($post) {
            return $post;
        }
        return null;
    }

    public function get_amount_by_transaction_id($transaction_id) {
        $meta_post_id = self::get_post_id_by_meta_key_and_meta_value('TRANSACTIONID', $transaction_id);
        $AMT = get_post_meta($meta_post_id, 'AMT', true);
        return self::number_format($AMT);
    }

    public function angelleye_max_authorize_amount($order_id) {
        if (!is_object($order_id)) {
            $order = wc_get_order($order_id);
        }
        $percentage = 115;
        $new_percentage_amount = self::number_format(($percentage / 100) * $order->get_total());
        $diff_percentage_amount = self::round($new_percentage_amount - $order->get_total());
        if ($diff_percentage_amount > 75) {
            $max_authorize_amount = self::round($order->get_total() + 75);
        } else {
            $max_authorize_amount = self::round($new_percentage_amount - 0.01);;
        }
        $this->max_authorize_amount = self::round($max_authorize_amount);
        
    }

    public function angelleye_remain_authorize_amount() {
        $this->remain_authorize_amount = self::number_format($this->max_authorize_amount - ( self::round($this->total_Pending_DoAuthorization + $this->total_Completed_DoAuthorization)));
    }

    public static function currency_has_decimals($currency) {
        if (in_array($currency, array('HUF', 'JPY', 'TWD'))) {
            return false;
        }
        return true;
    }

    public static function round($price) {
        $precision = 2;
        if (!self::currency_has_decimals(get_woocommerce_currency())) {
            $precision = 0;
        }
        return round($price, $precision);
    }

    /**
     * @since    1.1.8.1
     * Non-decimal currency bug..?? #384
     * Round prices
     * @param type $price
     * @return type
     */
    public static function number_format($price) {
        $decimals = 2;
        if (!self::currency_has_decimals(get_woocommerce_currency())) {
            $decimals = 0;
        }
        return number_format($price, $decimals, '.', '');
    }

    public function angelleye_is_display_paypal_transaction_details($post_id) {
        $order = wc_get_order($post_id);
        if (empty($order)) {
            return false;
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $_payment_method = $old_wc ? get_post_meta($order_id, '_payment_method', true) : get_post_meta($order->get_id(), '_payment_method', true);
        $_payment_action = $old_wc ? get_post_meta($order_id, '_payment_action', true) : get_post_meta($order->get_id(), '_payment_action', true);
        if (isset($_payment_method) && !empty($_payment_method) && isset($_payment_action) && !empty($_payment_action)) {
            if (($_payment_method == 'paypal_pro' || $_payment_method == 'paypal_express' || $_payment_method == 'paypal_pro_payflow') && $_payment_method != "Sale") {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function is_valid_for_use_paypal_express() {
        return in_array(get_woocommerce_currency(), apply_filters('woocommerce_paypal_express_supported_currencies', array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP')));
    }

    public function angelleye_set_payment_method() {
        if (empty($this->payment_method) || $this->payment_method == false) {
            global $post;
            if (empty($post->ID)) {
                return false;
            }
            if ($post->post_type != 'shop_order') {
                return false;
            }
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $order = wc_get_order($post->ID);
            $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
            $this->payment_method = $old_wc ? get_post_meta($order_id, '_payment_method', true) : get_post_meta($order->get_id(), '_payment_method', true);
        }
    }

    public function angelleye_set_payment_method_using_transaction_id($transaction) {
        if (empty($this->payment_method) || $this->payment_method == false) {
            global $wpdb;
            $results = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_value = %s ORDER BY meta_id", $transaction));
            if (!empty($results[0]->post_id)) {
                $old_wc = version_compare(WC_VERSION, '3.0', '<');
                $order = wc_get_order($results[0]->post_id);
                if (empty($order)) {
                    return false;
                }
                $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
                $this->payment_method = $old_wc ? get_post_meta($order_id, '_payment_method', true) : get_post_meta($order->get_id(), '_payment_method', true);
            }
        }
    }

    public static function crypting($string, $action = 'e') {
        $secret_key = AUTH_SALT;
        $secret_iv = SECURE_AUTH_SALT;
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action == 'e') {
            $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
        } else if ($action == 'd') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }

    
    public function angelleye_paypal_for_woocommerce_billing_agreement_details($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $billing_agreement_id = $old_wc ? get_post_meta($order_id, 'BILLINGAGREEMENTID', true) : get_post_meta($order->get_id(), 'BILLINGAGREEMENTID', true);
        if (empty($billing_agreement_id)) {
            return false;
        }
        ?>
        <h3>
            <?php _e('Billing Agreement Details', 'paypal-for-woocommerce'); ?>
        </h3>
        <p> <?php echo $billing_agreement_id; ?></p> <?php
    }

    public static function angelleye_paypal_for_woocommerce_is_set_sandbox_product() {
        $is_sandbox_set = false;
        if (did_action('wp_loaded')) {
            if (isset(WC()->cart) && sizeof(WC()->cart->get_cart()) > 0) {
                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                    $_enable_sandbox_mode = get_post_meta($product_id, '_enable_sandbox_mode', true);
                    if ($_enable_sandbox_mode == 'yes') {
                        $is_sandbox_set = true;
                        return $is_sandbox_set;
                    }
                }
            }
        }
        return $is_sandbox_set;
    }

    public static function angelleye_set_address($order_id, $address, $type = 'billing') {
        foreach ($address as $key => $value) {
            update_post_meta($order_id, "_{$type}_" . $key, $value);
        }
    }

    public static function angelleye_paypal_for_woo_wc_autoship_cart_has_autoship_item() {
        if (!function_exists('WC')) {
            return false;
        }
        $cart = WC()->cart;
        if (empty($cart)) {
            return false;
        }
        $has_autoship_items = false;
        foreach ($cart->get_cart() as $item) {
            if (isset($item['wc_autoship_frequency'])) {
                $has_autoship_items = true;
                break;
            }
        }
        return $has_autoship_items;
    }

    public static function angelleye_is_save_payment_token($current, $order_id) {
        if ((!empty($_POST['wc-' . $current->id . '-payment-token']) && $_POST['wc-' . $current->id . '-payment-token'] == 'new') || self::is_subscription($order_id) || self::angelleye_paypal_for_woo_wc_autoship_cart_has_autoship_item()) {
            if ((!empty($_POST['wc-' . $current->id . '-new-payment-method']) && $_POST['wc-' . $current->id . '-new-payment-method'] == true) || self::is_subscription($order_id) || self::angelleye_paypal_for_woo_wc_autoship_cart_has_autoship_item()) {
                return true;
            }
        }
        return false;
    }

    public static function is_subscription($order_id) {
        return ( function_exists('wcs_order_contains_subscription') && ( wcs_order_contains_subscription($order_id) || wcs_is_subscription($order_id) || wcs_order_contains_renewal($order_id) ) );
    }

    public function inquiry_transaction($ORIGID) {
        $PayPalRequestData = array(
            'TENDER' => 'C', // C = credit card, P = PayPal
            'TRXTYPE' => 'I', //  S=Sale, A= Auth, C=Credit, D=Delayed Capture, V=Void
            'ORIGID' => $ORIGID,
        );
        try {
            $PayPalResult = $this->paypal->ProcessTransaction($PayPalRequestData);
            return $PayPalResult;
        } catch (Exception $ex) {
            
        }
    }

    public function angelleye_wc_paypal_pro_payflow_dovoid($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        // ensure the authorization is still valid for capture
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if ($this->has_authorization_expired($order_id)) {
            return;
        }
        remove_action('woocommerce_order_action_wc_paypal_pro_payflow_dovoid', array($this, 'angelleye_wc_paypal_pro_payflow_dovoid'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_paypal_pro_payflow_do_void($order);
    }

    public function angelleye_wc_paypal_pro_payflow_docapture($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        // ensure the authorization is still valid for capture
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if ($this->has_authorization_expired($order_id)) {
            return;
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        remove_action('woocommerce_order_action_wc_paypal_pro_payflow_docapture', array($this, 'angelleye_wc_paypal_pro_payflow_docapture'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_paypal_pro_payflow_docapture($order, $transaction_id, null);
    }

    public function call_paypal_pro_payflow_docapture($order, $transaction_id, $capture_total) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('Delayed Capture API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();

        if( !empty($_POST['_regular_price'])) {
            $AMT = self::number_format($_POST['_regular_price']);
        } elseif ($capture_total == null) {
            $AMT = $this->get_amount_by_transaction_id($transaction_id);
        } else {
            $AMT = $capture_total;
        }

        $AMT = self::round($AMT - $order->get_total_refunded());
        if (isset($transaction_id) && !empty($transaction_id)) {
            $PayPalRequestData = array(
                'TENDER' => 'C', // C = credit card, P = PayPal
                'TRXTYPE' => 'D', //  S=Sale, A= Auth, C=Credit, D=Delayed Capture, V=Void
                'ORIGID' => $transaction_id,
                'AMT' => $AMT,
                'CAPTURECOMPLETE' => 'N'
            );

            $do_delayed_capture_result = $this->paypal->ProcessTransaction($PayPalRequestData);
            $this->angelleye_write_request_response_api_log($do_delayed_capture_result);
            if (isset($do_delayed_capture_result['RESULT']) && ($do_delayed_capture_result['RESULT'] == 0 || $do_delayed_capture_result['RESULT'] == 126)) {
                $order->add_order_note(__('PayPal Delayed Capture', 'paypal-for-woocommerce') .
                        ' ( Response Code: ' . $do_delayed_capture_result['RESULT'] . ", " .
                        ' Delayed Capture AUTHORIZATIONID: ' . $transaction_id . ' )'
                );

                $payment_order_meta = array('_transaction_id' => $transaction_id);
                self::angelleye_add_order_meta($order_id, $payment_order_meta);
                self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_delayed_capture_result, $order, 'DoCapture');
                $this->angelleye_get_transactionDetails($transaction_id);
                $this->angelleye_paypal_for_woocommerce_order_status_handler($order);
            } else {
                $ErrorCode = urldecode($do_delayed_capture_result["RESULT"]);
                $ErrorLongMsg = urldecode($do_delayed_capture_result["RESPMSG"]);
                $this->ec_add_log(__('PayPal Delayed Capture API call failed. ', 'paypal-for-woocommerce'));
                $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
                $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
                $order->add_order_note(__('PayPal Delayed Capture API call failed. ', 'paypal-for-woocommerce') .
                        ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                        ' Error Code: ' . $ErrorCode . ' )'
                );
                //$this->call_error_email_notifications($subject = 'Delayed Capture failed', $method_name = 'Delayed Capture', $resArray = $do_delayed_capture_result);
            }
        }
    }

    public function call_paypal_pro_payflow_do_void($order) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('DoVoid API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        if (isset($_POST['angelleye_paypal_dovoid_transaction_dropdown']) && !empty($_POST['angelleye_paypal_dovoid_transaction_dropdown'])) {
            $transaction_id = $_POST['angelleye_paypal_dovoid_transaction_dropdown'];
        } else {
            $old_wc = version_compare(WC_VERSION, '3.0', '<');
            $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        }
        if (isset($transaction_id) && !empty($transaction_id)) {
            $PayPalRequestData = array(
                'TENDER' => 'C', // C = credit card, P = PayPal
                'TRXTYPE' => 'V', //  S=Sale, A= Auth, C=Credit, D=Delayed Capture, V=Void
                'ORIGID' => $transaction_id
            );

            $do_void_result = $this->paypal->ProcessTransaction($PayPalRequestData);
            $this->angelleye_write_request_response_api_log($do_void_result);
            if (isset($do_void_result['RESULT']) && ($do_void_result['RESULT'] == 0 || $do_void_result['RESULT'] == 126)) {
                $order->add_order_note(__('PayPal DoVoid', 'paypal-for-woocommerce') .
                        ' ( Response Code: ' . $do_void_result['RESULT'] . ", " .
                        ' Void AUTHORIZATIONID: ' . $transaction_id . ' )'
                );
                $this->angelleye_get_transactionDetails($transaction_id);
                $payment_order_meta = array('_transaction_id' => $transaction_id);
                self::angelleye_add_order_meta($order_id, $payment_order_meta);
                self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_void_result, $order, 'DoVoid');
                $this->angelleye_paypal_for_woocommerce_order_status_handler($order);
            } else {
                $ErrorCode = urldecode($do_void_result["RESULT"]);
                $ErrorLongMsg = urldecode($do_void_result["RESPMSG"]);
                $this->ec_add_log(__('PayPal DoVoid API call failed. ', 'paypal-for-woocommerce'));
                $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
                $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
                $order->add_order_note(__('PayPal DoVoid API call failed. ', 'paypal-for-woocommerce') .
                        ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                        ' Error Code: ' . $ErrorCode . ' )'
                );
                //$this->call_error_email_notifications($subject = 'DoVoid failed', $method_name = 'DoVoid', $resArray = $do_void_result);
            }
        }
    }

    /**
     *
     * @param type $order
     */
    public function angelleye_wc_paypal_pro_payflow_doauthorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        remove_action('woocommerce_order_action_wc_paypal_pro_payflow_doauthorization', array($this, 'angelleye_wc_paypal_pro_payflow_doauthorization'));
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
        $this->call_paypal_pro_payflow_do_authorization($order);
    }

    public function call_paypal_pro_payflow_do_authorization($order) {
        $this->add_ec_angelleye_paypal_php_library();
        $this->ec_add_log('Delayed Capture API call');
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        $transaction_id = $old_wc ? get_post_meta($order_id, '_first_transaction_id', true) : get_post_meta($order->get_id(), '_first_transaction_id', true);
        if (isset($transaction_id) && !empty($transaction_id)) {
            $PayPalRequestData = array(
                'TENDER' => 'C', // C = credit card, P = PayPal
                'TRXTYPE' => 'A', //  S=Sale, A= Auth, C=Credit, D=Delayed Capture, V=Void
                'ORIGID' => $transaction_id,
                'AMT' => self::number_format($_POST['_regular_price'])
            );
            $do_authorization_result = $this->paypal->ProcessTransaction($PayPalRequestData);
            $this->angelleye_write_request_response_api_log($do_authorization_result);
            if (isset($do_authorization_result['RESULT']) && ($do_authorization_result['RESULT'] == 0 || $do_authorization_result['RESULT'] == 126)) {
                $order->add_order_note(__('PayPal Auth', 'paypal-for-woocommerce') .
                        ' ( Response Code: ' . $do_authorization_result['RESULT'] . ", " .
                        ' AUTHORIZATIONID: ' . $transaction_id . ' )'
                );
                $this->angelleye_get_transactionDetails($transaction_id);
                $payment_order_meta = array('_transaction_id' => $transaction_id);
                self::angelleye_add_order_meta($order_id, $payment_order_meta);
                self::angelleye_paypal_for_woocommerce_add_paypal_transaction($do_authorization_result, $order, 'DoAuthorization');
                $this->angelleye_paypal_for_woocommerce_order_status_handler($order);
            } else {
                $ErrorCode = urldecode($do_authorization_result["RESULT"]);
                $ErrorLongMsg = urldecode($do_authorization_result["RESPMSG"]);
                $this->ec_add_log(__('PayPal Auth API call failed. ', 'paypal-for-woocommerce'));
                $this->ec_add_log(__('Detailed Error Message: ', 'paypal-for-woocommerce') . $ErrorLongMsg);
                $this->ec_add_log(__('Error Code: ', 'paypal-for-woocommerce') . $ErrorCode);
                $order->add_order_note(__('PayPal Auth API call failed. ', 'paypal-for-woocommerce') .
                        ' ( Detailed Error Message: ' . $ErrorLongMsg . ", " .
                        ' Error Code: ' . $ErrorCode . ' )'
                );
                //$this->call_error_email_notifications($subject = 'Delayed Capture failed', $method_name = 'Delayed Capture', $resArray = $do_delayed_capture_result);
            }
        }
    }
    
    public static function angelleye_express_checkout_validate_shipping_address($paypal_request) {
        if( !empty($paypal_request['SECFields']['addroverride']) && $paypal_request['SECFields']['addroverride'] == 1 ) {
            $address_required_field = array('shiptoname', 'shiptostreet', 'shiptostreet2', 'shiptocity', 'shiptostate', 'shiptozip', 'shiptocountrycode');
            foreach ($address_required_field as $key => $value) {
                if( empty($paypal_request['Payments'][0][$value]) ) {
                    unset($paypal_request['SECFields']['addroverride']);
                    return $paypal_request;
                }
            }
        }
        return $paypal_request;
    }

}
