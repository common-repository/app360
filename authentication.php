<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action("woocommerce_register_form_start", "app360_registration_form_add_custom_fields");

function app360_registration_form_add_custom_fields(){
    ?>
    <?php wp_nonce_field( 'registration_form_submit', 'app360_generate_nonce' );?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e( 'Phone Number', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="contact" autocomplete="contact" id="reg_contact" value="<?php echo ( ! empty( $_POST['contact'] ) ) ? esc_attr( wp_unslash( $_POST['contact'] ) ) : ''; ?>"/>
    </p>
    <p class="form-row">
		<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="register" value="<?php esc_attr_e( 'Send', 'woocommerce' ); ?>"><?php esc_html_e( 'Send code', 'woocommerce' ); ?></button>
	</p>
    <p class="woocommerce-form-row woocommerce-form-row form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e( 'Verification Code', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="contact_tac" id="reg_contact" />
    </p>
    <p class="woocommerce-form-row woocommerce-form-row form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e( 'Birthday', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="birthday" id="reg_birthday" value="<?php echo ( ! empty( $_POST['birthday'] ) ) ? esc_attr( $_POST['birthday'] ) : ''; ?>"/>
    </p>
    <?php
}

add_action( 'woocommerce_register_form', 'app360_register_form_add_confirm_password' );
function app360_register_form_add_confirm_password() {
    ?>
    <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
    <p class="form-row form-row-wide">
        <label for="reg_password2"><?php _e( 'Confirm Password', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="password" class="input-text" name="password2" id="reg_password2" value="<?php echo ( ! empty( $_POST['password2'] ) ) ? esc_attr( $_POST['password2'] ) : ''; ?>" />
    </p>
    <?php endif; ?>
    <?php
}

function app360_login_form_add_custom_fields(){
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e( 'Phone Number', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="login_contact" autocomplete="contact" id="reg_contact" value="<?php echo ( ! empty( $_POST['login_contact'] ) ) ? esc_attr( wp_unslash( $_POST['login_contact'] ) ) : ''; ?>"/>
    </p>
    <p class="form-row">
		<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Send', 'woocommerce' ); ?>"><?php esc_html_e( 'Send code', 'woocommerce' ); ?></button>
	</p>
    <p class="woocommerce-form-row woocommerce-form-row form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e( 'Verification Code', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="login_contact_tac" id="reg_contact" />
    </p>
    <p class="woocommerce-form-row woocommerce-form-row form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e( 'Birthday', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="login_birthday" id="reg_birthday" value="<?php echo ( ! empty( $_POST['login_birthday'] ) ) ? esc_attr( $_POST['login_birthday'] ) : ''; ?>"/>
    </p>
    <?php
}

add_action("woocommerce_login_form", "app360_login_form_nonce");
function app360_login_form_nonce(){
    wp_nonce_field( 'login_form_submit', 'app360_generate_nonce' );
}

add_filter(  'gettext',  'app360_register_text'  );
add_filter(  'ngettext',  'app360_register_text'  );
function app360_register_text( $translated ) {
    if ( $GLOBALS['pagenow'] !== 'wp-login.php' ){
        $translated = str_ireplace(  'Username or Email Address',  'Phone number or email address',  $translated );
        $translated = str_ireplace(  'Username or Email',  'Phone number or email address',  $translated );
        $translated = str_ireplace(  'Username',  'Name',  $translated );
    }
    return $translated;
}