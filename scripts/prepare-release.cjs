// Local preparation only. This script never updates or commits a remote SVN repository.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');

const slug = 'acf-onyx-poll';
const rootFiles = ['onyx-poll.php', 'uninstall.php', 'index.php', 'readme.txt'];
const folders = { admin: ['.php'], api: ['.php'], classes: ['.php'], 'assets/css': ['.css'], 'assets/js': ['.js'], languages: ['.mo', '.po', '.pot'] };
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
const svn = (...args) => execFileSync('svn', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
const xmlText = text => text.replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name)).flatMap(entry => {
    if (entry.name.startsWith('.')) return [];
    const file = path.join(directory, entry.name);
    if (entry.isSymbolicLink()) throw new Error(`Symbolic links are not allowed in release files: ${file}`);
    return entry.isDirectory() ? walk(file) : [file];
  });
}

function collect(source) {
  const files = rootFiles.map(file => path.join(source, file));
  for (const [folder, extensions] of Object.entries(folders)) {
    files.push(...walk(path.join(source, folder)).filter(file => extensions.includes(path.extname(file))));
  }
  return Object.fromEntries(files.map(file => {
    if (!fs.lstatSync(file).isFile()) throw new Error(`Expected a regular file: ${file}`);
    return [path.relative(source, file).split(path.sep).join('/'), fs.readFileSync(file)];
  }));
}

function versionOf(source) {
  const version = JSON.parse(fs.readFileSync(path.join(source, 'package.json'), 'utf8')).version;
  const php = fs.readFileSync(path.join(source, 'onyx-poll.php'), 'utf8');
  const readme = fs.readFileSync(path.join(source, 'readme.txt'), 'utf8');
  if (!/^\d+\.\d+\.\d+$/.test(version)) throw new Error('Use a numeric release version, such as 2.0.1.');
  if (php.match(/^Version:\s*(\S+)/m)?.[1] !== version || php.match(/define\('ACF_ONYX_POLL_VERSION', '([^']+)'\)/)?.[1] !== version || readme.match(/^Stable tag:\s*(\S+)/m)?.[1] !== version) {
    throw new Error('Version mismatch: package.json, plugin header, PHP constant and readme.txt must agree. Use npm run release:version -- X.Y.Z.');
  }
  if (!readme.includes(`= ${version} =`)) throw new Error(`Add the ${version} changelog entry to readme.txt before preparing the release.`);
  for (const field of ['Requires PHP', 'Requires at least']) {
    if (php.match(new RegExp(`^${field}:\\s*(\\S+)`, 'm'))?.[1] !== readme.match(new RegExp(`^${field}:\\s*(\\S+)`, 'm'))?.[1]) throw new Error(`Requirement mismatch: ${field}.`);
  }
  return version;
}

function status(workingCopy) {
  const xml = svn('status', '--xml', workingCopy);
  return [...xml.matchAll(/<entry\s+path="([^"]+)"[\s\S]*?<wc-status\s+([^>]+)>/g)].map(match => {
    const absolute = path.resolve(xmlText(match[1]));
    const item = match[2].match(/item="([^"]+)"/)?.[1];
    const props = match[2].match(/props="([^"]+)"/)?.[1];
    const fingerprint = fs.existsSync(absolute) ? (fs.lstatSync(absolute).isDirectory() ? 'directory' : hash(fs.readFileSync(absolute))) : null;
    return { file: path.relative(workingCopy, absolute).split(path.sep).join('/'), item, props, fingerprint };
  });
}

function syncTrunk(workingCopy, files) {
  const trunk = path.join(workingCopy, 'trunk');
  // Remove obsolete tracked runtime files, preserving SVN history through svn delete.
  for (const absolute of walk(trunk)) {
    const relative = path.relative(trunk, absolute).split(path.sep).join('/');
    if (!Object.hasOwn(files, relative)) {
      svn('delete', '--force', absolute);
    }
  }
  for (const [relative, contents] of Object.entries(files)) {
    const destination = path.join(trunk, relative);
    fs.mkdirSync(path.dirname(destination), { recursive: true });
    fs.writeFileSync(destination, contents);
  }
  svn('add', '--force', '--parents', trunk);
}

function prepare({ source, workingCopy, output }) {
  source = fs.realpathSync(source);
  workingCopy = fs.realpathSync(workingCopy);
  const version = versionOf(source);
  const files = collect(source);
  if (fs.realpathSync(svn('info', '--show-item', 'wc-root', workingCopy)) !== workingCopy) throw new Error('The SVN path must be the working-copy root.');
  const stateFile = path.join(output, 'svn-preparation.json');
  const previous = fs.existsSync(stateFile) ? JSON.parse(fs.readFileSync(stateFile, 'utf8')) : null;
  const changes = status(workingCopy);
  const reusable = previous && previous.workingCopy === workingCopy && previous.version === version;
  for (const change of changes) {
    if (!reusable || !previous.changes.some(old => JSON.stringify(old) === JSON.stringify(change))) {
      throw new Error(`SVN has a local change not generated by this preparation: ${change.file}. Review it before continuing.`);
    }
  }
  const tag = path.join(workingCopy, 'tags', version);
  const tagExists = fs.existsSync(tag);
  if (tagExists && (!reusable || !changes.some(change => change.file === `tags/${version}` && change.item === 'added'))) {
    throw new Error(`Tag ${version} already exists. Published tags are immutable; choose a new version.`);
  }
  // All checks above happen before changing the working copy.
  syncTrunk(workingCopy, files);
  if (tagExists) {
    // This tag is an uncommitted output from our prior run, whose hashes were verified above.
    svn('delete', '--force', tag);
  }
  svn('copy', path.join(workingCopy, 'trunk'), tag);
  fs.mkdirSync(output, { recursive: true });
  const manifest = { version, workingCopy, files: Object.fromEntries(Object.entries(files).map(([file, contents]) => [file, hash(contents)])), changes: status(workingCopy) };
  fs.writeFileSync(stateFile, JSON.stringify(manifest, null, 2) + '\n');
  return { version, fileCount: Object.keys(files).length, trunk: path.join(workingCopy, 'trunk'), tag };
}

function zip({ source, output }) {
  const version = versionOf(source);
  const files = collect(source);
  const staging = fs.mkdtempSync(path.join(os.tmpdir(), 'onyx-release-'));
  fs.mkdirSync(output, { recursive: true });
  const destination = path.resolve(output, `${slug}-${version}.zip`);
  try {
    for (const [file, contents] of Object.entries(files)) {
      const target = path.join(staging, slug, file);
      fs.mkdirSync(path.dirname(target), { recursive: true });
      fs.writeFileSync(target, contents);
    }
    // Write a new archive, so old entries cannot survive a repeated build.
    const temporaryZip = path.join(staging, 'package.zip');
    execFileSync('zip', ['-q', '-r', '-X', temporaryZip, slug], { cwd: staging });
    fs.renameSync(temporaryZip, destination);
    return destination;
  } finally { fs.rmSync(staging, { recursive: true, force: true }); }
}

if (require.main === module) {
  try {
    const source = path.resolve(__dirname, '..');
    const output = path.join(source, 'dist');
    if (process.argv.slice(2).some(arg => arg !== '--zip-only')) throw new Error('Supported option: --zip-only.');
    if (process.argv.includes('--zip-only')) console.log(`Optional installation ZIP: ${zip({ source, output })}`);
    else console.log(JSON.stringify(prepare({ source, output, workingCopy: path.join(source, '_svn') }), null, 2));
  } catch (error) { console.error(error.message); process.exitCode = 1; }
}

module.exports = { prepare, zip, collect, versionOf, status };
