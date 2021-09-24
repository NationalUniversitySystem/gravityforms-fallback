import TerserPlugin from 'terser-webpack-plugin';
import ESLintPlugin from 'eslint-webpack-plugin';

module.exports = {
	module: {
		rules: [
			{
				test: /\.js$/,
			},
		],
	},
	plugins: [ new ESLintPlugin() ],
	mode: 'production',
	devtool: 'nosources-source-map',
	output: {
		filename: '[name].min.js',
	},
	optimization: {
		minimize: true,
		minimizer: [ new TerserPlugin( {
			terserOptions: {
				output: {
					comments: false,
				},
			},
			extractComments: false,
		} ) ],
	},
	stats: {
		chunks: false,
		entrypoints: false,
	},
};
