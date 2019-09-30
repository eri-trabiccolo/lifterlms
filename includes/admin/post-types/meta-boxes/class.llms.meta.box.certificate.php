<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Certificates metabox
 *
 * @since    1.0.0
 * @version  3.17.4
 */
class LLMS_Meta_Box_Certificate extends LLMS_Admin_Metabox {

	/**
	 * Configure the metabox settings
	 *
	 * @return void
	 * @since  3.0.0
	 */
	public function configure() {

		$this->id       = 'lifterlms-certificate';
		$this->title    = __( 'Certificate Settings', 'lifterlms' );
		$this->screens  = array(
			'llms_certificate',
		);
		$this->priority = 'high';

	}

	/**
	 * Conditionally registers metabox on legacy certificates
	 *
	 * @return void
	 * @since [version] Introduced
	 */
	public function register() {

		// the migrator class will only exist when certificate builder module is available.
		if ( ! class_exists( 'LLMS_Certificate_Migrator' ) ) {
			parent::register();
			return;
		}

		global $post;

		// if this certificate has a legacy certificate; don't register the metabox.
		$has_legacy = ! empty( LLMS_Certificate_Migrator::has_legacy( $post->ID ) );

		if ( $has_legacy ) {
			return;
		}

		// only if this certificate is a legacy one, register metabox.
		$is_legacy = LLMS_Certificate_Migrator::is_legacy( $post->ID );
		if ( $is_legacy ) {
			parent::register();
			return;
		}

		// by default, don't register metabox.
		return;

	}

	/**
	 * Builds array of metabox options.
	 * Array is called in output method to display options.
	 * Appropriate fields are generated based on type.
	 *
	 * @return array [md array of metabox fields]
	 * @since   1.0.0
	 * @version 3.17.4
	 */
	public function get_fields() {

		 return array(
			 array(
				 'title'  => 'General',
				 'fields' => array(
					 array(
						 'label'      => __( 'Certificate Title', 'lifterlms' ),
						 'desc'       => __( 'Enter a title for your certificate. EG: Certificate of Completion', 'lifterlms' ),
						 'id'         => $this->prefix . 'certificate_title',
						 'type'       => 'text',
						 'section'    => 'certificate_meta_box',
						 'class'      => 'code input-full',
						 'desc_class' => 'd-all',
						 'group'      => '',
						 'value'      => '',
					 ),
					 array(
						 'label'      => __( 'Background Image', 'lifterlms' ),
						 'desc'       => __( 'Select an Image to use for the certificate.', 'lifterlms' ),
						 'id'         => $this->prefix . 'certificate_image',
						 'type'       => 'image',
						 'section'    => 'certificate_meta_box',
						 'class'      => 'certificate',
						 'desc_class' => 'd-all',
						 'group'      => '',
						 'value'      => '',
					 ),
				 ),
			 ),
		 );
	}

}
