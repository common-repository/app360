<?php


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 */
add_action('plugins_loaded', 'init_app360_gateway_class');
function init_app360_gateway_class(){

    class app360_WC_Gateway_Custom extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'custom_payment';

            $this->id                 = 'app360';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'App360', $this->domain );
            $this->method_description = __( 'Allows payments with App360 wallet.', $this->domain );

            // Load the settings.
            $this->app360_init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'completed' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'app360_thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'app360_email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function app360_init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Custom Payment', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                    'default'     => __( 'Custom Payment', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Output for the order received page.
         */
        public function app360_thankyou_page() {
            if ( $this->instructions )
                echo esc_html( wpautop( wptexturize( $this->instructions ) ) );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function app360_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && 'app360' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo esc_html( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
            }
        }

        public function payment_fields(){

            if ( $description = $this->get_description() ) {
                echo esc_html( wpautop( wptexturize( $description ) ) );
            }

            $app360_api_domain = get_option('app360_api_domain');
            $app360_api = get_option('app360_api');
            if( $app360_api_domain && $app360_api ){
                $user_id = get_current_user_id();
                $app360_user_id = get_user_meta( $user_id, 'app360_userid', true );

                $url = $app360_api_domain.'/client/member/profile?';
                $url .= 'user_id='.$app360_user_id;
                $headers = array();
                $headers['Content-type'] = 'application/json';
                $headers['apikey'] = $app360_api;
                $response = wp_remote_get($url, ['headers'=> $headers]);

                $result = is_array($response) && isset(json_decode($response['body'])->result) ? json_decode($response['body'])->result : null;
                if(is_array($response)){
                    if($result){
                        echo "Credit Balance: RM ".esc_html($result->balance);
                    }
                }
                else{
                    echo "API gateway not working";
                }
            }
            else{
                echo "API gateway not working";
            }

        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

        	if($order){
        		$order_data = $order->get_data();
        		$user_id = $order->get_customer_id();
        		$app360_user_id = get_user_meta( $user_id, 'app360_userid', true );

        		if($app360_user_id){
        			$app360_api_domain = get_option('app360_api_domain');
        			$app360_api = get_option('app360_api');
        			if( $app360_api_domain && $app360_api ){
        				$order_details = 'Order %23'.$order->id.' details:%0a';
        				foreach ( $order->get_items() as $item_id => $item ) {
        					$name = str_replace('&', 'and', $item->get_name());
        					$quantity = $item->get_quantity();
        					$order_details .= $name.' x'.$quantity.'%0a';
        				}
        				$order_details .= '%0aTotal($): '.$order->order_total;
        				$url = $app360_api_domain.'/client/spend?';
        				$url .= 'amount='.wc_format_decimal($order->get_total(), 2);
        				$url .= '&source_type=e';
        				$url .= '&staff_id=0';
        				$url .= '&outlet_id=1';
        				$url .= '&user_id='.$app360_user_id;
        				$url .= '&reference='.$order_data['id'];
        				$url .= '&additional_text='.$order_details;
        				$headers = array();
        				$headers['Content-type'] = 'application/json';
        				$headers['apikey'] = $app360_api;
        				$response = wp_remote_post($url, ['headers'=> $headers]);
        				$result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
        				if($result){
        					if(isset($result->code)){
        						if($result->code == 'TR9'){
        							wp_delete_post($order_id,true);
        							throw new Exception( __( 'Insufficient balance.', 'woocommerce' ), 110 );
        						}
        						elseif($result->code != '000' && $result->code != 000){
        							wp_delete_post($order_id,true);
        							throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
        						}
        					}
        					$transaction = isset($result->transaction) ? $result->transaction : 0;
        					if(!$transaction){
        						wp_delete_post($order_id,true);
        						throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
        					}
        					$app360_transaction_id = $transaction->id;
        					update_post_meta( $order_id, 'app360_transaction_id', $app360_transaction_id );
        					update_post_meta( $order_id, 'app360_updated', true );
        					$applied_coupons = WC()->cart->get_applied_coupons();
        					if($applied_coupons){
        						foreach($applied_coupons as $coupon){
        							$args = array(
        							    's'  => $coupon,
        							    'post_type'   => 'shop_coupon'
        							);
        							$query = new WP_Query( $args );
        							$coupon_posts = $query->posts;

        							if($coupon_posts){
        								$reward_id = get_post_meta($coupon_posts[0]->ID, 'voucher_id', true) ? get_post_meta($coupon_posts[0]->ID, 'voucher_id', true) : 0;
        								if($reward_id && $reward_id != 0){
        									$url = $app360_api_domain.'/client/voucher/use?';
        									$url .= 'user_id='.$app360_user_id;
        									$url .= '&reward_id='.$reward_id;
        									$headers = array();
        									$headers['Content-type'] = 'application/json';
        									$headers['apikey'] = $app360_api;
        									$response = wp_remote_get($url, ['headers'=> $headers]);

        									$result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
        								}
        							}
        						}
        					}
        				}
        				else{
        					wp_delete_post($order_id,true);
        					throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
        				}
        			}
        			else{
        				wp_delete_post($order_id,true);
        				throw new Exception( __( 'API not working.', 'woocommerce' ), 110 );
        			}
        		}
        		elseif(!is_user_logged_in()){ // if user is not logged in
        			// do nothing
        			wp_delete_post($order_id,true);
        			throw new Exception( __( 'Please Login to use this payment method.', 'woocommerce' ), 110 );
        		}
        		else{
        			wp_delete_post($order_id,true);
        			throw new Exception( __( 'User ID not found.', 'woocommerce' ), 110 );
        		}
        	}

            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

            // Set order status
            $order->update_status( $status, __( 'Checkout with custom payment. ', $this->domain ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
        }
    }
}

