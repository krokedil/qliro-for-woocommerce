const path = require('path');
module.exports = {
	mode: 'production', // production
	entry: {
		'qliro-for-woocommerce': './assets/js/qliro-one-for-woocommerce.js',
	},

	output: {
		filename: '[name].min.js',
		path: path.resolve(__dirname, './assets/js'),
	},
	devtool: 'source-map',
	module: {
		rules: [
			{
				test: /\.m?js$/,
				exclude: /(node_modules|bower_components)/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@babel/preset-env'],
						plugins: ['@babel/plugin-transform-object-rest-spread'],
					}
				}
			}
		],
	},
};
