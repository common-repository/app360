<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_filter( 'woocommerce_get_sections_products' , 'app360_tier_settings_tab' );

function app360_tier_settings_tab( $settings_tab ){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $settings_tab['tier_settings'] = __( 'Tier Discount' );
    }
    return $settings_tab;
}

add_filter( 'woocommerce_get_settings_products' , 'app360_tier_get_settings' , 10, 2 );

function app360_tier_get_settings( $settings, $current_section ) {
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $url = $app360_api_domain.'/client/tiers';
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $response = wp_remote_get($url, ['headers'=> $headers]);
        $tiers = is_array($response) && isset(json_decode($response['body'])->result) ? json_decode($response['body'])->result : array();
        $tier_options = array();
        foreach($tiers as $tier){
            /* $tier_options[] = array(
                'name' => ( $tier->name ),
                'type' => 'title',
                'id'   => 'tier_discount_'.$tier->id
            ); */

            $tier_options[] = array(
                'name' => __( $tier->name ),
                'type' => 'select',
                'desc' => __( 'Determine which type of discount apply for tier "'.$tier->name.'"'),
                'desc_tip' => true,
                'id'	=> 'discount_type_'.$tier->id,
                'options' => array(

                            'cash' => __( 'Fixed Amount' ),
                            'percentage' => __('Percentage')

                )

            );

            $tier_options[] = array(
                //'name' => __( 'Discount Amount' ),
                'type' => 'number',
                //'desc' => __( 'Value of the discount'),
                'placeholder' => 'Discount amount',
                'desc_tip' => false,
                'max_value' => '100',
                'id'	=> 'discount_amount_'.$tier->id

            );
        }

        $custom_settings = array();
        if( 'tier_settings' == $current_section ) {

            $custom_settings =  array(
                array(
                        'name' => __( 'Tier Discount' ),
                        'type' => 'title',
                        'desc' => __( 'Customise discount for each member tier' ),
                        'id'   => 'tier_discount'
                ),
            );

            $custom_settings = array_merge($custom_settings, $tier_options);
            $custom_settings = array_merge($custom_settings, array(
                array( 'type' => 'sectionend', 'id' => 'tier_discount' )
            ));

            return $custom_settings;
        } else {
            return $settings;
        }
    }
    else{
        return $settings;
    }

}

// Generating dynamically the product "regular price"
add_filter( 'woocommerce_product_get_regular_price', 'app360_dynamic_regular_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'app360_dynamic_regular_price', 10, 2 );
function app360_dynamic_regular_price( $regular_price, $product ) {
    if( empty($regular_price) || $regular_price == 0 )
        return $product->get_price();
    else
        return $regular_price;
}

// Generating dynamically the product "sale price"
add_filter( 'woocommerce_product_get_sale_price', 'app360_dynamic_sale_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'app360_dynamic_sale_price', 10, 2 );
function app360_dynamic_sale_price( $sale_price, $product ) {
    $tier_id = app360_get_tier_id();
    if( (empty($sale_price) || $sale_price == 0) && is_user_logged_in() ){
        if( get_option('discount_type_'.$tier_id)  == 'cash')
            return $product->get_regular_price() - get_option('discount_amount_'.$tier_id);
        else
            return $product->get_regular_price() * (100 - get_option('discount_amount_'.$tier_id))/100;
    }
    else{
        return $sale_price;
    }
};

function app360_get_tier_id(){// get user tier ID
    global $wp_session;
    if(!isset($wp_session['tier_id'])){
        $app360_api_domain = get_option('app360_api_domain');
        $app360_api = get_option('app360_api');
        if( $app360_api_domain && $app360_api ){
            $user_id = get_current_user_id();
            $user_id = get_user_meta($user_id, 'app360_userid') ? get_user_meta($user_id, 'app360_userid')[0] : 0;
            if($user_id != 0){
                $url = $app360_api_domain.'/client/member/profile?';
                $url .= 'user_id='.$user_id;
                $headers = array();
                $headers['Content-type'] = 'application/json';
                $headers['apikey'] = $app360_api;
                $response = wp_remote_get($url, ['headers'=> $headers]);

                $result = is_array($response) && isset(json_decode($response['body'])->result) ? json_decode($response['body'])->result : null;
                $tier_id = null;
                if($result){
                    $tier_id = isset($result->tier_id) ? $result->tier_id : 0;
                }
                $wp_session['tier_id'] = $tier_id;
                return $tier_id;
            }
            else{
                return 0;
            }
        }
        else{
            return 0;
        }
    }else{
        return $wp_session['tier_id'];
    }
}

// Displayed formatted regular price + sale price
add_filter( 'woocommerce_get_price_html', 'app360_dynamic_sale_price_html', 20, 2 );
function app360_dynamic_sale_price_html( $price_html, $product ) {
    if ((!get_post_meta($product->get_id(), '_sale_price', true) && is_admin()) || !is_user_logged_in())// if could not find any sale price set from panel return regular price
        return $price_html;
    if( $product->is_type('variable') ) return $price_html;

    $price_html = wc_format_sale_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ), wc_get_price_to_display(  $product, array( 'price' => $product->get_sale_price() ) ) ) . $product->get_price_suffix();

    return $price_html;
}

add_filter( 'woocommerce_add_cart_item_data', 'app360_add_cart_item_data', 10, 3 );

function app360_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
    //get product id & price
    $product = wc_get_product( ($variation_id ?: $product_id) );
    $price = $product->get_price();
    //new price
    if (!get_post_meta($product->get_id(), '_sale_price', true) ){
        $tier_id = app360_get_tier_id();
        if( get_option('discount_type_'.$tier_id)  == 'cash')
            $cart_item_data['new_price'] = $price - get_option('discount_amount_'.$tier_id);
        else
            $cart_item_data['new_price'] = $price * (100-get_option('discount_amount_'.$tier_id))/100;
    }
    return $cart_item_data;
}

add_action( 'woocommerce_before_calculate_totals', 'app360_before_calculate_totals', 10, 1 );

function app360_before_calculate_totals( $cart_obj ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }
    // Iterate through each cart item
    foreach( $cart_obj->get_cart() as $key=>$value ) {
        if( isset( $value['new_price'] ) ) {
            $price = $value['new_price'];
            $value['data']->set_price( ( $price ) );
        }
    }
}

add_filter('woocommerce_sale_flash', 'app360_hide_sale_flash', 10, 3);
function app360_hide_sale_flash($sale_word, $post, $product)
{
    if ( get_post_meta($post->ID, '_sale_price', true) )  {
        return $sale_word;
    }
}