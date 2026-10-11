const fs = require('node:fs');
const path = require('node:path');
const CleanCSS = require('clean-css');

for (const name of [ 'onyx-poll', 'admin' ]) {
	const source = path.join(__dirname, '..', 'assets', 'css', name + '.css');
	const result = new CleanCSS().minify(fs.readFileSync(source, 'utf8'));
	if (result.errors.length) {
		throw new Error(result.errors.join('\n'));
	}
	fs.writeFileSync(source.replace(/\.css$/, '.min.css'), result.styles + '\n');
}
