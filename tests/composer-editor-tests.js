/* Run with jsdom available on NODE_PATH. No browser/network or editor mocks. */
const fs = require('fs');
const assert = require('assert');
const { JSDOM } = require('jsdom');
const dom = new JSDOM('', { runScripts: 'outside-only' });
dom.window.eval(fs.readFileSync(require('path').join(__dirname, '../assets/js/editor.js'), 'utf8'));
const api = dom.window.YoOhwSupportEditor;
const doc = dom.window.document;
let checks = 0;
function check(ok, label) { assert.ok(ok, label); ++checks; }
function parse(html) { const t = doc.createElement('template'); t.innerHTML = html; return t.content; }
const code = '\tconst x = "<script>";\n\n  https://example.invalid/code\n& &amp; 😀\n';
const encoded = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
for (const source of ['<pre><code>' + encoded + '</code></pre>', '<div><p>Before</p><pre>' + encoded + '</pre><p>After</p></div>']) {
  const result = api.normalize(source); const fragment = parse(result.html);
  check(fragment.querySelector('pre code').textContent === code, 'Code characters/whitespace unchanged');
  check(!fragment.querySelector('pre a'), 'No code autolinking');
  check(!result.removedMedia, 'Code HTML is never treated as media');
  check(api.normalize(result.html).html === result.html, 'Code normalization idempotent');
}
const samples = [
  ['<div style="height:900px"><span class="MsoNormal" style="font-size:90px">Hello</span></div><p>&nbsp;<br></p>', 'Hello'],
  ['<h1>Heading</h1><blockquote><p>Quote</p></blockquote>', 'Quote'],
  ['<ol start="8"><li value="10">Item<ul><li>Nested</li></ul></li></ol>', 'Nested'],
  ['<table width="9999"><tr><th colspan="2">H</th></tr><tr><td>A</td><td>B</td></tr></table>', 'B'],
  ['<p>Tiếng Việt 👩‍👩‍👧‍👦 हिन्दी</p>', '👩‍👩‍👧‍👦'],
  ['<p>Use `a  b` and <code>c  d</code></p>', 'a  b'],
  ['<div hidden>HIDDEN</div><p style="display:none">HIDDEN</p><!-- HIDDEN --><p>Visible</p>', 'Visible'],
  ['<p>A<br><br><br><br>B</p>', 'B'],
  ['<p><strong>Malformed</p>', 'Malformed'],
];
for (const [source, text] of samples) {
  const result = api.normalize(source); const fragment = parse(result.html);
  check(fragment.textContent.includes(text), 'Meaningful content survives');
  check(!fragment.querySelector('[style],[width],.MsoNormal'), 'Presentation attributes removed');
  check(!result.html.includes('HIDDEN') && !result.html.includes('<br><br><br>'), 'Hidden content and excessive breaks removed');
  check(api.normalize(result.html).html === result.html, 'Prose normalization idempotent');
}
for (const media of ['<img src="https://example.invalid/pixel">', '<iframe src="https://example.invalid"></iframe>', '<svg><text>hidden</text></svg>']) {
  const result = api.normalize('<p>Visible</p>' + media);
  check(result.removedMedia && !parse(result.html).querySelector('img,iframe,svg'), 'Media explicitly reported and removed');
}
const mixed = api.normalize(api.fromText('Before\n\n```js\n' + code + '```\n\nAfter'));
check(parse(mixed.html).querySelector('pre').textContent === code.slice(0, -1), 'Plain fenced code preserves its payload');
check(parse(mixed.html).textContent.startsWith('Before') && parse(mixed.html).textContent.endsWith('After'), 'Mixed prose stays outside code');
const richFence = api.normalize('<p>Before</p><p>```js</p><p>  one</p><p></p><p>  two</p><p>```</p><p>After</p>');
check(parse(richFence.html).querySelector('pre').textContent === '  one\n\n  two', 'Paragraph-split fences preserve code spacing');
const link = api.normalize('<a href="javascript:alert(1)" onclick="alert(1)">Bad</a><a href="https://example.invalid" target="_blank">Good</a>');
check(!link.html.includes('javascript:') && !link.html.includes('onclick'), 'Unsafe client links removed');
check(parse(link.html).querySelector('[target="_blank"]').getAttribute('rel') === 'noopener noreferrer', 'New-tab links protected');
for (const literal of ['<code>literal</code>', '<pre><code>literal</code></pre>']) {
  const result = api.normalize(api.fromText('```html\n' + literal + '\n```'));
  check(parse(result.html).querySelector('pre code').textContent === literal, 'Fenced literal HTML not interpreted');
  const inline = api.normalize(api.fromText('Use `' + literal + '` here'));
  check(parse(inline.html).querySelector('code').textContent === literal, 'Inline literal HTML not interpreted');
}
console.log(`composer-editor-ok: ${checks} checks using real DOM parsing; no network`);
