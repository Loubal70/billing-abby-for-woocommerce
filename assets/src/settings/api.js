/**
 * REST helpers for the settings panel.
 */
import apiFetch from '@wordpress/api-fetch';

const SETTINGS_ROUTE = '/bafw/v1/settings';
const TEST_ROUTE = '/bafw/v1/test-connection';
const LOGS_ROUTE = '/bafw/v1/logs';

export const getSettings = () => apiFetch( { path: SETTINGS_ROUTE } );

export const saveApiKey = ( apiKey ) =>
	apiFetch( {
		path: SETTINGS_ROUTE,
		method: 'POST',
		data: { api_key: apiKey },
	} );

export const saveProductType = ( productType ) =>
	apiFetch( {
		path: SETTINGS_ROUTE,
		method: 'POST',
		data: { product_type: productType },
	} );

export const testConnection = () =>
	apiFetch( { path: TEST_ROUTE, method: 'POST' } );

export const getLogs = ( page ) =>
	apiFetch( { path: `${ LOGS_ROUTE }?page=${ page }` } );
