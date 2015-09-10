<?php

/**
 * Calculate the importance (amount of stopping trains) for a station.
 * Stations with a higher weight are more important.
 * @Author Bertware
 */

// Constants
const GTFS_ZIP = 'http://gtfs.irail.be/nmbs/nmbs-latest.zip';
const TMP_UNZIP_PATH = 'nmbs-latest-gtfs';
const TMP_ZIPFILE = 'nmbs-latest-gtfs.zip';
const GTFS_STOP_TIMES = 'stop_times.txt';
const STOPS_CSV = '../stops.csv';

/*
 * Step 1 : Get the latest information from GTFS.
 * This information can be found at http://gtfs.irail.be/nmbs/nmbs-latest.zip
 */

echo('Gathering resources...' . PHP_EOL);


// Download zip file with GTFS data.
file_put_contents(TMP_ZIPFILE, file_get_contents(GTFS_ZIP));

// Load the zip file.
$zip = new ZipArchive;
if ($zip->open(TMP_ZIPFILE) != "true") {
  die("Could not extract downloaded GTFS data");
}

// Extract the zip file and remove it.
$zip->extractTo(TMP_UNZIP_PATH);
$zip->close();
unlink(TMP_ZIPFILE);

// Get the file we need.
rename(TMP_UNZIP_PATH .'/'. GTFS_STOP_TIMES, GTFS_STOP_TIMES);

echo('Cleaning up resources...' . PHP_EOL);
// Remove temporary data.
$tmpfiles = scandir(TMP_UNZIP_PATH);
foreach ($tmpfiles as $file) {
  if ($file != "." && $file != "..") {
    // Remove all extracted files from the zip file.
    unlink(TMP_UNZIP_PATH."/".$file);
  }
}
reset($tmpfiles);
// Remove the empty folder.
rmdir(TMP_UNZIP_PATH);


/*
 * Step 2: patch the csv file
 * For this step, we need 3 actions:
 * - Read the stop times, and create a frequency table for every stop id
 * - Read the existing stops.csv file, and append every line with the stop frequency (0...1)
 * - Write the new file to disk
 */

echo('Creating frequency table...' . PHP_EOL);

$handle = fopen(GTFS_STOP_TIMES, 'r');
if (!$handle) {
  die('stop times file could not be opened!');
}

// skip the first line (csv header)
fgets($handle);

// Create the frequency table.
$freq = [];

while (($line = fgets($handle)) !== FALSE) {
  /*
   * File format:
   * trip_id,arrival_time,departure_time,stop_id,stop_sequence
   * IC10611,05:36:00,05:36:00,stops:008841673:0,1
   */

  // Get stop ID.
  $id = explode(',', $line)[3];
  // Remove platform.
  $id = explode(':', $id)[1];
  // Increase frequency.
  if (isset($freq[$id])){
    $freq[$id]++;
  } else {
    // Set initial value if key isn't added yet.
    $freq[$id] = 1;
  }

}

// Get the highest frequency.
// Later on, this frequency will be equal to 1, all other frequencies will be scaled.
$max = 0;
foreach (array_keys($freq) as $id) {
  if ($freq[$id] > $max) {
    $max = $freq[$id];
  }
}

// Open the CSV file that needs a patch. 
$handle = fopen(STOPS_CSV, 'r');
if (!$handle) {
  die('stops.csv file could not be opened!');
}

// The new CSV file will be compiled in memory, in the $result variable.
echo('Compiling new CSV file...' . PHP_EOL);

// Update the first line (csv header)
$result = trim(fgets($handle)) . ',vehicle_frequency' . PHP_EOL;

// Go through all files. 
while (($line = fgets($handle)) !== FALSE) {
  // Line format:
  // http://irail.be/stations/NMBS/008811007#1,http://irail.be/stations/NMBS/008811007,4.378636,50.878513,Schaarbeek/Schaerbeek,1
  $line = trim($line);
  // Get the Id.
  $id = explode(",", $line)[1];
  $id = explode("/", $id)[5];

  // Copy the current line and append it with the relative frequency.
  $result .= $line . "," . $freq[$id] / $max . PHP_EOL;
}
echo('Saving...' . PHP_EOL);
// Create a backup, just in case.
copy(STOPS_CSV, STOPS_CSV . '.bak');
echo('A backup has been created at ' . STOPS_CSV . '.bak' . PHP_EOL);
// Write everything to a new file
file_put_contents(STOPS_CSV, $result);

echo('Saved to ' . STOPS_CSV . '! Don\'t forget to run build.js now!' . PHP_EOL);