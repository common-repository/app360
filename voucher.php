<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

//add_action('woocommerce_coupon_options', 'app360_voucherId');
function app360_voucherId($id){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $url = $app360_api_domain.'/client/vouchers';
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $response = wp_remote_get($url, ['headers'=> $headers]);
        $vouchers = is_array($response) && isset($response['body']) ? json_decode($response['body'])->result : [];
        $options = array(null => '- Select Voucher -');
        foreach($vouchers as $voucher){
            $options[$voucher->id] = $voucher->title;
        }

        /* $url = $app360_api_domain.'/client/tiers';
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $response = wp_remote_get($url, ['headers'=> $headers]);
        $tiers = json_decode($response['body'])->result;
        $tier_options = array(null => '- Select Tier -');
        foreach($tiers as $tier){
            $tier_options[$tier->id] = $tier->name;
        } */

        $coupon_id = absint( $id );
        $coupon    = new WC_Coupon( $coupon_id );
        if(get_post_meta($coupon_id, 'app360_tier', true) == null){
            woocommerce_wp_select(
                array(
                    'id'      => 'voucher_id',
                    'label'   => __( 'App360 Voucher', 'woocommerce' ),
                    'options' => $options,
                    'value'   => get_post_meta($coupon_id, 'voucher_id', true),
                )
            );
        }
        /* woocommerce_wp_checkbox(
            array(
                'id'          => 'app360_tier',
                'label'       => __( 'Enable App360 Tier', 'woocommerce' ),
                'description' => ''
            )
        );
        if(get_post_meta($coupon_id, 'app360_tier', true) == 'yes'){
            woocommerce_wp_select(
                array(
                    'id'      => 'app360_tier_id',
                    'label'   => __( 'App360 Tier Level', 'woocommerce' ),
                    'options' => $tier_options,
                    'value'   => get_post_meta($coupon_id, 'app360_tier_id', true),
                )
            );
        } */
    }
}

add_filter('woocommerce_coupon_is_valid', 'app360_coupon_is_valid', 10, 2);

function app360_coupon_is_valid($true, $coupon){
	if (!isset(WC()->cart)) {
		//no loaded cart, so cant add voucher
		return false;
	}
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
    	$applied_coupons = array();
    	$applied_coupons = WC()->cart->get_applied_coupons();
        $code = get_post($coupon->get_id())->post_title;
        if(!in_array($code, $applied_coupons)){
    		$wc_user_id = get_current_user_id();
        	$user_id = get_user_meta($wc_user_id, 'app360_userid') ? get_user_meta($wc_user_id, 'app360_userid')[0] : 0;
        	$reward_id = get_post_meta($coupon->get_id(), 'voucher_id', true) ? get_post_meta($coupon->get_id(), 'voucher_id', true) : 0;
        	//$tier = get_post_meta($coupon->get_id(), 'app360_tier', true) ? get_post_meta($coupon->get_id(), 'app360_tier', true) : 0;
        	//$tier_id = get_post_meta($coupon->get_id(), 'app360_tier_id', true) ? get_post_meta($coupon->get_id(), 'app360_tier_id', true) : 0;

        	if($reward_id && $reward_id != 0){
        		$url = $app360_api_domain.'/client/voucher/apply?';
        		$url .= 'user_id='.$user_id;
        		$url .= '&reward_id='.$reward_id;
        		$headers = array();
        		$headers['Content-type'] = 'application/json';
        		$headers['apikey'] = $app360_api;
        		$response = wp_remote_get($url, ['headers'=> $headers]);

        		$result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
        		if( !$result || !$result->available){
        			throw new Exception( __( 'You do not have this coupon.', 'woocommerce' ), 107 );
        		}
        		WC()->session->set( 'voucher_id', $result->voucher_id );
        	}
        }

        /* if($tier == 'yes' && $tier_id){
            $url = $app360_api_domain.'/client/member/profile?';
            $url .= 'user_id='.$user_id;
            $headers = array();
            $headers['Content-type'] = 'application/json';
            $headers['apikey'] = $app360_api;
            $response = wp_remote_get($url, ['headers'=> $headers]);

            $result = isset(json_decode($response['body'])->result) ? json_decode($response['body'])->result : null;
            if($result){
                if($result->tier_id != $tier_id){
                    return false;
                }
            }
            else{
                return false;
            }
        } */

        return true;
    }
    else{
        throw new Exception( __( 'API not working.', 'woocommerce' ), 107 );
    }
}

