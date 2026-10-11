// Exercises the release workflow against a disposable local SVN repository, never WordPress.org.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const { prepare, zip, collect, collectDirectoryAssets } = require('../scripts/prepare-release.cjs');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'onyx-release-test-'));
const command = (name, ...args) => execFileSync(name, args, { encoding: 'utf8' });
const workingCopy = path.join(temp, 'svn');
const source = path.join(temp, 'source');
const output = path.join(temp, 'output');
const version = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../package.json'), 'utf8')).version;
const priorVersion = version === '0.0.1' ? '0.0.0' : '0.0.1';
let checks = 0;
function check(test) {
	test(); checks++;
}
try {
	const files = collect(path.resolve(__dirname, '..'));
	const images = collectDirectoryAssets(path.resolve(__dirname, '..'));
	for (const [ file, contents ] of Object.entries(files)) {
		fs.mkdirSync(path.dirname(path.join(source, file)), { recursive: true });
		fs.writeFileSync(path.join(source, file), contents);
	}
	fs.copyFileSync(path.resolve(__dirname, '../package.json'), path.join(source, 'package.json'));
	fs.mkdirSync(path.join(source, 'assets/images/backups'), { recursive: true });
	fs.writeFileSync(path.join(source, 'assets/images/backups', 'old-banner.png'), 'Must not ship');
	for (const [ file, contents ] of Object.entries(images)) {
		fs.writeFileSync(path.join(source, 'assets/images', file), contents);
	}
	fs.mkdirSync(path.join(source, '_svn'));
	fs.writeFileSync(path.join(source, '_svn', 'private.txt'), 'Must not ship');
	fs.writeFileSync(path.join(source, '.env'), 'Must not ship');
	command('svnadmin', 'create', path.join(temp, 'repository'));
	command('svn', 'checkout', pathToFileURL(path.join(temp, 'repository')).href, workingCopy);
	for (const folder of [ 'trunk', 'tags', 'assets', 'branches' ]) {
		command('svn', 'mkdir', path.join(workingCopy, folder));
	}
	fs.writeFileSync(path.join(workingCopy, 'trunk', 'obsolete.php'), '<?php // old release');
	command('svn', 'add', path.join(workingCopy, 'trunk', 'obsolete.php'));
	command('svn', 'copy', path.join(workingCopy, 'trunk'), path.join(workingCopy, 'tags', priorVersion));
	fs.writeFileSync(path.join(workingCopy, 'assets', 'directory-banner.txt'), 'Preserved');
	command('svn', 'add', path.join(workingCopy, 'assets', 'directory-banner.txt'));
	command('svn', 'commit', '-m', 'Disposable fixture', workingCopy);
	const oldTag = fs.readFileSync(path.join(workingCopy, 'tags', priorVersion, 'obsolete.php'));
	fs.appendFileSync(path.join(workingCopy, 'trunk', 'obsolete.php'), '\nmanual edit');
	check(() => assert.throws(() => prepare({ source, workingCopy, output }), /local change/));
	check(() => assert.equal(fs.existsSync(path.join(workingCopy, 'tags', version)), false));
	command('svn', 'revert', path.join(workingCopy, 'trunk', 'obsolete.php'));
	const result = prepare({ source, workingCopy, output });
	check(() => assert.equal(result.fileCount, Object.keys(files).length));
	check(() => assert.equal(fs.existsSync(path.join(workingCopy, 'trunk', 'obsolete.php')), false));
	check(() => assert.deepEqual(fs.readFileSync(path.join(workingCopy, 'tags', priorVersion, 'obsolete.php')), oldTag));
	check(() => assert.equal(fs.readFileSync(path.join(workingCopy, 'assets', 'directory-banner.txt'), 'utf8'), 'Preserved'));
	check(() => assert.ok(Object.entries(images).every(([ file, bytes ]) => fs.readFileSync(path.join(workingCopy, 'assets', file)).equals(bytes))));
	check(() => assert.equal(fs.existsSync(path.join(workingCopy, 'assets', 'backups')), false));
	check(() => assert.equal(fs.existsSync(path.join(result.trunk, 'assets', 'images')), false));
	check(() => assert.ok(Object.entries(files).every(([ file, bytes ]) => fs.readFileSync(path.join(result.trunk, file)).equals(bytes) && fs.readFileSync(path.join(result.tag, file)).equals(bytes))));
	check(() => assert.equal(fs.existsSync(path.join(result.trunk, '_svn')), false));
	// Rebuilding an uncommitted generated tag is allowed; manual edits to it are protected.
	prepare({ source, workingCopy, output });
	const banner = path.join(workingCopy, 'assets', 'banner-1544x500.png');
	fs.appendFileSync(banner, 'manual edit');
	check(() => assert.throws(() => prepare({ source, workingCopy, output }), /local change/));
	fs.writeFileSync(banner, images['banner-1544x500.png']);
	const sourceIcon = path.join(source, 'assets/images', 'icon-128x128.png');
	fs.writeFileSync(sourceIcon, images['icon-256x256.png']);
	check(() => assert.throws(() => prepare({ source, workingCopy, output }), /128x128/));
	fs.writeFileSync(sourceIcon, images['icon-128x128.png']);
	fs.appendFileSync(path.join(source, 'index.php'), '\n// Changed source\n');
	prepare({ source, workingCopy, output });
	check(() => assert.deepEqual(fs.readFileSync(path.join(result.tag, 'index.php')), fs.readFileSync(path.join(source, 'index.php'))));
	fs.appendFileSync(path.join(result.tag, 'index.php'), '// Manual SVN edit\n');
	check(() => assert.throws(() => prepare({ source, workingCopy, output }), /local change/));
	fs.writeFileSync(path.join(result.tag, 'index.php'), fs.readFileSync(path.join(source, 'index.php')));
	command('svn', 'commit', '-m', 'Disposable release', workingCopy);
	const before = fs.readFileSync(path.join(result.trunk, 'readme.txt'));
	check(() => assert.throws(() => prepare({ source, workingCopy, output }), /immutable/));
	check(() => assert.deepEqual(fs.readFileSync(path.join(result.trunk, 'readme.txt')), before));
	const archive = zip({ source, output });
	const entries = command('unzip', '-Z1', archive).split('\n');
	check(() => assert.ok(entries.every((entry) => ! /(^|\/)(_svn|\.env|node_modules|tests|scripts|\.git|backups)(\/|$)/.test(entry))));
	check(() => assert.equal(command('unzip', '-p', archive, 'acf-onyx-poll/index.php'), fs.readFileSync(path.join(source, 'index.php'), 'utf8')));
	fs.writeFileSync(path.join(source, 'readme.txt'), fs.readFileSync(path.join(source, 'readme.txt'), 'utf8').replace(`Stable tag: ${version}`, 'Stable tag: 999.0.0'));
	check(() => assert.throws(() => prepare({ source, workingCopy, output }), /Version mismatch/));
	console.log(`${checks} release checks passed against a disposable local SVN repository.`);
} finally {
	fs.rmSync(temp, { recursive: true, force: true });
}