add_filter( 'woocommerce_payment_gateways', 'app360_add_custom_gateway_class' );
function app360_add_custom_gateway_class( $methods ) {
    $methods[] = 'app360_WC_Gateway_Custom';
    return $methods;
}

add_action('woocommerce_checkout_process', 'app360_process_custom_payment');
function app360_process_custom_payment(){

    if($_POST['payment_method'] != 'app360')
        return;

}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'app360_custom_payment_update_order_meta' );
function app360_custom_payment_update_order_meta( $order_id ) {
    if($_POST['payment_method'] == 'bacs'){
        $order = wc_get_order( $order_id );
        if($order){
            $order_data = $order->get_data();
            $app360_api_domain = get_option('app360_api_domain');
            $app360_api = get_option('app360_api');
            if( $app360_api_domain && $app360_api ){
                $url = $app360_api_domain.'/payment/gkash/paylink?';
                $url .= '&amount='.$order->order_total;
                $url .= '&description=Order #'.$order->id;
                $headers = array();
                $headers['Content-type'] = 'application/json';
                $response = wp_remote_get($url, ['headers'=> $headers]);
                $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
                if($result){
                    update_post_meta( $order->id, 'gkash_ref_no', $result->ref_no );
                    update_post_meta( $order->id, 'gkash_rem_id', $result->rem_id );
                    $contact = $order->billing_phone;
                    $order_details = 'Order %23'.$order->id.' details:%0a';
                    foreach ( $order->get_items() as $item_id => $item ) {
                        $name = str_replace('&', 'and', $item->get_name());
                        $quantity = $item->get_quantity();
                        $order_details .= $name.' x'.$quantity.'%0a';
                    }
                    $order_details .= '%0aTotal($): '.$order->order_total;
                    if($order->order_total != '0.00'){
                        $order_details .= '%0a%0aPlease make payment here:';
                        $order_details .= '%0a'.$result->paylink;
                    }
                    $url = $app360_api_domain.'/client/message/whatsapp?';
                    $url .= '&contact='.$contact;
                    $url .= '&message='.$order_details;
                    $headers = array();
                    $headers['Content-type'] = 'application/json';
                    $headers['apikey'] = $app360_api;
                    $response = wp_remote_post($url, ['headers'=> $headers]);
                    $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
                }
            }
        }
        return;
    }
}

