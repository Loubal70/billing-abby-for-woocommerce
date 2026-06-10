/**
 * Billing Abby for WooCommerce — admin settings panel entry point.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import SettingsApp from './SettingsApp';

domReady( () => {
	const root = document.getElementById( 'bafw-settings-root' );

	if ( root ) {
		createRoot( root ).render( <SettingsApp /> );
	}
} );
