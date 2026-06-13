<?php
/**
 * Main plugin class.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby;

use Rankea\BillingAbby\Admin\OrderInvoicePanel;
use Rankea\BillingAbby\Admin\ProductFields;
use Rankea\BillingAbby\Admin\SettingsPage;
use Rankea\BillingAbby\Admin\SettingsRestController;
use Rankea\BillingAbby\Admin\SetupWizard;
use Rankea\BillingAbby\Support\SyncLog;
use Rankea\BillingAbby\Sync\OrderSync;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton: wires up feature modules.
 */
final class Plugin {

	/** Plugin version, kept in sync with the main file header and readme.txt. */
	public const VERSION = '0.1.0';

	private static ?Plugin $instance = null;

	public static function instance( string $plugin_file ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
			self::$instance->init();
		}

		return self::$instance;
	}

	private function __construct( private readonly string $plugin_file ) {}

	public function dir(): string {
		return plugin_dir_path( $this->plugin_file );
	}

	public function url(): string {
		return plugin_dir_url( $this->plugin_file );
	}

	private function init(): void {
		SyncLog::register();

		$orders = new OrderSync();
		$orders->register();

		( new SettingsPage( $this ) )->register();
		( new SetupWizard( $this ) )->register();
		( new SettingsRestController() )->register();
		( new ProductFields() )->register();
		( new OrderInvoicePanel( $orders ) )->register();
	}
}
