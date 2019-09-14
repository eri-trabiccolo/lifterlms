<?php
/**
 * Contains Module loader class.
 *
 * @package LifterLMS/Modules
 *
 * @since [version] Introduced
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

if( ! class_exists( 'LLMS_Module_Loader' ) ) {

	/**
	 * Loads all modules
	 *
	 * @since [version]
	 */
	class LLMS_Module_Loader {

		/**
		 * Loads Modules.
		 *
		 * @since    [version] Introduced
		 */
		public static function load() {

			/**
			 * Filters list of LifterLMS modules just before load.
			 *
			 * @since	[version] Introduced.
			 */
			$to_load = apply_filters( 'lifterlms/modules/list', self::load_info() );

			$loaded = array();

			foreach ( $to_load as $module ) {

				// define the constant as true if it hasn't been defined by the user in wp-config.php or similar.
				if ( ! defined( $module['constant'] ) ) {
					define( $module['constant'] , true );
				}

				// if the constant's value is true and the class file exists, include the module class
				if ( constant( $module['constant'] ) === true && file_exists( $module['file_path'] ) ) {
					include_once $module['file_path'];
				}

				$loaded[ $module['name'] ] = $module;

			}

			/**
			 * Fires after all the modules are loaded.
			 *
			 * @since	[version] Introduced.
			 */
			do_action( 'lifterlms/modules/loaded', $loaded );

			return $loaded;

		}

		/**
		 * Loads Module Information.
		 *
		 * @since    [version]
		 */
		private static function load_info() {

			// get a list of directories inside the modules directory.
			$directories = glob( LLMS_PLUGIN_DIR . 'includes/modules/*' , GLOB_ONLYDIR );

			$modules = array();

			// loop through every directory
			foreach ( $directories as $module ) {

				// the name of the module is the same as the name of the directory. eg "certificate-builder"
				$module_name = basename( $module );

				$modules[ $module_name ] = array(
					'name' => $module_name,
				);

				// the name of the class file is similar. eg "class-llms-certificate-builder.php"
				$modules[ $module_name ]['file_path'] = "$module/class-llms-$module_name.php";

				// the constant name also uses similar conventions. eg "LLMS_CERTIFICATE_BUILDER"
				$modules[ $module_name ]['constant_name'] = 'LLMS_' . strtoupper( str_replace( '-', '_', $module_name ) );

				unset( $module_name );

			}

			return $modules;

		}

	}

}

/*
 * Of every measure,
 * Sliced neatly, tied together,
 * Some features clever.
 */
