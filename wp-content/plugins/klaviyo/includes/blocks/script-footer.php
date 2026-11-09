<?php
/**
 * Move auto-registered checkout block scripts to the footer.
 *
 * @package WooCommerceKlaviyo/Blocks
 */

if ( ! function_exists( 'wck_move_checkout_block_scripts_to_footer' ) ) {
	/**
	 * Move the auto-registered checkout block scripts to the footer.
	 *
	 * WooCommerce requires scripts with a `wc-settings` or `wc-blocks-checkout`
	 * dependency to load in the footer (WC 6.0 / WC Blocks 6.3+). WordPress'
	 * `register_block_type()` auto-registers `viewScript` / `editorScript` from
	 * block.json in the header, so WooCommerce force-moves them at render time
	 * and emits a console warning. Setting `group` to 1 here registers them in
	 * the footer up front. This is the same data `wp_register_script()` writes
	 * when `in_footer` is true. Tracked upstream as WP Trac #54018.
	 */
	function wck_move_checkout_block_scripts_to_footer() {
		$handles = array(
			'klaviyo-klaviyo-checkout-block-view-script',
			'klaviyo-klaviyo-checkout-block-editor-script',
		);
		foreach ( $handles as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_scripts()->add_data( $handle, 'group', 1 );
			}
		}
	}
}
