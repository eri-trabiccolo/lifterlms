<?php
/**
 * General post table management.
 *
 * @since 3.0.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * General post table management class.
 *
 * @since 3.0.0
 * @since [version] Made sure clone and export links are added only for users who can edit the very post type.
 */
class LLMS_Admin_Post_Tables {

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		// load all post table classes
		foreach ( glob( LLMS_PLUGIN_DIR . '/includes/admin/post-types/post-tables/*.php' ) as $filename ) {
			include_once $filename;
		}

		add_filter( 'post_row_actions', array( $this, 'add_links' ), 777, 2 );
		add_action( 'admin_init', array( $this, 'handle_link_actions' ) );

	}

	/**
	 * Adds clone links to post types which support lifterlms post cloning.
	 *
	 * @since 3.3.0
	 * @since [version] Made sure clone and export links are added only for users who can edit the very post type.
	 *
	 * @param array $actions Existing actions.
	 * @param obj   $post    WP_Post object.
	 * @return array $actions The new actions.
	 */
	public function add_links( $actions, $post ) {
		$post_caps = LLMS_Post_Types::get_post_type_caps( $post->post_type );

		if ( empty( $post_caps['edit_post'] ) ) {
			return $actions;
		}

		if ( current_user_can( $post_caps['edit_post'], $post->ID ) && post_type_supports( $post->post_type, 'llms-clone-post' ) ) {
			$url = add_query_arg( array(
				'post_type' => $post->post_type,
				'action' => 'llms-clone-post',
				'post' => $post->ID,
			) , admin_url( 'edit.php' ) );
			$actions['llms-clone'] = '<a href="' . esc_url( $url ) . '">' . __( 'Clone', 'lifterlms' ) . '</a>';
		}

		if ( current_user_can( $post_caps['edit_post'], $post->ID ) && post_type_supports( $post->post_type, 'llms-export-post' ) ) {
			$url = add_query_arg( array(
				'post_type' => $post->post_type,
				'action' => 'llms-export-post',
				'post' => $post->ID,
			) , admin_url( 'edit.php' ) );
			$actions['llms-export'] = '<a href="' . esc_url( $url ) . '">' . __( 'Export', 'lifterlms' ) . '</a>';
		}

		return $actions;

	}

	/**
	 * Handle events for our custom postrow actions.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function handle_link_actions() {

		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		if ( 'llms-clone-post' !== $_GET['action'] && 'llms-export-post' !== $_GET['action'] ) {
			return;
		}

		if ( ! isset( $_GET['post'] ) ) {
			wp_die( __( 'Missing post ID.', 'lifterlms' ) );
		}

		$post = get_post( $_GET['post'] );

		if ( ! $post ) {
			wp_die( __( 'Invalid post ID.', 'lifterlms' ) );
		}

		if ( ! post_type_supports( $post->post_type, $_GET['action'] ) ) {
			wp_die( __( 'Action cannot be executed on the current post.', 'lifterlms' ) );
		}

		$post = llms_get_post( $post );

		switch ( $_GET['action'] ) {

			case 'llms-export-post':
				$post->export();
			break;

			case 'llms-clone-post':
				$r = $post->clone_post();
				if ( is_wp_error( $r ) ) {
					LLMS_Admin_Notices::flash_notice( $r->get_error_message(), 'error' );
				}
				wp_redirect( admin_url( 'edit.php?post_type=' . $post->get( 'type' ) ) );
				exit;
			break;

		}

	}

	/**
	 * Get the HTML for a post type select2 filter.
	 *
	 * @since    3.12.0
	 *
	 * @param string $name      The name of the select element.
	 * @param string $post_type Optional. The post type to search by. Default 'course'.
	 * @param array  $selected  Optional. An array of POST IDs to use for the pre-selected options on page load. Default empty array.
	 * @return string
	 */
	public static function get_post_type_filter_html( $name, $post_type = 'course', $selected = array() ) {
		$obj = get_post_type_object( $post_type );
		$label = $obj->labels->singular_name;
		ob_start();
		?>
		<span class="llms-post-table-post-filter">
			<label for="<?php printf( 'filter-by-llms-post-%s', $post_type ); ?>" class="screen-reader-text">
				<?php printf( esc_html__( 'Filter by %s', 'lifterlms' ), $label ); ?>
			</label>
			<select class="llms-select2-post" data-allow_clear="true" data-placeholder="<?php printf( esc_attr__( 'Filter by %s', 'lifterlms' ), $label ); ?>" data-post-type="<?php echo $post_type; ?>" name="<?php echo $name; ?>" id="<?php printf( 'filter-by-llms-post-%s', $post_type ); ?>">
				<?php if ( $selected ) : ?>
					<?php foreach ( llms_make_select2_post_array( $selected ) as $data ) : ?>
						<option value="<?php echo $data['key']; ?>"><?php echo $data['title']; ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</span>
		<?php
		return ob_get_clean();
	}


}
return new LLMS_Admin_Post_Tables();
