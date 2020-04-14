'use strict';

module.exports = {
	"extends": "plugin:@wordpress/eslint-plugin/esnext",
	"env": {
		"node": true,
		"amd": true,
		"browser": true,
		"es6": true
	},
	"globals": {
		wp: true,
		onyxpoll: true
	},
	"rules": {
		"quotes": [
			'error',
			'single',
			{ allowTemplateLiterals: true },
		],
		"no-var": 0,
		"no-console": 0,
		"eqeqeq": 0,
		"space-in-parens": ["warn", "never"],
		"template-curly-spacing": ["warn", "never"],
		"computed-property-spacing": ["warn", "never"]
	}
}
