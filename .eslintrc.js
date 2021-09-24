module.exports = {
	"env": {
		"browser": true,
		"es6": true,
		"node": true,
		"jquery": true
	},
	"extends": [
		"eslint:recommended",
		"plugin:@wordpress/eslint-plugin/recommended-with-formatting"
	],
	'parser': "@babel/eslint-parser",
	"parserOptions": {
		"sourceType": "module"
	},
	"rules": {
		"arrow-parens": [
			"error",
			"as-needed"
		],
		"complexity": [
			"warn", {
				"max": 8
			}
		],
		"eqeqeq": [ "error", "smart"] ,
		"lines-around-comment": "off",
		"space-in-parens": [ "warn", "always" ],
		"no-empty-function": [
			"warn", {
				"allow": [
					"methods"
				]
			}
		],
		"no-multi-spaces": [
			"warn", {
				"exceptions": {
					"VariableDeclarator": true
				}
			}
		],
		"no-unused-vars": [
			"error", {
				"args": "after-used"
			}
		],
		"vars-on-top": "off",
		"wrap-iife": [
			"error",
			"inside"
		],
		"yoda": "off"
	}
};
