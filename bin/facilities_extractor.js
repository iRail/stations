#!/usr/bin/env node
/**
 * Rebuild facilities.csv from open data sources.
 *
 * This replaces web_facilities_extractor.php, which scraped
 * belgianrail.be/Station.ashx. That site was retired and its replacement sits
 * behind Cloudflare, so the old single source is gone for good. There is no
 * drop-in replacement: the fields are now assembled from several open sources,
 * and a handful of them can no longer be sourced at all.
 *
 * Sources
 *   - OpenStreetMap (Overpass API), joined on uic_ref  -> most facility booleans
 *   - Infrabel Open Data (perronhoogten-in-stations)   -> platform_height_cm
 *   - Blue-bike public API                             -> blue-bike
 *
 * Fields with no remaining source keep their previous value, so running this
 * never destroys data. Which fields those are, and how stale they are, is
 * written to facilities_coverage.md on every run.
 *
 * Usage:  node bin/facilities_extractor.js [--offline]
 *         --offline reuses the cached API responses in bin/.cache
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const STATIONS_CSV = path.join(ROOT, 'stations.csv');
const FACILITIES_CSV = path.join(ROOT, 'facilities.csv');
const COVERAGE_MD = path.join(ROOT, 'facilities_coverage.md');
const CACHE_DIR = path.join(__dirname, '.cache');

const OVERPASS = 'https://overpass-api.de/api/interpreter';
const INFRABEL_BASE =
  'https://opendata.infrabel.be/api/explore/v2.1/catalog/datasets';
const INFRABEL_PLATFORMS = `${INFRABEL_BASE}/perronhoogten-in-stations/records`;
const INFRABEL_OPERATIONAL_POINTS = `${INFRABEL_BASE}/operationele-punten-van-het-netwerk/records`;
const BLUEBIKE = 'https://api.blue-bike.be/pub/location';

const OFFLINE = process.argv.includes('--offline');

// Overpass rejects anonymous clients with 406, and their usage policy asks for
// a contactable identifier. Sent to every source so we are attributable.
const USER_AGENT =
  'irail-stations facilities extractor (+https://github.com/iRail/stations)';

// The published column order. External consumers depend on it: do not reorder.
const HEADERS = [
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

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

/**
 * Infrabel's three nominal platform classes, in centimetres above the rail, as
 * defined by the perronhoogten-in-stations dataset. 28cm is the historic model
 * being phased out in favour of 55cm and 76cm. These are nominal figures: the
 * built height of any individual platform may differ.
 */
const PLATFORM_HEIGHT_CM = { Low: 28, Middle: 55, High: 76 };

/**
 * Features OpenStreetMap maps thoroughly enough in Belgium that not finding one
 * is real evidence it is not there, so we may write a confident 0.
 *
 * For everything else absence only means "nobody has mapped it yet". Ticket
 * machines are the clearest case: NMBS has them at nearly every station, but
 * OSM knows of a few hundred. Writing 0 there would replace stale-but-true data
 * with confidently false data, so those fields fall back to the previous value
 * and are reported as unverified instead.
 */
const OSM_ABSENCE_IS_RELIABLE = new Set([
  'bicycle_spots',
  'bus',
  'tram',
  'metro',
  'elevator_platform',
]);

/**
 * Which source owns which field. Fields marked `null` have no open source
 * left; their previous value is carried over untouched.
 */
const FIELD_SOURCES = {
  street: null,
  zip: null,
  city: null,
  ticket_vending_machine: 'osm',
  luggage_lockers: 'osm',
  free_parking: 'osm',
  taxi: 'osm',
  bicycle_spots: 'osm',
  'blue-bike': 'blue-bike',
  bus: 'osm',
  tram: 'osm',
  metro: 'osm',
  wheelchair_available: null,
  ramp: null,
  disabled_parking_spots: 'osm',
  platform_height_cm: 'infrabel',
  escalator_up: 'osm',
  escalator_down: 'osm',
  elevator_platform: 'osm',
  audio_induction_loop: null,
  sales: 'osm',
};

