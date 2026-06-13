/**
 * Custom entry points layered on top of the @wordpress/scripts defaults.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve(
			process.cwd(),
			'assets/src/settings',
			'index.js'
		),
		setup: path.resolve( process.cwd(), 'assets/src/setup', 'index.js' ),
	},
};
