<?php

if (!defined('ABSPATH')) {
    exit;
}

class Angelleye_PayPal_Express_Checkout_Helper {

    public $setting;
    public $function_helper;

    public function __construct($version) {
        try {
            global $wpdb;
            $this->version = $version;
            $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'woocommerce_paypal_express_settings'));
            $this->setting = isset($row->option_value) ? maybe_unserialize($row->option_value) : array();
            $paypal_pro_row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'woocommerce_paypal_pro_settings'));
            $this->paypal_pro_setting = isset($paypal_pro_row->option_value) ? maybe_unserialize($paypal_pro_row->option_value) : array();
            $this->paypal_pro_enabled = !empty($this->paypal_pro_setting['enabled']) ? $this->paypal_pro_setting['enabled'] : 'no';
            $paypal_flow_row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'woocommerce_paypal_pro_payflow_settings'));
            $this->paypal_flow_setting = isset($paypal_flow_row->option_value) ? maybe_unserialize($paypal_flow_row->option_value) : array();
            $this->paypal_flow_enabled = !empty($this->paypal_flow_setting['enabled']) ? $this->paypal_flow_setting['enabled'] : 'no';
            $this->enable_tokenized_payments = !empty($this->setting['enable_tokenized_payments']) ? $this->setting['enable_tokenized_payments'] : 'no';
            $this->save_abandoned_checkout_value = !empty($this->setting['save_abandoned_checkout']) ? $this->setting['save_abandoned_checkout'] : 'no';
            $this->save_abandoned_checkout = 'yes' === $this->save_abandoned_checkout_value;
            $this->checkout_with_pp_button_type = !empty($this->setting['checkout_with_pp_button_type']) ? $this->setting['checkout_with_pp_button_type'] : 'paypalimage';
            $this->pp_button_type_text_button = !empty($this->setting['pp_button_type_text_button']) ? $this->setting['pp_button_type_text_button'] : 'Proceed to Checkout';
            $this->pp_button_type_my_custom = !empty($this->setting['pp_button_type_my_custom']) ? $this->setting['pp_button_type_my_custom'] :  WC_Gateway_PayPal_Express_AngellEYE::angelleye_get_paypalimage();
            $this->show_on_product_page = !empty($this->setting['show_on_product_page']) ? $this->setting['show_on_product_page'] : 'no';
            $this->enabled = !empty($this->setting['enabled']) ? $this->setting['enabled'] : 'no';
            $this->show_on_checkout = !empty($this->setting['show_on_checkout']) ? $this->setting['show_on_checkout'] : 'top';
            $this->button_position = !empty($this->setting['button_position']) ? $this->setting['button_position'] : 'bottom';
            $this->show_on_cart = !empty($this->setting['show_on_cart']) ? $this->setting['show_on_cart'] : 'yes';
            $this->prevent_to_add_additional_item_value = !empty($this->setting['prevent_to_add_additional_item']) ? $this->setting['prevent_to_add_additional_item'] : 'no';
            $this->prevent_to_add_additional_item = 'yes' === $this->prevent_to_add_additional_item_value;
            $this->testmode_value = !empty($this->setting['testmode']) ? $this->setting['testmode'] : 'yes';
            $this->testmode = 'yes' === $this->testmode_value;
            $this->cancel_page = !empty($this->setting['cancel_page']) ? $this->setting['cancel_page'] : '';
            if( $this->testmode == false ) {
                $this->testmode = AngellEYE_Utility::angelleye_paypal_for_woocommerce_is_set_sandbox_product();
            }
            if (substr(get_option("woocommerce_default_country"), 0, 2) == 'US' || substr(get_option("woocommerce_default_country"), 0, 2) == 'GB') {
                $this->is_us_or_uk = true;
            } else {
                $this->is_us_or_uk = false;
            }
            $this->show_paypal_credit = !empty($this->setting['show_paypal_credit']) ? $this->setting['show_paypal_credit'] : 'yes';
            if ($this->is_us_or_uk == false) {
                $this->show_paypal_credit = 'no';
            }
            if ($this->testmode == true) {
                $this->API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
                $this->PAYPAL_URL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
                $this->api_username = !empty($this->setting['sandbox_api_username']) ? $this->setting['sandbox_api_username'] : '';
                $this->api_password = !empty($this->setting['sandbox_api_password']) ? $this->setting['sandbox_api_password'] : '';
                $this->api_signature = !empty($this->setting['sandbox_api_signature']) ? $this->setting['sandbox_api_signature'] : '';
            } else {
                $this->API_Endpoint = "https://api-3t.paypal.com/nvp";
                $this->PAYPAL_URL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
                $this->api_username = !empty($this->setting['api_username']) ? $this->setting['api_username'] : '';
                $this->api_password = !empty($this->setting['api_password']) ? $this->setting['api_password'] : '';
                $this->api_signature = !empty($this->setting['api_signature']) ? $this->setting['api_signature'] : '';
            }
            $this->angelleye_skip_text = !empty($this->setting['angelleye_skip_text']) ? $this->setting['angelleye_skip_text'] : 'Skip the forms and pay faster with PayPal!';
            add_action('woocommerce_after_add_to_cart_button', array($this, 'buy_now_button'));
            if($this->save_abandoned_checkout == false) {
                if (version_compare(WC_VERSION, '3.0', '<')) {
                    add_action('woocommerce_after_checkout_validation', array($this, 'angelleye_paypal_express_checkout_redirect_to_paypal'), 99, 1);
                } else {
                    add_action('woocommerce_after_checkout_validation', array($this, 'angelleye_paypal_express_checkout_redirect_to_paypal'), 99, 2);
                }
            }
            add_action('woocommerce_add_to_cart_redirect', array($this, 'add_to_cart_redirect'));
            add_action('woocommerce_checkout_billing', array($this, 'ec_set_checkout_post_data'));
            add_action('woocommerce_available_payment_gateways', array($this, 'ec_disable_gateways'));
            add_filter('body_class', array($this, 'ec_add_body_class'));
            add_action('woocommerce_checkout_fields', array($this, 'ec_display_checkout_fields'));
            add_action('woocommerce_checkout_billing', array($this, 'ec_formatted_billing_address'), 9);
            add_action('woocommerce_checkout_shipping', array($this, 'ec_formatted_shipping_address'), 9);
            add_filter('woocommerce_terms_is_checked_default', array($this, 'ec_terms_express_checkout'));
            add_action('woocommerce_cart_emptied', array($this, 'ec_clear_session_data'));
            add_filter('woocommerce_thankyou_order_received_text', array($this, 'ec_order_received_text'), 10, 2);
            add_action('wp_enqueue_scripts', array($this, 'ec_enqueue_scripts_product_page'), 0);
            add_action('woocommerce_before_cart_table', array($this, 'top_cart_button'));
            if($this->show_on_cart == 'yes') {
                add_action( 'woocommerce_after_mini_cart', array($this, 'mini_cart_button'));
            }
            add_action( 'woocommerce_before_cart', array( $this, 'woocommerce_before_cart'), 12 );
            add_filter('woocommerce_is_sold_individually', array($this, 'angelleye_woocommerce_is_sold_individually'), 10, 2);
            add_filter('woocommerce_ship_to_different_address_checked', array($this, 'angelleye_ship_to_different_address_checked'), 10,1);
            add_filter('woocommerce_order_button_html', array($this, 'angelleye_woocommerce_order_button_html'), 10, 1);
            add_filter( 'woocommerce_coupons_enabled', array($this, 'angelleye_woocommerce_coupons_enabled'), 10, 1);
            add_action( 'woocommerce_cart_shipping_packages', array( $this, 'maybe_add_shipping_information' ) );
            if (AngellEYE_Utility::is_express_checkout_credentials_is_set()) {
                if ($this->button_position == 'bottom' || $this->button_position == 'both') {
                    add_action('woocommerce_proceed_to_checkout', array($this, 'woocommerce_paypal_express_checkout_button_angelleye'), 22);
                }
            }
            if ($this->enabled == 'yes' && ($this->show_on_checkout == 'top' || $this->show_on_checkout == 'both')) {
                add_action('woocommerce_before_checkout_form', array($this, 'checkout_message'), 5);
            }
            if (!class_exists('WC_Gateway_PayPal_Express_Function_AngellEYE')) {
                require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/angelleye-includes/express-checkout/class-wc-gateway-paypal-express-function-angelleye.php' );
            }
            $this->function_helper = new WC_Gateway_PayPal_Express_Function_AngellEYE();
            $this->is_order_completed = true;
        } catch (Exception $ex) {

        }
    }

    public function buy_now_button() {
        try {
            global $product;
            $is_ec_button_enable_product_level = get_post_meta($product->get_id(), '_enable_ec_button', true);
            if( $this->enabled == 'yes' && $this->show_on_product_page == 'yes' && $is_ec_button_enable_product_level == 'yes') {
                $ec_html_button = '';
                $_product = wc_get_product($product->get_id());
                if ( $_product->is_type( 'simple' ) && (version_compare( WC_VERSION, '3.0', '<' ) == false)) {
                    ?>
                    <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />
                    <?php
                }
                $ec_html_button .= '<div class="angelleye_button_single">';
                $button_dynamic_class = 'single_variation_wrap_angelleye_' . $product->get_id();
                $hide = '';
                if ($_product->is_type('variation') || $_product->is_type('external') || $_product->get_price() == 0 || $_product->get_price() == '') {
                    $hide = 'display:none;';
                }
                $add_to_cart_action = esc_url(add_query_arg('express_checkout', '1'));
                switch ($this->checkout_with_pp_button_type) {
                    case 'textbutton':
                        $ec_html_button .= '<input data-action="' . esc_url($add_to_cart_action) . '" type="button" style="'.$hide.'" class="single_add_to_cart_button button alt paypal_checkout_button single_variation_wrap_angelleye paypal_checkout_button button alt ec_product_page_button_type_textbutton "' . $button_dynamic_class . '" name="express_checkout"  value="' . $this->pp_button_type_text_button . '"/>';
                        break;
                    case "paypalimage":
                        $button_img = WC_Gateway_PayPal_Express_AngellEYE::angelleye_get_paypalimage();
                        $ec_html_button .= '<input data-action="' . esc_url($add_to_cart_action) . '" type="image" src="'.$button_img.'" style="'.$hide.'" class="single_add_to_cart_button button alt paypal_checkout_button single_variation_wrap_angelleye ec_product_page_button_type_paypalimage ' . $button_dynamic_class . '" name="express_checkout" value="' . __('Pay with PayPal', 'paypal-for-woocommerce') . '"/>';
                        break;
                    case "customimage":
                        $ec_html_button .= '<input data-action="' . esc_url($add_to_cart_action) . '" type="image" src="'.$this->pp_button_type_my_custom.'" style="'.$hide.'" class="single_add_to_cart_button button alt paypal_checkout_button single_variation_wrap_angelleye ec_product_page_button_type_customimage ' . $button_dynamic_class . '" name="express_checkout" value="' . __('Pay with PayPal', 'paypal-for-woocommerce') . '"/>';
                        break;
                }
                if ($this->show_paypal_credit == 'yes') {
                    $paypal_credit_button_markup = '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('use_paypal_credit', 'true', add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/'))))) . '" >';
                    $paypal_credit_button_markup .= '<img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-small.png" width="148" height="26" class="ppcreditlogo ec_checkout_page_button_type_pc"  align="top" alt="' . __('Check out with PayPal Credit', 'paypal-for-woocommerce') . '" />';
                    $paypal_credit_button_markup .= '</a>';
                    $ec_html_button .= $paypal_credit_button_markup;
                }
                $ec_html_button .= '</div>';
                if ($this->enable_tokenized_payments == 'yes') {
                    $ec_html_button .= $this->function_helper->angelleye_ec_save_payment_method_checkbox();
                }
                echo apply_filters('angelleye_ec_product_page_buy_now_button', $ec_html_button);
            }
        } catch (Exception $ex) {

        }
    }

    public function angelleye_paypal_express_checkout_redirect_to_paypal($data, $errors = null) {
        $notice_count = 0;
        if( !empty($errors)) {
            foreach ( $errors->get_error_messages() as $message ) {
                 $notice_count = $notice_count + 1;
            }
        } else {
            $notice_count = wc_notice_count( 'error' );
        }
        if ( empty( $_POST['woocommerce_checkout_update_totals'] ) && 0 === $notice_count ) {
            try {
                WC()->session->set( 'post_data', $_POST);
                if (isset($_POST['payment_method']) && 'paypal_express' === $_POST['payment_method'] && $this->function_helper->ec_notice_count('error') == 0) {
                    $this->function_helper->ec_redirect_after_checkout();
                }
            } catch (Exception $ex) {
                
            }
        } 
        
    }

    public function add_to_cart_redirect($url = null) {
        try {
            if (isset($_REQUEST['express_checkout']) || isset($_REQUEST['express_checkout_x'])) {
                wc_clear_notices();
                if( isset($_POST['wc-paypal_express-new-payment-method']) && $_POST['wc-paypal_express-new-payment-method'] = 'on' ) {
                    WC()->session->set( 'ec_save_to_account', 'on');
                }
                $url = esc_url_raw(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/'))));
            }
            return $url;
        } catch (Exception $ex) {

        }
    }

    public function ec_get_session_data($key = '') {
        try {
            $session_data = WC()->session->get( 'paypal_express_checkout' );
            if (isset($session_data[$key])) {
                $session_data = $session_data[$key];
            }
            return $session_data;
        } catch (Exception $ex) {

        }
    }

    public function ec_is_available() {
        try {
            return $this->function_helper->express_checkout_is_available();
        } catch (Exception $ex) {

        }
    }

    public function ec_set_checkout_post_data() {
        try {
            if (!$this->function_helper->ec_is_express_checkout() || !$this->ec_get_session_data('shipping_details')) {
                return;
            }
            foreach ($this->ec_get_session_data('shipping_details') as $field => $value) {
                if ($value) {
                    $_POST['billing_' . $field] = $value;
                }
            }
            $post_data = WC()->session->get( 'post_data' );
            $_POST['order_comments'] = isset($post_data['order_comments']) ? $post_data['order_comments'] : '';
            if( !empty($post_data) ) {
                foreach ($post_data as $key => $value) {
                    $_POST[$key] = $value;
                }
            }
            $this->chosen = true;
        } catch (Exception $ex) {

        }
    }

    public function ec_display_checkout_fields($checkout_fields) {
        try {
            if ($this->function_helper->ec_is_express_checkout() && $this->ec_get_session_data('shipping_details')) {
                foreach ($this->ec_get_session_data('shipping_details') as $field => $value) {
                    if (isset($checkout_fields['billing']) && isset($checkout_fields['billing']['billing_' . $field])) {
                        $required = isset($checkout_fields['billing']['billing_' . $field]['required']) && $checkout_fields['billing']['billing_' . $field]['required'];
                        if (!$required || $required && !empty($value)) {
                            $checkout_fields['billing']['billing_' . $field]['class'][] = 'express-provided';
                            $checkout_fields['billing']['billing_' . $field]['class'][] = 'hidden';
                        }
                    }
                }
            }
            return $checkout_fields;
        } catch (Exception $ex) {

        }
    }

    public function ec_formatted_address($type) {
        $post_data = WC()->session->get( 'post_data' );
        if( !empty($post_data) ) {
            $_POST = $post_data;
        }
        try {
            if (!$this->function_helper->ec_is_express_checkout()) {
                return;
            }
            if (!$this->is_order_completed) {
                return;
            }
            ?>
            <div class="express-provided-address">
<!--                <a href="#" class="ex-show-address-fields" data-type="<?php echo esc_attr($type); ?>"><?php esc_html_e('Edit', 'paypal-for-woocommerce'); ?></a>-->
                <address>
                    <?php
                    $address = array(
                        'first_name' => WC()->checkout->get_value($type . '_first_name'),
                        'last_name' => WC()->checkout->get_value($type . '_last_name'),
                        'company' => WC()->checkout->get_value($type . '_company'),
                        'address_1' => WC()->checkout->get_value($type . '_address_1'),
                        'address_2' => WC()->checkout->get_value($type . '_address_2'),
                        'city' => WC()->checkout->get_value($type . '_city'),
                        'state' => WC()->checkout->get_value($type . '_state'),
                        'postcode' => WC()->checkout->get_value($type . '_postcode'),
                        'country' => WC()->checkout->get_value($type . '_country'),
                    );
                    echo WC()->countries->get_formatted_address($address);
                    ?>
                </address>
            </div>
            <?php
        } catch (Exception $ex) {

        }
    }

    public function ec_disable_gateways($gateways) {
        try {
            if ($this->function_helper->ec_is_express_checkout()) {
                foreach ($gateways as $id => $gateway) {
                    if ($id !== 'paypal_express') {
                        unset($gateways[$id]);
                    }
                }
            }
            return $gateways;
        } catch (Exception $ex) {

        }
    }

    public function ec_add_body_class($classes) {
        try {
            if (sizeof(WC()->session) == 0) {
                return $classes;
            }
            $paypal_express_terms = WC()->session->get( 'paypal_express_terms' );
            if ($this->ec_is_checkout() && $this->function_helper->ec_is_express_checkout()) {
                $classes[] = 'express-checkout';
                if ($this->show_on_checkout && isset($paypal_express_terms)) {
                    $classes[] = 'express-hide-terms';
                }
            }
            return $classes;
        } catch (Exception $ex) {

        }
    }

    public function ec_formatted_billing_address() {
        if($this->function_helper->ec_is_express_checkout()) {
            echo '<h3>' . __( 'Billing details', 'paypal-for-woocommerce' ) . '</h3>';
            $post_data = WC()->session->get( 'post_data' );
            if(!empty($post_data['ship_to_different_address']) && $post_data['ship_to_different_address'] == '1') {
                $this->ec_formatted_address('billing');
            } else {
                $this->ec_formatted_address('shipping');
            }
            
        ?>
        <style type="text/css">
            .woocommerce-billing-fields > h3 {
                display: none;
            }
        </style>
        <?php
        }
    }
    
    public function ec_formatted_shipping_address() {
        if($this->function_helper->ec_is_express_checkout()) {
            echo '<h3>' . __( 'Shipping details', 'paypal-for-woocommerce' ) . '</h3>';
            $post_data = WC()->session->get( 'post_data' );
            if(!empty($post_data['ship_to_different_address']) && $post_data['ship_to_different_address'] == '1') {
                $this->ec_formatted_address('shipping');
            } else {
                $this->ec_formatted_address('billing');
            }
            ?>
            <style type="text/css">
                .woocommerce-shipping-fields {
                    display: none;
                }
            </style>
            <?php
        }
    }

    public function ec_terms_express_checkout($checked_default) {
        if (sizeof(WC()->session) == 0) {
            return $checked_default;
        }
        if (!$this->ec_is_available() || !$this->function_helper->ec_is_express_checkout()) {
            return $checked_default;
        }
        $paypal_express_terms = WC()->session->get( 'paypal_express_terms' );
        if ($this->show_on_checkout && isset($paypal_express_terms)) {
            $checked_default = true;
        }
        return $checked_default;
    }

    public function ec_clear_session_data() {
        unset(WC()->session->paypal_express_checkout);
        unset(WC()->session->paypal_express_terms);
        unset(WC()->session->ec_save_to_account);
        unset(WC()->session->held_order_received_text);
        unset(WC()->session->post_data);
        unset(WC()->session->shiptoname);
        unset(WC()->session->payeremail);
    }

    public function ec_is_checkout() {
        return is_page(wc_get_page_id('checkout')) || apply_filters('woocommerce_is_checkout', false);
    }

    public function ec_order_received_text($text, $order) {
        $held_order_received_text = WC()->session->get( 'held_order_received_text' );
        if ($order && $order->has_status('on-hold') && isset($held_order_received_text)) {
            $text = $held_order_received_text;
            unset(WC()->session->held_order_received_text);
        }
        return $text;
    }

    public function ec_enqueue_scripts_product_page() {
        try {
            wp_enqueue_style('angelleye-express-checkout-css', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/css/angelleye-express-checkout.css', array(), $this->version, 'all');
            if (is_checkout()) {
                wp_enqueue_script('angelleye-express-checkout-js', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/js/angelleye-express-checkout.js', array(), $this->version, 'all');
                wp_enqueue_script('angelleye-express-checkout-custom', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/js/angelleye-express-checkout-custom.js', array(), $this->version, 'all');
                wp_localize_script('angelleye-express-checkout-js', 'is_page_name', 'checkout_page');
                wp_localize_script('angelleye-express-checkout-custom', 'is_page_name', 'checkout_page');
            }
            if (is_product()) {
                wp_enqueue_script('angelleye-express-checkout-custom', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/js/angelleye-express-checkout-custom.js', array(), $this->version, 'all');
                wp_localize_script('angelleye-express-checkout-custom', 'is_page_name', 'single_product_page');
            }
            if (is_cart()) {
                wp_enqueue_script('angelleye-express-checkout-custom', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/js/angelleye-express-checkout-custom.js', array(), $this->version, 'all');
                wp_localize_script('angelleye-express-checkout-custom', 'is_page_name', 'cart_page');
            }
            return true;
        } catch (Exception $ex) {

        }
    }

    public function top_cart_button() {
        if (AngellEYE_Utility::is_express_checkout_credentials_is_set()) {
            $top_cart_button_html = '';
            if ($this->button_position == 'top' || $this->button_position == 'both') {
                do_action('angelleye_ec_before_top_cart_button', $this);
                $top_cart_button_html .= '<div class="wc-proceed-to-checkout angelleye_cart_button">';
                $top_cart_button_html .= $this->woocommerce_paypal_express_checkout_button_angelleye($return = true);
                $top_cart_button_html .= '</div>';
                echo apply_filters('angelleye_ec_top_cart_button', $top_cart_button_html);
                do_action('angelleye_ec_after_top_cart_button', $this);                
            }
        }
    }
    
     public function mini_cart_button() {
          if (AngellEYE_Utility::is_express_checkout_credentials_is_set()) {
             $this->woocommerce_before_cart();
             $mini_cart_button_html = '';
             $mini_cart_button_html .= $this->woocommerce_paypal_express_checkout_button_angelleye($return = true);
             $mini_cart_button_html .= "<div class='clear'></div>";
             echo apply_filters('angelleye_ec_mini_cart_button_html', $mini_cart_button_html);
          }
     }

    public function woocommerce_paypal_express_checkout_button_angelleye($return = false) {
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            define('WOOCOMMERCE_CHECKOUT', true);
        }
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }
        WC()->cart->calculate_totals();
        if (!AngellEYE_Utility::is_valid_for_use_paypal_express()) {
            return false;
        }
        if ($this->enabled == 'yes' && $this->show_on_cart == 'yes' && 0 < WC()->cart->total) {
            $cart_button_html = '';
            if($return == false) {
                do_action('angelleye_ec_before_buttom_cart_button', $this);
            }
            switch ($this->checkout_with_pp_button_type) {
                case 'textbutton':
                    $cart_button_html .= '<a  class="paypal_checkout_button button alt ec_checkout_page_button_type_textbutton" href="' . esc_url(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')))) . '">' . $this->pp_button_type_text_button . '</a>';
                    break;
                case 'paypalimage':
                    $cart_button_html .= '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')))) . '">';
                    $cart_button_html .= '<img src=' . WC_Gateway_PayPal_Express_AngellEYE::angelleye_get_paypalimage() . ' class="ec_checkout_page_button_type_paypalimage"  align="top" alt="' . __('Pay with PayPal', 'paypal-for-woocommerce') . '" />';
                    $cart_button_html .= "</a>";
                    break;
                case 'customimage':
                    $cart_button_html .= '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')))) . '">';
                    $cart_button_html .= '<img src="' . $this->pp_button_type_my_custom . '" class="ec_checkout_page_button_type_customimage" align="top" alt="' . __('Pay with PayPal', 'paypal-for-woocommerce') . '" />';
                    $cart_button_html .= "</a>";
                    break;
            }
            if ($this->show_paypal_credit == 'yes') {
                $paypal_credit_button_markup = '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('use_paypal_credit', 'true', add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/'))))) . '" >';
                $paypal_credit_button_markup .= '<img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-small.png" width="148" height="26" class="ppcreditlogo ec_checkout_page_button_type_pc"  align="top" alt="' . __('Check out with PayPal Credit', 'paypal-for-woocommerce') . '" />';
                $paypal_credit_button_markup .= '</a>';
                $cart_button_html .= $paypal_credit_button_markup;
            }
            if ($this->enable_tokenized_payments == 'yes') {
                $cart_button_html .= $this->function_helper->angelleye_ec_save_payment_method_checkbox();
            }
            if($return == true) {
                return $cart_button_html;
            } else {
                echo $cart_button_html;
            }
            do_action('angelleye_ec_after_buttom_cart_button', $this);
        }
    }

    public function checkout_message() {
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            define('WOOCOMMERCE_CHECKOUT', true);
        }
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }
        WC()->cart->calculate_totals(); 
        if (AngellEYE_Utility::is_express_checkout_credentials_is_set() == false) {
            return false;
        }
        if (!AngellEYE_Utility::is_valid_for_use_paypal_express()) {
            return false;
        }
        if (WC()->cart->total > 0) {
            $ec_top_checkout_button = '';
            wp_enqueue_script('angelleye_button');
            echo '<div id="checkout_paypal_message" class="woocommerce-info info">';
            
            do_action('angelleye_ec_checkout_page_before_checkout_button', $this);
            $ec_top_checkout_button .= '<div id="paypal_box_button">';
            switch ($this->checkout_with_pp_button_type) {
                case "textbutton":
                    $ec_top_checkout_button .= '<div class="paypal_ec_textbutton">';
                    $ec_top_checkout_button .= '<a class="paypal_checkout_button paypal_checkout_button_text button alt" href="' . esc_url(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')))) . '">' . $this->pp_button_type_text_button . '</a>';
                    $ec_top_checkout_button .= '</div>';
                    break;
                case "paypalimage":
                    $ec_top_checkout_button .= '<div id="paypal_ec_button">';
                    $ec_top_checkout_button .= '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')))) . '">';
                    $ec_top_checkout_button .= "<img src='" . WC_Gateway_PayPal_Express_AngellEYE::angelleye_get_paypalimage() . "' class='ec_checkout_page_button_type_paypalimage'  border='0' alt='" . __('Pay with PayPal', 'paypal-for-woocommerce') . "'/>";
                    $ec_top_checkout_button .= "</a>";
                    $ec_top_checkout_button .= '</div>';
                    break;
                case "customimage":
                    $button_img = $this->pp_button_type_my_custom;
                    $ec_top_checkout_button .= '<div id="paypal_ec_button">';
                    $ec_top_checkout_button .= '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')))) . '">';
                    $ec_top_checkout_button .= "<img src='{$button_img}' class='ec_checkout_page_button_type_paypalimage' width='150' border='0' alt='" . __('Pay with PayPal', 'paypal-for-woocommerce') . "'/>";
                    $ec_top_checkout_button .= "</a>";
                    $ec_top_checkout_button .= '</div>';
                    break;
            }
            if ($this->show_paypal_credit == 'yes') {
                $paypal_credit_button_markup = '<div id="paypal_ec_paypal_credit_button">';
                $paypal_credit_button_markup .= '<a class="paypal_checkout_button" href="' . esc_url(add_query_arg('use_paypal_credit', 'true', add_query_arg('pp_action', 'set_express_checkout', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/'))))) . '" >';
                $paypal_credit_button_markup .= "<img src='https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-small.png' class='ec_checkout_page_button_type_paypalimage' alt='Check out with PayPal Credit'/>";
                $paypal_credit_button_markup .= '</a>';
                $paypal_credit_button_markup .= '</div>';
                $ec_top_checkout_button .= $paypal_credit_button_markup;
            }
            if($this->enable_tokenized_payments == 'yes') {
                $ec_top_checkout_button .= $this->function_helper->angelleye_ec_save_payment_method_checkbox();
            }
            $ec_top_checkout_button .= '<div class="woocommerce_paypal_ec_checkout_message">';
            $ec_top_checkout_button .= '<p class="checkoutStatus">' . $this->angelleye_skip_text . '</p>';
            $ec_top_checkout_button .= '</div>';
            echo apply_filters('angelleye_ec_checkout_page_buy_now_nutton', $ec_top_checkout_button);
            do_action('angelleye_ec_checkout_page_after_checkout_button', $this);
            echo '<div class="clear"></div></div>';
            echo '</div>';
            echo '<div style="clear:both; margin-bottom:10px;"></div>';
        }
    }
    
    public function angelleye_woocommerce_is_sold_individually($return, $data) {
        if (isset($_REQUEST['express_checkout']) || isset($_REQUEST['express_checkout_x'])) {
            if($this->prevent_to_add_additional_item) {
		return true;
            }
        }
        return $return;
    }
    
    public function angelleye_ship_to_different_address_checked($bool) {
        if (sizeof(WC()->session) == 0) {
            return $bool;
        }
        $post_data = WC()->session->get( 'post_data' );
        if(!empty($post_data['ship_to_different_address']) && $post_data['ship_to_different_address'] == '1') {
            return 1;
        }
        return $bool;
    }
    
    public function woocommerce_before_cart() {
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            define('WOOCOMMERCE_CHECKOUT', true);
        }
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }
        WC()->cart->calculate_totals();
        $payment_gateways_count = 0;
        echo "<style>table.cart td.actions .input-text, table.cart td.actions .button, table.cart td.actions .checkout-button {margin-bottom: 0.53em !important}</style>";
        if ($this->enabled == 'yes' && 0 < WC()->cart->total) {
            $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
            unset($payment_gateways['paypal_pro']);
            unset($payment_gateways['paypal_pro_payflow']);
            $payment_gateway_count = count($payment_gateways);
            if( $this->show_on_checkout != 'regular' && $this->show_on_checkout != 'both') {
                $payment_gateway_count = $payment_gateway_count + 1;
            }
            if ($this->enabled == 'yes' && $payment_gateway_count == 1) {
                if ($this->paypal_pro_enabled == 'yes' || $this->paypal_flow_enabled == 'yes') {
                    $checkout_button_display_text = $this->show_on_cart == 'yes' ? __('Pay with Credit Card', 'paypal-for-woocommerce') : __('Proceed to Checkout','paypal-for-woocommerce');
                    echo '<script type="text/javascript">
                                jQuery(document).ready(function(){
                                    if (jQuery(".checkout-button, .button.checkout.wc-forward").is("input")) {
                                        jQuery(".checkout-button, .button.checkout.wc-forward").val("' . $checkout_button_display_text . '");
                                    } else {
                                        jQuery(".checkout-button, .button.checkout.wc-forward").html("<span>' . $checkout_button_display_text . '</span>");
                                    }
                                });
                              </script>';
                } elseif ($this->show_on_cart == 'yes') {
                    echo '<style> input.checkout-button,
                                 a.checkout-button, .button.checkout.wc-forward {
                                    display: none !important;
                                }</style>';
                }
            }
        }
    }
    
    public function angelleye_woocommerce_order_button_html($order_button_hrml) {
        if($this->function_helper->ec_is_express_checkout()) {
            $order_button_text = __('Cancel order', 'paypal-for-woocommerce');
            $cancel_order_url = add_query_arg('pp_action', 'cancel_order', WC()->api_request_url('WC_Gateway_PayPal_Express_AngellEYE'));
            $order_button_hrml = apply_filters( 'angelleye_review_order_cance_button_html', '<a class="button alt angelleye_cancel" name="woocommerce_checkout_place_order" href="' . esc_attr( $cancel_order_url ) . '" >' .$order_button_text. '</a>'. $order_button_hrml );
        }
        return $order_button_hrml;
    }
    
    public function angelleye_woocommerce_coupons_enabled($is_coupons_enabled) {
        if($this->function_helper->ec_is_express_checkout()) {
            return $is_coupons_enabled = false;
        } else {
            return $is_coupons_enabled;
        }
    }
    
    public function maybe_add_shipping_information($packages) {
        if ($this->function_helper->ec_is_express_checkout() || $this->ec_get_session_data('shipping_details')) {
            $destination = $this->ec_get_session_data('shipping_details');
            if( !empty($destination) ) {
                $packages[0]['destination']['country']   = $destination['country'];
                $packages[0]['destination']['state']     = $destination['state'];
                $packages[0]['destination']['postcode']  = $destination['postcode'];
                $packages[0]['destination']['city']      = $destination['city'];
                $packages[0]['destination']['address']   = $destination['address_1'];
                $packages[0]['destination']['address_2'] = $destination['address_2'];
            }
        }
        return $packages;
    }
}
