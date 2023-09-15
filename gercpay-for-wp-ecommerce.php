<?php
/**
 * Plugin Name:   GercPay for WP eCommerce
 * Plugin URI:    https://gercpay.com.ua
 * Description:   GercPay Payment Gateway for WP eCommerce.
 * Version:       1.0.0
 * Author:        GercPay
 * Author URI:    https://mustpay.tech
 * Domain Path:   /lang
 * Text Domain:   gercpay-for-wp-ecommerce
 * License:       GPLv3 or later
 * License URI:   https://opensource.org/licenses/GPL-3.0
 *
 * WP eCommerce requires at least:  3.0.0
 * WP eCommerce tested up to:       3.15.1
 *
 * @package wp-e-commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/../wp-e-commerce/wpsc-includes/merchant.class.php';
require_once __DIR__ . '/gercpay.cls.php';
define( 'GERCPAY_IMGDIR', WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/assets/img/' );

// Minimum PHP, WordPress and WP eCommerce versions needs for GercPay plugin.
const GERCPAY_PHP_VERSION          = 50400;
const GERCPAY_WP_VERSION           = '3.5';
const GERCPAY_WP_ECOMMERCE_VERSION = '3.0';

add_action( 'admin_init', 'gercpay_check_environment' );

/**
 * Check environment parameters
 *
 * @return bool
 */