/**
 * Change order status
 */
/**
 * Change order status
 */
add_action( 'woocommerce_order_status_changed', 'app360_order_status_changed', 99, 3 );
function app360_order_status_changed( $order_id, $old_status, $new_status ){
	$status_app360 = '';
	switch (strtolower($new_status)) {
		case 'completed':
			$status_app360 = 'completed';
			break;
		case 'refunded':
		case 'failed':
		case 'cancelled':
			$status_app360 = 'rejected';
			break;
		default:
			$status_app360 = 'pending';
	}

	if ($status_app360 != '') {
		$app360_api_domain = get_option('app360_api_domain');
		$app360_api = get_option('app360_api');
		if($app360_api_domain && $app360_api){
			$url = $app360_api_domain.'/order/status';

			$param = array();
			$param['order_id'] = $order->id;
			$param['source'] = 'w';
			$param['status'] = $status_app360;

			$headers = array();
			$headers['Content-type'] = 'application/json';
			$headers['apikey'] = $app360_api;
			wp_remote_request($url, [
				'method' => 'PUT',
				'headers'=> $headers,
				'body' => $param
			]);
		}
	}


	if (strtolower($new_status) === 'completed') {
		$order = wc_get_order( $order_id );
		$order_data = $order->get_data();

		if ($order_data['payment_method'] === 'app360') {
			//skip app360
			return;
		}

		$app360_transaction_id = $order->get_meta('app360_transaction_id');
		$app360_updated = $order->get_meta('app360_updated');

		if ($app360_updated === true || $app360_transaction_id) {
			//processed
			return;
		}

		$app360_api_domain = get_option('app360_api_domain');
		$app360_api = get_option('app360_api');
		if( $app360_api_domain && $app360_api ){
			$user_id = $order->get_customer_id();
			$app360_user_id = get_user_meta( $user_id, 'app360_userid', true );

			$order_details = 'Order %23'.$order->id.' details:%0a';
			foreach ( $order->get_items() as $item_id => $item ) {
				$name = str_replace('&', 'and', $item->get_name());
				$quantity = $item->get_quantity();
				$order_details .= $name.' x'.$quantity.'%0a';
			}
			$order_details .= '%0aTotal($): '.$order->order_total;
			$url = $app360_api_domain.'/client/spend?';
			$url .= 'amount='.wc_format_decimal($order->get_total(), 2);
			$url .= '&source_type=e';
			$url .= '&staff_id=0';
			$url .= '&outlet_id=1';
			$url .= '&user_id='.$app360_user_id;
			$url .= '&reference='.$order_data['id'];
			$url .= '&additional_text='.$order_details;
			$url .= '&mock_spend=1';
			$headers = array();
			$headers['Content-type'] = 'application/json';
			$headers['apikey'] = $app360_api;
			$response = wp_remote_post($url, ['headers'=> $headers]);
			$result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
			if($result){
				if(isset($result->code)){
					if($result->code != '000' && $result->code != 000){
						if (isset($result->message)) {
							throw new Exception( __( $result->message, 'woocommerce' ), 110 );
						} else {
							throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
						}
					}
				}
				$transaction = isset($result->transaction) ? $result->transaction : 0;
				if(!$transaction){
					throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
				}
				$app360_transaction_id = $transaction->id;
				update_post_meta( $order_id, 'app360_transaction_id', $app360_transaction_id );
				update_post_meta( $order_id, 'app360_updated', true );
			}
		}
	}

	if (strtolower($new_status) === 'processing') {
		$order = wc_get_order( $order_id );
		$order_data = $order->get_data();

		if ($order_data['payment_method'] === 'app360') {
			//skip app360
			return;
		}

		$user_id = $order->get_customer_id();
		$app360_user_id = get_user_meta( $user_id, 'app360_userid', true );

		if($app360_user_id){
			$app360_api_domain = get_option('app360_api_domain');
			$app360_api = get_option('app360_api');
			if( $app360_api_domain && $app360_api ){
				foreach($order->get_coupon_codes() as $coupon_code){
					$args = array(
					    's'  => $coupon_code,
					    'post_type'   => 'shop_coupon'
					);
					$query = new WP_Query( $args );
					$coupon_posts = $query->posts;
					if($coupon_posts){
						$reward_id = get_post_meta($coupon_posts[0]->ID, 'voucher_id', true) ? get_post_meta($coupon_posts[0]->ID, 'voucher_id', true) : 0;
						if($reward_id && $reward_id != 0){
							$url = $app360_api_domain.'/client/voucher/use?';
							$url .= 'user_id='.$app360_user_id;
							$url .= '&reward_id='.$reward_id;
							$headers = array();
							$headers['Content-type'] = 'application/json';
							$headers['apikey'] = $app360_api;
							$response = wp_remote_get($url, ['headers'=> $headers]);

							$result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
							if($result){
								if(isset($result->available)){
									if($result->available === false){
										$order->set_status($old_status);
										$order->save();
										throw new Exception( __( 'Voucher '.$coupon_code.' not available.', 'woocommerce' ), 110 );
									}
								} else {
									$order->set_status($old_status);
									$order->save();
									throw new Exception( __( 'Use Voucher API call failed.', 'woocommerce' ), 110 );
								}
							}
						}
					}
				}
			} else{
				$order->set_status($old_status);
				$order->save();
				throw new Exception( __( 'API not working.', 'woocommerce' ), 110 );
			}
		} else {
			$order->set_status($old_status);
			$order->save();
			throw new Exception( __( 'User ID not found.', 'woocommerce' ), 110 );
		}
	}

//    if($new_status == 'processing'){
//        $order = wc_get_order( $order_id );
//        $order->set_status('completed');
//        $order->save();
//    }
}

