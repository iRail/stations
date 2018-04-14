<?php

/**
 * Calculate the importance (amount of stopping trains) for a station. This is done using the GTFS data provided by the NMBS.
 * Stations with a higher weight are more important.
 * Extract the minimum transfer time, in seconds, and according to the NMBS, from the NMBS GTFS data.
 * Get access to the GTFS feed by filling out their form here: http://www.belgianrail.be/nl/klantendienst/infodiensten-reistools/public-data.aspx
 *
 * Pass the path to the original CSV file you want to patch, the target destination of the patched CSV file and the URL to your GTFS file when running this script.
 *
 * Example usage:
 * php gtfs_data_extractor.php stations.csv updated.csv example.com/gtfs.zip
 *
 * @Author Bertware
 *
 * Free to use, adapt, modify, redistribute however you want at your own responsibility.
 *
 * Requirements:
 *     php-zip
 *     fopen_url_allowed = true
 *
 * Remarks:
 *     All station IDs in this script will be stored in data structures as a 9 digit HAFAS ID. UIC IDs and URIs will be reduced to the 9 digit HAFAS ID for matching.
 */

// Constants
define("STATIONS_CSV_PATH", $argv[1]);
define("STATIONS_CSV_UPDATE_PATH", $argv[2]);
define("GTFS_URL", $argv[3]);

define("HEADER_TEXT", "avg_stop_times,minimum_transfer_time");

const GTFS_ZIP = GTFS_URL;
const TMP_UNZIP_PATH = 'nmbs-latest-gtfs';
const TMP_ZIPFILE = 'nmbs-latest-gtfs.zip';
const GTFS_STOP_TIMES = 'stop_times.txt';
const GTFS_TRIPS = 'trips.txt';
const GTFS_CAL_DATES = 'calendar_dates.txt';
const GTFS_TRANSFER_TIMES = 'transfers.txt';

/*
 * Step 1 : Get the latest information from GTFS.
 * This information can be found at http://gtfs.irail.be/nmbs/nmbs-latest.zip
 */

echo 'Gathering resources...' . PHP_EOL;

// Download zip file with GTFS data.
file_put_contents(TMP_ZIPFILE, file_get_contents(GTFS_ZIP));

// Load the zip file.
$zip = new ZipArchive();
if ($zip->open(TMP_ZIPFILE) != 'true') {
    die('Could not extract downloaded GTFS data');
}

// Extract the zip file and remove it.
$zip->extractTo(TMP_UNZIP_PATH);
$zip->close();
unlink(TMP_ZIPFILE);

// Get the files we need.
rename(TMP_UNZIP_PATH . '/' . GTFS_STOP_TIMES, GTFS_STOP_TIMES);
rename(TMP_UNZIP_PATH . '/' . GTFS_TRIPS, GTFS_TRIPS);
rename(TMP_UNZIP_PATH . '/' . GTFS_CAL_DATES, GTFS_CAL_DATES);
rename(TMP_UNZIP_PATH . '/' . GTFS_TRANSFER_TIMES, GTFS_TRANSFER_TIMES);

echo 'Cleaning up resources...' . PHP_EOL;
// Remove temporary data.
$tmpfiles = scandir(TMP_UNZIP_PATH);
foreach ($tmpfiles as $file) {
    if ($file != '.' && $file != '..') {
        // Remove all extracted files from the zip file.
        unlink(TMP_UNZIP_PATH . '/' . $file);
    }
}
reset($tmpfiles);
// Remove the empty folder.
rmdir(TMP_UNZIP_PATH);

/*
 * Step 2: Gather prerequisite data
 * We need assosiative arrays to link different files into the average stops per station
 * - Get a frequency table for every service id, by counting on how much different dates it's ran.
 * - Get the trip id for every service id from trips, creating a frequency table for how many times a trip is ran (instead of service).
 * - Create a station stop frequency table based on stop_times, converting the trip_id frequency table to a station stop frequency table.
 */

echo 'Creating service id frequency table...' . PHP_EOL;

$handle = fopen(GTFS_CAL_DATES, 'r');
if (!$handle) {
    die(GTFS_CAL_DATES . ' could not be opened!');
}

// skip the first line (csv header)
fgets($handle);

// Create the frequency table.
$service_freq = [];
// the dates we've handled.
$handled_dates = [];
while (($line = fgets($handle)) !== false) {
    /*
     * service_id,date,exception_type
     * 000001,20180610,1
     */
    $parts = explode(',', $line);
    // Get service ID.
    $id = $parts[0];
    $date = $parts[1];
    // Increase frequency.
    if (isset($service_freq[$id])) {
        $service_freq[$id]++;
    } else {
        // Set initial value if key isn't added yet.
        $service_freq[$id] = 1;
    }
    $handled_dates[$date] = 1;
}
// Close this handle. Important!
fclose($handle);

// We don't need this file anymore. Cleanup.
unlink(GTFS_CAL_DATES);

echo 'Creating trip id frequency table...' . PHP_EOL;
$handle = fopen(GTFS_TRIPS, 'r');
if (!$handle) {
    die(GTFS_TRIPS . ' could not be opened!');
}

