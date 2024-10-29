<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', function () {
    register_rest_route('wc', '/app360/customers', [
        'methods'  => 'GET',
        'callback' => 'app360_customers_list',
    ]);

	register_rest_route('wc', '/app360/customer/change_password', [
        'methods'  => 'PUT',
        'callback' => 'app360_customer_change_password',
        'permission_callback' => 'wc_auth'
    ]);
});

function wc_auth(){
	if ( ! function_exists( 'WC' ) ) {
		return false;
	}

	$method = new ReflectionMethod('WC_REST_Authentication', 'perform_basic_authentication');
	$method->setAccessible(true);

	return $method->invoke(new WC_REST_Authentication) !== false;
}

function app360_customers_list(WP_REST_Request $request)
{
    $args = array(
        'meta_key' => 'app360_userid',
        'meta_value' => $request['user_id']
    );
    $query = new WP_User_Query($args);
    $result = $query->get_results();

    return new WP_REST_RESPONSE($result);
 }

function app360_customer_change_password(WP_REST_Request $request)
{
	$user_id = $request->get_param('user_id');
	$new_password = $request->get_param('new_password');
	$signature = $request->get_param('signature');

	$signature_plain = get_option('app360_api').'|';
	$signature_plain .= date_format(date_create(NULL, timezone_open("Asia/Kuala_Lumpur")), 'Ymd').'|';
	$signature_plain .= base64_encode($user_id.':'.$new_password).'|';

	if (md5($signature_plain) !== $signature) {
		return new WP_Error('invalid_signature', 'Invalid Signature', array('status' => 403));
	}

	$args = array(
        'meta_key' => 'app360_userid',
        'meta_value' => $user_id
    );
	$query = new WP_User_Query($args);
	$result = $query->get_results();

	if (!$result) {
		return new WP_Error('user_not_found', 'User Not Found', array('status' => 404));
	}

	wp_set_password($new_password, $result[0]->data->ID);

	return new WP_REST_RESPONSE('success', 200);
}
?>