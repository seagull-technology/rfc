import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';

const root = new URL('../', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');
const readJson = async (path) => JSON.parse(await read(path));
const checkOnly = process.argv.includes('--check');
const [project, lock, lodash, source, license] = await Promise.all([
    readJson('package.json'),
    readJson('package-lock.json'),
    readJson('node_modules/lodash/package.json'),
    read('node_modules/lodash/lodash.min.js'),
    read('node_modules/lodash/LICENSE'),
]);

assert.equal(lodash.version, project.devDependencies.lodash,
    'Lodash must match the exact package.json pin; run npm ci.');
assert.equal(lodash.version, lock.packages['node_modules/lodash'].version,
    'Installed Lodash must match package-lock.json; run npm ci.');
assert.match(source, /^\/\*\*[\s\S]*?\*\//,
    'The upstream Lodash banner changed; review the vendor sync before upgrading.');

// The upstream banner contains a historical Underscore 1.8.3 attribution,
// which scanners can mistake for the running library. Preserve the complete
// upstream LICENSE alongside the asset and identify the actual release here.
const banner = `/*! Lodash ${lodash.version} | Copyright OpenJS Foundation and other contributors | MIT license | See lodash.LICENSE.txt */`;
const javascript = `${banner}\n${source.replace(/^\/\*\*[\s\S]*?\*\/\s*/, '').trimEnd()}\n`;
const assets = new Map([
    ['public/js/lodash.min.js', javascript],
    ['public/js/lodash.LICENSE.txt', license],
]);

for (const [path, expected] of assets) {
    const existing = await read(path).catch((error) => {
        if (error.code !== 'ENOENT') throw error;
        return null;
    });

    if (checkOnly) {
        assert.ok(existing === expected,
            `${path} does not match the locked dependency; run npm run vendor:sync.`);
    } else if (existing !== expected) {
        await writeFile(new URL(path, root), expected);
    }

    const hash = createHash('sha256').update(expected).digest('hex');
    console.log(`${checkOnly ? 'Verified' : 'Synced'} ${path} (SHA-256 ${hash})`);
}