// skip the first line (csv header)
fgets($handle);

// Create the frequency table.
$trips_freq = [];

while (($line = fgets($handle)) !== false) {
    /*
     * File format:
     * route_id,service_id,trip_id,trip_headsign,trip_short_name,direction_id,block_id,shape_id,trip_type
     * 1,000001,88____:046::8821402:8400526:3:745:20181208,Roosendaal (nl),10556,,1,,1
     */
    // Get service ID.
    $parts = explode(',', $line);
    $service_id = $parts[1];
    $trip_id = trim($parts[2]);

    // Set frequency, which is the same as the service frequency.
    $trips_freq[$trip_id] = $service_freq[$service_id];
}
// Close this handle. Important!
fclose($handle);

// We don't need this file anymore. Cleanup.
unlink(GTFS_TRIPS);

echo 'Creating frequency table...' . PHP_EOL;

$handle = fopen(GTFS_STOP_TIMES, 'r');
if (!$handle) {
    die('stop times file could not be opened!');
}

// skip the first line (csv header)
fgets($handle);

// Create the frequency table.
$freq = [];

while (($line = fgets($handle)) !== false) {
    /*
     * File format:
     * trip_id,arrival_time,departure_time,stop_id,stop_sequence,stop_headsign,pickup_type,drop_off_type,shape_dist_traveled
     * 88____:046::8821402:8400526:3:745:20181208,7:38:00,7:38:00,8821402,1,,0,1,
     */
    $parts = explode(',', $line);
    // Get stop ID.
    $id = "00" . $parts[3];

    $trip_id = $parts[0];
    // The number of times this trip is made.
    $trip_freq = $trips_freq[$trip_id];
    // Increase frequency.
    if (isset($freq[$id])) {
        $freq[$id] += $trip_freq;
    } else {
        // Set initial value if key isn't added yet.
        $freq[$id] = $trip_freq;
    }
}
// Close this handle. Important!
fclose($handle);

// We don't need this file anymore. Cleanup.
unlink(GTFS_STOP_TIMES);

$stationscsv = file_get_contents(STATIONS_CSV_PATH);
$transfers = file_get_contents(GTFS_TRANSFER_TIMES);

$transfersIdsMap = [];
$transfers = explode("\n", $transfers);
$transferValues = [];
foreach ($transfers as $line => $value) {
    if ($line == 0) {
        continue;
    }
    $fields = explode(",", $value);
    if (count($fields) < 4) {
        continue;
    }
    // This file only contains intra-stop transfer times, so we don't need to worry about validating anything
    // Every line only describes the transfer time between any 2 platforms in the same station
    // Station UIC ID to HAFAS
    $id = "00" . $fields[0];
    // Transfer value
    $transfer = $fields[3];
    if (strpos($id, "_") === false && strpos($id, "S") === false) {
        // Store value for station id
        $transferValues[$id] = $transfer;
    }
}
// We don't need this file anymore. Cleanup.
unlink(GTFS_TRANSFER_TIMES);

/*
 * Step 2: patch the csv file
 * For this step, we need 3 actions:
 * - Get the maximum frequency so we can calculate a relative frequency later on
 * - Read the existing stops.csv file, and append every line with
 *     - the average stops per day
 *     - the minimum transfer time
 * - Write the new file to disk
 */

// Get the number of days that were handled. We need this to calculate the average later on.
$handled_days_count = count($handled_dates);

// Open the CSV file that needs a patch.
$handle = fopen(STATIONS_CSV_PATH, 'r');
if (!$handle) {
    die(STATIONS_CSV_PATH . ' could not be opened!');
}

// The new CSV file will be compiled in memory, in the $result variable.
echo 'Compiling new CSV file...' . PHP_EOL;

// Update the first line (csv header)
$result = trim(fgets($handle)) . ',' . HEADER_TEXT . PHP_EOL;

// Go through all files.
while (($line = fgets($handle)) !== false) {
    // Line format:
    // http://irail.be/stations/NMBS/008821006,Antwerpen-Centraal,Anvers-Central,,,Antwerp-Central,be,4.421101,51.2172
    $line = trim($line);
    // Get the Id.
    $id = explode(',', $line)[0];
    $id = basename($id);

    if (!isset($freq[$id])) {
        // If the Id is not in here, there are no stopping trains. The frequency is zero.
        $frequency = 0;
    } else {
        $frequency = $freq[$id] / $handled_days_count;
    }

    if (array_key_exists($id, $transferValues)) {
        // Transfer time known
        $transferTime = $transferValues[$id];
    } else {
        // Transfer time not in transfers.txt, use 0 as placeholder
        $transferTime = 0;
    }

    // Copy the current line and append it with the average stop times count.
    $result .= $line . ',' . $frequency . ',' . $transferTime . PHP_EOL;

}
// Close this handle. Important!
fclose($handle);

echo 'Saving...' . PHP_EOL;
// Write everything to a new file
file_put_contents(STATIONS_CSV_UPDATE_PATH, $result);

echo 'Saved to ' . STATIONS_CSV_UPDATE_PATH . '! Don\'t forget to run build.js now!' . PHP_EOL;