// ---------------------------------------------------------------------------
// small helpers
// ---------------------------------------------------------------------------

function log(msg) {
  process.stderr.write(msg + '\n');
}

/**
 * Parse a CSV file into an array of row objects.
 * The files in this repo are plain comma-separated with no quoting, but quoted
 * fields are handled so a future correction cannot silently corrupt a row.
 */
function parseCsv(text) {
  const lines = text.split(/\r?\n/).filter((l) => l.length > 0);
  const headers = splitCsvLine(lines.shift());
  return lines.map((line) => {
    const cells = splitCsvLine(line);
    const row = {};
    headers.forEach((h, i) => {
      row[h] = cells[i] !== undefined ? cells[i] : '';
    });
    return row;
  });
}

function splitCsvLine(line) {
  const out = [];
  let cur = '';
  let quoted = false;
  for (let i = 0; i < line.length; i++) {
    const c = line[i];
    if (quoted) {
      if (c === '"' && line[i + 1] === '"') {
        cur += '"';
        i++;
      } else if (c === '"') {
        quoted = false;
      } else {
        cur += c;
      }
    } else if (c === '"') {
      quoted = true;
    } else if (c === ',') {
      out.push(cur.trim());
      cur = '';
    } else {
      cur += c;
    }
  }
  out.push(cur.trim());
  return out;
}

function serializeCsv(headers, rows) {
  const escape = (v) => {
    const s = v === undefined || v === null ? '' : String(v);
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  };
  const body = rows.map((r) => headers.map((h) => escape(r[h])).join(','));
  return headers.join(',') + '\n' + body.join('\n') + '\n';
}

/**
 * Fetch and cache to disk. A normal run always goes to the network, so the
 * data really is refreshed; --offline replays the last run's responses.
 */
async function cachedFetch(name, fn) {
  const file = path.join(CACHE_DIR, name);
  if (OFFLINE) {
    if (!fs.existsSync(file)) throw new Error(`--offline given but ${name} is not cached`);
    log(`  using cached ${name}`);
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  }
  const data = await fn();
  fs.mkdirSync(CACHE_DIR, { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data));
  return data;
}

async function postOverpass(query) {
  for (let attempt = 1; attempt <= 3; attempt++) {
    const res = await fetch(OVERPASS, {
      method: 'POST',
      body: 'data=' + encodeURIComponent(query),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'User-Agent': USER_AGENT,
      },
    });
    if (res.ok) return res.json();
    log(`  Overpass returned ${res.status}, attempt ${attempt}/3`);
    await new Promise((r) => setTimeout(r, 15000 * attempt));
  }
  throw new Error('Overpass API did not answer after 3 attempts');
}

// ---------------------------------------------------------------------------
// distance / geometry
// ---------------------------------------------------------------------------

function coordsOf(el) {
  if (el.lat !== undefined && el.lon !== undefined) return [el.lat, el.lon];
  if (el.center) return [el.center.lat, el.center.lon];
  return null;
}

/** Metres between two WGS84 points (equirectangular; fine at these distances). */
function distance(a, b) {
  const R = 6371000;
  const dLat = ((b[0] - a[0]) * Math.PI) / 180;
  const dLon = (((b[1] - a[1]) * Math.PI) / 180) * Math.cos(((a[0] + b[0]) / 2 * Math.PI) / 180);
  return Math.sqrt(dLat * dLat + dLon * dLon) * R;
}

// ---------------------------------------------------------------------------
// opening hours
// ---------------------------------------------------------------------------

const DAY_ALIASES = {
  mo: 0, tu: 1, we: 2, th: 3, fr: 4, sa: 5, su: 6,
};

