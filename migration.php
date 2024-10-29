<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function app360_migrate(){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $DBRecord = array();
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
                $user_id = (json_decode($response['body'])->user_id) ? json_decode($response['body'])->user_id : null;
                add_user_meta( $user->ID, 'app360_userid', $user_id, true );
            }
        }
        return DBRecord;
    }
}