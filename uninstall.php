<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin's stored options when it is deleted from WordPress.
 *
 * @package Rankea\BillingAbby
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bafw_abby_api_key' );
