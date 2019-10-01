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
	 */
	public function __construct() {

		$this->hook();

	}

	/**
	 * Hook ui and processing
	 *
	 * @since [version]
	 */
	public function hook() {
		add_filter( 'llms_status_tools', array( $this, 'buttons' ) );

		add_action( 'llms_status_tool', array( $this, 'process' ) );
	}

	/**
	 * Render Migration Buttons
	 *
	 * @tools array Existing LifterLMS Tools
	 *
	 * @since [version] Introduced
	 *
	 * @return array
	 */
	public function buttons( $tools ) {

		$legacies = $this->get_legacies();

		// no legacy metadata found, not legacy
		if ( ! empty( $legacies ) ) {

			$count = count( $legacies );

			$tools['certificates-bulk-migrate']       = array(
				'description' => sprintf( _n( 'You have %d legacy certificate that can be migrated.', 'You have %d legacy certificates that can be migrated.', $count, 'lifterlms' ), $count ),
				'label'       => __( 'Certificate Bulk Migrate', 'lifterlms' ),
				'text'        => __( 'Migrate all legacy certificates to new Builder', 'lifterlms' ),
			);
		}

		$migrated = $this->get_migrated();

		// no legacy metadata found, not legacy
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
	 * Get legacy certificates
	 *
	 * @since [version] Introduced
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
	 * Get migrated certificates
	 *
	 * @since [version] Introduced
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
	 */
	public function process( $tool ) {
		if ( ! in_array( $tool, array( 'certificates-bulk-migrate', 'certificates-bulk-rollback' ) ) ) {
			return;
		}

		$process = str_replace( 'certificates-bulk-', '', $tool );

		return $this->$process();
	}

	/**
	 * Migrate legacy certificates
	 *
	 * @since [version] Introduced
	 */
	private function migrate() {

		$migrated = $this->get_migrated();

		if ( empty( $migrated ) ) {
			return;
		}

		$migrated_ids = array();

		foreach ( $migrated as $migrated_id ) {
			$migrated_ids[] = LLMS_Certificate_Migrator::migrate( $migrated_id );
		}

		return $migrated_ids;

	}

	/**
	 * Rollback migrated certificates
	 *
	 * @since [version] Introduced
	 */
	private function rollback() {

		$legacies = $this->get_legacies();

		if ( empty( $legacies ) ) {
			return;
		}

		$legacy_ids = array();

		foreach ( $legacies as $legacy_id ) {
			$legacy_ids[] = LLMS_Certificate_Migrator::rollback( $legacy_id );
		}

		return $legacy_ids;

	}
}

return new LLMS_Certificate_Bulk_Migrator();
