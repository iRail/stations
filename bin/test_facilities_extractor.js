#!/usr/bin/env node
/**
 * Tests for the parsing rules in facilities_extractor.js.
 *
 * These run without touching the network. The opening hours grammar and the
 * operator filter are the two places where a subtle mistake produces data that
 * looks plausible but is wrong, which is exactly what nobody notices.
 *
 * Usage:  node bin/test_facilities_extractor.js
 */

const assert = require('assert');
const { parseOpeningHours, isNmbsOperated, parseCsv, serializeCsv } = require('./facilities_extractor.js');

const DAY_LABELS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
let failures = 0;

function test(name, fn) {
  try {
    fn();
    process.stdout.write(`  ok  ${name}\n`);
  } catch (err) {
    failures++;
    process.stdout.write(`FAIL  ${name}\n      ${err.message}\n`);
  }
}

/** Render the parser's output compactly so assertions stay readable. */
function render(value) {
  const parsed = parseOpeningHours(value);
  if (!parsed) return 'rejected';
  return parsed
    .map((span, i) => (span ? `${DAY_LABELS[i]} ${span[0]}-${span[1]}` : null))
    .filter(Boolean)
    .join(' ');
}

process.stdout.write('opening hours\n');

test('a plain weekday range', () => {
  assert.strictEqual(
    render('Mo-Fr 07:00-14:15'),
    'Mo 07:00-14:15 Tu 07:00-14:15 We 07:00-14:15 Th 07:00-14:15 Fr 07:00-14:15'
  );
});

test('a second rule for the weekend', () => {
  assert.strictEqual(
    render('Mo-Fr 06:15-20:00; Sa,Su 07:15-20:00'),
    'Mo 06:15-20:00 Tu 06:15-20:00 We 06:15-20:00 Th 06:15-20:00 Fr 06:15-20:00 ' +
      'Sa 07:15-20:00 Su 07:15-20:00'
  );
});

test('public holidays are not a day of the week', () => {
  assert.strictEqual(
    render('Mo-Su,PH 06:30-22:30'),
    'Mo 06:30-22:30 Tu 06:30-22:30 We 06:30-22:30 Th 06:30-22:30 Fr 06:30-22:30 ' +
      'Sa 06:30-22:30 Su 06:30-22:30'
  );
});

test('a holiday-only rule contributes nothing', () => {
  assert.strictEqual(
    render('Mo-Fr 07:30-17:30; PH off'),
    'Mo 07:30-17:30 Tu 07:30-17:30 We 07:30-17:30 Th 07:30-17:30 Fr 07:30-17:30'
  );
});

test('a day marked off stays closed', () => {
  assert.strictEqual(render('Mo 09:00-12:00; Tu-Su off'), 'Mo 09:00-12:00');
});

test('a comma separates rules when a time precedes it', () => {
  assert.strictEqual(
    render('Mo-Fr 07:00-14:15, Sa 08:00-15:15'),
    'Mo 07:00-14:15 Tu 07:00-14:15 We 07:00-14:15 Th 07:00-14:15 Fr 07:00-14:15 Sa 08:00-15:15'
  );
});

test('a lunch break collapses to the outer envelope', () => {
  // The CSV has one open/close pair per day, so the break cannot be expressed.
  assert.strictEqual(render('Sa 09:15-12:45,13:15-16:30'), 'Sa 09:15-16:30');
});

test('24/7 covers every day', () => {
  const parsed = parseOpeningHours('24/7');
  assert.strictEqual(parsed.filter(Boolean).length, 7);
  assert.deepStrictEqual(parsed[0], ['00:00', '23:59']);
});

test('grammar we cannot be sure of is rejected, not guessed', () => {
  for (const value of ['sunrise-sunset', 'Mo-Fr 09:00-12:00 open "on appointment"', 'week 1-53 Mo 09:00-17:00']) {
    assert.strictEqual(render(value), 'rejected', `should have rejected ${value}`);
  }
});

test('empty input yields nothing', () => {
  assert.strictEqual(parseOpeningHours(''), null);
  assert.strictEqual(parseOpeningHours(undefined), null);
});

process.stdout.write('\noperator filter\n');

test('NMBS counters are recognised', () => {
  for (const tags of [{ operator: 'NMBS/SNCB' }, { name: 'Travel Centre' }, { name: 'SNCB Travelstore' }]) {
    assert.ok(isNmbsOperated(tags), `should accept ${JSON.stringify(tags)}`);
  }
});

test('other operators sharing the station are excluded', () => {
  // STIB/MIVB, De Lijn and TEC all sell from inside stations. Their counters
  // are for urban transport, so their hours say nothing about rail tickets.
  const others = [
    { name: 'BOOTIK', operator: 'STIB/MIVB' },
    { name: 'KIOSK', operator: 'STIB/MIVB' },
    { name: 'Lijnwinkel' },
    { name: 'TEC Liège Verviers' },
  ];
  for (const tags of others) {
    assert.ok(!isNmbsOperated(tags), `should reject ${JSON.stringify(tags)}`);
  }
});

process.stdout.write('\ncsv round trip\n');

test('a value containing a comma survives', () => {
  const headers = ['URI', 'street'];
  const rows = [{ URI: 'x', street: 'Place de la Gare, 4' }];
  assert.deepStrictEqual(parseCsv(serializeCsv(headers, rows)), rows);
});

test('missing trailing columns read as empty', () => {
  const [row] = parseCsv('URI,name,street\nabc,Aalst\n');
  assert.strictEqual(row.street, '');
});

process.stdout.write(
  failures ? `\n${failures} test(s) failed\n` : '\nall tests passed\n'
);
process.exit(failures ? 1 : 0);
