<?php

class LLMS_Certificate_Builder_Screen {
	/**
	 *
	 */
	private $font_families;

	/**
	 * Constructs builder.
	 *
	 * Hooks loading of builder.
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'maybe_load' ) );
		add_action( 'wp_ajax_llms_certificate_builder_save' , array( $this, 'save' ) );
	}


	/**
	 * Conditionally loads certificate builder.
	 *
	 * Loads builder depending on the context (only on frontend, on certificates, for admins).
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	public function maybe_load() {

		$load_builder = true;

		/**
		 * Filters loading of certificate builder.
		 *
		 * @param     boolean  $load_builder If builder should be loaded.
		 * @since    [version]
 		 * @version  [version]
		 */
		if ( ! apply_filters( 'llms_load_certificate_builder', $load_builder ) ) {
			return;
		}

		// Don't load on admin screen.
		if ( is_admin() ) {
			return;
		}

		// Don't load for students, etc.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't load if not a certificate.
		if ( ! is_singular( 'llms_certificate' ) ) {
			return;
		}

		// Only load when the build mode parameter is set
		$build_mode_param = llms_filter_input( INPUT_GET, LLMS_CERTIFICATE_BUILD_MODE_PARAMETER );

		if ( empty( $build_mode_param ) ) {
			return;
		}

		// @todo add a way to check disabling via settings.

