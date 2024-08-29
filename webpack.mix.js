const mix      = require('laravel-mix');
const glob     = require('glob');
const path     = require('path');
const cssFiles = glob.sync('assets/css/!(*.min).css');

cssFiles.forEach((file) => {
	const outputDir = path.dirname(file);
	const fileName = path.basename(file, '.css');

	// Process and minify the CSS file
	mix.postCss(file, outputDir)
	  .minify(`${outputDir}/${fileName}.css`); // This will create fileName.min.css
});