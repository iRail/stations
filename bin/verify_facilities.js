#!/usr/bin/env node
/**
 * Sanity-check facilities.csv.
 *
 * The point is to fail loudly. The previous facility data went stale for years
 * because nothing ever complained: the scraper printed a warning per station
 * and carried on writing empty columns. If a source starts returning nothing,
 * or the schema drifts, this should stop the build rather than let a hollowed
 * out file be committed.
 *
 * Usage:  node bin/verify_facilities.js
 */

const fs = require('fs');
const path = require('path');
const { parseCsv } = require('./facilities_extractor.js');

const ROOT = path.join(__dirname, '..');

const EXPECTED_HEADERS = [
  'URI', 'name', 'street', 'zip', 'city',
  'ticket_vending_machine', 'luggage_lockers', 'free_parking', 'taxi',
  'bicycle_spots', 'blue-bike', 'bus', 'tram', 'metro',
  'wheelchair_available', 'ramp', 'disabled_parking_spots',
  'platform_height_cm', 'escalator_up', 'escalator_down', 'elevator_platform',
  'audio_induction_loop',
  'sales_open_monday', 'sales_close_monday',
  'sales_open_tuesday', 'sales_close_tuesday',
  'sales_open_wednesday', 'sales_close_wednesday',
  'sales_open_thursday', 'sales_close_thursday',
  'sales_open_friday', 'sales_close_friday',
  'sales_open_saturday', 'sales_close_saturday',
  'sales_open_sunday', 'sales_close_sunday',
];

const BOOLEAN_FIELDS = [
  'ticket_vending_machine', 'luggage_lockers', 'free_parking', 'taxi',
  'bicycle_spots', 'blue-bike', 'bus', 'tram', 'metro',
  'wheelchair_available', 'ramp',
  'escalator_up', 'escalator_down', 'elevator_platform', 'audio_induction_loop',
];

/**
 * Floors for columns we know are broadly true of the Belgian network. These
 * guard against a source going quiet and quietly zeroing a column; they are set
 * well below the current values so ordinary change does not trip them.
 */
const MINIMUM_POSITIVES = {
  ticket_vending_machine: 300,
  bicycle_spots: 300,
  bus: 300,
  'blue-bike': 80,
};

function main() {
  const text = fs.readFileSync(path.join(ROOT, 'facilities.csv'), 'utf8');
  const rawHeader = text.split(/\r?\n/)[0].split(',').map((h) => h.trim());
  const rows = parseCsv(text);
  const stations = parseCsv(fs.readFileSync(path.join(ROOT, 'stations.csv'), 'utf8'));

  const errors = [];

  if (rawHeader.join(',') !== EXPECTED_HEADERS.join(',')) {
    errors.push(
      'header does not match the published schema\n' +
        `  expected: ${EXPECTED_HEADERS.join(',')}\n` +
        `  found:    ${rawHeader.join(',')}`
    );
  }

  if (rows.length < stations.length) {
    errors.push(`only ${rows.length} rows for ${stations.length} stations`);
  }

  const seen = new Set();
  for (const [i, row] of rows.entries()) {
    const where = `row ${i + 2} (${row.name || row.URI})`;

    if (!/^http:\/\/irail\.be\/stations\/NMBS\/\d{9}$/.test(row.URI)) {
      errors.push(`${where}: malformed URI "${row.URI}"`);
    }
    if (seen.has(row.URI)) errors.push(`${where}: duplicate URI`);
    seen.add(row.URI);

    for (const field of BOOLEAN_FIELDS) {
      const v = row[field];
      if (v !== '' && v !== '0' && v !== '1') {
        errors.push(`${where}: ${field} is "${v}", expected 0, 1 or empty`);
      }
    }

    const spots = row.disabled_parking_spots;
    if (spots !== '' && !/^\d+$/.test(spots)) {
      errors.push(`${where}: disabled_parking_spots is "${spots}", expected a number`);
    }

    // One or more of Infrabel's three nominal heights, ascending, ";" separated.
    const height = row.platform_height_cm;
    if (height !== '') {
      const parts = height.split(';');
      const valid = parts.every((p) => ['28', '55', '76'].includes(p));
      const ascending = parts.every((p, i) => i === 0 || Number(parts[i - 1]) < Number(p));
      if (!valid || !ascending) {
        errors.push(
          `${where}: platform_height_cm is "${height}", expected ascending values from 28, 55, 76`
        );
      }
    }

    for (const field of EXPECTED_HEADERS.filter((h) => h.startsWith('sales_'))) {
      const v = row[field];
      if (v !== '' && !/^([01]\d|2[0-3]):[0-5]\d$/.test(v)) {
        errors.push(`${where}: ${field} is "${v}", expected HH:MM or empty`);
      }
    }

    // An opening time without its closing time (or the reverse) is unusable.
    for (const day of ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']) {
      const open = row[`sales_open_${day}`];
      const close = row[`sales_close_${day}`];
      if ((open === '') !== (close === '')) {
        errors.push(`${where}: ${day} has only one of open/close set`);
      }
    }
  }

  for (const [field, floor] of Object.entries(MINIMUM_POSITIVES)) {
    const count = rows.filter((r) => r[field] === '1').length;
    if (count < floor) {
      errors.push(
        `${field} is "1" for only ${count} stations, expected at least ${floor} ` +
          '- a source has probably stopped returning data'
      );
    }
  }

  // Infrabel covers the Belgian network, so a collapse here means the join on
  // the TAF/TAP code broke rather than that platforms were demolished.
  const withHeight = rows.filter((r) => r.platform_height_cm !== '').length;
  if (withHeight < 450) {
    errors.push(
      `platform_height_cm is set for only ${withHeight} stations, expected at least 450 ` +
        '- the Infrabel join has probably broken'
    );
  }

  if (errors.length) {
    process.stderr.write(`facilities.csv failed verification (${errors.length} problems):\n\n`);
    for (const e of errors.slice(0, 40)) process.stderr.write('  - ' + e + '\n');
    if (errors.length > 40) process.stderr.write(`  ... and ${errors.length - 40} more\n`);
    process.exit(1);
  }

  process.stderr.write(`facilities.csv looks good: ${rows.length} rows, schema intact.\n`);
}

main();
