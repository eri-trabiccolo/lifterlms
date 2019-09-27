<?php
/**
 * Post Table extensions for Certificate Builder
 *
 * @package LifterLMS/Modules/Certificate_Builder
 *
 * @since [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;


/**
 * Handles post editor & post table modifications.
 *
 * @since    [version]
 */
class LLMS_Certificate_Post_Table {

	/**
	 * Constructor
	 *
	 * Hooks editor related modifications to Certificate post type.
	 *
	 * @since    [version]
	 */
	public function __construct() {
		$this->hook();
	}

	public function hook(){
		// hook build link to posts table.
		add_filter( 'post_row_actions', array( $this, 'build_action' ), 10, 2 );
	}

	/**
	 * Adds builder link to post actions.
	 *
	 * @param array $actions
	 * @param WP_Post $post
	 *
	 * @return array
	 *
	 * @since    [version]
	 */
	public function build_action( $actions, $post ) {

		// Only load for certificates and for appropriate permissions.
		if ( 'llms_certificate' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {

			// don't show on trashed certificates
			if ( in_array( $post->post_status, array( 'trash', 'llms-legacy' ) ) ) {
				return $actions;
			}

			// Get the build url.
			$build_url = llms_certificate_build_url( $post->ID );

			// Build action.
			$build_action = array(
				'build' => sprintf( '<a href="%1$s">%2$s</a>', $build_url, __( 'Build', 'lifterlms' ) ),
			);

			// prepend build url to post actions.
			$actions = $build_action + $actions;
		}

		return $actions;
	}

}

return new LLMS_Certificate_Post_Table();
