<?php
class LLMS_Certificate_Builder_Toolbar{

	public function __construct(){

		add_action( 'admin_bar_menu', array( $this, 'builder_link' ), 90 );
	}

	/**
	 *
	 */
	public function builder_link( $admin_bar ) {

		global $post;

		if ( is_admin() ) {
			$current_screen = get_current_screen();

			if (! ( 'post' == $current_screen->base
				&& 'add' != $current_screen->action
				&& current_user_can( 'read_post', $post->ID )
			 )) {
				return;
			}
		}


		if ( empty( $post ) ) {
			return;
		}

		// Check for your post type.
		if ( $post->post_type === "llms_certificate" && current_user_can( 'edit_post', $post->ID ) ) {
			$build_link = llms_certificate_build_url( $post );
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