/**
 * Trigger After payment complete
 */
//add_action( 'woocommerce_payment_complete', 'app360_payment_complete' );
function app360_payment_complete( $order_id ){
    if(!get_post_meta($order_id, 'app360_updated', true)){
        $order = wc_get_order( $order_id );
        if($order){
            $order_data = $order->get_data();
            $user_id = $order->get_customer_id();
            $app360_user_id = get_user_meta( $user_id, 'app360_userid', true );

            if($app360_user_id){
                $app360_api_domain = get_option('app360_api_domain');
                $app360_api = get_option('app360_api');
                if( $app360_api_domain && $app360_api ){
                    $order_details = 'Order %23'.$order->id.' details:%0a';
                    foreach ( $order->get_items() as $item_id => $item ) {
                        $name = str_replace('&', 'and', $item->get_name());
                        $quantity = $item->get_quantity();
                        $order_details .= $name.' x'.$quantity.'%0a';
                    }
                    $order_details .= '%0aTotal($): '.$order->order_total;
                    $url = $app360_api_domain.'/client/spend?';
                    $url .= 'amount='.wc_format_decimal($order->get_total(), 2);
                    $url .= '&source_type=e';
                    $url .= '&staff_id=0';
                    $url .= '&outlet_id=1';
                    $url .= '&user_id='.$app360_user_id;
                    $url .= '&reference='.$order_data['id'];
                    $url .= '&additional_text='.$order_details;
                    $headers = array();
                    $headers['Content-type'] = 'application/json';
                    $headers['apikey'] = $app360_api;
                    $response = wp_remote_post($url, ['headers'=> $headers]);
                    $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
                    if($result){
                        if(isset($result->code)){
                            if($result->code == 'TR9'){
                                wp_delete_post($order_id,true);
                                throw new Exception( __( 'Insufficient balance.', 'woocommerce' ), 110 );
                            }
                            elseif($result->code != '000' && $result->code != 000){
                                wp_delete_post($order_id,true);
                                throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
                            }
                        }
                        $transaction = isset($result->transaction) ? $result->transaction : 0;
                        if(!$transaction){
                            wp_delete_post($order_id,true);
                            throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
                        }
                        $app360_transaction_id = $transaction->id;
                        update_post_meta( $order_id, 'app360_transaction_id', $app360_transaction_id );
                        update_post_meta( $order_id, 'app360_updated', true );
                        $applied_coupons = $order->get_coupon_codes();
                        if($applied_coupons){
                            foreach($applied_coupons as $coupon){
                                $args = array(
                                    's'  => $coupon,
                                    'post_type'   => 'shop_coupon'
                                );
                                $query = new WP_Query( $args );
                                $coupon_posts = $query->posts;

                                if($coupon_posts){
                                    $reward_id = get_post_meta($coupon_posts[0]->ID, 'voucher_id', true) ? get_post_meta($coupon_posts[0]->ID, 'voucher_id', true) : 0;
                                    if($reward_id && $reward_id != 0){
                                        $url = $app360_api_domain.'/client/voucher/use?';
                                        $url .= 'user_id='.$app360_user_id;
                                        $url .= '&reward_id='.$reward_id;
                                        $headers = array();
                                        $headers['Content-type'] = 'application/json';
                                        $headers['apikey'] = $app360_api;
                                        $response = wp_remote_get($url, ['headers'=> $headers]);

                                        $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
                                    }
                                }
                            }
                        }
                    }
                    else{
                        wp_delete_post($order_id,true);
                        throw new Exception( __( 'Payment Gateway service down.', 'woocommerce' ), 110 );
                    }
                }
                else{
                    wp_delete_post($order_id,true);
                    throw new Exception( __( 'API not working.', 'woocommerce' ), 110 );
                }
            }
            elseif(!is_user_logged_in()){ // if user is not logged in
                // do nothing
            }
            else{
                wp_delete_post($order_id,true);
                throw new Exception( __( 'User ID not found.', 'woocommerce' ), 110 );
            }
        }
    }
}