/**
 * Translate an OSM `opening_hours` value into the 7 open/close pairs this CSV
 * uses. Only the plain forms are handled ("Mo-Fr 09:00-17:00; Sa 10:00-12:00").
 * Anything with conditions, holidays or multiple spans per day is rejected
 * rather than guessed at -- a wrong opening time is worse than a blank one.
 */
function parseOpeningHours(value) {
  if (!value) return null;
  let v = value.trim();
  if (/24\/7/i.test(v)) {
    return DAYS.map(() => ['00:00', '23:59']);
  }
  // Reject grammar we would only be guessing at.
  if (/easter|"|\|\||week\d|\[/i.test(v)) return null;

  const result = DAYS.map(() => null);
  let matched = false;

  // Rules are normally semicolon-separated, but a comma is sometimes used too
  // ("Mo-Fr 07:00-14:15, Sa 08:00-15:15"). A comma only ends a rule when a time
  // precedes it; otherwise it is separating days ("Mo-Su,PH 06:30-22:30").
  for (const rule of v.split(/;|(?<=\d),(?=\s*[A-Za-z]{2})/)) {
    const r = rule.trim().replace(/^,+|,+$/g, '').trim();
    if (!r || /^off$/i.test(r)) continue;

    const m = r.match(
      /^((?:[A-Za-z]{2}(?:\s*-\s*[A-Za-z]{2})?)(?:\s*,\s*[A-Za-z]{2}(?:\s*-\s*[A-Za-z]{2})?)*)\s+(.+)$/
    );
    if (!m) return null;

    const dayPart = m[1];
    const timePart = m[2].trim();
    const days = expandDays(dayPart);
    if (!days) return null;
    // The rule only covered public or school holidays, which have no column.
    if (days.length === 0) continue;

    if (/^off$/i.test(timePart)) {
      for (const d of days) result[d] = null;
      matched = true;
      continue;
    }

    // A counter may shut for lunch ("09:15-12:45,13:15-16:30"), but the CSV has
    // only one pair per day: report the outer envelope, first open to last close.
    const spans = timePart.split(',').map((s) => s.trim().match(/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/));
    if (spans.some((s) => !s)) return null;
    const open = spans[0][1].padStart(5, '0');
    const close = spans[spans.length - 1][2].padStart(5, '0');

    for (const d of days) {
      result[d] = [open, close];
      matched = true;
    }
  }
  return matched ? result : null;
}

function expandDays(spec) {
  const days = [];
  for (const part of spec.split(',')) {
    // Public and school holidays are not days of the week here: ignore them.
    if (/^(PH|SH)$/i.test(part.trim())) continue;
    const range = part.trim().match(/^([A-Za-z]{2})\s*-\s*([A-Za-z]{2})$/);
    if (range) {
      const a = DAY_ALIASES[range[1].toLowerCase()];
      const b = DAY_ALIASES[range[2].toLowerCase()];
      if (a === undefined || b === undefined) return null;
      for (let i = a; ; i = (i + 1) % 7) {
        days.push(i);
        if (i === b) break;
      }
    } else {
      const d = DAY_ALIASES[part.trim().toLowerCase()];
      if (d === undefined) return null;
      days.push(d);
    }
  }
  return days;
}

// ---------------------------------------------------------------------------
// sources
// ---------------------------------------------------------------------------

/** Belgian station nodes/ways carrying a uic_ref, our join key. */
async function fetchOsmStations() {
  return cachedFetch('osm-stations.json', () =>
    postOverpass(`
      [out:json][timeout:280];
      area["ISO3166-1"="BE"][admin_level=2]->.be;
      nwr["railway"~"^(station|halt)$"]["uic_ref"](area.be);
      out tags center;
    `)
  );
}

/**
 * Facility objects near those stations. Radii differ per feature: a lift must
 * be in the station, a taxi rank or bus stop is normally just outside it.
 */
async function fetchOsmFacilities() {
  return cachedFetch('osm-facilities.json', () =>
    postOverpass(`
      [out:json][timeout:280];
      area["ISO3166-1"="BE"][admin_level=2]->.be;
      nwr["railway"~"^(station|halt)$"]["uic_ref"](area.be)->.st;
      (
        nwr(around.st:150)["amenity"="vending_machine"]["vending"~"public_transport_tickets"];
        nwr(around.st:150)["shop"="ticket"];
        nwr(around.st:150)["amenity"="ticket_office"];
        nwr(around.st:100)["amenity"="luggage_locker"];
        nwr(around.st:250)["amenity"="parking"];
        nwr(around.st:250)["amenity"="taxi"];
        nwr(around.st:200)["amenity"="bicycle_parking"];
        nwr(around.st:200)["amenity"="bicycle_rental"];
        nwr(around.st:150)["highway"="elevator"];
        nwr(around.st:150)["highway"="steps"]["conveying"];
        nwr(around.st:250)["highway"="bus_stop"];
        nwr(around.st:250)["public_transport"="platform"]["bus"="yes"];
        nwr(around.st:250)["railway"="tram_stop"];
        nwr(around.st:300)["railway"="subway_entrance"];
        nwr(around.st:300)["station"="subway"];
      );
      out tags center;
    `)
  );
}

/** Page through one of Infrabel's Opendatasoft datasets. */
async function fetchInfrabelDataset(cacheName, endpoint) {
  return cachedFetch(cacheName, async () => {
    const all = [];
    for (let offset = 0; ; offset += 100) {
      const res = await fetch(`${endpoint}?limit=100&offset=${offset}`, {
        headers: { 'User-Agent': USER_AGENT },
      });
      if (!res.ok) throw new Error(`Infrabel API returned ${res.status}`);
      const page = await res.json();
      all.push(...page.results);
      if (all.length >= page.total_count || page.results.length === 0) break;
    }
    return all;
  });
}

/** Infrabel platform heights -> platform_height_cm, keyed on ptcarid. */
async function fetchInfrabelPlatforms() {
  return fetchInfrabelDataset('infrabel-platforms.json', INFRABEL_PLATFORMS);
}

/**
 * The register of operational points, used to confirm that a station's TAF/TAP
 * code really names that station before we trust it as a join key.
 */
async function fetchInfrabelOperationalPoints() {
  return fetchInfrabelDataset('infrabel-operational-points.json', INFRABEL_OPERATIONAL_POINTS);
}

async function fetchBlueBike() {
  return cachedFetch('blue-bike.json', async () => {
    const res = await fetch(BLUEBIKE, { headers: { 'User-Agent': USER_AGENT } });
    if (!res.ok) throw new Error(`Blue-bike API returned ${res.status}`);
    return res.json();
  });
}

// ---------------------------------------------------------------------------
// matching
// ---------------------------------------------------------------------------

/** Normalise a station name so Blue-bike's spellings line up with ours. */
function normalizeName(name) {
  return name
    .toLowerCase()
    .replace(/\u0153/g, 'oe')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\b(station|gare|bahnhof)\b/g, '')
    .replace(/[^a-z0-9]/g, '');
}

