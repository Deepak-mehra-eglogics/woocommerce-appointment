<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WC_Dependencies' ) ) {
	require_once 'class-wc-dependencies.php';
}

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		return WC_Dependencies::woocommerce_active_check();
	}
}

/**
 * Plugin Detection
 */
if ( ! function_exists( 'is_wp_plugin_active' ) ) {
	function is_wp_plugin_active( $plugin ) {
		return WC_Dependencies::plugin_active_check( $plugin );
	}
}
