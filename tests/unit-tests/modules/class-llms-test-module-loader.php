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
	 * Test the modules loading.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_load() {

		// get modules to be loades.
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
}
