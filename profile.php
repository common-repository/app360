<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

remove_action('init', 'save_account_details');
add_action('init', 'app360_save_account_details');

function app360_save_account_details(){
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    if( $app360_api_domain && $app360_api ){
        $nonce_value = wc_get_var( $_REQUEST['save-account-details-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

        if ( ! wp_verify_nonce( $nonce_value, 'save_account_details' ) ) {
            return;
        }

        if ( empty( $_POST['action'] ) || 'save_account_details' !== $_POST['action'] ) {
            return;
        }

        wc_nocache_headers();

        $user_id = get_current_user_id();

        if ( $user_id <= 0 ) {
            return;
        }

        $account_first_name   = ! empty( $_POST['account_first_name'] ) ? sanitize_text_field(wc_clean( wp_unslash( $_POST['account_first_name'] ) )) : '';
        $account_last_name    = ! empty( $_POST['account_last_name'] ) ? sanitize_text_field(wc_clean( wp_unslash( $_POST['account_last_name'] ) )) : '';
        $account_display_name = ! empty( $_POST['account_display_name'] ) ? sanitize_text_field(wc_clean( wp_unslash( $_POST['account_display_name'] ) )) : '';
        $account_email        = ! empty( $_POST['account_email'] ) ? sanitize_email(wc_clean( wp_unslash( $_POST['account_email'] ) )) : '';
        $account_contact      = ! empty( $_POST['account_contact']) ? sanitize_text_field(wc_clean( wp_unslash( $_POST['account_contact'] ) )) : '';
        $pass_cur             = ! empty( $_POST['password_current'] ) ? $_POST['password_current'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $pass1                = ! empty( $_POST['password_1'] ) ? $_POST['password_1'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $pass2                = ! empty( $_POST['password_2'] ) ? $_POST['password_2'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $save_pass            = true;

        // Current user data.
        $current_user       = get_user_by( 'id', $user_id );
        $current_first_name = $current_user->first_name;
        $current_last_name  = $current_user->last_name;
        $current_email      = $current_user->user_email;

        // New user data.
        $user               = new stdClass();
        $user->ID           = $user_id;
        $user->first_name   = $account_first_name;
        $user->last_name    = $account_last_name;
        $user->display_name = $account_display_name;

        //change phone number
        $args = array(
            'include' => array($user_id),
            'meta_key' => 'contact',
            'meta_value' => $account_contact 
        );
        $query = new WP_User_Query($args);
        $result = $query->get_results();
        if(!$result){
            $args = array(
                'meta_key' => 'contact',
                'meta_value' => $account_contact 
            );
            $query = new WP_User_Query($args);
            $result = $query->get_results();
            if(!$result){
                update_user_meta($user_id, 'contact', $account_contact);
            }
            else{
                wc_add_notice( __( 'This phone number is already registered.', 'woocommerce' ), 'error' );
            }
        }

        // Prevent display name to be changed to email.
        if ( is_email( $account_display_name ) ) {
            wc_add_notice( __( 'Display name cannot be changed to email address due to privacy concern.', 'woocommerce' ), 'error' );
        }

        // Handle required fields.
        $required_fields = apply_filters(
            'woocommerce_save_account_details_required_fields',
            array(
                'account_first_name'   => __( 'First name', 'woocommerce' ),
                'account_last_name'    => __( 'Last name', 'woocommerce' ),
                'account_display_name' => __( 'Display name', 'woocommerce' ),
                'account_email'        => __( 'Email address', 'woocommerce' ),
            )
        );

        foreach ( $required_fields as $field_key => $field_name ) {
            if ( empty( $_POST[ $field_key ] ) ) {
                /* translators: %s: Field name. */
                wc_add_notice( sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field_name ) . '</strong>' ), 'error', array( 'id' => $field_key ) );
            }
        }

        if ( $account_email ) {
            $account_email = sanitize_email( $account_email );
            if ( ! is_email( $account_email ) ) {
                wc_add_notice( __( 'Please provide a valid email address.', 'woocommerce' ), 'error' );
            } elseif ( email_exists( $account_email ) && $account_email !== $current_user->user_email ) {
                wc_add_notice( __( 'This email address is already registered.', 'woocommerce' ), 'error' );
            }
            $user->user_email = $account_email;
        }

        if ( ! empty( $pass_cur ) && empty( $pass1 ) && empty( $pass2 ) ) {
            wc_add_notice( __( 'Please fill out all password fields.', 'woocommerce' ), 'error' );
            $save_pass = false;
        } elseif ( ! empty( $pass1 ) && empty( $pass_cur ) ) {
            wc_add_notice( __( 'Please enter your current password.', 'woocommerce' ), 'error' );
            $save_pass = false;
        } elseif ( ! empty( $pass1 ) && empty( $pass2 ) ) {
            wc_add_notice( __( 'Please re-enter your password.', 'woocommerce' ), 'error' );
            $save_pass = false;
        } elseif ( ( ! empty( $pass1 ) || ! empty( $pass2 ) ) && $pass1 !== $pass2 ) {
            wc_add_notice( __( 'New passwords do not match.', 'woocommerce' ), 'error' );
            $save_pass = false;
        } elseif ( ! empty( $pass1 ) && ! wp_check_password( $pass_cur, $current_user->user_pass, $current_user->ID ) ) {
            wc_add_notice( __( 'Your current password is incorrect.', 'woocommerce' ), 'error' );
            $save_pass = false;
        }

        if ( $pass1 && $save_pass ) {
            $user->user_pass = $pass1;
        }

        // Allow plugins to return their own errors.
        $errors = new WP_Error();
        do_action_ref_array( 'woocommerce_save_account_details_errors', array( &$errors, &$user ) );

        if ( $errors->get_error_messages() ) {
            foreach ( $errors->get_error_messages() as $error ) {
                wc_add_notice( $error, 'error' );
            }
        }

        if ( wc_notice_count( 'error' ) === 0 ) {
            wp_update_user( $user );

            // Update customer object to keep data in sync.
            $customer = new WC_Customer( $user->ID );

            if ( $customer ) {
                // Keep billing data in sync if data changed.
                if ( is_email( $user->user_email ) && $current_email !== $user->user_email ) {
                    $customer->set_billing_email( $user->user_email );
                }

                if ( $current_first_name !== $user->first_name ) {
                    $customer->set_billing_first_name( $user->first_name );
                }

                if ( $current_last_name !== $user->last_name ) {
                    $customer->set_billing_last_name( $user->last_name );
                }

                $customer->save();
            }

            $url = $app360_api_domain.'/client/profile/update?';
            $url .= 'email='.$account_email;
            $url .= '&contact='.$account_contact;
            $url .= '&password='.$pass1;
            $url .= '&current_password='.$save_pass;
            $url .= '&fullname='.$account_display_name;
            $headers = array();
            $headers['Content-type'] = 'application/json';
            $headers['apikey'] = $app360_api;
            $headers['userid'] = get_user_meta($user_id, 'app360_userid')[0];
            $response = wp_remote_get($url, ['headers'=> $headers]);

            wc_add_notice( __( 'Account details changed successfully.', 'woocommerce' ) );

            do_action( 'woocommerce_save_account_details', $user->ID );

            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    }
}

add_action("woocommerce_edit_account_form_start", "app360_form_edit_profile");

function app360_form_edit_profile(){
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_contact"><?php esc_html_e( 'Phone Number', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_contact" id="account_contact" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'contact')[0]);?>" />
	</p>
    <?php
}

add_action('woocommerce_account_dashboard', 'app360_display_balance_in_profile');
function app360_display_balance_in_profile(){
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

        $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
        if($result){
            $url = get_permalink( get_option('woocommerce_myaccount_page_id') );
            echo"<div style='background-color:rgb(245, 245, 245); padding: 15px'>";
            if($result->web_url){
                echo "<p><a href='https://".esc_attr($result->web_url)."'><u>Member Home</u></a></p>";
            }
            if($result->module->module_topup){
                echo "<p>Credit Balance: RM ".esc_attr($result->result->balance) . "</p>";
            }
            if($result->module->module_point){
                echo "<p>Reward Point: " . esc_attr($result->point_balance) . "</p>";
            }
            if($result->module->module_tier){
                echo "<p>Member Tier: " . esc_attr($result->result->tier->name) . "</p>";
            }
            if($result->module->module_voucher){
                echo "<p><a href='".esc_attr($url).'voucher'."'><u>Vouchers</u></a></p>";
            }
            if($result->module->module_stamp){
                echo "<p><a href='".esc_attr($url).'stamp'."'><u>Stamps</u></a></p>";
            }
            echo "</div>";
        }
    }
}

/*
 * Step 1. Add Link (Tab) to My Account menu
 */
add_filter ( 'woocommerce_account_menu_items', 'app360_profile_voucher_stamp_link', 40 );
function app360_profile_voucher_stamp_link( $menu_links ){
 
	$menu_links = array_slice( $menu_links, 0, 5, true ) 
	+ array( 'voucher' => 'Vouchers' )
    + array( 'stamp' => 'Stamps' )
	+ array_slice( $menu_links, 5, NULL, true );
 
	return $menu_links;
 
}

/*
 * Step 2. Register Permalink Endpoint
 */
add_action( 'init', 'app360_profile_voucher_endpoint' );
function app360_profile_voucher_endpoint() {
	add_rewrite_endpoint( 'voucher', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'app360_profile_stamp_endpoint' );
function app360_profile_stamp_endpoint() {
	add_rewrite_endpoint( 'stamp', EP_ROOT | EP_PAGES );
}

/*
 * Step 3. Content for the new page in My Account, woocommerce_account_{ENDPOINT NAME}_endpoint
 */
add_action( 'woocommerce_account_voucher_endpoint', 'app360_profile_voucher_endpoint_content' );
function app360_profile_voucher_endpoint_content() {
    $app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    $url = $app360_api_domain.'/client/member/vouchers?';
    $url .= '&reward_type=voucher';
    $url .= '&status=available';
    $url .= '&user_id='.get_user_meta(get_current_user_id(), 'app360_userid')[0];
    $headers = array();
    $headers['Content-type'] = 'application/json';
    $headers['apikey'] = $app360_api;
    $response = wp_remote_get($url, ['headers'=> $headers]);
    $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
    if($result && $result->code != 999){
        foreach($result->rewards as $reward){
            if($reward->expires_at == null){
                echo '
                <h3>'.esc_html($reward->title).' : '.esc_html($reward->subtitle).'</h3>
                No expiry<br/>
                <a href="'.esc_attr($reward->image->src).'" target="popup" onclick="window.open('."'".esc_attr($reward->image->src)."'".','."'".'name'."'".','."'".'width=600,height=300'."'".')">View Image</a>
                <hr/><br/>
                ';
            }
            else{
                echo '
                <h3>'.esc_html($reward->title).' : '.esc_html($reward->subtitle).'</h3>
                Expired on '.esc_html($reward->expires_at).'<br/>
                <a href="'.esc_attr($reward->image->src).'" target="popup" onclick="window.open('."'".esc_attr($reward->image->src)."'".','."'".'name'."'".','."'".'width=600,height=300'."'".')">View Image</a>
                <hr/><br/>
                ';
            }
        }
    }
	
}
add_action( 'woocommerce_account_stamp_endpoint', 'app360_profile_stamp_endpoint_content' );
function app360_profile_stamp_endpoint_content() {
    if($_GET['claim']==1){
        $app360_api_domain = get_option('app360_api_domain');
        $app360_api = get_option('app360_api');
        $url = $app360_api_domain.'/client/stamp/redeem';
        $headers = array();
        $headers['Content-type'] = 'application/json';
        $headers['apikey'] = $app360_api;
        $headers['userid'] = get_user_meta(get_current_user_id(), 'app360_userid')[0];
        $response = wp_remote_get($url, ['headers'=> $headers]);
        $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
    }
	$app360_api_domain = get_option('app360_api_domain');
    $app360_api = get_option('app360_api');
    $url = $app360_api_domain.'/client/member/stamps';
    $headers = array();
    $headers['Content-type'] = 'application/json';
    $headers['apikey'] = $app360_api;
    $headers['userid'] = get_user_meta(get_current_user_id(), 'app360_userid')[0];
    $response = wp_remote_get($url, ['headers'=> $headers]);
    $result = is_array($response) && isset($response['body']) ? json_decode($response['body']) : null;
    if($result && $result->code != 999){
        echo 'Stamp balance: <h4>'.esc_html($result->balance).'/'.esc_html($result->campaign->trigger_value).'</h4><br/>';
        if($result->balance >= $result->campaign->trigger_value){
            $url = '?claim=1';
            echo '<a href="'.esc_attr($url).'" class="button">Redeem</a>';
        }
        foreach($result->transaction as $reward){
            if($reward->redeemed_at == null){
                echo '
                <h3>Stamp</h3>
                Expired on '.esc_html($reward->expires_at).'
                <hr/><br/>
                ';
            }
            else{
                echo '
                <strike><h3>Stamp</h3></strike>
                <strike>Expired on '.esc_html($reward->expires_at).'</strike>
                <hr/><br/>
                ';
            }
        }
    }
}