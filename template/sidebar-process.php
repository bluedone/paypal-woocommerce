<?php

add_action('wp_ajax_angelleye_marketing_mailchimp_subscription', 'own_angelleye_marketing_mailchimp_subscription');

function own_angelleye_marketing_mailchimp_subscription() {
    $url = 'https://facebook.us20.list-manage.com/subscribe/post-json?u=4a114f67f01027e3493064c37&amp;id=2ecba2d5e8';
    $url = add_query_arg(array('EMAIL' => $_POST['email']), $url);
    $response = wp_remote_post($url, array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(),
        'body' => array(),
        'cookies' => array()
            )
    );
    if (is_wp_error($response)) {
        wp_send_json(wp_remote_retrieve_body($response));
    } else {
        wp_send_json(wp_remote_retrieve_body($response));
    }
}
