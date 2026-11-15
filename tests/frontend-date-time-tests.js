/* Run with jsdom on NODE_PATH. Real Intl under separate process locales/timezones. */
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { spawnSync } = require('child_process');
const { JSDOM } = require('jsdom');
if (!process.env.YSP_DATE_TIME_CASE) {
  for (const [zone, locale] of [['Asia/Ho_Chi_Minh', 'vi_VN.UTF-8'], ['America/Los_Angeles', 'en_US.UTF-8'], ['Europe/Berlin', 'de_DE.UTF-8']]) {
    const result = spawnSync(process.execPath, [__filename], { env: { ...process.env, TZ: zone, LANG: locale, LC_ALL: locale, YSP_DATE_TIME_CASE: '1' }, encoding: 'utf8' });
    process.stdout.write(result.stdout); process.stderr.write(result.stderr);
    assert.strictEqual(result.status, 0, `Browser clock case ${zone}/${locale}`);
  }
  process.exit(0);
}
const source = fs.readFileSync(path.join(__dirname, '../assets/js/support-center.js'), 'utf8');
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; }
async function page(mode, noIntl = false) {
  const dom = new JSDOM(`<p data-yoohw-timezone hidden></p>
    <time id="date" datetime="2026-11-15T00:30:00Z" data-yoohw-date="date">Site date</time>
    <time id="full" datetime="2026-11-15T00:30:00Z" data-yoohw-date="datetime">Site time</time>
    <div hidden id="older"><time datetime="2026-03-08T10:30:00Z" data-yoohw-date="datetime">Older site time</time></div>
    <time id="invalid" datetime="2026-02-30T00:00:00Z" data-yoohw-date="date">Invalid fallback</time>
    <time id="ambiguous" datetime="2026-11-15 00:30:00" data-yoohw-date="date">Ambiguous fallback</time>`, { runScripts: 'outside-only' });
  dom.window.YoOhwSupportPortalConfig = { dateTime: { mode } };
  if (noIntl) dom.window.Intl = undefined;
  dom.window.eval(source);
  await new Promise(resolve => dom.window.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
  return dom;
}
(async () => {
  const browser = await page('browser'); const doc = browser.window.document;
  const instant = new Date('2026-11-15T00:30:00Z');
  const dateExpected = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(instant);
  const fullExpected = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(instant);
  check(doc.getElementById('date').textContent === dateExpected, 'Date uses browser locale/timezone and stays date-only');
  check(doc.getElementById('full').textContent === fullExpected, 'Reply uses locale date/time and default hour cycle');
  if (process.env.TZ === 'America/Los_Angeles') check(doc.getElementById('date').textContent.includes('14'), 'UTC date crosses to previous local day');
  check(doc.getElementById('full').getAttribute('datetime') === '2026-11-15T00:30:00Z', 'Canonical UTC is unchanged');
  const detail = doc.getElementById('full').getAttribute('data-yoohw-full-date');
  check(detail.includes(new Intl.DateTimeFormat().resolvedOptions().timeZone) && /UTC[+−]\d\d:\d\d/.test(detail), 'Full detail includes timezone and UTC offset');
  check(doc.getElementById('full').getAttribute('aria-label') === detail && doc.getElementById('full').tabIndex === 0, 'Detail available on keyboard focus and to screen readers');
  const focused = doc.getElementById('full');
  focused.focus();
  focused.dispatchEvent(new browser.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  check(focused.hasAttribute('data-yoohw-tooltip-dismissed') && doc.activeElement === focused, 'Escape dismisses tooltip without moving focus');
  focused.blur(); focused.focus();
  check(!focused.hasAttribute('data-yoohw-tooltip-dismissed'), 'Refocusing restores tooltip');
  focused.dispatchEvent(new browser.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  focused.dispatchEvent(new browser.window.MouseEvent('mouseenter'));
  check(!focused.hasAttribute('data-yoohw-tooltip-dismissed'), 'Pointer re-entry restores tooltip');
  focused.blur();
  doc.dispatchEvent(new browser.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  check(focused.hasAttribute('data-yoohw-tooltip-dismissed') && doc.activeElement !== focused, 'Escape at document dismisses hover-only detail without moving focus');
  check(!doc.querySelector('[data-yoohw-timezone]').hidden && doc.querySelector('[data-yoohw-timezone]').textContent.includes(new Intl.DateTimeFormat().resolvedOptions().timeZone), 'Conversation timezone note is visible after successful conversion');
  const older = doc.querySelector('#older time');
  check(older.textContent !== 'Older site time', 'Collapsed reply is formatted before reveal');
  if (process.env.TZ === 'America/Los_Angeles') {
    check(detail.includes('UTC−08:00') && older.getAttribute('data-yoohw-full-date').includes('UTC−07:00'), 'Detail uses offset of each instant across DST');
  }
  doc.getElementById('older').hidden = false;
  check(older.getAttribute('data-yoohw-full-date'), 'Revealed reply retains local formatting');
  check(doc.getElementById('invalid').textContent === 'Invalid fallback' && !doc.getElementById('invalid').hasAttribute('tabindex'), 'Invalid date retains fallback');
  check(doc.getElementById('ambiguous').textContent === 'Ambiguous fallback', 'Timezone-less timestamp is never parsed');
  browser.window.close();
  for (const [mode, noIntl] of [['site', false], ['browser', true]]) {
    const fallback = await page(mode, noIntl); const d = fallback.window.document;
    check(d.getElementById('full').textContent === 'Site time' && d.querySelector('[data-yoohw-timezone]').hidden, 'Site selection or missing Intl preserves server output');
    fallback.window.close();
  }
  console.log(`frontend-date-time-js-ok: ${checks} checks; ${process.env.TZ}/${new Intl.DateTimeFormat().resolvedOptions().locale}`);
})().catch(error => { console.error(error); process.exitCode = 1; });
