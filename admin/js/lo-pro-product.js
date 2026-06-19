/* global jQuery */
/**
 * LaunchOverlay Pro — Product panel JS
 * Color picker init and range slider are handled inline in the PHP render()
 * method after the fields are moved into the panel. This file is a safety
 * net for any additional product-page interactions.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		// Re-init color pickers if WooCommerce JS reinitialises the panel
		$( document ).on( 'woocommerce-product-type-change', function () {
			$( '.lo-pro-color' ).each( function () {
				if ( ! $( this ).closest( '.wp-picker-container' ).length && $.fn.wpColorPicker ) {
					$( this ).wpColorPicker();
				}
			} );
		} );
	} );

} ( jQuery ) );
