<?php

class WC_Gateway_PayPal_Advanced_AngellEYE extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'paypal_advanced';
        $this->icon = apply_filters('woocommerce_paypal_advanced_icon', plugins_url('/assets/images/cards.png', plugin_basename(dirname(__FILE__))));
        $this->has_fields = true;
        $this->home_url = is_ssl() ? home_url('/', 'https') : home_url('/'); //set the urls (cancel or return) based on SSL
        $this->testurl = 'https://pilot-payflowpro.paypal.com';
        $this->liveurl = 'https://payflowpro.paypal.com';
        $this->relay_response_url = add_query_arg('wc-api', 'WC_Gateway_PayPal_Advanced_AngellEYE', $this->home_url);
        $this->method_title = __('PayPal Advanced', 'paypal-for-woocommerce');
        $this->secure_token_id = '';
        $this->securetoken = '';
        $this->supports = array(
            'products',
            'refunds'
        );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();


        // Define user set variables
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->testmode = $this->settings['testmode'];
        $this->loginid = $this->settings['loginid'];
        $this->resellerid = $this->settings['resellerid'];
        $this->transtype = $this->settings['transtype'];
        $this->password = $this->settings['password'];
        $this->debug = $this->settings['debug'];
        $this->invoice_prefix = $this->settings['invoice_prefix'];
        $this->page_collapse_bgcolor = $this->settings['page_collapse_bgcolor'];
        $this->page_collapse_textcolor = $this->settings['page_collapse_textcolor'];
        $this->page_button_bgcolor = $this->settings['page_button_bgcolor'];
        $this->page_button_textcolor = $this->settings['page_button_textcolor'];
        $this->label_textcolor = $this->settings['label_textcolor'];

        if (!isset($this->settings['mobilemode']))
            $this->mobilemode = 'yes';
        else
            $this->mobilemode = $this->settings['mobilemode'];

        switch ($this->settings['layout']) {
            case 'A': $this->layout = 'TEMPLATEA';
                break;
            case 'B': $this->layout = 'TEMPLATEB';
                break;
            case 'C': $this->layout = 'MINLAYOUT';
                break;
        }

        $this->user = $this->settings['user'] == '' ? $this->settings['loginid'] : $this->settings['user'];
        $this->hostaddr = $this->testmode == 'yes' ? $this->testurl : $this->liveurl;

        if ($this->debug == 'yes')
            $this->log = new WC_Logger();

        // Hooks
        add_action('admin_notices', array($this, 'checks'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_paypal_advanced', array($this, 'receipt_page'));
        add_action('woocommerce_api_wc_gateway_paypal_advanced_angelleye', array($this, 'relay_response'));

        if (!$this->is_available())
            $this->enabled = false;
    }

    /**
     * Check if required fields for configuring the gateway are filled up by the administrator
     * @access public
     * @return void
     * */
    public function checks() {
        if ($this->enabled == 'no') {
            return;
        }
        if (!$this->loginid) {
            echo '<div class="error"><p>' . sprintf(__('Paypal Advanced error: Please enter your PayPal Advanced Account Merchant Login <a href="%s">here</a>', 'paypal-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower('WC_Gateway_PayPal_Advanced_AngellEYE'))) . '</p></div>';
        } elseif (!$this->resellerid) {
            echo '<div class="error"><p>' . sprintf(__('Paypal Advanced error: Please enter your PayPal Advanced Account Partner <a href="%s">here</a>', 'paypal-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower('WC_Gateway_PayPal_Advanced_AngellEYE'))) . '</p></div>';
        } elseif (!$this->password) {
            echo '<div class="error"><p>' . sprintf(__('Paypal Advanced error: Please enter your PayPal Advanced Account Password <a href="%s">here</a>', 'paypal-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower('WC_Gateway_PayPal_Advanced_AngellEYE'))) . '</p></div>';
        }
        return;
    }

    /**
     * redirect_to - redirects to the url based on layout type
     *
     * @access public
     * @return javascript code to redirect the parent to a page
     */
    public function redirect_to($redirect_url) {
        // Clean
        @ob_clean();

        // Header
        header('HTTP/1.1 200 OK');

        //redirect to the url based on layout type
        if ($this->layout != 'MINLAYOUT') {
            wp_redirect($redirect_url);
        } else {
            echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
        }
        exit;
    }

    /**
     * inquiry_transaction - Performs inquiry transaction
     *
     * @access private
     * @param  WC_Order $order
     * @param int $order_id
     * @return result code of the inquiry transaction
     */
    private function inquiry_transaction($order, $order_id) {

        //inquire transaction, whether it is really paid or not
        $paypal_args = array(
            'USER' => $this->user,
            'VENDOR' => $this->loginid,
            'PARTNER' => $this->resellerid,
            'PWD[' . strlen($this->password) . ']' => $this->password,
            'ORIGID' => $_POST['PNREF'],
            'TENDER' => 'C',
            'TRXTYPE' => 'I',
            'BUTTONSOURCE' => 'WooThemes_Cart'
        );

        $postData = ''; //stores the post data string
        foreach ($paypal_args as $key => $val) {
            $postData .='&' . $key . '=' . $val;
        }

        $postData = trim($postData, '&');

        /* Using Curl post necessary information to the Paypal Site to generate the secured token */
        $response = wp_remote_post($this->hostaddr, array(
            'method' => 'POST',
            'body' => $postData,
            'timeout' => 70,
            'user-agent' => 'Woocommerce ' . WC_VERSION,
            'httpversion' => '1.1',
            'headers' => array('host' => 'www.paypal.com')
        ));
        if (is_wp_error($response)) {
            throw new Exception(__('There was a problem connecting to the payment gateway.', 'paypal-for-woocommerce'));
        }
        if (empty($response['body'])) {
            throw new Exception(__('Empty response.', 'paypal-for-woocommerce'));
        }

        $inquiry_result_arr = array(); //stores the response in array format
        parse_str($response['body'], $inquiry_result_arr);

        if ($inquiry_result_arr['RESULT'] == 0 && $inquiry_result_arr['RESPMSG'] == 'Approved') {
            $order->add_order_note(sprintf(__('Received result of Inquiry Transaction for the  (Order: %s) and is successful', 'paypal-for-woocommerce'), $order->get_order_number()));
            return 'Approved';
        } else {
            $order->add_order_note(sprintf(__('Received result of Inquiry Transaction for the  (Order: %s) and with error:%s', 'paypal-for-woocommerce'), $order->get_order_number(), $inquiry_result_arr['RESPMSG']));
            return 'Error';
        }
    }

    /**
     * success_handler - Handles the successful transaction
     *
     * @access private
     * @param  WC_Order $order
     * @param int $order_id
     * @param bool $silent_post
     * @return
     */
    private function success_handler($order, $order_id, $silent_post) {
        if (get_post_meta($order_id, '_secure_token', true) == $_REQUEST['SECURETOKEN']) {
            if ($this->debug == 'yes') {
                $this->log->add('paypal_advanced', __('Relay Response Tokens Match', 'paypal-for-woocommerce'));
            }
        } else { // Redirect to homepage, if any invalid request or hack
            if ($this->debug == 'yes') {
                $this->log->add('paypal_advanced', __('Relay Response Tokens Mismatch', 'paypal-for-woocommerce'));
            }
            //redirect to the checkout page, if not silent post
            if ($silent_post === false)
                $this->redirect_to($order->get_checkout_payment_url(true));
            exit;
        }

        // Add order note
        $order->add_order_note(sprintf(__('PayPal Advanced payment completed (Order: %s). Transaction number/ID: %s.', 'paypal-for-woocommerce'), $order->get_order_number(), $_POST['PNREF']));

        $inq_result = $this->inquiry_transaction($order, $order_id);

        // Handle response
        if ($inq_result == 'Approved') {//if approved
            // Payment complete
            $order->payment_complete($_POST['PNREF']);

            // Remove cart
            WC()->cart->empty_cart();

            // Add order note
            $order->add_order_note(sprintf(__('Payment completed for the  (Order: %s)', 'paypal-for-woocommerce'), $order->get_order_number()));

            //log the completeion
            if ($this->debug == 'yes')
                $this->log->add('paypal_advanced', sprintf(__('Payment completed for the  (Order: %s)', 'paypal-for-woocommerce'), $order->get_order_number()));

            //redirect to the thanks page, if not silent post
            if ($silent_post === false) {
                $this->redirect_to($this->get_return_url($order));
            }
        }
    }

    /**
     * error_handler - Handles the error transaction
     *
     * @access private
     * @param  WC_Order $order
     * @param int $order_id
     * @param bool $silent_post
     * @return
     */
    private function error_handler($order, $order_id, $silent_post) {

        // 12-0 messages
        wc_clear_notices();
        // Add error
        wc_add_notice(__('Error:', 'paypal-for-woocommerce') . ' "' . urldecode($_POST['RESPMSG']) . '"', 'error');

        //redirect to the checkout page, if not silent post
        if ($silent_post === false)
            $this->redirect_to($order->get_checkout_payment_url(true));
    }

    /**
     * cancel_handler - Handles the cancel transaction
     *
     * @access private
     * @param  WC_Order $order
     * @param int $order_id
     * @return
     */
    private function cancel_handler($order, $order_id) {
        wp_redirect($order->get_cancel_order_url());
        exit;
    }

    /**
     * decline_handler - Handles the decline transaction
     *
     * @access private
     * @param  WC_Order $order
     * @param int $order_id
     * @param bool $silent_post
     * @return
     */
    private function decline_handler($order, $order_id, $silent_post) {


        $order->update_status('failed', __('Payment failed via PayPal Advanced because of.', 'paypal-for-woocommerce') . '&nbsp;' . $_POST['RESPMSG']);

        if ($this->debug == 'yes') {
            $this->log->add('paypal_advanced', sprintf(__('Status has been changed to failed for order %s', 'paypal-for-woocommerce'), $order->get_order_number()));
        }
        if ($this->debug == 'yes') {
            $this->log->add('paypal_advanced', sprintf(__('Error Occurred while processing %s : %s, status: %s', 'paypal-for-woocommerce'), $order->get_order_number(), urldecode($_POST['RESPMSG']), $_POST['RESULT']));
        }
        $this->error_handler($order, $order_id, $silent_post);
    }

    /**
     * Relay response - Checks the payment transaction reponse based on that either completes the transaction or shows thows the exception and show sthe error
     *
     * @access public
     * @return javascript code to redirect the parent to a page
     */
    public function relay_response() {

        //define a variable to indicate whether it is a silent post or return
        if (isset($_REQUEST['silent']) && $_REQUEST['silent'] == 'true')
            $silent_post = true;
        else
            $silent_post = false;


        //log the event
        if ($silent_post === true && $this->debug == 'yes')
            $this->log->add('paypal_advanced', sprintf(__('Silent Relay Response Triggered: %s', 'paypal-for-woocommerce'), print_r($_REQUEST, true)));
        else if ($this->debug == 'yes')
            $this->log->add('paypal_advanced', sprintf(__('Relay Response Triggered: %s', 'paypal-for-woocommerce'), print_r($_REQUEST, true)));

        //if valid request
        if (!isset($_REQUEST['INVOICE'])) { // Redirect to homepage, if any invalid request or hack
            //if not silent post redirect it to home page otherwise just exit
            if ($silent_post === false)
                wp_redirect(home_url('/'));
            exit;
        }

        // get Order ID
        $order_id = $_REQUEST['USER1'];

        // Create order object
        $order = new WC_Order($order_id);

        //check for the status of the order, if completed or processing, redirect to thanks page. This case happens when silentpost is on
        $status = isset($order->status) ? $order->status : $order->get_status();

        if ($status == 'processing' || $status == 'completed') {
            // Log
            if ($this->debug == "yes")
                $this->log->add('paypal_advanced', sprintf(__('Redirecting to Thank You Page for order %s', 'paypal-for-woocommerce'), $order->get_order_number()));

            //redirect to the thanks page, if not silent post
            if ($silent_post === false)
                $this->redirect_to($this->get_return_url($order));
        }

        //define RESULT, if not provided in case of cancel, define with -1
        if (isset($_REQUEST['cancel_ec_trans']) && $_REQUEST['cancel_ec_trans'] == 'true')
            $_REQUEST['RESULT'] = -1;

        //handle the successful transaction
        switch ($_REQUEST['RESULT']) {

            case 0 :
                //handle exceptional cases
                if ($_REQUEST['RESPMSG'] == 'Approved')
                    $this->success_handler($order, $order_id, $silent_post);
                else if ($_REQUEST['RESPMSG'] == 'Declined')
                    $this->decline_handler($order, $order_id, $silent_post);
                else
                    $this->error_handler($order, $order_id, $silent_post);
                break;
            case 12:
                $this->decline_handler($order, $order_id, $silent_post);
                break;
            case -1:
                $this->cancel_handler($order, $order_id);
                break;
            default:
                //handles error order
                $this->error_handler($order, $order_id, $silent_post);
                break;
        }
    }

    /**
     * Gets the secured token by passing all the required information to PayPal site
     *
     * @param order an WC_ORDER Object
     * @return secure_token as string
     */
    function get_secure_token($order) {
        static $length_error = 0;

        // Log
        if ($this->debug == 'yes')
            $this->log->add('paypal_advanced', sprintf(__('Requesting for the Secured Token for the order %s', 'paypal-for-woocommerce'), $order->get_order_number()));

        // Generate unique id
        $this->secure_token_id = uniqid(substr($_SERVER['HTTP_HOST'], 0, 9), true);

        // Prepare paypal_ars array to pass to paypal to generate the secure token
        $paypal_args = array();

        //override the layout with mobile template, if browsed from mobile if the exitsing layout is C or MINLAYOUT
        if (($this->layout == 'MINLAYOUT' || $this->layout == 'C') && $this->mobilemode == "yes") {
            $template = wp_is_mobile() ? "MOBILE" : $this->layout;
        } else {
            $template = $this->layout;
        }

        $paypal_args = array(
            'VERBOSITY' => 'HIGH',
            'USER' => $this->user,
            'VENDOR' => $this->loginid,
            'PARTNER' => $this->resellerid,
            'PWD[' . strlen($this->password) . ']' => $this->password,
            'SECURETOKENID' => $this->secure_token_id,
            'CREATESECURETOKEN' => 'Y',
            'TRXTYPE' => $this->transtype,
            'CUSTREF' => $order->get_order_number(),
            'USER1' => $order->id,
            'INVNUM' => $this->invoice_prefix . ltrim($order->get_order_number(), '#'),
            'AMT' => $order->get_total(),
            'FREIGHTAMT' => number_format($order->get_total_shipping(), 2, '.', ''),
            'COMPANYNAME[' . strlen($order->billing_company) . ']' => $order->billing_company,
            'CURRENCY' => get_woocommerce_currency(),
            'EMAIL' => $order->billing_email,
            'BILLTOFIRSTNAME[' . strlen($order->billing_first_name) . ']' => $order->billing_first_name,
            'BILLTOLASTNAME[' . strlen($order->billing_last_name) . ']' => $order->billing_last_name,
            'BILLTOSTREET[' . strlen($order->billing_address_1 . ' ' . $order->billing_address_2) . ']' => $order->billing_address_1 . ' ' . $order->billing_address_2,
            'BILLTOCITY[' . strlen($order->billing_city) . ']' => $order->billing_city,
            'BILLTOSTATE[' . strlen($order->billing_state) . ']' => $order->billing_state,
            'BILLTOZIP' => $order->billing_postcode,
            'BILLTOCOUNTRY[' . strlen($order->billing_country) . ']' => $order->billing_country,
            'BILLTOEMAIL' => $order->billing_email,
            'BILLTOPHONENUM' => $order->billing_phone,
            'SHIPTOFIRSTNAME[' . strlen($order->shipping_first_name) . ']' => $order->shipping_first_name,
            'SHIPTOLASTNAME[' . strlen($order->shipping_last_name) . ']' => $order->shipping_last_name,
            'SHIPTOSTREET[' . strlen($order->shipping_address_1 . ' ' . $order->shipping_address_2) . ']' => $order->shipping_address_1 . ' ' . $order->shipping_address_2,
            'SHIPTOCITY[' . strlen($order->shipping_city) . ']' => $order->shipping_city,
            'SHIPTOZIP' => $order->shipping_postcode,
            'SHIPTOCOUNTRY[' . strlen($order->shipping_country) . ']' => $order->shipping_country,
            'BUTTONSOURCE' => 'AngellEYE_SP_WooCommerce',
            'RETURNURL[' . strlen($this->relay_response_url) . ']' => $this->relay_response_url,
            'URLMETHOD' => 'POST',
            'TEMPLATE' => $template,
            'PAGECOLLAPSEBGCOLOR' => ltrim($this->page_collapse_bgcolor, '#'),
            'PAGECOLLAPSETEXTCOLOR' => ltrim($this->page_collapse_textcolor, '#'),
            'PAGEBUTTONBGCOLOR' => ltrim($this->page_button_bgcolor, '#'),
            'PAGEBUTTONTEXTCOLOR' => ltrim($this->page_button_textcolor, '#'),
            'LABELTEXTCOLOR' => ltrim($this->settings['label_textcolor'], '#')
        );

        //handle empty state exception e.g. Denmark
        if (empty($order->shipping_state)) {
            //replace with city
            $paypal_args['SHIPTOSTATE[' . strlen($order->shipping_city) . ']'] = $order->shipping_city;
        } else {
            //retain state
            $paypal_args['SHIPTOSTATE[' . strlen($order->shipping_state) . ']'] = $order->shipping_state;
        }

        // Determine the ERRORURL,CANCELURL and SILENTPOSTURL
        $cancelurl = add_query_arg('wc-api', 'WC_Gateway_PayPal_Advanced_AngellEYE', add_query_arg('cancel_ec_trans', 'true', $this->home_url));
        $paypal_args['CANCELURL[' . strlen($cancelurl) . ']'] = $cancelurl;

        $errorurl = add_query_arg('wc-api', 'WC_Gateway_PayPal_Advanced_AngellEYE', add_query_arg('error', 'true', $this->home_url));
        $paypal_args['ERRORURL[' . strlen($errorurl) . ']'] = $errorurl;

        $silentposturl = add_query_arg('wc-api', 'WC_Gateway_PayPal_Advanced_AngellEYE', add_query_arg('silent', 'true', $this->home_url));
        $paypal_args['SILENTPOSTURL[' . strlen($silentposturl) . ']'] = $silentposturl;


        // If prices include tax or have order discounts, send the whole order as a single item
        if ($order->prices_include_tax == 'yes' || $order->get_total_discount() > 0 || $length_error > 1) {

            // Don't pass items - paypal borks tax due to prices including tax. PayPal has no option for tax inclusive pricing sadly. Pass 1 item for the order items overall
            $item_names = array();

            if (sizeof($order->get_items()) > 0) {

                $paypal_args['FREIGHTAMT'] = number_format($order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '');

                if ($length_error <= 1) {
                    foreach ($order->get_items() as $item)
                        if ($item['qty'])
                            $item_names[] = $item['name'] . ' x ' . $item['qty'];
                } else {
                    $item_names[] = "All selected items, refer to Woocommerce order details";
                }
                $items_str = sprintf(__('Order %s', 'paypal-for-woocommerce'), $order->get_order_number()) . " - " . implode(', ', $item_names);
                $items_names_str = $this->paypal_advanced_item_name($items_str);
                $items_desc_str = $this->paypal_advanced_item_desc($items_str);
                $paypal_args['L_NAME0[' . strlen($items_names_str) . ']'] = $items_names_str;
                $paypal_args['L_DESC0[' . strlen($items_desc_str) . ']'] = $items_desc_str;
                $paypal_args['L_QTY0'] = 1;
                $paypal_args['L_COST0'] = number_format($order->get_total() - round($order->get_total_shipping() + $order->get_shipping_tax(), 2), 2, '.', '');

                //determine ITEMAMT
                $paypal_args['ITEMAMT'] = $paypal_args['L_COST0'] * $paypal_args['L_QTY0'];
            }
        } else {


            // Tax
            $paypal_args['TAXAMT'] = $order->get_total_tax();

            //ITEM AMT, total amount
            $paypal_args['ITEMAMT'] = 0;


            // Cart Contents
            $item_loop = 0;
            if (sizeof($order->get_items()) > 0) {
                foreach ($order->get_items() as $item) {
                    if ($item['qty']) {

                        $product = $order->get_product_from_item($item);

                        $item_name = $item['name'];

                        //create order meta object and get the meta data as string
                        $item_meta = new WC_order_item_meta($item['item_meta']);
                        if ($length_error == 0 && $meta = $item_meta->display(true, true)) {
                            $item_name .= ' (' . $meta . ')';
                            $item_name = $this->paypal_advanced_item_name($item_name);
                        }
                        $paypal_args['L_NAME' . $item_loop . '[' . strlen($item_name) . ']'] = $item_name;
                        if ($product->get_sku())
                            $paypal_args['L_SKU' . $item_loop] = $product->get_sku();
                        $paypal_args['L_QTY' . $item_loop] = $item['qty'];
                        $paypal_args['L_COST' . $item_loop] = $order->get_item_total($item, false, false); /* No Tax , No Round) */
                        $paypal_args['L_TAXAMT' . $item_loop] = $order->get_item_tax($item, false); /* No Round it */

                        //calculate ITEMAMT
                        $paypal_args['ITEMAMT'] += $order->get_line_total($item, false, false); /* No tax, No Round */

                        $item_loop++;
                    }
                }
            }
        }

        try {


            $postData = '';
            $logData = '';

            foreach ($paypal_args as $key => $val) {

                $postData .='&' . $key . '=' . $val;
                if (strpos($key, 'PWD') === 0)
                    $logData .='&PWD=XXXX';
                else
                    $logData .='&' . $key . '=' . $val;
            }

            $postData = trim($postData, '&');


            // Log
            if ($this->debug == 'yes') {

                $logData = trim($logData, '&');

                $this->log->add('paypal_advanced', sprintf(__('Requesting for the Secured Token for the order %s with following URL and Paramaters: %s', 'paypal-for-woocommerce'), $order->get_order_number(), $this->hostaddr . '?' . $logData));
            }

            /* Using Curl post necessary information to the Paypal Site to generate the secured token */
            $response = wp_remote_post($this->hostaddr, array(
                'method' => 'POST',
                'body' => $postData,
                'timeout' => 70,
                'user-agent' => 'WooCommerce ' . WC_VERSION,
                'httpversion' => '1.1',
                'headers' => array('host' => 'www.paypal.com')
            ));

            //if error occurs, throw exception with the error message
            if (is_wp_error($response)) {

                throw new Exception($response->get_error_message());
            }
            if (empty($response['body']))
                throw new Exception(__('Empty response.', 'paypal-for-woocommerce'));

            /* Parse and assign to array */

            parse_str($response['body'], $arr);

            // Handle response
            if ($arr['RESULT'] > 0) {
                // raise exception
                throw new Exception(__('There was an error processing your order - ' . $arr['RESPMSG'], 'paypal-for-woocommerce'));
            } else {//return the secure token
                return $arr['SECURETOKEN'];
            }
        } catch (Exception $e) {

            if ($this->debug == 'yes')
                $this->log->add('paypal_advanced', sprintf(__('Secured Token generation failed for the order %s with error: %s', 'paypal-for-woocommerce'), $order->get_order_number(), $e->getMessage()));

            if ($arr['RESULT'] != 7) {
                wc_add_notice(__('Error:', 'paypal-for-woocommerce') . ' "' . $e->getMessage() . '"', 'error');
                $length_error = 0;
                return;
            } else {

                if ($this->debug == 'yes')
                    $this->log->add('paypal_advanced', sprintf(__('Secured Token generation failed for the order %s with error: %s', 'paypal-for-woocommerce'), $order->get_order_number(), $e->getMessage()));

                $length_error++;
                return $this->get_secure_token($order);
            }
        }
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     * @access public
     * @return boolean
     */
    public function is_available() {

        //if enabled checkbox is checked
        if ($this->enabled == 'yes')
            return true;

        return false;
    }

    /**
     * Admin Panel Options
     * - Settings
     *
     * @access public
     * @return void
     */
    public function admin_options() {
        ?>
        <h3><?php _e('PayPal Advanced', 'paypal-for-woocommerce'); ?></h3>
        <p><?php _e('PayPal Payments Advanced uses an iframe to seamlessly integrate PayPal hosted pages into the checkout process.', 'paypal-for-woocommerce'); ?></p>
        <table class="form-table">
            <?php
            //if user's currency is USD
            if (!in_array(get_woocommerce_currency(), array('USD', 'CAD'))) {
                ?>
                <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'paypal-for-woocommerce'); ?></strong>: <?php _e('PayPal does not support your store currency.', 'paypal-for-woocommerce'); ?></p></div>
                <?php
                return;
            } else {
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
            }
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style( 'wp-color-picker' );
            ?>
        </table><!--/.form-table-->
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                jQuery('.paypal_for_woocommerce_color_field').wpColorPicker();
            });
        </script>
        <?php
    }

// End admin_options()

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Advanced', 'paypal-for-woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'paypal-for-woocommerce'),
                'default' => __('PayPal Advanced', 'paypal-for-woocommerce')
            ),
            'description' => array(
                'title' => __('Description', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'paypal-for-woocommerce'),
                'default' => __('PayPal Advanced description', 'paypal-for-woocommerce')
            ),
            'loginid' => array(
                'title' => __('Merchant Login', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => '',
                'default' => ''
            ),
            'resellerid' => array(
                'title' => __('Partner', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your PayPal Advanced Partner. If you purchased the account directly from PayPal, use PayPal.', 'paypal-for-woocommerce'),
                'default' => ''
            ),
            'user' => array(
                'title' => __('User (or Merchant Login if no designated user is set up for the account)', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your PayPal Advanced user account for this site.', 'paypal-for-woocommerce'),
                'default' => ''
            ),
            'password' => array(
                'title' => __('Password', 'paypal-for-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your PayPal Advanced account password.', 'paypal-for-woocommerce'),
                'default' => ''
            ),
            'testmode' => array(
                'title' => __('PayPal sandbox', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal sandbox', 'paypal-for-woocommerce'),
                'default' => 'yes',
                'description' => sprintf(__('PayPal sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>', 'paypal-for-woocommerce'), 'https://developer.paypal.com/'),
            ),
            'transtype' => array(
                'title' => __('Transaction Type', 'paypal-for-woocommerce'),
                'type' => 'select',
                'label' => __('Transaction Type', 'paypal-for-woocommerce'),
                'default' => 'S',
                'description' => '',
                'options' => array('A' => 'Authorization', 'S' => 'Sale')
            ),
            'layout' => array(
                'title' => __('Layout', 'paypal-for-woocommerce'),
                'type' => 'select',
                'label' => __('Layout', 'paypal-for-woocommerce'),
                'default' => 'C',
                'description' => __('Layouts A and B redirect to PayPal\'s website for the user to pay. <br/>Layout C (recommended) is a secure PayPal-hosted page but is embedded on your site using an iFrame.', 'paypal-for-woocommerce'),
                'options' => array('A' => 'Layout A', 'B' => 'Layout B', 'C' => 'Layout C')
            ),
            'mobilemode' => array(
                'title' => __('Mobile Mode', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Display Mobile optimized form if browsed through Mobile', 'paypal-for-woocommerce'),
                'default' => 'yes',
                'description' => sprintf(__('Disable this option if your theme is not compatible with Mobile. Otherwise You would get Silent Post Error in Layout C.', 'paypal-for-woocommerce'), 'https://developer.paypal.com/'),
            ),
            'invoice_prefix' => array(
                'title' => __('Invoice Prefix', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'woocommerce'),
                'default' => 'WC-PPADV',
                'desc_tip' => true,
            ),
            'page_collapse_bgcolor' => array(
                'title' => __('Page Collapse Border Color', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the border around the embedded template C.', 'paypal-for-woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_for_woocommerce_color_field'
            ),
            'page_collapse_textcolor' => array(
                'title' => __('Page Collapse Text Color', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the words "Pay with PayPal" and "Pay with credit or debit card".', 'paypal-for-woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_for_woocommerce_color_field'
            ),
            'page_button_bgcolor' => array(
                'title' => __('Page Button Background Color', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Sets the background color of the Pay Now / Submit button.', 'paypal-for-woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_for_woocommerce_color_field'
            ),
            'page_button_textcolor' => array(
                'title' => __('Page Button Text Color', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the text on the Pay Now / Submit button.', 'paypal-for-woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_for_woocommerce_color_field'
            ),
            'label_textcolor' => array(
                'title' => __('Label Text Color', 'paypal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Sets the color of the text for "card number", "expiration date", ..etc.', 'paypal-for-woocommerce'),
                'default' => '',
                'desc_tip' => true,
                'class' => 'paypal_for_woocommerce_color_field'
            ),
            'debug' => array(
                'title' => __('Debug Log', 'paypal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'paypal-for-woocommerce'),
                'default' => 'no',
                'description' => __('Log PayPal events, helpful in debugging when issue with transactions with the gateway', 'paypal-for-woocommerce'),
            )
        );
    }

// End init_form_fields()

    /**
     * There are no payment fields for paypal, but we want to show the description if set.
     *
     * @access public
     * @return void
     * */
    public function payment_fields() {

        if ($this->description)
            echo wpautop(wptexturize($this->description));
    }

    /**
     * Process the payment
     *
     * @access public
     * @return void
     * */
    public function process_payment($order_id) {

        //create the order object
        $order = new WC_Order($order_id);

        //use try/catch blocks to handle exceptions while processing the payment
        try {

            //get secure token
            $this->securetoken = $this->get_secure_token($order);

            //if valid securetoken
            if ($this->securetoken != "") {

                //add token values to post meta and we can use it later
                update_post_meta($order->id, '_secure_token_id', $this->secure_token_id);
                update_post_meta($order->id, '_secure_token', $this->securetoken);

                //Log
                if ($this->debug == 'yes')
                    $this->log->add('paypal_advanced', sprintf(__('Secured Token generated successfully for the order %s', 'paypal-for-woocommerce'), $order->get_order_number()));

                //redirect to pay
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
        } catch (Exception $e) {

            //add error
            wc_add_notice(__('Error:', 'paypal-for-woocommerce') . ' "' . $e->getMessage() . '"', 'error');

            //Log
            if ($this->debug == 'yes')
                $this->log->add('paypal_advanced', 'Error Occurred while processing the order ' . $order_id);
        }
        return;
    }

    /**
     * Process a refund if supported
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '') {

        $order = wc_get_order($order_id);

        if (!$order || !$order->get_transaction_id()) {
            return false;
        }

        if (!is_null($amount) && $order->get_total() > $amount) {
            return new WP_Error('paypal-advanced-error', __('Partial refund is not supported', 'woocommerce'));
        }



        //refund transaction, parameters
        $paypal_args = array(
            'USER' => $this->user,
            'VENDOR' => $this->loginid,
            'PARTNER' => $this->resellerid,
            'PWD[' . strlen($this->password) . ']' => $this->password,
            'ORIGID' => $order->get_transaction_id(),
            'TENDER' => 'C',
            'TRXTYPE' => 'C',
            'VERBOSITY' => 'HIGH'
        );

        $postData = ''; //stores the post data string
        foreach ($paypal_args as $key => $val) {
            $postData .='&' . $key . '=' . $val;
        }

        $postData = trim($postData, '&');

        // Using Curl post necessary information to the Paypal Site to generate the secured token 
        $response = wp_remote_post($this->hostaddr, array(
            'method' => 'POST',
            'body' => $postData,
            'timeout' => 70,
            'user-agent' => 'Woocommerce ' . WC_VERSION,
            'httpversion' => '1.1',
            'headers' => array('host' => 'www.paypal.com')
        ));

        if (is_wp_error($response))
            throw new Exception(__('There was a problem connecting to the payment gateway.', 'paypal-for-woocommerce'));

        if (empty($response['body']))
            throw new Exception(__('Empty response.', 'paypal-for-woocommerce'));


        // Parse and assign to array 
        $refund_result_arr = array(); //stores the response in array format
        parse_str($response['body'], $refund_result_arr);

        //Log
        if ($this->debug == 'yes') {
            $this->log->add('paypal_advanced', sprintf(__('Response of the refund transaction: %s', 'paypal-for-woocommerce'), print_r($refund_result_arr, true)));
        }

        if ($refund_result_arr['RESULT'] == 0) {

            $order->add_order_note(sprintf(__('Successfully Refunded - Refund Transaction ID: %s', 'woocommerce'), $refund_result_arr['PNREF']));
        } else {

            $order->add_order_note(sprintf(__('Refund Failed - Refund Transaction ID: %s, Error Msg: %s', 'woocommerce'), $refund_result_arr['PNREF'], $refund_result_arr['RESPMSG']));
            throw new Exception(sprintf(__('Refund Failed - Refund Transaction ID: %s, Error Msg: %s', 'woocommerce'), $refund_result_arr['PNREF'], $refund_result_arr['RESPMSG']));

            return false;
        }
        return true;
    }

    /**
     * Displays IFRAME/Redirect to show the hosted page in Paypal
     *
     * @access public
     * @return void
     * */
    public function receipt_page($order_id) {

        //get the mode
        $PF_MODE = $this->settings['testmode'] == 'yes' ? 'TEST' : 'LIVE';

        //create order object
        $order = new WC_Order($order_id);

        //get the tokens
        $this->secure_token_id = get_post_meta($order->id, '_secure_token_id', true);
        $this->securetoken = get_post_meta($order->id, '_secure_token', true);

        //Log the browser and its version
        if ($this->debug == 'yes')
            $this->log->add('paypal_advanced', sprintf(__('Browser Info: %s', 'paypal-for-woocommerce'), $_SERVER['HTTP_USER_AGENT']));

        //display the form in IFRAME, if it is layout C, otherwise redirect to paypal site
        if ($this->layout == 'MINLAYOUT' || $this->layout == 'C') {
            //define the url
            $location = 'https://payflowlink.paypal.com?mode=' . $PF_MODE . '&amp;SECURETOKEN=' . $this->securetoken . '&amp;SECURETOKENID=' . $this->secure_token_id;

            //Log
            if ($this->debug == 'yes')
                $this->log->add('paypal_advanced', sprintf(__('Show payment form(IFRAME) for the order %s as it is configured to use Layout C', 'paypal-for-woocommerce'), $order->get_order_number()));

            //display the form
            ?>
            <iframe id="paypal_for_woocommerce_iframe" src="<?php echo $location; ?>" width="550" height="565" scrolling="no" frameborder="0" border="0" allowtransparency="true"></iframe>

            <?php
        }else {
            //define the redirection url
            $location = 'https://payflowlink.paypal.com?mode=' . $PF_MODE . '&SECURETOKEN=' . $this->securetoken . '&SECURETOKENID=' . $this->secure_token_id;

            //Log
            if ($this->debug == 'yes')
                $this->log->add('paypal_advanced', sprintf(__('Show payment form redirecting to ' . $location . ' for the order %s as it is not configured to use Layout C', 'paypal-for-woocommerce'), $order->get_order_number()));

            //redirect
            wp_redirect($location);
            exit;
        }
    }

    /**
     * Limit the length of item names
     * @param  string $item_name
     * @return string
     */
    public function paypal_advanced_item_name($item_name) {
        if (strlen($item_name) > 36) {
            $item_name = substr($item_name, 0, 33) . '...';
        }
        return html_entity_decode($item_name, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * Limit the length of item desc
     * @param  string $item_desc
     * @return string
     */
    public function paypal_advanced_item_desc($item_desc) {
        if (strlen($item_desc) > 127) {
            $item_desc = substr($item_desc, 0, 124) . '...';
        }
        return html_entity_decode($item_desc, ENT_NOQUOTES, 'UTF-8');
    }

}
