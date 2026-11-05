import { defineConfig } from 'allure';

export default defineConfig({
	name: 'Qliro for WooCommerce tests',
	plugins: {
		awesome: {
			options: {
				reportName: 'Qliro for WooCommerce tests',
				singleFile: true,
			},
		},
	},
});
