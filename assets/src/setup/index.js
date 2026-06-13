/**
 * Billing Abby for WooCommerce — first-run setup wizard entry point.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import SetupWizard from './SetupWizard';

domReady( () => {
	const root = document.getElementById( 'bafw-setup-root' );

	if ( root ) {
		createRoot( root ).render( <SetupWizard /> );
	}
} );
