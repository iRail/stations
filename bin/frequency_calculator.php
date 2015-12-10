<?php

/**
 * Calculate the importance (amount of stopping trains) for a station.
 * Stations with a higher weight are more important.
 *
 * @Author Bertware
 */

// Constants
const GTFS_ZIP = 'http://gtfs.irail.be/nmbs/nmbs-latest.zip';
const TMP_UNZIP_PATH = 'nmbs-latest-gtfs';
const TMP_ZIPFILE = 'nmbs-latest-gtfs.zip';
const GTFS_STOP_TIMES = 'stop_times.txt';
const GTFS_TRIPS = 'trips.txt';
const GTFS_CAL_DATES = 'calendar_dates.txt';
const STATIONS_CSV = '../stations.csv';

/*
 * Step 1 : Get the latest information from GTFS.
 * This information can be found at http://gtfs.irail.be/nmbs/nmbs-latest.zip
 */

echo 'Gathering resources...'.PHP_EOL;

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
rename(TMP_UNZIP_PATH.'/'.GTFS_STOP_TIMES, GTFS_STOP_TIMES);
rename(TMP_UNZIP_PATH.'/'.GTFS_TRIPS, GTFS_TRIPS);
rename(TMP_UNZIP_PATH.'/'.GTFS_CAL_DATES, GTFS_CAL_DATES);

echo 'Cleaning up resources...'.PHP_EOL;
// Remove temporary data.
$tmpfiles = scandir(TMP_UNZIP_PATH);
foreach ($tmpfiles as $file) {
    if ($file != '.' && $file != '..') {
        // Remove all extracted files from the zip file.
    unlink(TMP_UNZIP_PATH.'/'.$file);
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

echo 'Creating service id frequency table...'.PHP_EOL;

$handle = fopen(GTFS_CAL_DATES, 'r');
if (!$handle) {
    die(GTFS_CAL_DATES.' could not be opened!');
}

// skip the first line (csv header)
fgets($handle);

// Create the frequency table.
$service_freq = [];
// the dates we've handled.
$handled_dates = [];
while (($line = fgets($handle)) !== false) {
    /*
   * File format:
   * service_id,date,exception_type
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

echo 'Creating trip id frequency table...'.PHP_EOL;
$handle = fopen(GTFS_TRIPS, 'r');
if (!$handle) {
    die(GTFS_TRIPS.' could not be opened!');
}

// skip the first line (csv header)
fgets($handle);

// Create the frequency table.
$trips_freq = [];

while (($line = fgets($handle)) !== false) {
    /*
 * File format:
 * route_id,service_id,trip_id
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

echo 'Creating frequency table...'.PHP_EOL;

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
 * trip_id,arrival_time,departure_time,stop_id,stop_sequence
 * IC10611,05:36:00,05:36:00,stops:008841673:0,1
 */
  $parts = explode(',', $line);
  // Get stop ID.
  $id = $parts[3];
  // Remove platform.
  $id = explode(':', $id)[1];

    $trip_id = $parts[0];
  // The amount of time this trip is made.
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

/*
 * Step 2: patch the csv file
 * For this step, we need 3 actions:
 * - Get the maximum frequency so we can calculate a relative frequency later on
 * - Read the existing stops.csv file, and append every line with the stop frequency (0...1)
 * - Write the new file to disk
 */

// Get the amount of days that were handled. We need this to calculate the average later on.
$handled_days_count = count($handled_dates);

// Open the CSV file that needs a patch.
$handle = fopen(STATIONS_CSV, 'r');
if (!$handle) {
    die('stops.csv file could not be opened!');
}

// The new CSV file will be compiled in memory, in the $result variable.
echo 'Compiling new CSV file...'.PHP_EOL;

// Update the first line (csv header)
$result = trim(fgets($handle)).',avg_stop_times'.PHP_EOL;

// Go through all files.
while (($line = fgets($handle)) !== false) {
    // Line format:
  // http://irail.be/stations/NMBS/008821006,Antwerpen-Centraal,Anvers-Central,,,Antwerp-Central,be,4.421101,51.2172
  $line = trim($line);
  // Get the Id.
  $id = explode(',', $line)[0];
    $id = explode('/', $id)[5];
    if (!isset($freq[$id])) {
        // If the Id is not in here, there are no stopping trains. The frequency is zero.
    $freq[$id] = 0;
    }
  // Copy the current line and append it with the average stop times count.
  $result .= $line.','.$freq[$id] / $handled_days_count.PHP_EOL;
}
// Close this handle. Important!
fclose($handle);

echo 'Saving...'.PHP_EOL;
// Create a backup, just in case.
copy(STATIONS_CSV, STATIONS_CSV.'.bak');
echo 'A backup has been created at '.STATIONS_CSV.'.bak'.PHP_EOL;
// Write everything to a new file
file_put_contents(STATIONS_CSV, $result);

echo 'Saved to '.STATIONS_CSV.'! Don\'t forget to run build.js now!'.PHP_EOL;
