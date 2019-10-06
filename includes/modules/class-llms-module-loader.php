<?php
/**
 * Contains Module loader class.
 *
 * @package LifterLMS/Modules
 * @since [version] Introduced
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads all modules
 *
 * Each module is an array of the following information:
 *
 *    $module = array(
 *        'name' => 'module-name',
 *        'file_path' => 'lifterlms/includes/modules/module-name/class-llms-module-name.php',
 *        'constant_name' => 'LLMS_MODULE_NAME',
 *    );
 *
 * Like this dummy model, core modules also follow this naming convention.
 *
 * The boolean value of the LLMS_MODULE_NAME constant acts like a switch
 * to turn a module on or off. By default, if the value of this constant isn't explicitly set
 * (in wp.config.php or elsewhere), it is assumed to be true.
 * So, to turn a module off, you add the following line to wp-config.php:
 *
 *    define( 'LLMS_MODULE_NAME', false );
 *
 * For core modules, this information is extracted from the directory structure inside
 * lifterlms/includes/modules/. Custom modules can obviously be added or used to replace existing modules
 * using lifterlms_modules_to_load filter which provides an array of all the modules about to be loaded.
 *
 * @since [version]
 */
class LLMS_Module_Loader {

	/**
	 * Singleton instance of LLMS_Module_Loader.
	 *
	 * @var    LLMS_Module_Loader
	 * @since [version] Introduced
	 */
	protected static $_instance = null;

	/**
	 * List loaded modules.
	 *
	 * @var   array
	 * @since [version] Introduced
	 */
	private $loaded = array();

	/**
	 * List of module information
	 *
	 * @var array
	 */
	private $info = array();

	/**
	 * Main Instance of LifterLMS Module Loader
	 *
	 * @since  [version] Introduced
	 * @return LLMS_Module_Loader
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self(); }
		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @since [version] Introduced
	 */
	private function __construct() {
		$this->info = $this->load_info();
		$this->loaded = $this->load();
	}

	/**
	 * Loads Modules.
	 *
	 * @since [version] Introduced
	 */
	private function load() {

		/**
		 * Filters list of LifterLMS modules just before load.
		 *
		 * The modules are listed as indexed elements in an array:
		 *
		 *    $modules = array(
		 *        'module_name' => array(
		 *            'name'          => 'module-name',
		 *            'file_path'     => 'lifterlms/includes/modules/module-name/class-llms-module-name.php',
		 *            'constant_name' => 'LLMS_MODULE_NAME',
		 *        ),
		 *        'module2_name' => array(
		 *            ...
		 *        ),
		 *        ...
		 *    )
		 *
		 * @since [version] Introduced.
		 */
		$to_load = apply_filters( 'lifterlms_modules_to_load', $this->info );

		// initialise after-load information.
		$loaded = array();

		foreach ( $to_load as $module ) {

			// define the constant as true if it hasn't been defined explicitly.
			if ( ! defined( $module['constant_name'] ) ) {
				define( $module['constant_name'], true );
			}

			// bail, if the constant's value is explcitly defined to false.
			if ( constant( $module['constant_name'] ) === false ) {
				continue;
			}

			// bail, if the main file doesn't exist.
			if ( ! file_exists( $module['file_path'] ) ) {
				continue;
			}

			// all fine, include the file.
			include_once $module['file_path'];

			// add module's info to loaded information.
			$loaded[ $module['name'] ] = $module;

			/**
			 * Fires after a particular module is loaded.
			 *
			 * This only contains basic information about what module was loaded.
			 * If you want specific information related to the modules' functionality,
			 * look for hooks within the module itself.
			 *
			 * @since [version] Introduced.
			 */
			do_action( "lifterlms_module_{$module['name']}_loaded", $module );

		}

		/**
		 * Fires after all the modules are loaded.
		 *
		 * @param $loaded array Information about all loaded modules
		 *
		 * @since [version] Introduced
		 */
		do_action( 'lifterlms_modules_loaded', $loaded );

		return $loaded;

	}

	/**
	 * Loads Module Information.
	 *
	 * @since [version] Introduced
	 */
	private function load_info() {

		// get a list of directories inside the modules directory.
		$directories = glob( LLMS_PLUGIN_DIR . 'includes/modules/*', GLOB_ONLYDIR );

		$modules = array();

		// loop through every directory
		foreach ( $directories as $module ) {

			// the name of the module is the same as the name of the directory. eg "certificate-builder"
			$module_name = basename( $module );

			$modules[ $module_name ] = array(
				'name' => $module_name,
			);

			// the name of the class file is similar. eg "class-llms-certificate-builder.php"
			$modules[ $module_name ]['file_path'] = "{$module}/class-llms-{$module_name}.php";

			// the constant name also uses similar conventions. eg "LLMS_CERTIFICATE_BUILDER"
			$modules[ $module_name ]['constant_name'] = 'LLMS_' . strtoupper( str_replace( '-', '_', $module_name ) );

			unset( $module_name );

		}

		return $modules;

	}

}

/*
 * Of every measure,
 * Sliced neatly, tied together,
 * Some features clever.
 */
