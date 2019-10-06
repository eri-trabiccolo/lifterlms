<?php
/**
 * Certificate Builder Toolbar Button.
 *
 * @package LifterLMS/Modules/Certificate_Builder
 *
 * @since   [version] Introduced
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds Build button to WP Toolbar.
 *
 * @since [version] Introduced
 */
class LLMS_Certificate_Builder_Toolbar {

	/**
	 * Constructor
	 *
	 * @since [version] Introduced
	 */
	public function __construct() {
		$this->hook();
	}

	/**
	 * Hooks into tollbar.
	 *
	 * @since [version] Introduced
	 */
	private function hook() {
		add_action( 'admin_bar_menu', array( $this, 'builder_link' ), 90 );
	}

	/**
	 * Create a toolbar button.
	 *
	 * @param $admin_bar WP_Admin_Bar
	 *
	 * @since [version] Introduced
	 */
	public function builder_link( $admin_bar ) {

		global $post;

		// Bail on non-post screens.
		if ( empty( $post ) ) {
			return;
		}

		// Bail if user doesn't have permissions to see the button.
		if ( is_admin() ) {

			$current_screen = get_current_screen();

			if ( ! ( 'post' == $current_screen->base
				&& 'add' != $current_screen->action
				&& current_user_can( 'read_post', $post->ID )
			 ) ) {
				return;
			}

		}

		// Only load on certificate screens.
		if ( 'llms_certificate' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {

			// Fetch the build URL.
			$build_link = llms_certificate_build_url( $post );

			// Add menu item.
			$admin_bar->add_menu(
				array(
					'id'    => 'build',
					'title' => __( 'Launch Builder', 'lifterlms' ),
					'href'  => $build_link,
				)
			);
		}
	}

}

return new LLMS_Certificate_Builder_Toolbar();

/**
 * Redundance exists
 * Because creators resist
 * All instruction lists.
 */
