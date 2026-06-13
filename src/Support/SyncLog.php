<?php
/**
 * Abby sync error log (custom table).
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Stores Abby sync *errors* in a dedicated, indexed table so the settings panel can show a
 * fast, paginated, Abby-only error log (successes go to the order notes instead). Pruned to
 * RETENTION_DAYS by a daily action.
 */
final class SyncLog {

	private const TABLE             = 'bafw_sync_log';
	private const DB_VERSION        = '1';
	private const DB_VERSION_OPTION = 'bafw_sync_log_db_version';
	private const RETENTION_DAYS    = 60;
	private const PRUNE_HOOK        = 'bafw_prune_sync_log';
	private const ACTION_GROUP      = 'billing-abby';

	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_install' ) );
		add_action( 'init', array( self::class, 'schedule_pruning' ) );
		add_action( self::PRUNE_HOOK, array( self::class, 'prune' ) );
	}

	/** Create or migrate the table when the stored schema version is out of date. */
	public static function maybe_install(): void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/** Create the table (also run on plugin activation). dbDelta is safe to re-run. */
	public static function install(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = self::table();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function error( int $order_id, string $message ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->insert(
			self::table(),
			array(
				'order_id'   => $order_id,
				'message'    => $message,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Read a page of error entries, newest first.
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Entries per page (clamped to 1-100).
	 * @return array{items: array<int, array<string, mixed>>, total: int, per_page: int}
	 */
	public static function get( int $page, int $per_page ): array {
		global $wpdb;

		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = max( 0, ( $page - 1 ) * $per_page );
		$table    = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; a live error log must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, order_id, message, created_at FROM %i ORDER BY id DESC LIMIT %d OFFSET %d',
				$table,
				$per_page,
				$offset
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table count for pagination.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		return array(
			'items'    => array_map( array( self::class, 'format_row' ), $rows ),
			'total'    => $total,
			'per_page' => $per_page,
		);
	}

	public static function last_for_order( int $order_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; a live status panel must not be cached.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, order_id, message, created_at FROM %i WHERE order_id = %d ORDER BY id DESC LIMIT 1',
				self::table(),
				$order_id
			)
		);

		return $row instanceof \stdClass ? self::format_row( $row ) : null;
	}

	public static function schedule_pruning(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		if ( false === as_next_scheduled_action( self::PRUNE_HOOK, array(), self::ACTION_GROUP ) ) {
			as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::PRUNE_HOOK, array(), self::ACTION_GROUP );
		}
	}

	public static function prune(): void {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table maintenance.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table(), $cutoff ) );
	}

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Shape a raw table row for the REST response.
	 *
	 * @param object $row Raw row from the table.
	 * @return array<string, mixed>
	 */
	private static function format_row( object $row ): array {
		return array(
			'id'       => (int) $row->id,
			'order_id' => (int) $row->order_id,
			'message'  => (string) $row->message,
			'date'     => get_date_from_gmt( (string) $row->created_at, 'Y-m-d H:i' ),
		);
	}
}
