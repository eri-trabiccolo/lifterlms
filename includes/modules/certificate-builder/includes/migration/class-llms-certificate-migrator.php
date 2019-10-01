<?php
/**
 * Certificate Migrator
 *
 * @package LifterLMS/Modules/Certificate_Builder
 *
 * @since [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper class that handles Certificate Migration and Rollbacks
 *
 * @since [version]
 * @todo Better error handling
 */
class LLMS_Certificate_Migrator {

	/**
	 * Legacies a certificate.
	 *
	 * @param int $certificate_id Certificate ID.
	 *
	 * @return WP_Error|int
	 *
	 * @since [version]
	 */
	public static function migrate( $certificate_id ) {

		$certificate = get_post( $certificate_id, ARRAY_A );

		// check if this is already a legacied certificate.
		if ( 0 !== $certificate['post_parent'] || 'llms-legacy' === $certificate['post_status'] ) {
			return new WP_Error( 'is-legacy', __( 'This is already a legacied version!', 'lifterlms' ) );
		}

		// check if this already has a legacy.
		if ( false !== self::has_legacy( $certificate_id ) ) {
			return new WP_Error( 'has-legacy', __( 'A legacied version already exists. Please delete it first to be able to create a new legacy for this certificate.', 'lifterlms' ) );
		}

		// unset ID so that a new post is created instead of simply updating the existing post.
		unset( $certificate['ID'] );

		unset( $certificate['post_name'] );

		unset( $certificate['guid'] );

		// insert new post with the same data as the current post.
		$new_certificate_id = wp_insert_post( $certificate );

		// change post status of current certificate ($certificate_id) to legacied and set new certificate as parent.
		$legacied_certificate_args = array(
			'post_id' => $certificate_id,
			'post_status' => 'llms-legacy',
			'post_parent' => $new_certificate_id,
		);

		wp_update_post( $legacied_certificate_args );

		// copy all metadata.
		self::duplicate_meta( $certificate_id, $new_certificate_id );

		// swap engagements.
		self::swap_engagements( $certificate_id, $new_certificate_id );

		// return new certificate ID.
		return $new_certificate_id;
	}


	/**
	 * Rolls back a migrated certificate to legacy
	 *
	 * @param int $certificate_id Certificate ID.
	 *
	 * @return WP_Error|int
	 *
	 * @since [version]
	 */
	public static function rollback( $certificate_id ) {

		// check if a legacied version exists.
		$legacy = self::has_legacy( $certificate_id );

		// throw error if no legacied version was found.
		if ( false === $legacy ) {
			return new WP_Error( 'missing-legacy', __( 'Sorry! No legacied certificate found to rollback to.', 'lifterlms' ) );
		}

		// swap back engagements.
		self::swap_engagements( $certificate_id, $legacy->ID );

		// get the current certificates status.
		$post_status = get_post_status( $certificate_id );

		// maintain the post status during rollback to legacy.
		$legacy_id = wp_update_post(
			array(
				'post_id' => $legacy,
				'post_status' => $post_status,
				'post_parent' => 0,
			)
		);

		// do nothing to the migrated version.

		return $legacy_id;
	}

	/**
	 * Determines if a certificate is legacy.
	 *
	 * One reliable difference between a legacy certificate and a builder built certificate
	 *  is the presence of meta keys '_llms_certificate_title' and '_llms_certificate_image' in the db
	 *  since these are deleted after migration.
	 * Since get_post_meta() cannot distinguish between a meta key that is present but empty
	 *  from one that is absent in the db, a direct db query is needed.
	 * (The other reliable difference is the markup of the content)
	 *
	 * @param  $certificate_id Post ID of certificate
	 * @return bool
	 * @since  [version]
	 */
	public static function is_legacy( $certificate_id ) {

		global $wpdb;

		$query_sql = "SELECT * FROM $wpdb->postmeta WHERE post_id=%d AND ( meta_key = %1s OR meta_key = %2s )";

		$values = array(
			$certificate_id,
			'_llms_certificate_title',
			'_llms_certificate_image',
		);

		$meta_info = $wpdb->get_results( $wpdb->prepare( $query_sql, $values ) );

		// no legacy metadata found, not legacy
		if ( empty( $meta_info ) ) {
			return false;
		}

		// metadata was found, is legacy
		return true;

	}

