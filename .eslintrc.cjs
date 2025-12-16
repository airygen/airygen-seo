module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended-with-formatting' ],
	ignorePatterns: [ 'build/', 'node_modules/' ],
	env: {
		browser: true,
		es6: true,
		node: true,
	},
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module',
	},
	settings: {
		'import/resolver': {
			node: {
				extensions: [ '.js', '.jsx', '.ts', '.tsx' ],
			},
		},
	},
	overrides: [
		{
			files: [ '**/*.ts', '**/*.tsx' ],
			parser: '@typescript-eslint/parser',
			parserOptions: {
				project: './tsconfig.json',
				tsconfigRootDir: __dirname,
			},
			extends: [
				'plugin:@wordpress/eslint-plugin/recommended-with-formatting',
				'plugin:@typescript-eslint/recommended',
			],
			rules: {
				'no-unused-vars': 'off',
				'@typescript-eslint/no-unused-vars': [
					'error',
					{
						argsIgnorePattern: '^_',
						varsIgnorePattern: '^_',
					},
				],
			},
		},
	],
};
