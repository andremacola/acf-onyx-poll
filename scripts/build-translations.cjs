const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const languages = path.join(root, 'languages');
const checkOnly = process.argv.includes('--check');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'onyx-translations-'));

try {
	for (const file of fs.readdirSync(languages).filter((name) => name.endsWith('.po')).sort()) {
		const source = path.join(languages, file);
		const compiled = source.replace(/\.po$/, '.mo');
		const target = checkOnly ? path.join(temporary, path.basename(compiled)) : compiled;
		execFileSync('msgcmp', [ source, path.join(languages, 'acf-onyx-poll.pot') ], { stdio: 'inherit' });
		execFileSync('msgfmt', [ '--check', '--check-format', '--statistics', source, '--output-file=' + target ], { stdio: 'inherit' });
		if (checkOnly && (! fs.existsSync(compiled) || ! fs.readFileSync(compiled).equals(fs.readFileSync(target)))) {
			throw new Error(`${path.basename(compiled)} is missing or outdated. Run npm run i18n:build.`);
		}
	}
} catch (error) {
	if (error.code === 'ENOENT') {
		process.stderr.write('GNU gettext is required. Install msgcmp and msgfmt and add them to PATH.\n');
	} else {
		process.stderr.write(`${error.message}\n`);
	}
	process.exitCode = 1;
} finally {
	fs.rmSync(temporary, { recursive: true, force: true });
}
