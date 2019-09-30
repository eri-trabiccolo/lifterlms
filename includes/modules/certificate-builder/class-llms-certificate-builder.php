<?php
/**
 * Certificate Builder Module
 *
 * @package LifterLMS/Modules/Certificate_Builder
 *
 * @since [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Certificate Builder Class
 *
 * @since [version] Introduced
 */
class LLMS_Certificate_Builder {

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->init();

	}

	/**
	 * Initialise
	 */
	public function init() {
		// load all constants
		$this->constants();

		// load migrator and extend editor for admin
		if ( is_admin() ) {
			$this->load_migrator();
			$this->extend_editor();
		}

		// toolbar button
		$this->toolbar_button();

		// load builder
		$this->load_builder();

	}

	/**
	 * Define constants
	 */
	public function constants() {

		if ( ! defined( 'LLMS_CERTIFICATE_BUILD_MODE_PARAMETER' ) ) {
			define( 'LLMS_CERTIFICATE_BUILD_MODE_PARAMETER', 'llms-build-mode' );
		}
		if ( ! defined( 'LLMS_CERTIFICATE_ENABLE_MIGRATION' ) ) {
			define( 'LLMS_CERTIFICATE_ENABLE_MIGRATION', true );
		}

		if ( ! defined( 'LLMS_CERTIFICATE_ENABLE_TOOLBAR_BUTTON' ) ) {
			define( 'LLMS_CERTIFICATE_ENABLE_TOOLBAR_BUTTON', true );
		}
	}

	/**
	 * Add toolbar button
	 */
	private function toolbar_button(){
		if ( ! LLMS_CERTIFICATE_ENABLE_TOOLBAR_BUTTON ){
			return;
		}
		include_once 'includes/admin/class-llms-certificate-builder-toolbar.php';
	}

	/**
	 * Load migration functionality
	 */
	public function load_migrator() {
		if ( ! LLMS_CERTIFICATE_ENABLE_MIGRATION ){
			return;
		}
		include_once 'includes/migration/class-llms-certificate-migrator.php';
		include_once 'includes/migration/class-llms-certificate-bulk-migrator.php';

		include_once 'includes/admin/class-llms-certificate-migration-metabox.php';
		new LLMS_Certificate_Migration_Metabox();
	}

	/**
	 * Extend post editor.
	 */
	public function extend_editor() {
		include_once 'includes/admin/class-llms-certificate-post-table.php';
		include_once 'includes/admin/class-llms-certificate-post-editor.php';
	}

	/**
	 * Load Builder UI
	 */
	public function load_builder() {
		include_once 'includes/llms-certificate-builder-functions.php';
		if ( ! is_admin() ) {
			include_once 'includes/builder/class-llms-webpage-fonts.php';
			include_once 'includes/builder/class-llms-certificate-builder-screen.php';
		}
	}

}

new LLMS_Certificate_Builder();

/*
 * Hands of the master
 * Cast endorsements aplenty,
 * To discern faster.
 */
