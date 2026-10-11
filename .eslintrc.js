'use strict';

module.exports = {
	root: true,
	extends: 'plugin:@wordpress/eslint-plugin/esnext',
	parser: 'babel-eslint',
	parserOptions: {
		ecmaVersion: 2020,
	},
	env: {
		node: true,
		amd: true,
		browser: true,
		es6: true,
	},
	globals: {
		wp: 'readonly',
		onyxpoll: 'readonly',
		onyxGetCookie: 'readonly',
	},
	rules: {
		quotes: [
			'error',
			'single',
			{ allowTemplateLiterals: true },
		],
		'no-var': 0,
		'no-console': 0,
		eqeqeq: 0,
		'space-in-parens': [ 'warn', 'never' ],
		'template-curly-spacing': [ 'warn', 'never' ],
		'computed-property-spacing': [ 'warn', 'never' ],
	},
	overrides: [
		{
			files: [ 'assets/js/admin.js' ],
			env: { jquery: true },
			globals: { onyxPollAdmin: 'readonly' },
		},
		{
			files: [ 'scripts/*.cjs', 'tests/*.cjs', '.eslintrc.js' ],
			env: { browser: false, amd: false },
		},
	],
};
