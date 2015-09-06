<?php
/**
 * Review Order
 */

global $woocommerce;
$checked = get_option('woocommerce_enable_guest_checkout');

//Add hook to show login form or not
$show_login = apply_filters('paypal-for-woocommerce-show-login', !is_user_logged_in() && $checked==="no" && isset($_REQUEST['pp_action']));
### After PayPal payment method confirmation, user is redirected back to this page with token and Payer ID ###
if (isset($_GET["token"]) && isset($_GET["PayerID"]) && isset($_GET["paymentId"])) {
    $_SESSION['payment_args'] = array('token' => $_GET["token"], 'PayerID' => $_GET["PayerID"], 'paymentId' => $_GET["paymentId"]);
    $frm_act = add_query_arg('pp_action', 'payaction', add_query_arg('wc-api', 'WC_Gateway_PayPal_Plus_AngellEYE', home_url('/')));
} else {
    $frm_act = add_query_arg('pp_action', 'payaction', add_query_arg('wc-api', 'WC_Gateway_PayPal_Express_AngellEYE', home_url('/')));
}

?>
<form class="angelleye_checkout" method="POST" action="<?php echo $frm_act;?>">

<div id="paypalexpress_order_review">
        <?php woocommerce_order_review();?>
</div>

<?php if ( WC()->cart->needs_shipping()  ) : ?>


    <div class="title">
        <h2><?php _e( 'Customer details', 'woocommerce' ); ?></h2>
    </div>

    <div class="col2-set addresses">

        <div class="col-1">

            <div class="title">
                <h3><?php _e( 'Shipping Address', 'woocommerce' ); ?></h3>
            </div>
            <div class="address">
                <p>
                    <?php
                    // Formatted Addresses
                    $address = array(
                    'first_name' 	=> WC()->customer->shiptoname,
                    'company'		=> WC()->customer->company,
                    'address_1'		=> WC()->customer->get_address(),
                    'address_2'		=> "",
                    'city'			=> WC()->customer->get_city(),
                    'state'			=> WC()->customer->get_state(),
                    'postcode'		=> WC()->customer->get_postcode(),
                    'country'		=> WC()->customer->get_country()
                    ) ;

                    echo WC()->countries->get_formatted_address( $address );
                    ?>
                </p>
            </div>

        </div><!-- /.col-1 -->
        <div class="col-2">
        	<?php 
        	$woocommerce_paypal_express_settings = maybe_unserialize(get_option('woocommerce_paypal_express_settings'));
        	if( isset($woocommerce_paypal_express_settings['billing_address']) && $woocommerce_paypal_express_settings['billing_address'] == 'yes') :
        	// Formatted Addresses
        	$user_submit_form = maybe_unserialize(WC()->session->checkout_form);

        	if( (isset($user_submit_form) && !empty($user_submit_form)) && is_array($user_submit_form) ) {
        		if( isset($user_submit_form['ship_to_different_address']) && $user_submit_form['ship_to_different_address'] == true ) {
        			$billing_address = array(
        			'first_name' 	=> $user_submit_form['billing_first_name'],
        			'last_name'		=> $user_submit_form['billing_last_name'],
        			'company'		=> $user_submit_form['billing_company'],
        			'address_1'		=> $user_submit_form['billing_address_1'],
        			'address_2'		=> $user_submit_form['billing_address_2'],
        			'city'			=> $user_submit_form['billing_city'],
        			'state'			=> $user_submit_form['billing_state'],
        			'postcode'		=> $user_submit_form['billing_postcode'],
        			'country'		=> $user_submit_form['billing_country']
        			) ;
        		}
        	} else {

        		$billing_address = array(
        		'first_name' 	=> WC()->customer->shiptoname,
        		'company'		=> WC()->customer->company,
        		'address_1'		=> WC()->customer->get_address(),
        		'address_2'		=> "",
        		'city'			=> WC()->customer->get_city(),
        		'state'			=> WC()->customer->get_state(),
        		'postcode'		=> WC()->customer->get_postcode(),
        		'country'		=> WC()->customer->get_country()
        		) ;
        	}

        	if( isset($billing_address) && !empty($billing_address) ) :
        		?>
        	<div class="col-1">

	            <div class="title">
	                <h3><?php _e( 'Billing Address', 'woocommerce' ); ?></h3>
	            </div>
	            <div class="address">
	                <p>
	                    
	                    <?php 
	                    echo WC()->countries->get_formatted_address( $billing_address );

	                    ?>
	                </p>
	            </div>

        </div><!-- /.col-1 -->
        	<?php endif; endif; ?>
        </div><!-- /.col-2 -->
    </div><!-- /.col2-set -->
