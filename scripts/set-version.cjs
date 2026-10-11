const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const version = process.argv[2];
if (!/^\d+\.\d+\.\d+$/.test(version || '')) {
  console.error('Usage: npm run release:version -- X.Y.Z');
  process.exitCode = 1;
} else {
  const changes = [
    ['package.json', text => text.replace(/("version":\s*")[^"]+/, `$1${version}`)],
    ['onyx-poll.php', text => text.replace(/^Version: .+$/m, `Version: ${version}`).replace(/define\('ACF_ONYX_POLL_VERSION', '[^']+'\)/, `define('ACF_ONYX_POLL_VERSION', '${version}')`)],
    ['readme.txt', text => text.replace(/^Stable tag: .+$/m, `Stable tag: ${version}`)],
  ].map(([file, transform]) => [path.join(root, file), transform(fs.readFileSync(path.join(root, file), 'utf8'))]);
  for (const [file, contents] of changes) fs.writeFileSync(file, contents);
  console.log(`Version set to ${version}. Add its changelog in readme.txt, then run npm run release.`);
}
