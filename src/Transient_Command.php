<?php

use WP_CLI\Utils;

/**
 * Adds, gets, and deletes entries in the WordPress Transient Cache.
 *
 * By default, the transient cache uses the WordPress database to persist values
 * between requests. On a single site installation, values are stored in the
 * `wp_options` table. On a multisite installation, values are stored in the
 * `wp_options` or the `wp_sitemeta` table, depending on use of the `--network`
 * flag.
 *
 * When a persistent object cache drop-in is installed (e.g. Redis or Memcached),
 * the transient cache skips the database and simply wraps the WP Object Cache.
 *
 * ## EXAMPLES
 *
 *     # Set transient.
 *     $ wp transient set sample_key "test data" 3600
 *     Success: Transient added.
 *
 *     # Get transient.
 *     $ wp transient get sample_key
 *     test data
 *
 *     # Delete transient.
 *     $ wp transient delete sample_key
 *     Success: Transient deleted.
 *
 *     # Delete expired transients.
 *     $ wp transient delete --expired
 *     Success: 12 expired transients deleted from the database.
 *
 *     # Delete all transients.
 *     $ wp transient delete --all
 *     Success: 14 transients deleted from the database.
 */
class Transient_Command extends WP_CLI_Command {

	/**
	 * Gets a transient value.
	 *
	 * For a more complete explanation of the transient cache, including the
	 * network|site cache, please see docs for `wp transient`.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the transient.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--network]
	 * : Get the value of a network|site transient. On single site, this is
	 * is a specially-named cache key. On multisite, this is a global cache
	 * (instead of local to the site).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp transient get sample_key
	 *     test data
	 *
	 *     $ wp transient get random_key
	 *     Warning: Transient with key "random_key" is not set.
	 */
	public function get( $args, $assoc_args ) {
		list( $key ) = $args;

		$func  = Utils\get_flag_value( $assoc_args, 'network' ) ? 'get_site_transient' : 'get_transient';
		$value = $func( $key );

		if ( false === $value ) {
			WP_CLI::warning( 'Transient with key "' . $key . '" is not set.' );
			exit;
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Sets a transient value.
	 *
	 * `<expiration>` is the time until expiration, in seconds.
	 *
	 * For a more complete explanation of the transient cache, including the
	 * network|site cache, please see docs for `wp transient`.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the transient.
	 *
	 * <value>
	 * : Value to be set for the transient.
	 *
	 * [<expiration>]
	 * : Time until expiration, in seconds.
	 *
	 * [--network]
	 * : Set the value of a network|site transient. On single site, this is
	 * is a specially-named cache key. On multisite, this is a global cache
	 * (instead of local to the site).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp transient set sample_key "test data" 3600
	 *     Success: Transient added.
	 */
	public function set( $args, $assoc_args ) {
		list( $key, $value ) = $args;

		$expiration = Utils\get_flag_value( $args, 2, 0 );

		$func = Utils\get_flag_value( $assoc_args, 'network' ) ? 'set_site_transient' : 'set_transient';
		if ( $func( $key, $value, $expiration ) ) {
			WP_CLI::success( 'Transient added.' );
		} else {
			WP_CLI::error( 'Transient could not be set.' );
		}
	}

	/**
	 * Deletes a transient value.
	 *
	 * For a more complete explanation of the transient cache, including the
	 * network|site cache, please see docs for `wp transient`.
	 *
	 * ## OPTIONS
	 *
	 * [<key>]
	 * : Key for the transient.
	 *
	 * [--network]
	 * : Delete the value of a network|site transient. On single site, this is
	 * is a specially-named cache key. On multisite, this is a global cache
	 * (instead of local to the site).
	 *
	 * [--all]
	 * : Delete all transients.
	 *
	 * [--expired]
	 * : Delete all expired transients.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete transient.
	 *     $ wp transient delete sample_key
	 *     Success: Transient deleted.
	 *
	 *     # Delete expired transients.
	 *     $ wp transient delete --expired
	 *     Success: 12 expired transients deleted from the database.
	 *
	 *     # Delete expired site transients.
	 *     $ wp transient delete --expired --network
	 *     Success: 1 expired transient deleted from the database.
	 *
	 *     # Delete all transients.
	 *     $ wp transient delete --all
	 *     Success: 14 transients deleted from the database.
	 *
	 *     # Delete all site transients.
	 *     $ wp transient delete --all --network
	 *     Success: 2 transients deleted from the database.
	 *
	 *     # Delete all transients in a multsite.
	 *     $ wp transient delete --all --network && wp site list --field=url | xargs -n1 -I % wp --url=% transient delete --all
	 */
	public function delete( $args, $assoc_args ) {
		$key = ( ! empty( $args ) ) ? $args[0] : NULL;

		$all     = Utils\get_flag_value( $assoc_args, 'all' );
		$expired = Utils\get_flag_value( $assoc_args, 'expired' );
		$network = Utils\get_flag_value( $assoc_args, 'network' );

		if ( true === $all ) {
			$this->delete_all( $network );
			return;
		} elseif ( true === $expired ) {
			$this->delete_expired( $network );
			return;
		}

		if ( ! $key ) {
			WP_CLI::error( 'Please specify transient key, or use --all or --expired.' );
		}

		$func = $network ? 'delete_site_transient' : 'delete_transient';

		if ( $func( $key ) ) {
			WP_CLI::success( 'Transient deleted.' );
		} else {
			$func = Utils\get_flag_value( $assoc_args, 'network' ) ? 'get_site_transient' : 'get_transient';
			if ( $func( $key ) )
				WP_CLI::error( 'Transient was not deleted even though the transient appears to exist.' );
			else
				WP_CLI::warning( 'Transient was not deleted; however, the transient does not appear to exist.' );
		}
	}

	/**
	 * Determines the type of transients implementation.
	 *
	 * Indicates whether the transients API is using an object cache or the
	 * database.
	 *
	 * For a more complete explanation of the transient cache, including the
	 * network|site cache, please see docs for `wp transient`.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp transient type
	 *     Transients are saved to the database.
	 */
	public function type() {
		if ( wp_using_ext_object_cache() ) {
			$message = 'Transients are saved to the object cache.';
		} else {
			$message = 'Transients are saved to the database.';
		}

		WP_CLI::line( $message );
	}

	/**
	 * Deletes all expired transients.
	 *
	 * Only deletes the expired transients from the database.
	 *
	 * @param bool $network Whether to delete transients or network|site transients.
	 */
	private function delete_expired( $network ) {
		global $wpdb;

		$count = 0;

		if ( ! $network ) {
			$count += $wpdb->query(
				$wpdb->prepare(
					"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
						WHERE a.option_name LIKE %s
						AND a.option_name NOT LIKE %s
						AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
						AND b.option_value < %d",
					Utils\esc_like( '_transient_' ) . '%',
					Utils\esc_like( '_transient_timeout_' ) . '%',
					time()
				)
			);
		} else {
			if ( ! is_multisite() ) {
				// Non-Multisite stores site transients in the options table.
				$count += $wpdb->query(
					$wpdb->prepare(
						"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
							WHERE a.option_name LIKE %s
							AND a.option_name NOT LIKE %s
							AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )
							AND b.option_value < %d",
						Utils\esc_like( '_site_transient_' ) . '%',
						Utils\esc_like( '_site_transient_timeout_' ) . '%',
						time()
					)
				);
			} else {
				// Multisite stores site transients in the sitemeta table.
				$count += $wpdb->query(
					$wpdb->prepare(
						"DELETE a, b FROM {$wpdb->sitemeta} a, {$wpdb->sitemeta} b
							WHERE a.meta_key LIKE %s
							AND a.meta_key NOT LIKE %s
							AND b.meta_key = CONCAT( '_site_transient_timeout_', SUBSTRING( a.meta_key, 17 ) )
							AND b.meta_value < %d",
						Utils\esc_like( '_site_transient_' ) . '%',
						Utils\esc_like( '_site_transient_timeout_' ) . '%',
						time()
					)
				);
			}
		}

		// The above queries delete the transient and the transient timeout
		// thus each transient is counted twice.
		$count = $count / 2;

		if ( $count > 0 ) {
			WP_CLI::success(
				sprintf(
					'%d expired %s deleted from the database.',
					$count,
					Utils\pluralize( 'transient', $count )
				)
			);
		} else {
			WP_CLI::success( 'No expired transients found.' );
		}

		if ( wp_using_ext_object_cache() ) {
			WP_CLI::warning( 'Transients are stored in an external object cache, and this command only deletes those stored in the database. You must flush the cache to delete all transients.' );
		}
	}

	/**
	 * Deletes all transients.
	 *
	 * Only deletes the transients from the database.
	 *
	 * @param bool $network Whether to delete transients or network|site transients.
	 */
	private function delete_all( $network ) {
		global $wpdb;

		// To ensure proper count values we first delete all transients with a timeout
		// and then the remaining transients without a timeout.
		$count = 0;

		if ( ! $network ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
						WHERE a.option_name LIKE %s
						AND a.option_name NOT LIKE %s
						AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )",
					Utils\esc_like( '_transient_' ) . '%',
					Utils\esc_like( '_transient_timeout_' ) . '%'
				)
			);