function app360_required_stylesheets(){
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
}
add_action('wp_enqueue_scripts','app360_required_stylesheets');

/**
 * Notify button
 */
add_action( 'woocommerce_order_details_after_order_table', 'app360_order_again_button' );
function app360_order_again_button( $order ) {
    /*
        %23 = #
        %0a = new line
    */
	$order_id = $order->get_id();
    $order_details = 'Order %23'.$order_id.' details:%0a';
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    $phone = '';
    $url = $app360_api_domain.'/client/contact';
    $headers = array();
    $headers['Content-type'] = 'application/json';
    $headers['apikey'] = $app360_api;
    $response = wp_remote_get($url, ['headers'=> $headers]);
    $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
    if($result){
        $phone = $result->contact;
    }
    foreach ( $order->get_items() as $item_id => $item ) {
        $name = str_replace('&', 'and', $item->get_name());
        $quantity = $item->get_quantity();
        $order_details .= $name.' x'.$quantity.'%0a';
    }
    echo '<p class="order-again">';
    if($phone != null){
        echo '<a href="https://api.whatsapp.com/send?phone='.esc_attr($phone).'&text='.esc_attr($order_details).'" class="button" target="_blank"><i class="fa fa-whatsapp"></i> Notify Us!</a> ';
    }
    $url = wp_nonce_url( add_query_arg( 'order_again', $order_id ) , 'woocommerce-order_again' );
	//echo '<a href="'.$url.'" class="button">Order again</a>';
    if(!is_user_logged_in()){
        echo
        '<form method="POST" action="'.get_permalink( get_option('woocommerce_myaccount_page_id') ).'">
            <input type="hidden" name="contact" value="'.esc_attr($order->billing_phone).'"/>
            <input type="hidden" name="username" value="'.esc_attr($order->billing_first_name).' '.esc_attr($order->billing_last_name).'"/>
            <input type="hidden" name="email" value="'.esc_attr($order->billing_email).'"/>
            <button>Register Now</button>
        </form>';
    }
    echo '</p>';
};

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'app360_custom_checkout_field_display_admin_order_meta', 10, 1 );
function app360_custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->get_id(), '_payment_method', true );
    if($method != 'app360')
        return;

    $transaction_id = get_post_meta( $order->get_id(), 'app360_transaction_id', true );
    $app360_crm_domain = str_replace('api','crm',get_option('app360_api_domain'));

    echo '<p><strong>'.__( 'Transaction ID').':</strong><br/><a target="_blank" href="'.esc_attr($app360_crm_domain).'/transaction/view/'.esc_attr($transaction_id).'">' . esc_html($transaction_id) . '</a></p>';

    $gkash_ref_no = get_post_meta( $order->get_id(), 'gkash_ref_no', true);
    if($gkash_ref_no){
        echo '<p><strong>'.__( 'GKash Paylink Reference No.').':</strong><br/>'.esc_html($gkash_ref_no).'</p>';
    }
}

