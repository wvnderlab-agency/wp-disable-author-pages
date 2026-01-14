<?php

/*
 * Plugin Name:     Disable Author Pages
 * Plugin URI:      https://github.com/wvnderlab-agency/wp-disable-author-pages/
 * Author:          Wvnderlab Agency
 * Author URI:      https://wvnderlab.com
 * Text Domain:     wvnderlab-disable-author-pages
 * Version:         0.1.1
 */

/*
 *  ################
 *  ##            ##    Copyright (c) 2025 Wvnderlab Agency
 *  ##
 *  ##   ##  ###  ##    ✉️ moin@wvnderlab.com
 *  ##    #### ####     🔗 https://wvnderlab.com
 *  #####  ##  ###
 */

declare(strict_types=1);

namespace WvnderlabAgency\DisableAuthorPage;

defined( 'ABSPATH' ) || die;

// Return early if running in WP-CLI context.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	return;
}

/**
 * Filter: Disable Author Pages Enabled
 *
 * @param bool $enabled Whether to enable the disable author pages functionality. Default true.
 * @return bool
 */
if ( ! apply_filters( 'wvnderlab/disable-author-pages/enabled', true ) ) {
	return;
}

/**
 * Clear Author Canonical URL
 *
 * Removes the canonical URL for author archive pages to prevent SEO issues.
 *
 * @link   https://developer.wordpress.org/reference/hooks/get_canonical_url/
 * @hooked filter get_canonical_url
 *
 * @param string|null $canonical_url The existing canonical URL.
 * @return string|null
 */
function clear_author_canonical_url( ?string $canonical_url ): ?string {

	return ! is_author()
		? $canonical_url
		: null;
}

add_filter( 'get_canonical_url', __NAMESPACE__ . '\\clear_author_canonical_url', PHP_INT_MAX );

/**
 * Disable or redirects any author archive page or author feed.
 *
 * @link   https://developer.wordpress.org/reference/hooks/template_redirect/
 * @hooked action template_redirect
 *
 * @return void
 */
function disable_or_redirect_author_page(): void {
	// return early if not an author archive page.
	if ( ! is_author() ) {
		return;
	}

	// return early if in admin, ajax, cron, rest api or wp-cli context.
	if (
		is_admin()
		|| wp_doing_ajax()
		|| wp_doing_cron()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
	) {
		return;
	}

	/**
	 * Filter: Disable Author Page Status Code
	 *
	 * Supported:
	 * - 301 / 302 / 307 / 308  → redirect
	 * - 404 / 410              → no redirect, proper error response
	 *
	 * @param int $status_code The HTTP status code for the redirect. Default is 404 (Not Found).
	 * @return int
	 */
	$status_code = (int) apply_filters(
		'wvnderlab/disable-author-pages/status-code',
		404
	);

	// Handle 404 and 410 status codes separately.
	if ( in_array( $status_code, array( 404, 410 ), true ) ) {
		global $wp_query;

		$wp_query->set_404();
		status_header( $status_code );
		nocache_headers();

		$template = get_query_template( '404' );

		if ( $template ) {
			include $template;
		} else {
			wp_die(
				esc_html__( '404 Not Found', 'wvnderlab-disable-author-pages' ),
				esc_html__( 'Not Found', 'wvnderlab-disable-author-pages' ),
				array( 'response' => esc_html( $status_code ) )
			);
		}

		exit;
	}

	// Ensure the status code is a valid redirect code.
	if ( $status_code < 300 || $status_code > 399 ) {
		$status_code = 301;
	}

	/**
	 * Filter: Disable Author Page Redirect URL
	 *
	 * Allows modification of the redirect URL for disabled author pages.
	 *
	 * @param string $redirect_url The URL to redirect to. Default is the homepage.
	 * @return string
	 */
	$redirect_url = (string) apply_filters(
		'wvnderlab/disable-author-pages/redirect-url',
		home_url()
	);

	// Ensure the redirect URL is not empty.
	if ( empty( $redirect_url ) ) {
		$redirect_url = home_url();
	}

	wp_safe_redirect( $redirect_url, $status_code );

	exit;
}

add_action( 'template_redirect', __NAMESPACE__ . '\\disable_or_redirect_author_page', PHP_INT_MIN );

/**
 * Unregister Author Blocks
 *
 * @link   https://developer.wordpress.org/reference/hooks/admin_print_scripts/
 * @hooked action admin_print_scripts
 *
 * @return void
 */
function unregister_author_blocks(): void {
	$blocks = array(
		'core/post-author',
		'core/post-author-biography',
		'core/post-author-name',
	);

	echo '<script type="text/javascript">';
	echo "addEventListener('DOMContentLoaded', function() {";
	echo 'window.wp.domReady( function() {';
	foreach ( $blocks as $block ) {
		echo "window.wp.blocks.unregisterBlockType( '" . esc_js( $block ) . "' );";
	}
	echo '} );';
	echo '} );';
	echo '</script>';
}

add_action( 'admin_print_scripts', __NAMESPACE__ . '\\unregister_author_blocks', PHP_INT_MAX );
