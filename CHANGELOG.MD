## Changelog

### 0.2.0
#### Features
- Add utilities bash file for releases
- Add gitattributes file with export-ignore files and treat asset files as binary
- feat: add DEBUG constant for not loading REST route
- refactor: remove singletons, call classes directly
- Add editorconfig file
- build: add browserslistrc file
- workflow: expose gulp tasks in package.json
- workflow: add lint gulp task
- workflow(dependencies): change branch of dependabot config file to `master

#### Fixes
- fix: exclude 'hidden' visibility fields from showing on forms
- cleanup: remove unused jQuery mentions in files

#### Packages
- Bump axios from 0.19.2 to 0.21.4
- Bump @babel/core from 7.10.3 to 7.15.5
- Bump @babel/preset-env from 7.10.3 to 7.15.6
- Bump @babel/register from 7.10.3 to 7.15.3
- Bump @wordpress/eslint-plugin from 7.1.0 to 9.1.2
- Bump babel-loader from 8.1.0 to 8.2.2
- Bump browser-sync from 2.26.7 to 2.27.5
- Bump del from 5.1.0 to 6.0.0
- Bump eslint from 7.3.1 to 7.32.0
- Bump gulp-autoprefixer from 7.0.1 to 8.0.0
- Bump gulp-notify from 3.2.0 to 4.0.0
- Bump stylelint-scss from 3.18.0 to 3.21.0
- Bump stylelint from 13.6.1 to 13.13.1
- Bump stylelint-config-recommended-scss from 4.2.0 to 4.3.0
- Bump stylelint-declaration-use-variable from 1.7.2 to 1.7.3
- fix: Update outdated stylelint rules
- Bump gulp-sass from 4.1.0 to 5.0.0
- Bump node-sass from 4.14.1 to 6.0.1
- fix: import node-sass manually
- Bump webpack-stream from 5.2.1 to 7.0.0
- Add webpack package v5.53.0 as a dev dependency
- fix: import webpack manually in gulp/webpack build
- Bump terser-webpack-plugin from 3.0.6 to 5.2.4
- fix: update webpack config to build with new version of Terser package
- build: run `npm audit fix`
- build: install and config gulp-sourcemaps v3.0.0
- build: silence jsdoc/check-line-aligment rule
- build: add and configure eslint-webpack-plugin package v3.0.1
- build: remove deprecated babel-loader package
- build: add and configure @babel/eslint-parser v7.15.7
- reformat eslintrc file
- workflow: turn off global @wordpress react rules
- Remove deprecated babel-eslint package

### 0.1.2
- Add hidden label functionality
- Update NPM packages

### 0.1.1
- Add dependabot config file

### 0.1.0
- Add formID functionality
- Add functionality for scrolling to message when there has been an err… …
- Add method to flush programs transient
- Remove fallback forms from the DOM if they will not be used
- Split form build methods into their own build class
- Do not use input wrapper when the field is "hidden" type
- Update npm packages
- Add functionality to use default values defined in admin
- Incorporate Gravity Forms confirmations to the fallback forms
- Add gitignore file
- Add initial composer.json file
- Add checks for feeds
- Add placeholders support
- Add gform_wrapper class to wrapper div

### 0.0.6
- Fix `data-keys-*`, had been fully excluded.

### 0.0.5
- Add functionality for admin selected choice in dropdowns.
- Refactor input and wrapper classes to their own methods.

### 0.0.4
- Set up checkboxes properly.
- Set types of fields as properties of the class.
- Add required attribute and aria.
- Add Degree and Program fields functionality (population and dependency).

### 0.0.3
- Add more invalid fields to not display.
- Add filter `gf_fallback_form_markup` to manipulate the form externally.

### 0.0.2
- Add faux REST API endpoint to test cache against.

### 0.0.1
- Initial development.