/**
 * Every way a station is written, for comparing against Infrabel's spellings.
 * Includes each half of a combined bilingual name like "Haren-Sud/Haren-Zuid".
 *
 * Parenthesised qualifiers are kept both ways: dropping them lets "Beveren
 * (Waas)" match "Beveren", while keeping them lets "Sint-Gillis(Dendermonde)"
 * match "Sint-Gillis-Dendermonde". Either form counts as a match.
 */
function namesOf(record, keys) {
  const names = new Set();
  for (const key of keys) {
    const value = record[key];
    if (!value) continue;
    for (const part of [value, ...value.split('/')]) {
      names.add(normalizeName(part));
      names.add(normalizeName(part.replace(/\(.*?\)/g, '')));
    }
  }
  names.delete('');
  return names;
}

function bool(v) {
  return v ? '1' : '0';
}

/** Does this OSM object belong to the national rail operator? */
function isNmbsOperated(tags) {
  const who = [tags.operator, tags.brand, tags.name, tags['operator:short']]
    .filter(Boolean)
    .join(' ');
  // Other operators sell from inside the same halls: STIB/MIVB (BOOTIK, KIOSK),
  // De Lijn (Lijnwinkel) and TEC. Their hours are for urban transport tickets.
  if (/stib|mivb|bootik|kiosk|lijnwinkel|de lijn|\bTEC\b|relay|press/i.test(who)) return false;
  return /nmbs|sncb|travel\s*centre|travel\s*store|travelstore/i.test(who);
}

