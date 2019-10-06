<?php
/**
 * Contains class for bulk migration tools.
 *
 * @package LifterLMS/Modules/Certificate_Builder
 *
 * @since [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implement Bulk Certificate Migration.
 *
 * @since [version] Introduced.
 */
class LLMS_Certificate_Bulk_Migrator {

	/**
	 * Constructor.
	 *
	 * @since [version] Introduced
	 */
	public function __construct() {
		$this->hook();
	}

	/**
	 * Hook ui and processing.
	 *
	 * @since [version]
	 */
	public function hook() {

		add_filter( 'llms_status_tools', array( $this, 'buttons' ) );

		add_action( 'llms_status_tool', array( $this, 'process' ) );
	}

	/**
	 * Render Migration Buttons.
	 *
	 * @param $tools    array Existing LifterLMS Tools
	 * @since [version] Introduced
	 *
	 * @return array
	 */
	public function buttons( $tools ) {

		// get all legacied versions.
		$legacies = $this->get_legacies();

		// add bulk migration only if legacies are found.
		if ( ! empty( $legacies ) ) {

			$count = count( $legacies );

			$tools['certificates-bulk-migrate']       = array(
				'description' => sprintf( _n( 'You have %d legacy certificate that can be migrated.', 'You have %d legacy certificates that can be migrated.', $count, 'lifterlms' ), $count ),
				'label'       => __( 'Certificate Bulk Migrate', 'lifterlms' ),
				'text'        => __( 'Migrate all legacy certificates to new Builder', 'lifterlms' ),
			);
		}

		// get all migrated versions.
		$migrated = $this->get_migrated();

		// add bulk rollback only if migrated versions are found.
		if ( ! empty( $migrated ) ) {

			$count = count( $migrated );

			$tools['certificates-bulk-rollback']       = array(
				'description' => sprintf( _n( 'You have %d migrated certificate that can be rolled back.', 'You have %d migrated certificates that can be rolled back.', $count, 'lifterlms' ), $count ),
				'label'       => __( 'Certificates Bulk Rollback', 'lifterlms' ),
				'text'        => __( 'Rollback all migrated certificates to legacy system', 'lifterlms' ),
			);
		}

		return $tools;
	}

	/**
	 * Get legacy certificates.
	 *
	 * @since  [version] Introduced
	 *
	 * @return object
	 */
	public function get_legacies() {

		global $wpdb;

		$query_sql = "SELECT p.ID FROM $wpdb->posts as p LEFT JOIN $wpdb->postmeta as pm ON p.ID=pm.post_id WHERE p.post_type = %1s AND p.post_parent = 0 AND ( pm.meta_key = %2s OR pm.meta_key = %3s )";

		$values = array(
			'llms_certificate',
			'_llms_certificate_title',
			'_llms_certificate_image',
		);

		return $wpdb->get_results( $wpdb->prepare( $query_sql, $values ) );

	}

	/**
	 * Get migrated certificates.
	 *
	 * @since  [version] Introduced
	 *
	 * @return object
	 */
	public function get_migrated() {

		global $wpdb;

		$query_sql = "SELECT post_parent FROM $wpdb->posts WHERE post_type = %s AND post_parent > %d";

		$values = array(
			'llms_certificate',
			0,
		);

		return $wpdb->get_results( $wpdb->prepare( $query_sql, $values ) );
	}

	/**
	 * Process migration/rollback commands
	 *
	 * @since [version] Introduced
	 *
	 * @return array    List of processed certificate post IDs
	 */
	public function process( $tool ) {

		// only process for builder related tools.
		if ( ! in_array( $tool, array( 'certificates-bulk-migrate', 'certificates-bulk-rollback' ) ) ) {
			return;
		}

		// get the method name from the tool action.
		$process = str_replace( 'certificates-bulk-', '', $tool );

		$msg_fragment = ( 'migrate' === $process ) ? __( 'Migrated', 'lifterlms' ) : __( 'Rolled back', 'lifterlms' );

		// call appropriate method (migrate/rollback).
		$results = $this->$process();

		// initialise message array.
		$messages = array();

		foreach ( $results as $result ) {

			// process errors differently
			if ( is_wp_error( $result ) ) {
				$messages[] = $result->get_error_message();
				continue;
			}

			if ( is_int( $result ) ) {
				$messages[] = sprintf( __( '%s certificate with ID %d', 'lifterlms' ), $msg_fragment, $result );
			}
		}

		$message = implode( '<br>', $messages );

		// display message
		llms_add_notice( $message );

	}

	/**
	 * Migrate legacy certificates.
	 *
	 * @since [version] Introduced
	 *
	 * @return array    List of migrated IDs
	 */
	private function migrate() {

		// get all legacy certificates.
		$legacies = $this->get_legacies();

		// bail if none found.
		if ( empty( $legacies ) ) {
			return;
		}

		// initialise array to store IDs.
		$migrated_ids = array();

		// loop through legacied certificates and migrate each of them.
		foreach ( $legacies as $legacy_id ) {

			// populate return array.
			$migrated_ids[] = LLMS_Certificate_Migrator::migrate( $legacy_id );
		}

		return $migrated_ids;


	}

	/**
	 * Rollback migrated certificates.
	 *
	 * @since [version] Introduced
	 *
	 * @return array List of rolled back certificates' post IDs
	 */
	private function rollback() {

		// get all migrated certificates.
		$migrated = $this->get_migrated();

		// bail if none found.
		if ( empty( $migrated ) ) {
			return;
		}

		// initialise array to store IDs.
		$legacy_ids = array();

		// loop through migrated certificates and roll each of them back.
		foreach ( $migrated as $migrated_id ) {

			// populate return array.
			$legacy_ids[] = LLMS_Certificate_Migrator::rollback( $migrated_id );
		}

		return $legacy_ids;

	}
}

return new LLMS_Certificate_Bulk_Migrator();
