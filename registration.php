<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action('wp_loaded', 'app360_process_registration',30);
add_action('wp_loaded', 'app360_process_login',30);

function app360_remove_process_registration(){
    remove_action('wp_loaded', array('WC_Form_Handler','process_registration'), 20);
}
add_action('wp_loaded', 'app360_remove_process_registration');

function app360_remove_process_login(){
    remove_action('wp_loaded', array('WC_Form_Handler','process_login'), 20);
}
add_action('wp_loaded', 'app360_remove_process_login');

function app360_process_registration(){
    if ( isset( $_POST['register'], $_POST['email'] ) ){
        if(!wp_verify_nonce($_POST['app360_generate_nonce'],'registration_form_submit')){
            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Unauthorized action detected.', 'error' );
            return;
        }else{
            $app360_api_domain = get_option('app360_api_domain');
            $app360_api = get_option('app360_api');
            if( $app360_api_domain && $app360_api ){
                $contact = sanitize_text_field($_POST['contact']);
                $email = sanitize_email($_POST['email']);
                $password = $_POST['password'];
                $confirm_password = $_POST['password2'];
            	//$username = sanitize_text_field($_POST['username']);
            	$username = $contact;
                $tac = sanitize_text_field($_POST['contact_tac']);
                $birthday = isset($_POST['birthday']) ? sanitize_text_field($_POST['birthday']) : null;

                if($_POST['register'] == 'Send'){
                    $url = $app360_api_domain.'/client/tac/send?';
                    $url .= 'contact='.$contact;
                    $headers = array();
                    $headers['Content-type'] = 'application/json';
                    $headers['apikey'] = $app360_api;
                    $response = wp_remote_get($url, ['headers'=> $headers]);
                    if(is_array($response)){
                        if( !json_decode($response['body'])->generated){
                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> '.json_decode($response['body'])->message.'', 'error' );
                            return;
                        }
                    }
                    else{
                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                        return;
                    }
                    //wc_clear_notices();
                    wc_add_notice( '<strong>' . __( 'Success:', 'woocommerce' ) . '</strong> TAC sent.', 'success' );
                    return;
                }

                $args = array(
                    'meta_key' => 'contact',
                    'meta_value' => $contact
                );
                $query = new WP_User_Query($args);
                $result = $query->get_results();

                if($result){
                    wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Phone number exists.', 'error' );
                    return;
                }

                $url = $app360_api_domain.'/client/tac/verify?';
                $url .= 'contact='.$contact;
                $url .= '&tac='.$tac;
                $headers = array();
                $headers['Content-type'] = 'application/json';
                $headers['apikey'] = $app360_api;
                $response = wp_remote_get($url, ['headers'=> $headers]);
                if(is_array($response)){
                    if(!json_decode($response['body'])->verified){
                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> TAC invalid.', 'error' );
                        return;
                    }
                }
                else{
                    wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                    return;
                }

                $user_id = email_exists($_POST['email']);

                if($user_id){
                    wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Email exists.', 'error' );
                    return;
                }

                if(!$birthday){
                    wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Birthday is required.', 'error' );
                    return;
                }

                if($password != $confirm_password){
                    wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Password and confirm password mismatch.', 'error' );
                    return;
                }

                $url = $app360_api_domain.'/client/register?';
                $url .= 'email='.urlencode($email);
                $url .= '&contact='.urlencode($contact);
                $url .= '&password='.urlencode($password);
                $url .= '&password_confirm='.urlencode($password);
                $url .= '&fullname='.urlencode($username);
                $url .= '&birthday='.urlencode($birthday);
                $headers = array();
                $headers['Content-type'] = 'application/json';
                $headers['apikey'] = $app360_api;
                $response = wp_remote_post($url, ['headers'=> $headers]);
                $user_id = (is_array($response) && json_decode($response['body'])->user_id) ? json_decode($response['body'])->user_id : null;

                if(is_array($response)){
                    if($user_id == null){
                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> '.json_decode($response['body'])->message, 'error' );
                        return;
                    }
                }
                else{
                    wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                    return;
                }

                $new_customer_data = array(
                    'user_login' => $username.' ['.str_pad($user_id, 9, 0).']',
                    'user_pass'  => $password,
                    'user_email' => $email,
                    'display_name'=> $username,
                    'role'       => 'customer',
                );

                $customer_id = wp_insert_user( $new_customer_data );

                add_user_meta( $customer_id, 'contact', $contact, true );
                add_user_meta( $customer_id, 'app360_userid', $user_id, true );

                $new_customer = $customer_id;

                // Only redirect after a forced login - otherwise output a success notice.
                if ( apply_filters( 'woocommerce_registration_auth_new_customer', true, $new_customer ) ) {
                    wc_set_customer_auth_cookie( $new_customer );

                    if ( ! empty( $_POST['redirect'] ) ) {
                        $redirect = sanitize_url( wp_unslash( $_POST['redirect'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    } elseif ( wc_get_raw_referer() ) {
                        $redirect = wc_get_raw_referer();
                    } else {
                        $redirect = wc_get_page_permalink( 'myaccount' );
                    }

                    wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_registration_redirect', $redirect ), wc_get_page_permalink( 'myaccount' ) ) ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                    exit;
                }
            }
            else{
                wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API not working.', 'error' );
                return;
            }
        }
    }
}

function app360_process_login(){
    if ( isset( $_POST['login'], $_POST['username'], $_POST['password'] ) ) {
    	if (isset($_POST['login_type'], $_POST['signature'])) {
    		if ($_POST['login_type'] === 'singlesign') {
    			$signature = $_POST['signature'];

    			$signature_plain = get_option('app360_api').'|';
    			$signature_plain .= date_format(date_create(NULL, timezone_open("Asia/Kuala_Lumpur")), 'Ymd').'|';
    			$signature_plain .= base64_encode($_POST['username'].':'.$_POST['password']).'|';

    			if (md5($signature_plain) !== $signature) {
    				wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Unauthorized action detected.', 'error' );
    				return;
    			}

    			$_POST['app360_generate_nonce'] = wp_create_nonce( 'login_form_submit');
    			if (!isset($_POST['redirect'])) {
    				$_POST['redirect'] = wc_get_page_permalink( 'myaccount' );
    			} else {
    				$app360_api_domain = get_option('app360_api_domain');
    				$parsed_app360_api_domain = parse_url($app360_api_domain);

    				$host_app360 = $parsed_app360_api_domain['host'];

    				$app360_member_domain = str_replace("api.", "member.", $host_app360);

    				add_filter('allowed_redirect_hosts', function($allowed) use ($app360_member_domain) {
    					$allowed[] = $app360_member_domain;
    					return $allowed;
    				});
    			}
    		}
    	}

        if(!wp_verify_nonce($_POST['app360_generate_nonce'],'login_form_submit')){
            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Unauthorized action detected.', 'error' );
            return;
        }else{
            $app360_api_domain = get_option('app360_api_domain');
            $app360_api = get_option('app360_api');
            if( $app360_api_domain && $app360_api ){
                $referrer = $_SERVER['HTTP_REFERER'];  // where did the post submission come from?
                $contact = null;
                $tac = null;
                $birthday = null;
                if(isset($_POST['login_contact'])){
                    $contact = sanitize_text_field($_POST['login_contact']);
                    $tac = sanitize_text_field($_POST['login_contact_tac']);
                    $birthday = sanitize_text_field($_POST['login_birthday']);
                }
                if($_POST['login'] == 'Send'){
                    $url = $app360_api_domain.'/client/tac/send?';
                    $url .= 'contact='.$contact;
                    $headers = array();
                    $headers['Content-type'] = 'application/json';
                    $headers['apikey'] = $app360_api;
                    $response = wp_remote_get($url, ['headers'=> $headers]);
                    add_action("woocommerce_login_form", "app360_login_form_add_custom_fields");
                    if(is_array($response)){
                        if(!json_decode($response['body'])->generated){
                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> '.json_decode($response['body'])->message.'', 'error' );
                            return;
                        }
                    }
                    else{
                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                        return;
                    }
                    //wc_clear_notices();
                    wc_add_notice( '<strong>' . __( 'Success:', 'woocommerce' ) . '</strong> TAC sent.', 'success' );
                    return;
                }
                if( is_numeric($_POST['username']) ){//if it is phone number
                    $args = array(
                        'meta_key' => 'contact',
                        'meta_value' => sanitize_text_field($_POST['username'])
                    );
                    $query = new WP_User_Query($args);
                    $result = $query->get_results();
                    if($result){
                        if(wp_check_password($_POST['password'],$result[0]->data->user_pass)){
                            //login
                            wc_set_customer_auth_cookie( $result[0]->data->ID );
                            if ( ! empty( $_POST['redirect'] ) ) {
                                $redirect = sanitize_url( wp_unslash( $_POST['redirect'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                            } elseif ( wc_get_raw_referer() ) {
                                $redirect = wc_get_raw_referer();
                            } else {
                                $redirect = wc_get_page_permalink( 'myaccount' );
                            }

                            wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_login_redirect', remove_query_arg( 'wc_error', $redirect ), $user ), wc_get_page_permalink( 'myaccount' ) ) ); // phpcs:ignore
                            exit;
                        }
                        else{//wrong password
                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Incorrect password.', 'error' );
                            return;
                        }
                    }
                    else{// could not find the phone number
                        $url = $app360_api_domain.'/client/login?';
                        $url .= 'username='.urlencode(sanitize_text_field($_POST['username']));
                        $url .= '&password='.urlencode($_POST['password']);
                        $headers = array();
                        $headers['Content-type'] = 'application/json';
                        $headers['apikey'] = $app360_api;
                        $response = wp_remote_get($url, ['headers'=> $headers]);
                        if(is_array($response)){
                            if(json_decode($response['body'])->code == '000'){
                                // register in woocommerce
                                $user = json_decode($response['body'])->user;
                                $new_customer_data = array(
                                    'user_login' => $user->fullname.' ['.str_pad($user->id, 9, 0).']',
                                    'user_pass'  => $_POST['password'],
                                    'user_email' => $user->email,
                                    'display_name'=> $user->fullname,
                                    'role'       => 'customer',
                                );

                                $customer_id = wp_insert_user( $new_customer_data );
                                add_user_meta( $customer_id, 'contact', sanitize_text_field($_POST['username']), true );
                                add_user_meta( $customer_id, 'app360_userid', $user->id, true );

                                // try login again
                                $args = array(
                                    'meta_key' => 'contact',
                                    'meta_value' => sanitize_text_field($_POST['username'])
                                );
                                $query = new WP_User_Query($args);
                                $result = $query->get_results();
                                if($result){
                                    if(wp_check_password($_POST['password'],$result[0]->data->user_pass)){
                                        //login
                                        wc_set_customer_auth_cookie( $result[0]->data->ID );
                                        if ( ! empty( $_POST['redirect'] ) ) {
                                            $redirect = wp_unslash( $_POST['redirect'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                                        } elseif ( wc_get_raw_referer() ) {
                                            $redirect = wc_get_raw_referer();
                                        } else {
                                            $redirect = wc_get_page_permalink( 'myaccount' );
                                        }

                                        wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_login_redirect', remove_query_arg( 'wc_error', $redirect ), $user ), wc_get_page_permalink( 'myaccount' ) ) ); // phpcs:ignore
                                        exit;
                                    }
                                    else{//wrong password
                                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Incorrect password.', 'error' );
                                        return;
                                    }
                                }
                            }
                            elseif(json_decode($response['body'])->code == 'U00'){
                                wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Incorrect password.', 'error' );
                                return;
                            }
                        }
                        else{
                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                            return;
                        }
                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Phone number could not be found.', 'error' );
                        return;
                    }
                }
                else{//if it is email
                    $user_id = email_exists($_POST['username']);
                    if($user_id){
                        $result = get_userdata($user_id);
                        if(wp_check_password($_POST['password'],$result->data->user_pass)){
                            $phone_number = get_user_meta($user_id, 'contact', true);
                            if($phone_number){// if the user has filled up their phone number, proceed to login
                                //login
                                wc_set_customer_auth_cookie( $result->data->ID );
                                if ( ! empty( $_POST['redirect'] ) ) {
                                    $redirect = wp_unslash( $_POST['redirect'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                                } elseif ( wc_get_raw_referer() ) {
                                    $redirect = wc_get_raw_referer();
                                } else {
                                    $redirect = wc_get_page_permalink( 'myaccount' );
                                }

                                wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_login_redirect', remove_query_arg( 'wc_error', $redirect ), $user ), wc_get_page_permalink( 'myaccount' ) ) ); // phpcs:ignore
                                exit;
                            }
                            else{// else, prompt user to insert their phone number
                                add_action("woocommerce_login_form", "app360_login_form_add_custom_fields");
                                if($tac){
                                    $url = $app360_api_domain.'/client/tac/verify?';
                                    $url .= 'contact='.$contact;
                                    $url .= '&tac='.$tac;
                                    $headers = array();
                                    $headers['Content-type'] = 'application/json';
                                    $headers['apikey'] = $app360_api;
                                    $response = wp_remote_get($url, ['headers'=> $headers]);
                                    if(is_array($response)){
                                        if(!json_decode($response['body'])->verified){
                                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> TAC invalid.', 'error' );
                                            return;
                                        }
                                    }
                                    else{
                                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                                        return;
                                    }

                                    if(!$birthday){
                                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Birthday is required.', 'error' );
                                        return;
                                    }

                                    add_user_meta( $user_id, 'contact', $contact, true );
                                    $url = $app360_api_domain.'/client/profile/update?';
                                    $url .= 'contact='.$contact;
                                    $url .= '&birthday='.$birthday;
                                    $headers = array();
                                    $headers['Content-type'] = 'application/json';
                                    $headers['apikey'] = $app360_api;
                                    $headers['userid'] = get_user_meta($user_id, 'app360_userid')[0];
                                    $response = wp_remote_get($url, ['headers'=> $headers]);
                                    //login
                                    wc_set_customer_auth_cookie( $result->data->ID );
                                    if ( ! empty( $_POST['redirect'] ) ) {
                                        $redirect = wp_unslash( $_POST['redirect'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                                    } elseif ( wc_get_raw_referer() ) {
                                        $redirect = wc_get_raw_referer();
                                    } else {
                                        $redirect = wc_get_page_permalink( 'myaccount' );
                                    }

                                    wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_login_redirect', remove_query_arg( 'wc_error', $redirect ), $user ), wc_get_page_permalink( 'myaccount' ) ) ); // phpcs:ignore
                                    exit;
                                }
                                else{
                                    return;
                                }

                            }

                        }
                        else{//wrong password
                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Incorrect password.', 'error' );
                            return;
                        }
                    }
                    else{// could not find the email address
                        $url = $app360_api_domain.'/client/login?';
                        $url .= 'username='.urlencode(sanitize_text_field($_POST['username']));
                        $url .= '&password='.urlencode($_POST['password']);
                        $headers = array();
                        $headers['Content-type'] = 'application/json';
                        $headers['apikey'] = $app360_api;
                        $response = wp_remote_get($url, ['headers'=> $headers]);
                        if(is_array($response)){
                            if(json_decode($response['body'])->code == '000'){
                                // register in woocommerce
                                $user = json_decode($response['body'])->user;
                                $new_customer_data = array(
                                    //'user_login' => sanitize_text_field($_POST['username']),
                                    'user_login' => $user->fullname.' ['.str_pad($user->id, 9, 0).']',
                                    'user_pass'  => $_POST['password'],
                                    'user_email' => sanitize_email($_POST['username']),
                                    'display_name'=> $user->fullname,
                                    'role'       => 'customer',
                                );

                                $customer_id = wp_insert_user( $new_customer_data );

                                add_user_meta( $customer_id, 'contact', $user->contact, true );
                                add_user_meta( $customer_id, 'app360_userid', $user->id, true );

                                // try login again
                                $user_id = email_exists($_POST['username']);
                                $result = get_userdata($user_id);
                                if(wp_check_password($_POST['password'],$result->data->user_pass)){
                                    $phone_number = get_user_meta($user_id, 'contact', true);
                                    if($phone_number){// if the user has filled up their phone number, proceed to login
                                        //login
                                        wc_set_customer_auth_cookie( $result->data->ID );
                                        if ( ! empty( $_POST['redirect'] ) ) {
                                            $redirect = sanitize_url( wp_unslash( $_POST['redirect'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                                        } elseif ( wc_get_raw_referer() ) {
                                            $redirect = wc_get_raw_referer();
                                        } else {
                                            $redirect = wc_get_page_permalink( 'myaccount' );
                                        }

                                        wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_login_redirect', remove_query_arg( 'wc_error', $redirect ), $user ), wc_get_page_permalink( 'myaccount' ) ) ); // phpcs:ignore
                                        exit;
                                    }
                                }
                            }
                            elseif(json_decode($response['body'])->code == 'U00'){
                                wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Incorrect password.', 'error' );
                                return;
                            }
                        }
                        else{
                            wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API gateway not working.', 'error' );
                            return;
                        }
                        wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> Email could not be found.', 'error' );
                        return;
                    }
                }
            }
            else{
                wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> API not working.', 'error' );
                return;
            }
        }
    }
}