function gercpay_check_environment() {
	global $wp_version;
	$messages = array();

	if ( PHP_VERSION_ID < GERCPAY_PHP_VERSION ) {
		/* translators: 1: Required PHP version, 2: Running PHP version. */
		$messages[] = sprintf( esc_html__( 'The minimum PHP version required for GercPay is %1$s. You are running %2$s.', 'gercpay-for-wp-ecommerce' ), '5.4.0', (float) PHP_VERSION );
	}

	if ( version_compare( $wp_version, GERCPAY_WP_VERSION, '<' ) ) {
		/* translators: 1: Required WordPress version, 2: Running WordPress version. */
		$messages[] = sprintf( esc_html__( 'The minimum WordPress version required for GercPay is %1$s. You are running %2$s.', 'gercpay-for-wp-ecommerce' ), GERCPAY_WP_VERSION, $wp_version );
	}

	if ( ! is_plugin_active( 'wp-e-commerce/wp-shopping-cart.php' )
	) {
		$messages[] = esc_html__( 'WP eCommerce needs to be activated.', 'gercpay-for-wp-ecommerce' );
	}

	if ( is_plugin_active( 'wp-e-commerce/wp-shopping-cart.php' ) &&
		version_compare( WPSC_VERSION, GERCPAY_WP_ECOMMERCE_VERSION, '<' )
	) {
		/* translators: 1: Required WP eCommerce version, 2: Running WP eCommerce version. */
		$messages[] = sprintf( esc_html__( 'The minimum WP eCommerce version required for GercPay is %1$s. You are running %2$s.', 'gercpay-for-wp-ecommerce' ), GERCPAY_WP_ECOMMERCE_VERSION, WPSC_VERSION );
	}

	if ( ! empty( $messages ) ) {
		add_action( 'admin_notices', 'gercpay_error_notice', 10, 1 );
		foreach ( $messages as $message ) {
			do_action( 'admin_notices', $message );
		}
		deactivate_plugins( plugin_basename( __FILE__ ) );
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	$gercpay_plugin_file = plugin_basename( __FILE__ );
	if ( is_plugin_active( $gercpay_plugin_file ) ) {
		// Add GercPay settings link on Plugins page.
		add_filter( "plugin_action_links_$gercpay_plugin_file", 'gercpay_plugin_settings_link', 10, 1 );
	}

	return false;
}

/**
 * Displays error messages if present.
 *
 * @param string $message Admin error message.
 */
function gercpay_error_notice( $message ) {
	?><div class="error is-dismissible"><p><?php echo $message; ?></p></div>
	<?php
}

/*
 * This is the gateway variable $nzshpcrt_gateways, it is used for displaying gateway information on the wp-admin pages and also
 * for internal operations.
 */
$nzshpcrt_gateways[ $num ] = array(
	'name'            => 'GercPay',
	'internalname'    => 'gercpay',
	'function'        => 'gateway_gercpay',
	'form'            => 'form_gercpay',
	'submit_function' => 'submit_gercpay',
	'class_name'      => 'GercPay',
	'payment_type'    => 'credit_card',
	'display_name'    => 'GercPay',
	'image'           => plugin_dir_url( __FILE__ ) . 'assets/img/gercpay.svg',
);

load_plugin_textdomain( 'gercpay-for-wp-ecommerce', false, basename( __DIR__ ) . '/lang' );

// Variables for translate plugin header.
$plugin_name        = esc_html__( 'GercPay for WP eCommerce', 'gercpay-for-wp-ecommerce' );
$plugin_description = esc_html__( 'GercPay Payment Gateway for WP eCommerce.', 'gercpay-for-wp-ecommerce' );

/**
 * Add GercPay settings link on Plugins page.
 *
 * @param array $links Links under the name of the plugin.
 *
 * @return array
 */
function gercpay_plugin_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=gercpay' ) . '">' . esc_html__( 'Settings', 'gercpay-for-wp-ecommerce' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}

/**
 * Prepare and send payment form to payment gateway
 *
 * @param string $separator Separator.
 * @param string $sessionid Session ID.
 */
function gateway_gercpay( $separator, $sessionid ) {
	global $wpdb;

	$sessionid    = sanitize_text_field( $sessionid );
	$purchase_log = new WPSC_Purchase_Log( $sessionid, 'sessionid' );
	if ( ! $purchase_log->exists() ) {
		return;
	}

	$cart          = $purchase_log->get_cart_contents();
	$order_id      = $purchase_log->get( 'id' );
	$checkout_data = new WPSC_Checkout_Form_Data( $order_id );
	$transactid    = get_bloginfo() . '_' . md5( 'gercpay_' . $order_id );
	$amount        = (float) $purchase_log->get_total();

	$gercpay_args = array(
		'operation'    => 'Purchase',
		'amount'       => $amount,
		'order_id'     => $order_id,
		'currency_iso' => GercPay::CURRENCY_HRYVNA,
		'add_params'   => array(),
		'language'     => GercPay::getInst()->get_gercpay_locale(),
	);

	// Default URL.
	$default_url   = get_site_url();
	$result_ques   = "/?gercpay_results=true&sessionid=$sessionid";
	$callback_ques = "/?gercpay_callback=true&sessionid=$sessionid";

	$gercpay_args['approve_url']  = ( ! empty( get_option( 'gercpay_approve_url' ) ) ) ?
		esc_url( get_option( 'gercpay_approve_url' ) ) . $result_ques :
		$default_url . $result_ques;
	$gercpay_args['decline_url']  = ( ! empty( get_option( 'gercpay_decline_url' ) ) ) ?
		esc_url( get_option( 'gercpay_decline_url' ) ) . $result_ques :
		$default_url . $result_ques;
	$gercpay_args['cancel_url']   = ( ! empty( get_option( 'gercpay_cancel_url' ) ) ) ?
		esc_url( get_option( 'gercpay_cancel_url' ) ) . $result_ques :
		$default_url . $result_ques;
	$gercpay_args['callback_url'] = ( ! empty( get_option( 'gercpay_callback_url' ) ) ) ?
		esc_url( get_option( 'gercpay_callback_url' ) ) . $callback_ques :
		$default_url . $callback_ques;

	foreach ( $cart as $item ) {
		$gercpay_args['productName'][]  = $item->name;
		$gercpay_args['productCount'][] = $item->quantity;
		$gercpay_args['productPrice'][] = $item->price;
	}

	$client_fullname = $checkout_data->get( 'billingfirstname' ) . ' ' . $checkout_data->get( 'billinglastname' );
	$client_phone    = str_replace( array( '+', ' ', '(', ')' ), array( '', '', '', '' ), $checkout_data->get( 'billingphone' ) );
	if ( strlen( $client_phone ) === 10 ) {
		$client_phone = '38' . $client_phone;
	} elseif ( strlen( $client_phone ) === 11 ) {
		$client_phone = '3' . $client_phone;
	}

	$gercpay_args['description'] = esc_html__( 'Payment by card on the site', 'gercpay-for-wp-ecommerce' ) .
		' ' . get_bloginfo() . ', ' . $client_fullname . ', ' . $client_phone;

	// Statistics.
	$gercpay_args['client_first_name'] = $checkout_data->get( 'billingfirstname' ) ?? '';
	$gercpay_args['client_last_name']  = $checkout_data->get( 'billinglastname' ) ?? '';
	$gercpay_args['phone']             = $client_phone;
	$gercpay_args['email']             = $checkout_data->get( 'billingemail' ) ?? '';

	$img = WPSC_URL . '/images/indicator.gif';

	$button = "<img style='position:absolute; top:50%; left:47%; margin-top:-125px; margin-left:-60px;' src='$img' alt=''>
	<script>
		function submitGercPayForm()
		{
			document.getElementById('form_gercpay').submit();
		}
		setTimeout( submitGercPayForm, 200 );
	</script>";

	$pay = GercPay::getInst()->fill_pay_form( $gercpay_args );
	echo $button;
	echo $pay->get_answer();

	$data = array(
		'processed'  => WPSC_Purchase_Log::ORDER_RECEIVED,
		'transactid' => $transactid,
		'date'       => time(),
	);

	$where  = array( 'sessionid' => $sessionid );
	$format = array( '%d', '%s', '%s' );
	$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );

	transaction_results( $sessionid, false, $transactid );

	exit();
}

