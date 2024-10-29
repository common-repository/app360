<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * @package App360CRM
 */
/**
 * Plugin Name: App360 CRM
 * Plugin URI: https://www.app360.my/
 * Description: App360 CRM allows the integration between WooCommerce and App360
 * Version: 1.4.4
 * Author: App360
 * License: GPLv2 or later
 * Text Domain: app360-crm
 */

/*
license details here
*/
define( 'APP360__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'ABSPATH' ) or die( "You can't access this file.");
require_once( APP360__PLUGIN_DIR . 'authentication.php' );
require_once( APP360__PLUGIN_DIR . 'registration.php' );
require_once( APP360__PLUGIN_DIR . 'profile.php' );
require_once( APP360__PLUGIN_DIR . 'transaction.php' );
require_once( APP360__PLUGIN_DIR . 'voucher.php' );
require_once( APP360__PLUGIN_DIR . 'tier.php' );
require_once( APP360__PLUGIN_DIR . 'setting.php' );
require_once( APP360__PLUGIN_DIR . 'rest_api.php' );

class App360Plugin
{
    function __construct() {
        remove_action('wp_loaded', 'process_registration');
    }

    function activate() {
        flush_rewrite_rules();
        app360_migrate();
    }

    function deactivate() {
        flush_rewrite_rules();
    }
}

if( class_exists( 'App360Plugin') ) {
    $app360Plugin = new App360Plugin();
}

//activation
register_activation_hook(__FILE__, array( $app360Plugin, 'activate'));

//deactivation
register_deactivation_hook(__FILE__, array( $app360Plugin, 'deactivate'));

function app360_migrate(){
	global $wpdb;

	$usermeta_app360 = $wpdb->get_results('SELECT `umeta_id` FROM '.$wpdb->usermeta.' WHERE `meta_key` LIKE "app360_%" LIMIT 1');
	if ($usermeta_app360) {
		//if found at least 1 usermeta for app360, then skip below migration
		return;
	}

    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $args = array(
            'role'    => 'customer'
        );
        $users = get_users( $args );
        foreach ( $users as $user )
        {
            $app360_user_id = get_user_meta($user->ID, 'app360_userid', true);
            if(!$app360_user_id){
                $email = $user->user_email;
                $password = $email;
                $username = $user->display_name;
                $url = $app360_api_domain.'/client/register?';
                $url .= 'email='.$email;
                $url .= '&password='.$password;
                $url .= '&password_confirm='.$password;
                $url .= '&fullname='.$username;
                $headers = array();
                $headers['Content-type'] = 'application/json';
                $headers['apikey'] = $app360_api;
                $response = wp_remote_get($url, ['headers'=> $headers]);
                $user_id = (is_array($response) && isset(json_decode($response['body'])->user_id)) ? json_decode($response['body'])->user_id : null;
                if($user_id)
                    add_user_meta( $user->ID, 'app360_userid', $user_id, true );
            }
        }
    }
}
?>