add_filter( 'woocommerce_cart_needs_payment', 'filter_cart_needs_payment', 10, 2 );
function filter_cart_needs_payment( $needs_payment, $cart  ) {
    $needs_payment = true;
    return  $needs_payment;
}

add_filter( 'woocommerce_checkout_coupon_message', 'app360_have_coupon_message');
function app360_have_coupon_message() {
    $url = get_permalink( get_option('woocommerce_myaccount_page_id') ).'/voucher';
    return '<i class="fa fa-ticket" aria-hidden="true"></i> Have a coupon? <a href="#" class="showcoupon">Click here to enter your discount code</a>. <a href="'.esc_attr($url).'" target="_blank">Check your voucher here!</a>';
}

add_action( 'woocommerce_cart_coupon', 'app360_cart_coupon', 10, 0 );
function app360_cart_coupon() {
    $url = get_permalink( get_option('woocommerce_myaccount_page_id') ).'/voucher';
    echo ' <a href="'.esc_attr($url).'" target="_blank">Check your voucher here!</a>';
};

add_action( 'manage_posts_extra_tablenav', 'app360_order_payment_check_button', 20, 1 );
function app360_order_payment_check_button( $which ) {
    global $pagenow, $typenow;

    if ( 'shop_order' === $typenow && 'edit.php' === $pagenow && 'top' === $which ) {
        //var_dump('test');die;
        ?>
        <div class="alignleft actions custom">
            <button type="submit" name="payment_check" style="height:32px;" class="button" value="yes"><?php
                echo __( 'Payment check', 'woocommerce' ); ?></button>
        </div>
        <?php
    }
}

add_action( 'restrict_manage_posts', 'display_admin_shop_order_language_filter' );
function display_admin_shop_order_language_filter() {
    global $pagenow, $typenow;

    if ( 'shop_order' === $typenow && 'edit.php' === $pagenow &&
    isset($_GET['payment_check']) && $_GET['payment_check'] === 'yes' ) {
        app360_order_payment_check();
    }
}

function app360_order_payment_check(){
    $args = array(
        'payment_method' => 'bacs',
        'status' => array('wc-on-hold'),
    );
    $orders = wc_get_orders( $args );
    for($i=0; $i<count($orders); $i++){
        $app360_api_domain = get_option('app360_api_domain');
        $app360_api = get_option('app360_api');
        if( $app360_api_domain && $app360_api ){
            $ref_no = get_post_meta($orders[$i]->ID, 'gkash_ref_no', true);
            $rem_id = get_post_meta($orders[$i]->ID, 'gkash_rem_id', true);
            $url = $app360_api_domain.'/payment/gkash/query?';
            $url .= '&reference_id='.$ref_no;
            $url .= '&rem_id='.$rem_id;
            $headers = array();
            $headers['Content-type'] = 'application/json';
            $response = wp_remote_get($url, ['headers'=> $headers]);
            $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
            if($result){
                if($result->status == '88 - Transferred'){
                    $orders[$i]->set_status('completed');
                }
            }
        }
    }
}