/**
 * Callback handler.
 */
function gercpay_callback() {
	// Callback url : 'http://yoursite.com/?gercpay_callback=true'.

	if ( ! isset( $_GET['gercpay_callback'], $_GET['sessionid'] ) ||
		empty( $_GET['sessionid'] ) ||
		'true' !== sanitize_text_field( $_GET['gercpay_callback'] )
	) {
		return;
	}

	$data = json_decode( file_get_contents( 'php://input' ), true );
	$data = array_map( 'sanitize_text_field', $data );
	$data['sessionid'] = sanitize_text_field( $_GET['sessionid'] );

	echo GercPay::getInst()->check_response( $data );
	exit();
}

/**
 * Payment results message page.
 */
function gercpay_results() {
	// Callback url : 'http://yoursite.com/?gercpay_results=true'.
	if ( ! isset( $_GET['gercpay_results'] ) ||
		( 'true' !== sanitize_text_field( $_GET['gercpay_results'] ) && ! empty( $_GET['sessionid'] ) )
	) {
		return;
	}

    $session_id = sanitize_text_field( $_GET['sessionid'] );
	( new wpsc_merchant() )->go_to_transaction_results( $session_id );

	exit();
}

/**
 * Returns customer on checkout page.
 */
function return_gercpay_to_checkout() {
	global $wpdb;

	if ( ! isset( $_GET['gercpay_checkout'] ) ||
		'true' !== sanitize_text_field( $_GET['gercpay_checkout'] )
	) {
		return;
	}

	wp_safe_redirect( get_option( 'shopping_cart_url' ) );
	exit(); // follow the redirect with an exit, just to be sure.
}


/**
 * Update Payment Gateway settings.
 *
 * @return bool
 */
function submit_gercpay() {
	$gercpay_settings = array(
		'gercpay_merchant_id',
		'gercpay_secret_key',
		'gercpay_url',
		'gercpay_approve_url',
		'gercpay_decline_url',
		'gercpay_cancel_url',
		'gercpay_callback_url',
		'gercpay_locale',
	);

	foreach ( $gercpay_settings as $setting ) {
		if ( isset( $_POST[ $setting ] ) ) {
			update_option( $setting, sanitize_text_field( wp_unslash( $_POST[ $setting ] ) ) );
		}
	}

	if ( GercPay::getInst()->get_gercpay_locale() !== $gercpay_settings['gercpay_locale'] ) {
		GercPay::getInst()->set_gercpay_locale( $gercpay_settings['gercpay_locale'] );
	}

	return true;
}

/**
 * Generate admin page form.
 *
 * @return string
 */