add_action('woocommerce_removed_coupon', 'app360_remove_coupon');

function app360_remove_coupon($coupon_code){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $reward_id = WC()->session->get( 'voucher_id', 0 );
        $url = $app360_api_domain.'/client/voucher/remove?';
        $url .= 'reward_id='.$reward_id;
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $response = wp_remote_get($url, ['headers'=> $headers]);
    }
}

add_action('woocommerce_check_cart_items', 'app360_check_cart');

function app360_check_cart(){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    $applied_coupons = WC()->cart->get_applied_coupons();
    if(!$applied_coupons){
        $user_id = get_current_user_id();
        $user_id = get_user_meta($user_id, 'app360_userid') ? get_user_meta($user_id, 'app360_userid')[0] : 0;
        $url = $app360_api_domain.'/client/voucher/reset?';
        $url .= 'user_id='.$user_id;
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $response = wp_remote_get($url, ['headers'=> $headers]);
    }
}

//add_filter('woocommerce_apply_with_individual_use_coupon', 'app360_apply_tier_coupon_with_individual_use_coupon', 10, 2);

function app360_apply_tier_coupon_with_individual_use_coupon($false, $coupon){
    $tier = get_post_meta($coupon->get_id(), 'app360_tier', true) ? get_post_meta($coupon->get_id(), 'app360_tier', true) : 0;
    $tier_id = get_post_meta($coupon->get_id(), 'app360_tier_id', true) ? get_post_meta($coupon->get_id(), 'app360_tier_id', true) : 0;
    if($tier == 'yes' && $tier_id){
        return true;
    }
}

//add_filter('woocommerce_apply_individual_use_coupon', 'app360_apply_individual_use_coupon_with_tier_coupon', 10, 3);

function app360_apply_individual_use_coupon_with_tier_coupon($empty_array, $coupon, $applied_coupons){
    foreach($applied_coupons as $applied_coupon){
        $coupon_id = wc_get_coupon_id_by_code($applied_coupon);
        $tier = get_post_meta($coupon_id, 'app360_tier', true) ? get_post_meta($coupon_id, 'app360_tier', true) : 0;
        if($tier){
            return array($applied_coupon);
        }
    }
}

//add_action('woocommerce_check_cart_items', 'app360_auto_apply_tier_coupon');

function app360_auto_apply_tier_coupon(){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $applied_coupons = WC()->cart->get_applied_coupons();
        $user_id = get_current_user_id();
        $user_id = get_user_meta($user_id, 'app360_userid') ? get_user_meta($user_id, 'app360_userid')[0] : 0;
        $url = $app360_api_domain.'/client/member/profile?';
        $url .= 'user_id='.$user_id;
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $response = wp_remote_get($url, ['headers'=> $headers]);

        $result = isset(json_decode($response['body'])->result) ? json_decode($response['body'])->result : null;
        if($result){
            $post_id = app360_get_post_id_by_meta_key_and_value('app360_tier_id', $result->tier_id);
            if($post_id){
                $code = get_post($post_id[0])->post_title;
                if(!in_array($code, $applied_coupons)){
                    WC()->cart->apply_coupon($code);
                }
            }
        }
    }
}
/**
 * source: https://gist.github.com/feedmeastraycat/3065969
 * @author: feedmeastraycat
 */
function app360_get_post_id_by_meta_key_and_value($key, $value) {
    global $wpdb;
    $tbl = $wpdb->prefix.'postmeta';
    $query = $wpdb->prepare("SELECT post_id FROM $tbl WHERE meta_key='%s' AND meta_value='%d'", esc_sql($key), esc_sql($value));
    $get_value = $wpdb->get_col($query);
    return $get_value;
}
