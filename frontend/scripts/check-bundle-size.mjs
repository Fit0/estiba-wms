// Checks the size of the build against `bundle-budget.json` (E10-07, ADR 0061).
//
// What the browser downloads to show the first screen is the entry file plus the ones it asks for
// right away (the `modulepreload` links of index.html). That is the "initial" size. Every other file is
// downloaded when a screen or the camera needs it, so it only has to stay under the limit of one file.
// Sizes are gzip, as Vite reports them, in kB of 1000 bytes.
import { readFileSync, readdirSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join } from 'node:path';

const dist = new URL('../dist/', import.meta.url).pathname;
const budget = JSON.parse(readFileSync(new URL('../bundle-budget.json', import.meta.url), 'utf8'));

const kb = (bytes) => bytes / 1000;
const gzipOf = (file) => gzipSync(readFileSync(join(dist, file))).length;

const html = readFileSync(join(dist, 'index.html'), 'utf8');
const initial = new Set(
  [...html.matchAll(/<(?:script|link)[^>]*(?:src|href)="\/(assets\/[^"]+\.js)"/g)].map(
    (match) => match[1],
  ),
);

const files = readdirSync(join(dist, 'assets'))
  .filter((name) => name.endsWith('.js'))
  .map((name) => `assets/${name}`)
  .map((file) => ({ file, gzip: gzipOf(file), initial: initial.has(file) }))
  .sort((a, b) => b.gzip - a.gzip);

const initialGzip = files.filter((f) => f.initial).reduce((sum, f) => sum + f.gzip, 0);
const largestLazy = files.filter((f) => !f.initial)[0];

console.log('JavaScript of the build (gzip):');
for (const f of files) {
  console.log(
    `  ${kb(f.gzip).toFixed(1).padStart(7)} kB  ${f.initial ? 'initial' : 'lazy   '}  ${f.file}`,
  );
}
console.log(
  `  ${kb(initialGzip).toFixed(1).padStart(7)} kB  initial in all, budget ${budget.initialGzipKb} kB`,
);

const failures = [];
if (kb(initialGzip) > budget.initialGzipKb) {
  failures.push(
    `The initial JavaScript is ${kb(initialGzip).toFixed(1)} kB and the budget is ${budget.initialGzipKb} kB.`,
  );
}
for (const f of files) {
  if (!f.initial && kb(f.gzip) > budget.lazyFileGzipKb) {
    failures.push(
      `${f.file} is ${kb(f.gzip).toFixed(1)} kB and a lazy file may have ${budget.lazyFileGzipKb} kB.`,
    );
  }
}
if (largestLazy === undefined) {
  failures.push('There is no lazy file: the screens are being downloaded with the app.');
}

if (failures.length > 0) {
  for (const failure of failures) {
    console.error(`::error title=Bundle budget::${failure}`);
  }
  process.exit(1);
}