/**
 * Record an OSM-derived boolean, honouring how well OSM maps that feature.
 * A hit is always a confident 1. A miss is only written as 0 when absence is
 * trustworthy; otherwise the previous value stands and the miss is counted as
 * unverified so the coverage report can say so.
 */
function setOsmBoolean(row, field, found, prev, unverified) {
  if (found) {
    row[field] = '1';
    return;
  }
  if (OSM_ABSENCE_IS_RELIABLE.has(field)) {
    row[field] = '0';
    return;
  }
  row[field] = prev[field] !== undefined ? prev[field] : '';
  unverified[field] = (unverified[field] || 0) + 1;
}

// ---------------------------------------------------------------------------
// main
// ---------------------------------------------------------------------------

async function main() {
  const stations = parseCsv(fs.readFileSync(STATIONS_CSV, 'utf8'));
  const previous = new Map(
    parseCsv(fs.readFileSync(FACILITIES_CSV, 'utf8')).map((r) => [r.URI, r])
  );

  log('Fetching sources...');
  const [osmStations, osmFacilities, platforms, operationalPoints, blueBike] = [
    await fetchOsmStations(),
    await fetchOsmFacilities(),
    await fetchInfrabelPlatforms(),
    await fetchInfrabelOperationalPoints(),
    await fetchBlueBike(),
  ];
  log(
    `  OSM: ${osmStations.elements.length} stations, ` +
      `${osmFacilities.elements.length} facility objects`
  );
  log(
    `  Infrabel: ${platforms.length} platform records, ` +
      `${operationalPoints.length} operational points`
  );
  log(`  Blue-bike: ${blueBike.length} locations`);

  // --- index the sources -----------------------------------------------------

  // uic_ref -> OSM station element
  const osmByUic = new Map();
  for (const el of osmStations.elements) {
    const uic = (el.tags.uic_ref || '').trim();
    if (uic) osmByUic.set(uic, el);
  }

  // TAF/TAP code -> every name Infrabel knows that operational point by.
  const INFRABEL_NAME_KEYS = [
    'longnamedutch', 'longnamefrench',
    'commerciallongnamedutch', 'commerciallongnamefrench',
    'commercialmiddlenamedutch', 'commercialmiddlenamefrench',
    'commercialshortnamedutch', 'commercialshortnamefrench',
  ];
  const namesByTafTap = new Map();
  for (const point of operationalPoints) {
    if (point.taftapcode) {
      namesByTafTap.set(point.taftapcode, namesOf(point, INFRABEL_NAME_KEYS));
    }
  }

  // ptcarid -> the nominal platform heights present, in cm above the rail.
  // Most stopping points are uniform, but 48 of them mix two or three heights,
  // so every distinct value is kept rather than reduced to a single number.
  const heightsByPtcar = new Map();
  for (const p of platforms) {
    const cm = PLATFORM_HEIGHT_CM[p.height];
    if (!cm) continue;
    const id = String(p.ptcarid);
    if (!heightsByPtcar.has(id)) heightsByPtcar.set(id, new Set());
    heightsByPtcar.get(id).add(cm);
  }

  // Blue-bike locations, matched by name and by proximity.
  const blueBikeByName = new Map();
  for (const loc of blueBike) {
    blueBikeByName.set(normalizeName(loc.name), loc);
  }
  const blueBikePoints = blueBike
    .map((l) => ({ loc: l, at: [parseFloat(l.latitude), parseFloat(l.longitude)] }))
    .filter((b) => !isNaN(b.at[0]) && !isNaN(b.at[1]));

  // Facility objects, kept with their coordinates for the spatial join.
  const facilityPoints = [];
  for (const el of osmFacilities.elements) {
    const at = coordsOf(el);
    if (at) facilityPoints.push({ tags: el.tags || {}, at });
  }

  // --- build each row --------------------------------------------------------

  const rows = [];
  const stats = { osmMatched: 0, infrabelMatched: 0, blueBikeMatched: 0, carried: 0 };
  // field -> how many stations OSM had nothing to say about, so the previous
  // value was kept rather than a 0 invented.
  const unverified = {};
  // stations.csv rows whose TAF/TAP code names a different operational point.
  const mismatchedTafTap = [];
  let openingHoursFound = 0;

  for (const station of stations) {
    const uri = station.URI;
    const uic7 = uri.slice(-7);
    const prev = previous.get(uri) || {};
    const row = { URI: uri, name: station.name };

    // Fields we cannot source any more: carry the previous value over.
    for (const field of ['street', 'zip', 'city', 'wheelchair_available', 'ramp', 'audio_induction_loop']) {
      row[field] = prev[field] || '';
    }

    const osm = osmByUic.get(uic7);
    const isBelgian = station['country-code'] === 'be';

    if (!osm) {
      // No OSM counterpart (mostly foreign stations): keep whatever we had.
      for (const h of HEADERS) if (row[h] === undefined) row[h] = prev[h] || '';
      if (Object.keys(prev).length) stats.carried++;
      rows.push(row);
      continue;
    }
    stats.osmMatched++;

    const at = coordsOf(osm);
    const near = (radius, predicate) =>
      facilityPoints.filter((f) => predicate(f.tags) && distance(at, f.at) <= radius);

    // Ticket vending machines and staffed counters.
    const tvm = near(150, (t) => t.amenity === 'vending_machine' && /public_transport_tickets/.test(t.vending || ''));
    // Only NMBS's own counters. Stations also host STIB/MIVB BOOTIKs and
    // KIOSKs, De Lijn Lijnwinkels and TEC points, whose opening hours say
    // nothing about when rail tickets can be bought.
    const ticketOffices = near(150, (t) =>
      (t.shop === 'ticket' || t.amenity === 'ticket_office') && isNmbsOperated(t)
    );
    setOsmBoolean(row, 'ticket_vending_machine', tvm.length, prev, unverified);

    setOsmBoolean(row, 'luggage_lockers', near(100, (t) => t.amenity === 'luggage_locker').length, prev, unverified);

    // "Free" parking: a car park explicitly tagged as not charging. A car park
    // with no fee tag at all says nothing either way, so it does not count.
    const parkings = near(250, (t) => t.amenity === 'parking');
    setOsmBoolean(row, 'free_parking', parkings.some((p) => p.tags.fee === 'no'), prev, unverified);

    setOsmBoolean(row, 'taxi', near(250, (t) => t.amenity === 'taxi').length, prev, unverified);

    const bikeParkings = near(200, (t) => t.amenity === 'bicycle_parking');
    setOsmBoolean(row, 'bicycle_spots', bikeParkings.length, prev, unverified);

    // Blue-bike: the API is authoritative, OSM is the fallback.
    let hasBlueBike = blueBikeByName.has(normalizeName(station.name));
    if (!hasBlueBike) {
      hasBlueBike = blueBikePoints.some((b) => distance(at, b.at) <= 300);
    }
    if (!hasBlueBike) {
      hasBlueBike = near(200, (t) => t.amenity === 'bicycle_rental' && /blue.?bike/i.test((t.brand || t.operator || ''))).length > 0;
    }
    if (hasBlueBike) stats.blueBikeMatched++;
    // Blue-bike publishes every location it operates, so a miss really is a no.
    row['blue-bike'] = bool(hasBlueBike);

    setOsmBoolean(row, 'bus', near(250, (t) => t.highway === 'bus_stop' || (t.public_transport === 'platform' && t.bus === 'yes')).length, prev, unverified);
    setOsmBoolean(row, 'tram', near(250, (t) => t.railway === 'tram_stop').length, prev, unverified);
    setOsmBoolean(row, 'metro', near(300, (t) => t.railway === 'subway_entrance' || t.station === 'subway').length, prev, unverified);

    // Reserved parking spaces, summed over the car parks around the station.
    const disabled = parkings.reduce((sum, p) => {
      const n = parseInt(p.tags['capacity:disabled'], 10);
      return sum + (isNaN(n) ? 0 : n);
    }, 0);
    row.disabled_parking_spots = disabled > 0 ? String(disabled) : (prev.disabled_parking_spots || '0');

    // Platform height, from Infrabel, keyed on the ptcarid inside the TAF/TAP
    // code. Left empty when Infrabel has nothing: the previous column was a
    // 0/1 flag, so carrying its value over here would be meaningless.
    //
    // A handful of stations.csv rows carry a TAF/TAP code belonging to another
    // operational point entirely, which would silently give a station some
    // other station's platforms. Check the code names this station before
    // trusting it, and report the ones that do not so they can be corrected.
    row.platform_height_cm = '';
    const tafTap = station['taf-tap-code'] || '';
    const ptcar = tafTap.replace(/^BE0*/, '');
    if (tafTap && heightsByPtcar.has(ptcar)) {
      const infrabelNames = namesByTafTap.get(tafTap);
      const ours = namesOf(station, ['name', 'alternative-nl', 'alternative-fr', 'alternative-de', 'alternative-en']);
      if (infrabelNames && ![...ours].some((n) => infrabelNames.has(n))) {
        mismatchedTafTap.push({
          name: station.name,
          code: tafTap,
          infrabel: [...infrabelNames][0] || '?',
        });
      } else {
        row.platform_height_cm = [...heightsByPtcar.get(ptcar)].sort((a, b) => a - b).join(';');
        stats.infrabelMatched++;
      }
    }

    // Escalators. OSM records that a flight of steps is conveying, and in which
    // direction it runs, but the direction is relative to the way's drawing
    // order, so only presence is trustworthy: report it for both columns.
    const escalators = near(150, (t) => t.highway === 'steps' && t.conveying && t.conveying !== 'no');
    setOsmBoolean(row, 'escalator_up', escalators.length, prev, unverified);
    setOsmBoolean(row, 'escalator_down', escalators.length, prev, unverified);

    setOsmBoolean(row, 'elevator_platform', near(150, (t) => t.highway === 'elevator').length, prev, unverified);

    // Ticket counter opening hours, where a ticket office nearby publishes them.
    let hours = null;
    for (const office of ticketOffices) {
      hours = parseOpeningHours(office.tags.opening_hours);
      if (hours) break;
    }
    if (hours) {
      openingHoursFound++;
      DAYS.forEach((day, i) => {
        row[`sales_open_${day}`] = hours[i] ? hours[i][0] : '';
        row[`sales_close_${day}`] = hours[i] ? hours[i][1] : '';
      });
    } else {
      DAYS.forEach((day) => {
        row[`sales_open_${day}`] = prev[`sales_open_${day}`] || '';
        row[`sales_close_${day}`] = prev[`sales_close_${day}`] || '';
      });
    }

    for (const h of HEADERS) if (row[h] === undefined) row[h] = '';
    rows.push(row);
  }

  fs.writeFileSync(FACILITIES_CSV, serializeCsv(HEADERS, rows));
  writeCoverageReport(rows, stats, openingHoursFound, unverified);

  log('');
  log(`Wrote ${rows.length} rows to facilities.csv`);
  log(`  matched to OSM:       ${stats.osmMatched}`);
  log(`  platform height:      ${stats.infrabelMatched}`);
  log(`  blue-bike:            ${stats.blueBikeMatched}`);
  log(`  opening hours found:  ${openingHoursFound}`);
  log(`  carried over intact:  ${stats.carried}`);

  if (mismatchedTafTap.length) {
    log('');
    log(
      `${mismatchedTafTap.length} station(s) have a taf-tap-code that Infrabel gives to ` +
        'another operational point, so their platform height was left empty:'
    );
    for (const m of mismatchedTafTap) {
      log(`  ${m.name} has ${m.code}, which Infrabel calls ${m.infrabel}`);
    }
    log('These codes in stations.csv need correcting.');
  }

  log('');
  log('See facilities_coverage.md for per-field provenance.');
}

