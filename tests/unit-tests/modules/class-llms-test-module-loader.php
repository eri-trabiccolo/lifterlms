<?php
/**
 * Tests for LifterLMS Module loader
 *
 * @group modules
 *
 * @since [version]
 * @version [version]
 */
class LLMS_Test_Module_Loader extends LLMS_UnitTestCase {

	/**
	 * Dummy modules.
	 * name => filename
	 *
	 * @var array
	 */
	private $dummy_modules = array(
		'dummy-right'   => 'class-llms-dummy-right.php',
		'dummy_wrong'   => 'class-llms_dummy_wrong.php', // doesn't follow the naming convention.
		'dummy_wrong_2' => 'class-llms_dummy_wrong-2.php', // doesn't follow the naming convention.
	);

	/**
	 * Test the modules loading.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_load() {

		// get modules to be loaded.
		$modules = $this->_get_load_info();

		// check they've been loaded.
		foreach ( $modules as &$module ) {
			$module['actions'] = did_action( "lifterlms_module_{$module['name']}_loaded" );
			$this->assertEquals( 1, $module['actions'] );
			LLMS_Module_Loader::instance()->is_module_loaded( $module['name'] );
		}

		// define module constants to false so to skip the loading.
		foreach ( $modules as $module ) {
			! defined( $module['constant_name'] ) && define( $module['constant_name'], FALSE );
		}

		// Fire the loading once again.
		$loaded = LLMS_Unit_Test_Util::call_method( LLMS_Module_Loader::instance(), 'load' );

		// check they've not been loaded this time.
		$this->assertEquals( array(), $loaded );

		foreach ( $modules as &$module ) {
			$actions_counter   = $module['actions'];
			$module['actions'] = did_action( "lifterlms_module_{$module['name']}_loaded" );
			$this->assertEquals( $actions_counter, $module['actions'] );
		}

	}

	/**
	 * Test the modules loading dummy modules.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_load_dummy_modules() {

		// filter the modules to load with our list of dummy modules.
		add_filter( 'lifterlms_modules_to_load', array( $this, 'get_dummy_modules_info' ) );

		// Fire the loading once again.
		$loaded = LLMS_Unit_Test_Util::call_method( LLMS_Module_Loader::instance(), 'load' );

		// check only the dummy-right module has been loaded.
		$this->assertEquals( array( 'dummy-right' ), array_keys( $loaded ) );
		$this->assertEquals( 1, did_action( 'lifterlms_module_dummy-right_loaded' ) );
		$this->assertEquals( 0, did_action( 'lifterlms_module_dummy_wrong_loaded' ) );
		$this->assertEquals( 0, did_action( 'lifterlms_module_dummy_wrong-2_loaded' ) );

		// remove created files and remove filter for next tests.
		// consider to move this into the `tearDown()` method if these actions
		// become frequent.
		$this->remove_dummy_files();
		remove_filter( 'lifterlms_modules_to_load', array( $this, 'get_dummy_modules_info' ) );

	}

	/**
	 * Get the array of modules to load.
	 * Of the type:
	 *    $module = array(
	 *        'name' => 'module-name',
	 *        'file_path' => 'lifterlms/includes/modules/module-name/class-llms-module-name.php',
	 *        'constant_name' => 'LLMS_MODULE_NAME',
	 *    );
	 *
	 * @since [version]
	 *
	 * @return array
	 */
	private function _get_load_info() {
		return LLMS_Unit_Test_Util::call_method( LLMS_Module_Loader::instance(), 'load_info' );
	}

	/**
	 * Creates and return a list of dummy modules info.
	 *
	 * @return array
	 */
	public function get_dummy_modules_info() {


		$this->create_dummy_files();

		$basedir = LLMS_PLUGIN_DIR . 'includes/modules';
		$modules = array();

		foreach ( $this->dummy_modules as $module => $filename ) {

			// the name of the module is the same as the name of the directory. eg "certificate-builder".
			$module_name = basename( $module );
			$modules[ $module_name ] = array(
				'name' => $module_name,
			);

			// the name of the class file is similar. eg "class-llms-certificate-builder.php".
			$modules[ $module_name ]['file_path'] = "{$basedir}/{$module}/class-llms-{$module_name}.php";

			// the constant name also uses similar conventions. eg "LLMS_CERTIFICATE_BUILDER".
			$modules[ $module_name ]['constant_name'] = 'LLMS_' . strtoupper( str_replace( '-', '_', $module_name ) );

		}

		return $modules;
	}

	/**
	 * Create dummy modules dir and file.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	private function create_dummy_files() {

		$basedir = LLMS_PLUGIN_DIR . 'includes/modules';

		foreach ( $this->dummy_modules as $module => $filename ) {

			if ( ! file_exists( "{$basedir}/{$module}/{$filename}" ) ) {
				mkdir( "{$basedir}/{$module}" );
				touch( "{$basedir}/{$module}/{$filename}" );
			}
		}

	}

	/**
	 * Remover dummy modules dir and file.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	private function remove_dummy_files() {

		$basedir = LLMS_PLUGIN_DIR . 'includes/modules';

		foreach ( $this->dummy_modules as $module => $filename ) {
			if ( file_exists( "{$basedir}/{$module}/{$filename}" ) ) {
				unlink( "{$basedir}/{$module}/{$filename}" );
				rmdir( "{$basedir}/{$module}" );
			}
		}

	}

}
