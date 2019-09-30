'<?php
/**
 * Post Table/Editor extensions
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
class LLMS_Certificate_Editor {

	/**
	 * All available overlay configurations.
	 *
	 * @var array $overlay_configs
	 */
	private $overlay_configs = array();

	/**
	 * Current overlay configuration.
	 *
	 * @var array $current_overlay_configs
	 */
	private $current_overlay_config = array();

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

	/**
	 * Set
	 */
	protected function configure() {

		$builder_url = llms_certificate_build_url();

		$overlay_config = array(
			'add' => array(
				'title' => __( 'New certificates must be saved before using the builder.', 'lifterlms' ),
				'buttons' => array(
					array(
						'class' => array( 'llms-certificate-switch', 'button-secondary' ),
						'text' => __( 'Use WP Editor', 'lifterlms' ),
					),
					array(
						'class' => array( 'llms-certificate-save', 'button-primary' ),
						'text' => __( 'Save Draft & Launch Builder', 'lifterlms' ),
					),
				),
			),
			'edit' => array(
				'title' => __( 'Drag & Drop Builder is active on this certificate.', 'lifterlms' ),
				'buttons' => array(
					array(
						'class' => array( 'llms-certificate-switch', 'button-secondary' ),
						'text' => __( 'Use WP Editor', 'lifterlms' ),
					),
					array(
						'class' => array( 'llms-certificate-build', 'button-primary' ),
						'text' => __( 'Launch Builder', 'lifterlms' ),
						'url' => $builder_url,
					),
				),
			),
			'legacy_redirect' => array(
				'title' => __( "You've been redirected from a legacy certificate. Drag & Drop Builder is active on this certificate.", 'lifterlms' ),
				'buttons' => array(
					array(
						'class' => array( 'llms-certificate-switch', 'button-secondary' ),
						'text' => __( 'Use WP Editor', 'lifterlms' ),
					),
					array(
						'class' => array( 'llms-certificate-build', 'button-primary' ),
						'text' => __( 'Launch Builder', 'lifterlms' ),
						'url' => $builder_url,
					),
				),
			),
			'legacy' => array(
				'title' => __( 'Legacy certificate detected! Why not migrate to a new Drag & Drop Builder?', 'lifterlms' ),
				'buttons' => array(
					array(
						'class' => array( 'llms-certificate-switch', 'button-secondary' ),
						'text' => __( 'Use WP Editor', 'lifterlms' ),
					),
					array(
						'class' => array( 'llms-certificate-migrate', 'button-primary' ),
						'text' => __( 'Migrate to Builder', 'lifterlms' ),
					),
				),
			),
			'migrated_to' => array(
				'title' => __( "Congratulations! Your older certificate has been migrated to the new drag and drop builder. We haven't deleted the old certificate yet, and you can choose to rollback if you want.", 'lifterlms' ),
				'buttons' => array(
					array(
						'class' => array( 'llms-certificate-switch', 'button-secondary' ),
						'text' => __( 'Use WP Editor', 'lifterlms' ),
					),
					array(
						'class' => array( 'llms-certificate-builder-migrated', 'button-primary' ),
						'text' => __( 'Launch Drag & Drop Builder', 'lifterlms' ),
					),
				),
			),
		);

		/**
		 * Filters all available overlay configurations
		 *
		 * @since    [version]
		 */
		$this->overlay_configs = apply_filters( 'llms_certificate_editor_overlay_configurations', $overlay_config );

	}

	protected function hook() {

		// hook redirection for legacy certificates.
		add_action( 'admin_init', array( $this, 'redirect_legacy' ) );

		// hook editor overlay.
		add_action( 'current_screen', array( $this, 'maybe_overlay_editor' ) );

		// set default post content for new certificates.
		add_filter( 'default_content', array( $this, 'default_content' ), 10, 2 );

	}

	/**
	 * limit this to certificate post types only
	 */
	public function redirect_legacy() {

		$post_id = llms_filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		if ( empty( $post_id ) ){
			$post_id = llms_filter_input( INPUT_POST, 'post', FILTER_VALIDATE_INT );
		}

		// bail if not single post action
		if ( empty( $post_id ) ) {
			return;
		}

		// don't interfere with deletion
		if ( 'edit' !== llms_filter_input( INPUT_GET, 'action' ) ) {
			return;
		}

		// override for already migrated legacy certificates
		$modern = LLMS_Certificate_Migrator::is_legacy_of_modern( $post_id );

		if ( false === $modern ) {
			return;
		}

		// don't interfere with deletion
		if ( 'trash' === llms_filter_input( INPUT_POST, 'get', FILTER_VALIDATE_STRING ) ) {
			return;
		}

		$modern_edit_url = get_edit_post_link( $modern, '' );

		$redirect_url = add_query_arg(
			array(
				'llms-certificate-legacy-redirect' => true, // special query parameter to trigger content migration.
			),
			$modern_edit_url
		);

		if ( wp_redirect( $redirect_url ) ) {
			exit();
		}
	}

	/**
	 * Conditionally overlays WP Editor.
	 *
	 * @since    [version]
	 */
	public function maybe_overlay_editor() {

		// get the current admin screen.
		$screen = get_current_screen();

		// if no post type is set, no point doing anything.
		if ( ! isset( $screen->post_type ) ) {
			return;
		}

		// only load for certificates.
		if ( 'llms_certificate' !== $screen->post_type ) {
			return;
		}

		$this->set_config( $screen->action );

		// add a build button alongside the Add Media button.
		add_action( 'media_buttons', array( $this, 'builder_button' ) );

		// add overlay markup after the form. this will be moved in DOM by js.
		add_action( 'edit_form_after_editor', array( $this, 'editor_overlay' ) );

		// enqueue js and css
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

	}

	/**
	 * Sets up Overlay configuration
	 *
	 * @since [version] Introduced
	 */
	private function set_config( $action = '' ) {

		$this->configure();

		// set default overlay config to edit.
		$this->current_overlay_config = $this->overlay_configs['edit'];

		// override for add new screen.
		if ( 'add' === $action ) {
			$this->current_overlay_config = $this->overlay_configs['add'];
		}

		$post_id = llms_filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		if ( empty( $post_id ) ) {
			$post_id = llms_filter_input( INPUT_POST, 'post', FILTER_VALIDATE_INT );
		}

		// override for current migration
		if ( true === llms_filter_input( INPUT_GET, 'llms-certificate-migrate', FILTER_VALIDATE_BOOLEAN ) ) {
			$this->current_overlay_config = $this->overlay_configs['migrated_to'];
		}

		// override for legacy redirection
		if ( true === llms_filter_input( INPUT_GET, 'llms-certificate-legacy-redirect', FILTER_VALIDATE_BOOLEAN ) ) {
			$this->current_overlay_config = $this->overlay_configs['legacy_redirect'];
		}

		// override for legacy cerificates
		if ( LLMS_Certificate_Migrator::is_legacy( $post_id ) ) {
			$this->current_overlay_config = $this->overlay_configs['legacy'];
		}

	}

	/**
	 * Generates a builder button.
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	public function builder_button() {
		?>
		<a class="button llms-certificate-builder-button" href="<?php echo llms_certificate_build_url(); ?>">
			<?php _e( 'Launch Builder', 'lifterlms' ); ?>
		</a>
		<?php
	}


	/**
	 * Generates editor overlay markup.
	 *
	 * @param WP_Post Post object
	 *
	 * @since    [version]
	 */
	public function editor_overlay( $post ) {

		/**
		 * Filters current overlay configuration
		 *
		 * @since    [version]
		 * @version  [version]
		 */
		$current_overlay_config = apply_filters( 'llms_certificate_editor_current_overlay_configuration', $this->current_overlay_config );

		/**
		 * Triggers just before editor overlay markup
		 *
		 * @param WP_Post $post Post object of the certificate
		 *
		 * @since    [version]
		 * @version  [version]
		 */
		do_action( 'llms_certificate_overlay_before', $post );
		?>
		<div class="llms-editor-overlay">

			<?php
				/**
				 * Triggers just after editor overlay wrapper and before overlay content
				 *
				 * @param WP_Post $post Post object of the certificate
				 *
				 * @since    [version]
				 * @version  [version]
				 */
				do_action( 'llms_certificate_overlay_content_before', $post );
			?>

			<div class="llms-editor-overlay-content">
				<p><?php echo $current_overlay_config['title']; ?></p>
				<?php
				foreach ( $current_overlay_config['buttons'] as $button ) {

					if ( ! is_array( $button['class'] ) ) {
						$button['class'] = array( $button['class'] );
					}

					$classes = array( 'button' ) + $button['class'];

					$url = isset( $button['url'] ) ? $button['url'] : '#';
					?>
					<a href="<?php echo $url; ?>" class="<?php echo implode( ' ', $button['class'] ); ?>">
						<?php echo $button['text']; ?>
					</a>
					<?php
				}
				?>
			</div>

			<?php
				/**
				 * Triggers just after overlay content and before editor overlay wrapper's closing div
				 *
				 * @param WP_Post $post Post object of the certificate
				 *
				 * @since    [version]
				 */
				do_action( 'llms_certificate_overlay_content_after', $post );
			?>

		</div>
		<?php
		/**
		 * Triggers just after editor overlay markup
		 *
		 * @param WP_Post $post Post object of the certificate
		 *
		 * @since    [version]
		 */
		do_action( 'llms_certificate_overlay_after', $post );
	}

	/**
	 * Enqueue editor assets
	 *
	 * @since    [version]
	 */
	public function enqueue_assets() {

		// enqueue editor js.
		wp_enqueue_script( 'llms-certificate-editor', LLMS_PLUGIN_URL . 'assets/js/llms-certificate-editor.js', array( 'jquery', 'wp-editor' ), '', true );

		// enqueue editor css.
		// wp_enqueue_style( 'llms-certificate-editor', LLMS_PLUGIN_URL . 'assets/css/certificate-editor' . LLMS_ASSETS_SUFFIX . '.css' );
		wp_enqueue_style( 'llms-certificate-editor', LLMS_PLUGIN_URL . 'assets/css/certificate-editor.css' );
	}

	/**
	 * Generates default content for new certificates
	 *
	 * @param string $content Default new post content
	 * @param WP_Post $post Post Object
	 *
	 * @return string
	 * @since  [version]
	 */
	public function default_content( $content, $post ) {

		if ( 'llms_certificate' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $content;
		}

		ob_start();
		?>
		<div class="llms-certificate-container">
			<img class="llms-certificate-background" src="<?php echo LLMS_PLUGIN_URL . 'assets/images/optional_certificate.png'; ?>"></p>
			<div class="llms-certificate-content-area">
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

}

return new LLMS_Certificate_Editor();