			$count += $deleted / 2; // Ignore affected rows for timeouts.

			$count += $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
					Utils\esc_like( '_transient_' ) . '%'
				)
			);
		} else {
			if ( ! is_multisite() ) {
				// Non-Multisite stores site transients in the options table.
				$deleted = $wpdb->query(
					$wpdb->prepare(
						"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
							WHERE a.option_name LIKE %s
							AND a.option_name NOT LIKE %s
							AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )",
						Utils\esc_like( '_site_transient_' ) . '%',
						Utils\esc_like( '_site_transient_timeout_' ) . '%'
					)
				);

				$count += $deleted / 2; // Ignore affected rows for timeouts.

				$count += $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
						Utils\esc_like( '_site_transient_' ) . '%'
					)
				);
			} else {
				// Multisite stores site transients in the sitemeta table.
				$deleted = $wpdb->query(
					$wpdb->prepare(
						"DELETE a, b FROM {$wpdb->sitemeta} a, {$wpdb->sitemeta} b
							WHERE a.meta_key LIKE %s
							AND a.meta_key NOT LIKE %s
							AND b.meta_key = CONCAT( '_site_transient_timeout_', SUBSTRING( a.meta_key, 17 ) )",
						Utils\esc_like( '_site_transient_' ) . '%',
						Utils\esc_like( '_site_transient_timeout_' ) . '%'
					)
				);

				$count += $deleted / 2; // Ignore affected rows for timeouts.

				$count += $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE %s",
						Utils\esc_like( '_site_transient_' ) . '%'
					)
				);
			}
		}

		if ( $count > 0 ) {
			WP_CLI::success(
				sprintf(
					'%d %s deleted from the database.',
					$count,
					Utils\pluralize( 'transient', $count )
				)
			);
		} else {
			WP_CLI::success( 'No transients found.' );
		}

		if ( wp_using_ext_object_cache() ) {
			WP_CLI::warning( 'Transients are stored in an external object cache, and this command only deletes those stored in the database. You must flush the cache to delete all transients.' );
		}
	}
}