	public static function is_legacy_of_modern( $certificate_id ) {

		$certificate = get_post( $certificate_id );

		if ( empty( $certificate->post_parent ) ) {
			return false;
		}

		$modern = get_post( $certificate->post_parent );

		if ( 'llms_certificate' !== $modern->post_type ) {
			return false;
		}

		return $modern->ID;

	}

	/**
	 * Swaps the engagement's association with certificate.
	 *
	 * @param int $from_certificate_id Certificate ID to swap from
	 * @param int $to_certificate_id Certificate ID to swap to
	 *
	 * @return array|bool
	 *
	 * @since [version]
	 */
	private static function swap_engagements( $from_certificate_id, $to_certificate_id ) {

		// locate engagement using $old_certificate_id.
		$engagements = get_posts(
			array(
				'post_type' => 'llms_engagement',
				'post_status' => 'any',
				'meta_query' => array(
					array(
						'key'   => '_llms_engagement',
						'value' => $from_certificate_id,
						'type' => 'NUMERIC',
					),
				),
			)
		);

		// no engagements found, bail
		if ( empty( $engagements ) ) {
			return false;
		}

		// swap the $old_certificate_id with the $new_certificate_id.
		foreach ( $engagements as $engagement ) {
			update_post_meta( $engagement->ID, '_llms_engagement', $to_certificate_id );
		}

		// return engagement/ engagement_id.
		return $engagements;

	}

	/**
	 * Checks and returns legacy version of certificate.
	 *
	 * @param int $certificate_id Certificate ID.
	 *
	 * @return WP_Post|bool Legacy certificate's post data or 'false' if no legacy found.
	 *
	 * @since [version]
	 */
	public static function has_legacy( $certificate_id ) {

		// set up arguments for get_posts()
		$legacied_args = array(
			'post_type'   => 'llms_certificate',
			'post_status' => 'llms-legacy',
			'post_parent' => $certificate_id,
		);

		$found_legacies = get_posts( $legacied_args );

		return empty( $found_legacies ) ? false : $found_legacies[0];
	}

	/**
	 * Duplicates all metadata of a post to another
	 *
	 * @param int $from_certificate_id Certificate ID to copy meta from
	 * @param int $to_certificate_id Certificate ID to copy meta to
	 *
	 * @return int|bool
	 *
	 * @since [version]
	 */
	private static function duplicate_meta( $from_certificate_id, $to_certificate_id ) {
		global $wpdb;

		// get all the current metadata rows.
		$post_metas = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d',
				$from_certificate_id
			)
		);

		// if there's no metadata, return early.
		if ( 0 === count( $post_metas ) ) {
			return;
		}

		// delete all existing metadata added through wp_insert_post().
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM $wpdb->postmeta WHERE post_id = %d',
				$to_certificate_id
			)
		);

		// start constructing insert query statement.
		$sql_query = $wpdb->prepare( 'INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) ' );

		// construct statement fragments for duplicating and inserting each metadata.
		foreach ( $post_metas as $meta ) {

			// copy meta key
			$meta_key = $meta->meta_key;

			// skip copying old slugs and legacy certificate meta not needed by the builder
			if ( in_array( $meta_key, array( '_wp_old_slug', '_llms_certificate_title', '_llms_certificate_image' ) ) ) {
				continue;
			}

			// copy meta value
			$meta_value = addslashes( $meta->meta_value );

			// setup copying to new post's ID
			$sql_query_sel[] = $wpdb->prepare( 'SELECT %d, %1s, %2s;', $to_certificate_id, $meta_key, $meta_value );
		}

		// merge all metadata insertion fragments.
		$sql_query .= implode( ' UNION ALL ', $sql_query_sel );

		// run the bulk insertion of duplicated metadata.
		return $wpdb->query( $sql_query );
	}

	/**
	 * Deletes legacy version
	 *
	 * @param int $certificate_id Certificate ID.
	 *
	 * @return WP_Post|false|null
	 *
	 * @since [version]
	 */
	public static function delete_legacy( $certificate_id ) {
		$legacy = self::has_legacy( $certificate_id );

		if ( false === $legacy ) {
			return new WP_Error( 'missing-legacy', __( 'No legacy found for deletion.', 'lifterlms' ) );
		}

		return wp_delete_post( $legacy, true );
	}
}
