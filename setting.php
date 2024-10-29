<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_filter( 'woocommerce_get_sections_advanced' , 'app360_settings_tab' );

function app360_settings_tab( $settings_tab ){
     $settings_tab['app360_api'] = __( 'App360 API' );
     return $settings_tab;
}

add_filter( 'woocommerce_get_settings_advanced' , 'app360_get_settings' , 10, 2 );

function app360_get_settings( $settings, $current_section ) {

    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    $url = $app360_api_domain.'/client/tac/verify?';
    $url .= 'contact=0';
    $url .= '&tac=0';
    $headers = array();
    $headers['Content-type'] = 'application/json';
    $headers['apikey'] = $app360_api;
    $response = wp_remote_get($url, ['headers'=> $headers]);

    $custom_settings = array();
    if( 'app360_api' == $current_section ) {

        $custom_settings =  array(
            array(
                'name' => __( 'App360 API' ),
                'type' => 'title',
                'desc' => __( 'To modify the App360 API credentials' ),
                'id'   => 'app360_api' 
            )
        );

        if( isset($response->errors) || !isset(json_decode($response['body'])->verified) ){
            $custom_settings = array_merge($custom_settings, array(
                array(
                    'name' => __( 'API status' ),
                    'type' => 'title',
                    'desc' => __( 'Inactive' ),
                    'id'   => 'status' 
                )
            ));
        }
        else{
            $custom_settings = array_merge($custom_settings, array(
                array(
                    'name' => __( 'API status' ),
                    'type' => 'title',
                    'desc' => __( 'Active' ),
                    'id'   => 'status'
                )
            ));
        }

        $custom_settings = array_merge($custom_settings, array(
            array(
                'name' => __( 'API Domain' ),
                'type' => 'text',
                'desc' => __( 'API Domain can be get from the system setting page'),
                'placeholder' => 'https://sample.api.app360.my',
                'desc_tip' => true,
                'max_value' => '100',
                'id'	=> 'app360_api_domain'
            ),

            array(
                'name' => __( 'License Key' ),
                'type' => 'text',
                'desc' => __( 'License key can be get from the system setting page'),
                'placeholder' => '',
                'desc_tip' => true,
                'max_value' => '100',
                'id'	=> 'app360_api'
            ),

            array( 
                'type' => 'sectionend', 'id' => 'app360_api' 
            )
        ));

        $custom_settings =  array_merge($custom_settings, array(
            array(
                'name' => __( 'Not yet subscribed to App360 CRM?' ),
                'type' => 'title',
                'desc' => __( 'Check it out now at <a href="https://www.app360.my/" target="_blank">https://www.app360.my/</a>' ),
                'id'   => 'app360_api' 
            )
        ));

        return $custom_settings;
    } else {
        return $settings;
    }

}
