jQuery(function(e){if("undefined"==typeof angelleye_in_content_param)return!1;function n(){var n=angelleye_in_content_param.disallowed_funding_methods;return null===n&&(n=[]),!(e.inArray("card",n)>-1)}function t(){e(".angelleye_button_single").length>0&&(e(".angelleye_button_single").empty(),window.paypalCheckoutReady=function(){allowed_funding_methods_single_array=e.parseJSON(angelleye_in_content_param.allowed_funding_methods),disallowed_funding_methods_single_array=e.parseJSON(angelleye_in_content_param.disallowed_funding_methods),"no"==angelleye_in_content_param.is_paypal_credit_enable&&disallowed_funding_methods_single_array.push("credit");var t=function(){var n=e(".variations_form").find(".variations select"),t={},a=0,o=0;return n.each(function(){var n=e(this).data("attribute_name")||e(this).attr("name"),_=e(this).val()||"";_.length>0&&o++,a++,t[n]=_}),{count:a,chosenCount:o,data:t}};angelleye_cart_style_object={size:angelleye_in_content_param.button_size,color:angelleye_in_content_param.button_color,shape:angelleye_in_content_param.button_shape,label:angelleye_in_content_param.button_label,layout:angelleye_in_content_param.button_layout,tagline:"true"===angelleye_in_content_param.button_tagline},"horizontal"===angelleye_in_content_param.button_layout&&!0===n()&&"credit"!==angelleye_in_content_param.button_label&&"true"===angelleye_in_content_param.button_fundingicons&&(angelleye_cart_style_object.fundingicons="true"===angelleye_in_content_param.button_fundingicons),void 0!==angelleye_in_content_param.button_height&&""!==angelleye_in_content_param.button_height&&(angelleye_cart_style_object.height=parseInt(angelleye_in_content_param.button_height)),e(".angelleye_button_single").empty(),paypal.Button.render({env:angelleye_in_content_param.environment,style:angelleye_cart_style_object,locale:angelleye_in_content_param.locale,commit:"false"!==angelleye_in_content_param.zcommit,funding:{allowed:allowed_funding_methods_single_array,disallowed:disallowed_funding_methods_single_array},payment:function(n,a){var o,_={nonce:angelleye_in_content_param.generate_cart_nonce,qty:e(".quantity .qty").val(),attributes:e(".variations_form").length?JSON.stringify(t().data):[],is_cc:"",product_id:e("input[name=add-to-cart]").val(),variation_id:e("input[name=variation_id]").val(),request_from:"JSv4",express_checkout:"true"};return o=angelleye_in_content_param.add_to_cart_ajaxurl,e("#wc-paypal_express-new-payment-method").is(":checked")&&(o+="&ec_save_to_account=true"),paypal.request.post(o,_).then(function(e){return paypal.request.post(e.url,{request_from:"JSv4"}).then(function(e){return e.token})})},onAuthorize:function(n,t){e(".woocommerce").block({message:null,overlayCSS:{background:"#fff",opacity:.6}});var a={paymentToken:n.paymentToken,payerID:n.payerID,token:n.paymentToken,request_from:"JSv4"};paypal.request.post(n.returnUrl,a).then(function(e){n.returnUrl=e.url,t.redirect()})},onCancel:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_oncancel"),window.location.href=window.location.href},onClick:function(){e(document.body).trigger("angelleye_paypal_onclick"),"yes"===angelleye_in_content_param.enable_google_analytics_click&&"undefined"!=typeof ga&&e.isFunction(ga)&&ga("send",{hitType:"event",eventCategory:"Checkout",eventAction:"button_click"})},onError:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_onerror"),window.location.href=angelleye_in_content_param.cancel_page}},".angelleye_button_single")})}function a(){window.paypalCheckoutReady=function(){var t,a=[],o=[];"yes"==angelleye_in_content_param.is_checkout&&"yes"==angelleye_in_content_param.is_display_on_checkout&&a.push(".angelleye_smart_button_checkout_top"),"yes"==angelleye_in_content_param.is_cart&&("both"==angelleye_in_content_param.cart_button_possition?a.push(".angelleye_smart_button_top",".angelleye_smart_button_bottom"):"bottom"==angelleye_in_content_param.cart_button_possition?a.push(".angelleye_smart_button_bottom"):"top"==angelleye_in_content_param.cart_button_possition&&a.push(".angelleye_smart_button_top")),o=e.parseJSON(angelleye_in_content_param.disallowed_funding_methods),t=e.parseJSON(angelleye_in_content_param.allowed_funding_methods),"no"==angelleye_in_content_param.is_paypal_credit_enable&&o.push("credit"),angelleye_cart_style_object={size:angelleye_in_content_param.button_size,color:angelleye_in_content_param.button_color,shape:angelleye_in_content_param.button_shape,label:angelleye_in_content_param.button_label,layout:angelleye_in_content_param.button_layout,tagline:"true"===angelleye_in_content_param.button_tagline},void 0!==angelleye_in_content_param.button_height&&""!==angelleye_in_content_param.button_height&&(angelleye_cart_style_object.height=parseInt(angelleye_in_content_param.button_height)),a.forEach(function(a){if(e(a).html(""),o=e.grep(o,function(e){return"venmo"!==e}),a.length>0&&e(a).length>0&&("horizontal"===angelleye_in_content_param.button_layout&&!0===n()&&"credit"!==angelleye_in_content_param.button_label&&"true"===angelleye_in_content_param.button_fundingicons&&(angelleye_cart_style_object.fundingicons="true"===angelleye_in_content_param.button_fundingicons),paypal.Button.render({env:angelleye_in_content_param.environment,style:angelleye_cart_style_object,locale:angelleye_in_content_param.locale,commit:"false"!==angelleye_in_content_param.zcommit,funding:{allowed:t,disallowed:o},payment:function(){var n;return n=angelleye_in_content_param.set_express_checkout,e("#wc-paypal_express-new-payment-method").is(":checked")?n+="&ec_save_to_account=true":e("#wc-paypal_express-new-payment-method_bottom").is(":checked")&&(n+="&ec_save_to_account=true"),paypal.request.post(n,{request_from:"JSv4"}).then(function(e){return e.token})},onAuthorize:function(n,t){e(".woocommerce").block({message:null,overlayCSS:{background:"#fff",opacity:.6}});var a={paymentToken:n.paymentToken,payerID:n.payerID,token:n.paymentToken,request_from:"JSv4"};paypal.request.post(n.returnUrl,a).then(function(e){n.returnUrl=e.url,t.redirect()})},onCancel:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_oncancel"),window.location.href=window.location.href},onClick:function(){e(document.body).trigger("angelleye_paypal_onclick"),"yes"===angelleye_in_content_param.enable_google_analytics_click&&"undefined"!=typeof ga&&e.isFunction(ga)&&ga("send",{hitType:"event",eventCategory:"Checkout",eventAction:"paypal_button_click"})},onError:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_onerror"),window.location.href=angelleye_in_content_param.cancel_page}},a)),"angelleye_smart_button_checkout_top"===a)return!1})}}function o(){window.paypalCheckoutReady=function(){var t,a=[],o=[];a.push(".angelleye_smart_button_mini"),o=e.parseJSON(angelleye_in_content_param.mini_cart_disallowed_funding_methods),t=e.parseJSON(angelleye_in_content_param.mini_cart_allowed_funding_methods),"no"==angelleye_in_content_param.is_paypal_credit_enable&&o.push("credit"),angelleye_cart_style_object={size:angelleye_in_content_param.mini_cart_button_size,color:angelleye_in_content_param.button_color,shape:angelleye_in_content_param.button_shape,label:angelleye_in_content_param.mini_cart_button_label,layout:angelleye_in_content_param.mini_cart_button_layout,tagline:"true"===angelleye_in_content_param.button_tagline},void 0!==angelleye_in_content_param.mini_cart_button_height&&""!==angelleye_in_content_param.mini_cart_button_height&&(angelleye_cart_style_object.height=parseInt(angelleye_in_content_param.mini_cart_button_height)),a.forEach(function(a){e(a).html(""),o=e.grep(o,function(e){return"venmo"!==e}),a.length>0&&e(a).length>0&&(angelleye_cart_style_object.size="responsive","horizontal"===angelleye_in_content_param.button_layout&&!0===n()&&"credit"!==angelleye_in_content_param.button_label&&"true"===angelleye_in_content_param.button_fundingicons&&(angelleye_cart_style_object.fundingicons="true"===angelleye_in_content_param.button_fundingicons),paypal.Button.render({env:angelleye_in_content_param.environment,style:angelleye_cart_style_object,locale:angelleye_in_content_param.locale,commit:"false"!==angelleye_in_content_param.zcommit,funding:{allowed:t,disallowed:o},payment:function(){var n;return n=angelleye_in_content_param.set_express_checkout,e("#wc-paypal_express-new-payment-method").is(":checked")&&(n+="&ec_save_to_account=true"),paypal.request.post(n,{request_from:"JSv4"}).then(function(e){return e.token})},onAuthorize:function(n,t){e(".woocommerce").block({message:null,overlayCSS:{background:"#fff",opacity:.6}});var a={paymentToken:n.paymentToken,payerID:n.payerID,token:n.paymentToken,request_from:"JSv4"};paypal.request.post(n.returnUrl,a).then(function(e){n.returnUrl=e.url,t.redirect()})},onCancel:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_oncancel"),window.location.href=window.location.href},onClick:function(){e(document.body).trigger("angelleye_paypal_onclick"),"yes"===angelleye_in_content_param.enable_google_analytics_click&&"undefined"!=typeof ga&&e.isFunction(ga)&&ga("send",{hitType:"event",eventCategory:"Checkout",eventAction:"paypal_button_click"})},onError:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_onerror"),window.location.href=angelleye_in_content_param.cancel_page}},a))})}}function _(){window.paypalCheckoutReady=function(){var t,a=[],o=[];a.push(".angelleye_smart_button_wsc"),o=e.parseJSON(angelleye_in_content_param.wsc_cart_disallowed_funding_methods),t=e.parseJSON(angelleye_in_content_param.wsc_cart_allowed_funding_methods),"no"==angelleye_in_content_param.is_paypal_credit_enable&&o.push("credit"),angelleye_cart_style_object={size:angelleye_in_content_param.wsc_cart_button_size,color:angelleye_in_content_param.button_color,shape:angelleye_in_content_param.button_shape,label:angelleye_in_content_param.wsc_cart_button_label,layout:angelleye_in_content_param.wsc_cart_button_layout,tagline:"true"===angelleye_in_content_param.button_tagline},void 0!==angelleye_in_content_param.wsc_cart_button_height&&""!==angelleye_in_content_param.wsc_cart_button_height&&(angelleye_cart_style_object.height=parseInt(angelleye_in_content_param.wsc_cart_button_height)),a.forEach(function(a){e(a).html(""),o=e.grep(o,function(e){return"venmo"!==e}),a.length>0&&e(a).length>0&&(angelleye_cart_style_object.size="responsive","horizontal"===angelleye_in_content_param.button_layout&&!0===n()&&"credit"!==angelleye_in_content_param.button_label&&"true"===angelleye_in_content_param.button_fundingicons&&(angelleye_cart_style_object.fundingicons="true"===angelleye_in_content_param.button_fundingicons),paypal.Button.render({env:angelleye_in_content_param.environment,style:angelleye_cart_style_object,locale:angelleye_in_content_param.locale,commit:"false"!==angelleye_in_content_param.zcommit,funding:{allowed:t,disallowed:o},payment:function(){var n;return n=angelleye_in_content_param.set_express_checkout,e("#wc-paypal_express-new-payment-method").is(":checked")&&(n+="&ec_save_to_account=true"),paypal.request.post(n,{request_from:"JSv4"}).then(function(e){return e.token})},onAuthorize:function(n,t){e(".woocommerce").block({message:null,overlayCSS:{background:"#fff",opacity:.6}});var a={paymentToken:n.paymentToken,payerID:n.payerID,token:n.paymentToken,request_from:"JSv4"};paypal.request.post(n.returnUrl,a).then(function(e){n.returnUrl=e.url,t.redirect()})},onCancel:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_oncancel"),window.location.href=window.location.href},onClick:function(){e(document.body).trigger("angelleye_paypal_onclick"),"yes"===angelleye_in_content_param.enable_google_analytics_click&&"undefined"!=typeof ga&&e.isFunction(ga)&&ga("send",{hitType:"event",eventCategory:"Checkout",eventAction:"paypal_button_click"})},onError:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_onerror"),window.location.href=angelleye_in_content_param.cancel_page}},a))})}}if(a(),o(),t(),_(),e(document.body).on("cart_totals_refreshed updated_shipping_method wc_fragments_refreshed updated_checkout updated_wc_div updated_cart_totals wc_fragments_loaded",function(e){a()}),"no"===angelleye_in_content_param.checkout_page_disable_smart_button&&e(document.body).on("updated_shipping_method wc_fragments_refreshed updated_checkout",function(t){window.paypalCheckoutReady=function(){var t,a=[],o=[];a.push(".angelleye_smart_button_checkout_bottom"),o=e.parseJSON(angelleye_in_content_param.disallowed_funding_methods),t=e.parseJSON(angelleye_in_content_param.allowed_funding_methods),"no"==angelleye_in_content_param.is_paypal_credit_enable&&o.push("credit"),angelleye_cart_style_object={size:angelleye_in_content_param.button_size,color:angelleye_in_content_param.button_color,shape:angelleye_in_content_param.button_shape,label:angelleye_in_content_param.button_label,layout:angelleye_in_content_param.button_layout,tagline:"true"===angelleye_in_content_param.button_tagline},void 0!==angelleye_in_content_param.button_height&&""!==angelleye_in_content_param.button_height&&(angelleye_cart_style_object.height=parseInt(angelleye_in_content_param.button_height)),a.forEach(function(a){if(e(a).html(""),o=e.grep(o,function(e){return"venmo"!==e}),a.length>0&&e(a).length>0&&("horizontal"===angelleye_in_content_param.button_layout&&!0===n()&&"credit"!==angelleye_in_content_param.button_label&&"true"===angelleye_in_content_param.button_fundingicons&&(angelleye_cart_style_object.fundingicons="true"===angelleye_in_content_param.button_fundingicons),paypal.Button.render({env:angelleye_in_content_param.environment,style:angelleye_cart_style_object,locale:angelleye_in_content_param.locale,commit:"false"!==angelleye_in_content_param.zcommit,funding:{allowed:t,disallowed:o},payment:function(){var n=e(a).closest("form").add(e('<input type="hidden" name="request_from" /> ').attr("value","JSv4")).add(e('<input type="hidden" name="from_checkout" /> ').attr("value","yes")).serialize();return paypal.request({method:"post",url:angelleye_in_content_param.set_express_checkout,body:n}).then(function(e){return e.token})},onAuthorize:function(n,t){e(".woocommerce").block({message:null,overlayCSS:{background:"#fff",opacity:.6}});var a={paymentToken:n.paymentToken,payerID:n.payerID,token:n.paymentToken,request_from:"JSv4"};paypal.request.post(n.returnUrl,a).then(function(a){"no"===angelleye_in_content_param.is_pre_checkout_offer?(n.returnUrl=a.url,t.redirect()):(e(".woocommerce").unblock(),e("form.checkout").triggerHandler("checkout_place_order"))})},onCancel:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_oncancel"),window.location.href=window.location.href},onClick:function(){e(document.body).trigger("angelleye_paypal_onclick"),"yes"===angelleye_in_content_param.enable_google_analytics_click&&"undefined"!=typeof ga&&e.isFunction(ga)&&ga("send",{hitType:"event",eventCategory:"Checkout",eventAction:"paypal_button_click"})},onError:function(n,t){e(".woocommerce").unblock(),e(document.body).trigger("angelleye_paypal_onerror"),window.location.href=window.location.href}},a)),"angelleye_smart_button_checkout_bottom"===a)return!1})}}),e(document.body).on("wc_fragments_loaded wc_fragments_refreshed",function(){var n=e(".angelleye_smart_button_mini");n.length&&(n.empty(),o());var a=e(".angelleye_button_single");a.length&&(a.empty(),t());var l=e(".angelleye_smart_button_wsc");l.length&&(l.empty(),_())}),"no"===angelleye_in_content_param.checkout_page_disable_smart_button){function l(){var n=e("#payment_method_paypal_express").is(":checked");e('input[name="wc-paypal_express-payment-token"]:checked').length>0?n&&e('input[name="wc-paypal_express-payment-token"]').length&&"new"===e('input[name="wc-paypal_express-payment-token"]:checked').val()?(e("#place_order").hide(),e(".angelleye_smart_button_checkout_bottom").show()):n&&e('input[name="wc-paypal_express-payment-token"]').length&&"new"!==e('input[name="wc-paypal_express-payment-token"]:checked').val()?(e("#place_order").show(),e(".angelleye_smart_button_checkout_bottom").hide()):n?(e(".angelleye_smart_button_checkout_bottom").show(),e("#place_order").hide()):(e(".angelleye_smart_button_checkout_bottom").hide(),e("#place_order").show()):n?(e(".angelleye_smart_button_checkout_bottom").show(),e("#place_order").hide()):(e(".angelleye_smart_button_checkout_bottom").hide(),e("#place_order").show())}e(document.body).on("updated_checkout wc-credit-card-form-init update_checkout",function(e){l()}),e("form.checkout").on("click",'input[name="payment_method"]',function(){l()}),e("form.checkout").on("click",'input[name="wc-paypal_express-payment-token"]',function(){"new"===e(this).val()?(e("#place_order").hide(),e(".angelleye_smart_button_checkout_bottom").show()):"new"!==e(this).val()&&(e("#place_order").show(),e(".angelleye_smart_button_checkout_bottom").hide())})}});