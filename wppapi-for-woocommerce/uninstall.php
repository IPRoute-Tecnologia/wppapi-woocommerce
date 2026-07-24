<?php
/**
 * Limpeza ao desinstalar o plugin.
 *
 * @package WPPAPI_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wppapi_wc_settings' );
delete_option( 'wppapi_wc_log' );

// Cancela envios agendados pendentes.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wppapi_wc_send_message', array(), 'wppapi-for-woocommerce' );
}
wp_clear_scheduled_hook( 'wppapi_wc_send_message' );