		// load builder.
		$this->load();

	}

	/**
	 * Loads certificate builder.
	 *
	 * Loads assets and markup for certificate builder.
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	public function load() {

		// load assets.
		$this->load_assets();

		// hook toolbox markup into content.
		add_filter( 'lifterlms_certificate_content', array( $this, 'load_builder' ) );

	}

	/**
	 * Loads assets for certificate builder.
	 *
	 * Loads scripts and styles, and localizes strings.
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	private function load_assets() {

		wp_register_script( 'llms-tinymce', 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.0.1/tinymce.min.js' );

		wp_register_script( 'llms-tinymce-jquery', 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.0.1/jquery.tinymce.min.js' );

		// enqueue builder js.
		wp_enqueue_script( 'llms-certificate-builder', LLMS_PLUGIN_URL . 'assets/js/llms-certificate-builder.js', array( 'jquery', 'jquery-ui-resizable', 'jquery-ui-draggable', 'llms-iziModal', 'llms-tinymce', 'llms-tinymce-jquery', 'jquery-touch-punch' ), '', true );

		// enqueue builder css.
		wp_enqueue_style( 'llms-certificate-builder', LLMS_PLUGIN_URL . 'assets/css/certificate-builder.css' );


		wp_enqueue_media();

		$this->enqueue_color_picker();
		$this->localize_data();

	}

	private function enqueue_color_picker() {
		wp_enqueue_style( 'wp-color-picker' );

		// Manually enqueing Iris itself by linking directly
		//    to it and naming its dependencies
		wp_enqueue_script(
			'iris',
			admin_url( 'js/iris.min.js' ),
			array(
				'jquery-ui-draggable',
				'jquery-ui-slider',
				'jquery-touch-punch'
			),
			false,
			1
		);

		// Now we can enqueue the color-picker script itself,
		//    naming iris.js as its dependency
		wp_enqueue_script(
			'wp-color-picker',
			admin_url( 'js/color-picker.min.js' ),
			array( 'iris' ),
			false,
			1
		);

		// Manually passing text strings to the JavaScript
		$colorpicker_l10n = array(
			'clear' => __( 'Clear' ),
			'defaultString' => __( 'Default' ),
			'pick' => __( 'Select Color' ),
			'current' => __( 'Current Color' ),
		);
		wp_localize_script(
			'wp-color-picker',
			'wpColorPickerL10n',
			$colorpicker_l10n
		);
	}

	private function localize_data() {
		// get the current post object.
		global $post;

		// create localized values for js.
		$localize_array = array(
			'original_content' => $post->post_content,
			'certificate_id' => $post->ID,
			'edit_link' => get_edit_post_link( $post->ID ),
			'nonce' => wp_create_nonce( 'llms_certificate_builder_save' ),
			'merge_codes' => $this->merge_code_data(),
			'content' => $this->default_content(),
			'default_fonts' => $this->font_data(),
		);

		// @todo check if this is a new version where image is part of post content before calling the older image mechanism
		// get certificate image (falls back to default).
		$image = llms_get_certificate_image();

		if ( $image ) {
			$localize_array += array(
				'image_src' => $image['src'],
				'migrate' => true
			);
		}

		wp_localize_script( 'llms-certificate-builder', 'llms_certificate_builder', $localize_array );

	}

	private function font_data() {

		require_once( LLMS_PLUGIN_DIR . '/includes/modules/certificate-builder/class-llms-webpage-fonts.php' );

		$font_loader = new LLMS_Webpage_Fonts();

		$fonts = $font_loader->get_font_familes_for_tinymce();

		if ( is_wp_error( $fonts ) || ! is_array( $fonts ) ) {

			$fonts = array();
		}

		$fonts = array_merge( $this->get_default_fonts(), $fonts );

		/**
		 * @since    [version]
	 	 * @version  [version]
		 */
		$fonts = apply_filters( 'llms_certificate_builder_fonts', $fonts );


		return $fonts;
	}

	private function default_content() {

		$default_content = array(
			'image' => LLMS_PLUGIN_URL . '/assets/images/placeholder.png',
			'text' => 'Double Click to Edit',
			'autofittext' => 'Double Click to Edit',
		);

		/**
		 * @since    [version]
	 	 * @version  [version]
		 */
		$default_content = apply_filters( 'llms_certificate_builder_default_content', $default_content );

		return $default_content;
	}

	private function merge_code_data() {
		// get the current post object.
		global $post;

		$codes = array(
			'{site_title}' => __( 'Site Title', 'lifterlms' ),
			'{site_url}' => __( 'Site URL', 'lifterlms' ),
			'{current_date}' => __( 'Earned Date', 'lifterlms' ),
			'{first_name}' => __( 'Student First Name', 'lifterlms' ),
			'{last_name}' => __( 'Student Last Name', 'lifterlms' ),
			'{email_address}' => __( 'Student Email', 'lifterlms' ),
			'{student_id}' => __( 'Student User ID', 'lifterlms' ),
			'{user_login}' => __( 'Student Username', 'lifterlms' ),
		);

		/**
		 * @since    [version]
	 	 * @version  [version]
		 */
		$codes = apply_filters( 'llms_merge_codes_for_button', $codes, $post, 'certificate-builder' );

		return $codes;

	}

	/**
	 * Initializes default font families
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	private function get_default_fonts() {

		//
		$font_families = array(
			'Arial' => 'arial,helvetica,sans-serif',
			'Times New Roman' =>'times new roman,times',
			'Courier New' => 'courier new,courier',
		);

		/**
		 * @since    [version]
	 	 * @version  [version]
		 */
		return apply_filters( 'llms_get_default_font_families', $font_families );

	}

	/**
	 * Appends builder markup to certificate.
	 *
	 * @param $content string Certificate post content
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	public function load_builder( $content ) {

		// get element toolbox.
		$element_toolbox = $this->render_toolbox('element-controls',  $this->element_controls() );

		// get workflow tollbox.
		$workflow_toolbox = $this->render_toolbox('workflow-controls', $this->workflow_controls() );

		// render builder with toolboxes.
		ob_start();
		?>
		<div class="llms-certificate-builder">
			<?php echo $content; ?>
			<div class="llms-certificate-builder-toolbox">
				<h3 class="llms-certificate-builder-toolbox-handle" title="<?php _e( 'Toolbox', 'lifterlms' ); ?>">
					<i class="fa fa-pencil-square-o"></i>
					<span class="screen-reader-text"><?php _e( 'Toolbox', 'lifterlms' ); ?></span>
				</h3>
				<?php echo $element_toolbox; ?>
				<?php echo $workflow_toolbox; ?>
			</div>
			<div id="llms-certificate-builder-scratch-pad"></div>
		</div>
		<?php
		return ob_get_clean();

	}

	/**
	 * Renders toolbox markup.
	 *
	 * @param $id       string Toolbox ID
	 * @param $label    string Toolbox label
	 * @param $controls array  Toolbox controls
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	private function render_toolbox( $id, $controls ) {

		ob_start();
		?>
		<div class="llms-certificate-builder-<?php echo $id; ?>-toolbox">
			<?php
			foreach ( $controls as $control_id => $control ) {
				?>
				<div class="llms-certificate-builder-control" id="llms-certificate-builder-control-<?php echo $control_id; ?>">
				<button id="<?php echo $control_id; ?>" title="<?php echo $control['title']; ?>">
					<i class="<?php echo $control['icon-class']; ?>"></i>
					<span class="screen-reader-text"><?php echo $control['title']; ?></span>
				</button>
				</div>
				<?php
				if ( isset( $control['tool-input-callback'] ) ) {
					echo $this->render_tool_input( $control_id, $control['tool-input-callback'] );
				}
			}
			?>
		</div>
		<?php

		return ob_get_clean();

	}

	/**
	 * Renders control input callback.
	 *
	 * @param $control_id      string ID of control
	 * @param $tool_input_callback string Control callback
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	private function render_tool_input( $control_id, $tool_input_callback ) {

		// do nothing if there's no callback.
		if ( empty( $tool_input_callback ) ) {
			return '';
		}

		// do nothing if the callback is invalid.
		if ( ! is_callable( $tool_input_callback ) ) {
			return '';
		}

		// render the callback in a wrapper.
		ob_start();
		?>
		<div class="llms-certificate-builder-control-input" id="llms-certificate-builder-control-input-<?php echo $control_id; ?>">
			<?php call_user_func( $tool_input_callback ); ?>
		</div>

		<?php
		return ob_get_clean();

	}

	/**
	 * Generates certificate element controls.
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	private function element_controls() {

		/**
		 * Filters default certificate element controls.
		 *
		 * @param array Controls for certificate elements
		 */
		return apply_filters( 'llms_certificate_builder_element_controls', array(

			'add-text' => array(
				'title' => __('Add Text', 'lifterlms' ),
				'icon-class' => 'fa fa-font',
			),
			'add-autofit-text' => array(
				'title' => __('Add Autofit Text', 'lifterlms' ),
				'icon-class' => 'fa fa-text-width',
			),
			'add-image' => array(
				'title' => __('Add Image', 'lifterlms' ),
				'icon-class' => 'fa fa-picture-o',
			),
		) );

	}

	/**
	 * Generates certificate builder workflow controls.
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	private function workflow_controls() {

		/**
		 * Filters default certificate builder workflow controls.
		 *
		 * @param array Controls for certificate builder workflow
		 */
		return apply_filters( 'llms_certificate_builder_workflow_controls', array(

			'change-background' => array(
				'title' => __('Change Background', 'lifterlms' ),
				'icon-class' => 'fa fa-upload',
				'tool-input-callback' => array($this, 'change_background_control')
			),
			'toggle-grid' => array(
				'title' => __('Toggle Grid', 'lifterlms' ),
				'icon-class' => 'fa fa-th-large',
			),
			'resize-content-area' => array(
				'title' => __('Resize Content Area', 'lifterlms' ),
				'icon-class' => 'fa fa-expand',
				'tool-input-callback' => array( $this, 'resize_content_area_control' ),
			),
			'preview' => array(
				'title' => __('Preview', 'lifterlms' ),
				'icon-class' => 'fa fa-eye',
			),
			'save' => array(
				'title' => __('Save', 'lifterlms' ),
				'icon-class' => 'fa fa-floppy-o',
				'tool-input-callback' => array( $this, 'save_certificate_nonce' ),
			),
		) );

	}

	/**
	 * Saves certificate content
	 *
	 * @since    [version]
	 * @version  [version]
	 */
	public function save() {

		$nonce = llms_filter_input( INPUT_POST, '_ajax_nonce' );

		// no nonce, fail silently.
		if ( ! $nonce ) {
			return;
		}

		// insecure nonce, fail silently.
		if ( ! check_ajax_referer( 'llms_certificate_builder_save' ) ) {
			return;
		}

		// requested certificate's ID.
		$certificate_id = absint( llms_filter_input( INPUT_POST, 'certificate_id', FILTER_SANITIZE_NUMBER_INT ) );

		// can't save without a certificate ID.
		if ( ! $certificate_id ) {
			$this->send_response( __( 'Invalid certificate ID.', 'lifterlms' ), false );
		}

		// make sure the ID is for a certificate post type.
		$certificate    = get_post( $certificate_id );

		if ( ! $certificate ) {
			return $this->send_response( __( 'Invalid certificate ID.', 'lifterlms' ), false );
		}

		if ( ! $certificate->post_type === 'llms_certificate' ) {
			return $this->send_response( __( 'Invalid certificate.', 'lifterlms' ), false );
		}

		// retreive the content from request.
		$certificate_content = llms_filter_input( INPUT_POST, 'html' );

		// if no content was sent, there's nothing to do.
		if ( empty( $certificate_content ) ) {
			return $this->send_response( __( 'No content received.', 'lifterlms' ), false );
		}

		// set up update arguments.
		$certificate_update = array(
			'ID'	=> $certificate_id,
			'post_content'	=> $certificate_content,
		);

		// try saving the certificate content.
		$status = wp_update_post( $certificate_update, true );

		// saving failed.
		if ( is_wp_error( $status ) ) {
			return $this->send_response(  sprintf( __('Saving failed: %s','lifterlms' ), $status.get_error_message() ), false, $new_certificate );
		}

		// clear legacy metadata
		delete_post_meta( $certificate_id, '_llms_certificate_image' );
		delete_post_meta( $certificate_id, '_llms_certificate_title' );

		// send the updated post data.
		return $this->send_response( __( 'Success', 'lifterlms' ), true, $status );


	}

	/**
	 * Send a JSON response (&die)
	 *
	 * @param    string  $message  message.
	 * @param    boolean $success  success.
	 * @param    array   $data     data to send with the message.
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	private function send_response( $message, $success = true, $data = array() ) {
		wp_send_json(
			array(
				'data'    => $data,
				'message' => $message,
				'success' => $success,
			)
		);
	}
}
