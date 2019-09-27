<?php

class LLMS_Certificate_Bulk_Migrator {

	public function __construct() {

		add_filter( 'llms_status_tools', array( $this, 'buttons' ) );

		add_action( 'llms_status_tool', array( $this, 'process' ) );

	}

	public function buttons( $tools ){

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
				'description' =>  sprintf( _n( 'You have %d migrated certificate that can be rolled back.', 'You have %d migrated certificates that can be rolled back.', $count, 'lifterlms' ), $count ),
				'label'       => __( 'Certificates Bulk Rollback', 'lifterlms' ),
				'text'        => __( 'Rollback all migrated certificates to legacy system', 'lifterlms' ),
			);
		}

		return $tools;
	}

	public function get_legacies(){

		global $wpdb;

		$query_sql = "SELECT p.ID FROM $wpdb->posts as p LEFT JOIN $wpdb->postmeta as pm ON p.ID=pm.post_id WHERE p.post_type = 'llms_certificate' AND p.post_parent = 0 AND ( pm.meta_key = '_llms_certificate_title' OR pm.meta_key = '_llms_certificate_image' )";

		return $wpdb->get_results( $query_sql );

	}

	public function get_migrated(){

		global $wpdb;

		$query_sql = "SELECT post_parent FROM $wpdb->posts WHERE post_type = 'llms_certificate' AND post_parent > 0";

		return $wpdb->get_results( $query_sql );
	}

	public function process( $tool ) {
		if( ! in_array ( $tool, array( 'certificates-bulk-migrate', 'certificates-bulk-rollback' ) ) ){
			return;
		}

		$process = str_replace( 'certificates-bulk-', '', $tool );

		return $this->$process();
	}

	private function migrate() {

		$migrated = $this->get_migrated();

		if( empty( $migrated ) ){
			return;
		}

		$migrated_ids = array();


		foreach( $migrated as $migrated_id){
			$migrated_ids[]= LLMS_Certificate_Migrator::migrate( $migrated_id );
		}

		return $migrated_ids;

	}

	private function rollback() {

		$legacies = $this->get_legacies();

		if( empty( $legacies ) ){
			return;
		}

		$legacy_ids = array();


		foreach( $legacies as $legacy_id){
			$legacy_ids[] = LLMS_Certificate_Migrator::rollback( $legacy_id );
		}

		return $legacy_ids;


	}
}

return new LLMS_Certificate_Bulk_Migrator();