function form_gercpay() {
	$cells             = get_gercpay_form_init();
	$gercpay_locale = GercPay::getInst()->get_gercpay_locale();
	$otp               = '';
	foreach ( $cells as $key => $val ) {
		$otp .= '<div><label>' . esc_attr( $val['name'] ) . '</label>' .
			( ( ! $val['isInput'] ) ?
				esc_attr( $val['code'] ) :
				'<input type="text" size="40" value="' . esc_attr( get_option( $key ) ) . '" name=' . esc_attr( $key ) . ' />'
			) . '<div class="subtext">' . ( ( '' === $val['subText'] ) ? '&nbsp;' : esc_attr( $val['subText'] ) ) . '</div>';

	}
	$otp .= '<label>' . esc_html__( 'Payment page language', 'gercpay-for-wp-ecommerce' ) . '</label>';
	$otp .= '<select name="gercpay_locale">';
	foreach ( GercPay::GERCPAY_ALLOWED_LOCALES as $locale ) {
		$otp .= '<option value="' . $locale . '" ' . ( $gercpay_locale === $locale ? 'selected' : '' ) . '>' . mb_strtoupper( $locale ) . '</option>';
	}
	$otp .= '</select><div class="subtext">' . esc_html__( 'The language of the GercPay payment page', 'gercpay-for-wp-ecommerce' ) . '</div></div>';

	$output = "<style>
		#gercpayoptions label { width:150px; font-weight:bold; display: inline-block; }
		#gercpayoptions .subtext { margin-left:160px; font-size:12px; font-style:italic; margin-bottom:12px;}
		#gercpayoptions { border:1px dotted #aeaeae; padding:5px; }
		</style>
		<div id='gercpayoptions'>$otp</div>";

	return $output;
}

/**
 * Init admin form fields.
 *
 * @return array[]
 */
function get_gercpay_form_init() {

	return array(
		'gercpay_merchant_id'  => array(
			'name'    => esc_html__( 'Merchant Account', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'Given to Merchant by GercPay', 'gercpay-for-wp-ecommerce' ),
			'isInput' => true,
			'code'    => '',
		),
		'gercpay_secret_key'   => array(
			'name'    => esc_html__( 'Secret key', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'Given to Merchant by GercPay', 'gercpay-for-wp-ecommerce' ),
			'isInput' => true,
			'code'    => '',
		),
		'gercpay_url'          => array(
			'name'    => esc_html__( 'System URL', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'Default URL', 'gercpay-for-wp-ecommerce' ) . ' - https://api.gercpay.com.ua/api/',
			'isInput' => true,
			'code'    => '',
		),
		'gercpay_approve_url'  => array(
			'name'    => esc_html__( 'Successful payment redirect URL', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'Default URL', 'gercpay-for-wp-ecommerce' ) . ' - ' . get_site_url() . '/?gercpay_results=true',
			'isInput' => true,
			'code'    => '',
		),
		'gercpay_decline_url'  => array(
			'name'    => esc_html__( 'Redirect URL failed to pay', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'Default URL', 'gercpay-for-wp-ecommerce' ) . ' - ' . get_site_url() . '/?gercpay_results=true',
			'isInput' => true,
			'code'    => '',
		),
		'gercpay_cancel_url'   => array(
			'name'    => esc_html__( 'Redirect URL in case of failure to make payment', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'Default URL', 'gercpay-for-wp-ecommerce' ) . ' - ' . get_site_url() . '/?gercpay_results=true',
			'isInput' => true,
			'code'    => '',
		),
		'gercpay_callback_url' => array(
			'name'    => esc_html__( 'URL of the result information', 'gercpay-for-wp-ecommerce' ),
			'subText' => esc_html__( 'The URL to which will receive information about the result of the payment', 'gercpay-for-wp-ecommerce' ) . ' (' . get_site_url() . '/?gercpay_callback=true)',
			'isInput' => true,
			'code'    => '',
		),
	);
}

add_action( 'init', 'gercpay_callback' );
add_action( 'init', 'gercpay_results' );
add_action( 'init', 'return_gercpay_to_checkout' );