<?php endif; ?>
<?php if ( $show_login ):  ?>
</form>
    <style type="text/css">

        .woocommerce #content p.form-row input.button,
        .woocommerce #respond p.form-row input#submit,
        .woocommerce p.form-row a.button,
        .woocommerce p.form-row button.button,
        .woocommerce p.form-row input.button,
        .woocommerce-page p.form-row #content input.button,
        .woocommerce-page p.form-row #respond input#submit,
        .woocommerce-page p.form-row a.button,
        .woocommerce-page p.form-row button.button,
        .woocommerce-page p.form-row input.button{
            display: block !important;
        }
    </style>
    <div class="title">
        <h2><?php _e( 'Login', 'woocommerce' ); ?></h2>
    </div>
    <form name="" action="" method="post">
        <?php
        function curPageURL() {
        	$pageURL = 'http';
        	if (@$_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        	$pageURL .= "://";
        	if ($_SERVER["SERVER_PORT"] != "80") {
        		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        	} else {
        		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        	}
        	return $pageURL;
        }

        woocommerce_login_form(
        array(
        'message'  => 'Please login or create an account to complete your order.',
        'redirect' => curPageURL(),
        'hidden'   => true
        )
        );
        $result = unserialize(WC()->session->RESULT);
        $email = (!empty($_POST['email']))?$_POST['email']:$result['EMAIL'];
        ?>
    </form>
    <div class="title">
        <h2><?php _e( 'Create A New Account', 'woocommerce' ); ?></h2>
    </div>
    <form action="" method="post">
        <p class="form-row form-row-first">
            <label for="paypalexpress_order_review_username">Username:<span class="required">*</span></label>
            <input style="width: 100%;" type="text" name="username" id="paypalexpress_order_review_username" value="" />
        </p>
        <p class="form-row form-row-last">
            <label for="paypalexpress_order_review_email">Email:<span class="required">*</span></label>
            <input style="width: 100%;" type="email" name="email" id="paypalexpress_order_review_email" value="<?php echo $email; ?>" />
        </p>
        <div class="clear"></div>
        <p class="form-row form-row-first">
            <label for="paypalexpress_order_review_password">Password:<span class="required">*</span></label>
            <input type="password" name="password" id="paypalexpress_order_review_password" class="input-text" />
        </p>
        <p class="form-row form-row-last">
            <label for="paypalexpress_order_review_repassword">Re Password:<span class="required">*</span></label>
            <input type="password" name="repassword" id="paypalexpress_order_review_repassword" class="input-text"/>
        </p>
        <div class="clear"></div>
        <p>
            <input class="button" type="submit" name="createaccount" value="Create Account" />
            <input type="hidden" name="address" value="<?php echo WC()->customer->get_address(); ?>">
        </p>
    </form>
<?php else:
global $pp_settings;
$cancel_url = isset( $pp_settings['cancel_page'] ) ? get_permalink( $pp_settings['cancel_page'] ) : $woocommerce->cart->get_cart_url();
$cancel_url = apply_filters( 'angelleye_review_order_cance_url', $cancel_url );
echo '<div class="clear"></div>';
echo '<p><a class="button angelleye_cancel" href="' . $cancel_url . '">'.__('Cancel order', 'paypal-for-woocommerce').'</a> ';
echo '<input type="submit" onclick="jQuery(this).attr(\'disabled\', \'disabled\').val(\'Processing\'); jQuery(this).parents(\'form\').submit(); return false;" class="button" value="' . __( 'Place Order','paypal-for-woocommerce') . '" /></p>';
    ?>
    </form><!--close the checkout form-->
<?php endif; ?>
<div class="clear"></div>