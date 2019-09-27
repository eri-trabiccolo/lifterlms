<?php
/**
 * Contains helper functions for certificate builder
 *
 * @package LifterLMS/Modules/Certificate_Builder
 *
 * @version	[version]
 */
/**
 * Generates builder url.
 *
 * @param WP_Post|bool $post Post Object
 * @return   string
 *
 * @since    [version]
 */
function llms_certificate_build_url( $post_id = false ) {

	// make sure we have the current post.
	if ( empty( $post_id ) ) {
		global $post_id;
	}

	// add build mode parameter to post permalink.
	$build_url = add_query_arg(
		array(
			LLMS_CERTIFICATE_BUILD_MODE_PARAMETER => true,
		),
		get_permalink( $post_id )
	);

	return $build_url;
}