function writeCoverageReport(rows, stats, openingHoursFound, unverified) {
  const filled = (field) => rows.filter((r) => r[field] !== '' && r[field] !== undefined).length;
  const positive = (field) => rows.filter((r) => r[field] === '1').length;

  const lines = [];
  lines.push('# facilities.csv coverage');
  lines.push('');
  lines.push('Generated by `bin/facilities_extractor.js`. Do not edit by hand.');
  lines.push('');
  lines.push(`Rows: ${rows.length}. Matched to OpenStreetMap: ${stats.osmMatched}.`);
  lines.push('');
  lines.push(
    '"Carried over" counts stations where the source had nothing to say and the ' +
      'previous value was kept. A high number means that column is largely still ' +
      'the June 2023 scrape, not fresh data.'
  );
  lines.push('');
  lines.push('| field | source | filled | of which "1" | carried over |');
  lines.push('| --- | --- | ---: | ---: | ---: |');

  // These hold text, a count or a height rather than a flag, so a
  // "how many are 1" tally would be meaningless for them.
  const NOT_BOOLEAN = new Set(['street', 'zip', 'city', 'disabled_parking_spots', 'platform_height_cm']);

  for (const field of HEADERS.slice(2)) {
    if (field.startsWith('sales_')) continue;
    const src = FIELD_SOURCES[field];
    const carried = unverified[field] ? String(unverified[field]) : '—';
    const ones = NOT_BOOLEAN.has(field) ? '—' : String(positive(field));
    lines.push(
      `| \`${field}\` | ${src || '**none left**'} | ${filled(field)} | ${ones} | ${carried} |`
    );
  }
  lines.push(
    `| \`sales_open_*\` / \`sales_close_*\` | osm (NMBS ticket offices) | ${openingHoursFound} | — | — |`
  );
  lines.push('');
  lines.push('## Fields with no remaining source');
  lines.push('');
  lines.push(
    'These were only ever published by the NMBS website that is now behind Cloudflare. ' +
      'The extractor carries their previous values over untouched, so the numbers above ' +
      'still date from the last successful scrape (June 2023) and should be treated as stale:'
  );
  lines.push('');
  for (const [field, src] of Object.entries(FIELD_SOURCES)) {
    if (src === null) lines.push(`- \`${field}\``);
  }
  lines.push('');
  lines.push('## Licensing');
  lines.push('');
  lines.push(
    'This repository is CC0, but two of the sources above are not. OpenStreetMap is ' +
      'ODbL (share-alike), and the Blue-bike feed is registered as non-commercial on ' +
      'transportdata.be. Redistributing their data as CC0 is therefore not clearly ' +
      'permitted, and this needs a maintainer decision before the next release.'
  );
  lines.push('');

  fs.writeFileSync(COVERAGE_MD, lines.join('\n'));
}

if (require.main === module) {
  main().catch((err) => {
    log('Failed: ' + err.message);
    process.exit(1);
  });
}

// Exported so the parsing rules can be tested without touching the network.
module.exports = { parseOpeningHours, isNmbsOperated, parseCsv, serializeCsv, distance };
