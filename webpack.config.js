const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// JS.
		'mai-locations': './src/mai-locations.js',
		'mai-locations-sortable': './src/mai-locations-sortable.js',
		'markerclusterer': './src/markerclusterer.js',
		// CSS.
		'mai-locations-styles': './src/css/mai-locations.css',
		'mai-locations-form-styles': './src/css/mai-locations-form.css',
		'mai-locations-sortable-styles': './src/css/mai-locations-sortable.css',
	},
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
