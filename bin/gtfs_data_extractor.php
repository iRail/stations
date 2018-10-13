<?php

/**
 * Update stations.csv and create a new stops.csv
 *
 * ! Important: stops.csv will be completely overwritten - only for stations.csv manual edits are allowed at this point.
 *
 * Calculate the importance (number of stopping trains) for a station. This is done using the GTFS data provided by the NMBS.
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
const GTFS_ZIP = 'https://gtfs.irail.be/nmbs/gtfs/latest.zip';

const TMP_UNZIP_PATH = 'nmbs-latest-gtfs';
const TMP_ZIPFILE = 'nmbs-latest-gtfs.zip';

const GTFS_STOP_TIMES = 'stop_times.txt';
const GTFS_TRIPS = 'trips.txt';
const GTFS_STOPS = 'stops.txt';
const GTFS_TRANSLATIONS = 'translations.txt';
const GTFS_CAL_DATES = 'calendar_dates.txt';
const GTFS_TRANSFER_TIMES = 'transfers.txt';
const STATIONS_CSV = '../stations.csv';
const STOPS_CSV = '../stops.csv';

const CSV_HEADER_URI = 'URI';
const CSV_HEADER_NAME = 'name';
const CSV_HEADER_COUNTRY = 'country-code';
const CSV_HEADER_ALT_FR = 'alternative-fr';
const CSV_HEADER_ALT_NL = 'alternative-nl';
const CSV_HEADER_ALT_DE = 'alternative-de';
const CSV_HEADER_ALT_EN = 'alternative-en';
const CSV_HEADER_LONGITUDE = 'longitude';
const CSV_HEADER_LATITUDE = 'latitude';
const CSV_HEADER_AVG_STOP_TIMES = 'avg_stop_times';
const CSV_HEADER_TRANSFER_TIME = 'official_transfer_time';

const CSV_WRITE_HEADERS = [CSV_HEADER_URI, CSV_HEADER_NAME, CSV_HEADER_ALT_FR, CSV_HEADER_ALT_NL, CSV_HEADER_ALT_DE, CSV_HEADER_ALT_EN,
                           CSV_HEADER_COUNTRY, CSV_HEADER_LONGITUDE, CSV_HEADER_LATITUDE, CSV_HEADER_AVG_STOP_TIMES, CSV_HEADER_TRANSFER_TIME];


const IRAIL_STATION_BASE_URI = "http://irail.be/stations/NMBS/00";

/*
 * Step 1 : Get the latest information from GTFS.
 * This information can be found at http://www.belgianrail.be/nl/klantendienst/infodiensten-reistools/public-data/open-data.aspx
 */
echo 'Gathering resources...' . PHP_EOL;

downloadGTFS();

/*
 * Gather prerequisite data
 */
list($handledDaysCount, $stopFrequencies) = getStopTimes();
$transferTimes = parseTransferTimes();

/*
 * Patch the csv file
 *
 * For this step, we need 3 actions:
 * - Discover which stations are present already and storing their data in an associative array
 * - Update calculated or extracted data (official_transfer_time, avg_stop_times)
 * - Appending stations which aren't present yet
 * - Write the new file to disk
 */
$gtfsStations = getGTFSStops();

$gtfsTranslations = getGTFSTranslations();

// The new CSV file will be compiled in memory, in the $result variable.
echo 'Compiling new CSV file...' . PHP_EOL;

// Update the first line (csv header)
$result = implode(',', CSV_WRITE_HEADERS) . PHP_EOL;

$csvOutputStations = [];

$csvInput = deserializeCSV(STATIONS_CSV);

// Go through all files.
foreach ($csvInput as $key => $station) {
    // Get the station URI.
    $uri = $station[CSV_HEADER_URI];

    if (array_key_exists($uri, $gtfsStations)) {
        $gtfsStation = $gtfsStations[$uri];
    } else {
        $gtfsStation = null;
    }

    // copy existing values
    $updatedStation = $station;

    // Add missing translations
    /*if ($gtfsStation != null && array_key_exists($gtfsStation['stop_name'], $gtfsTranslations)) {
        $translations = $gtfsTranslations[$gtfsStation['stop_name']];

        $updatedStation = updateMissingTranslations($updatedStation, $translations);
    }*/

    if ($gtfsStation != null) {
        $updatedStation = validateCoordinates($updatedStation, $gtfsStation);
    }

    // overwrite avg_stop_times and transfer times
    if (!array_key_exists($uri, $stopFrequencies)) {
        $updatedStation[CSV_HEADER_AVG_STOP_TIMES] = 0;
    } else {
        $updatedStation[CSV_HEADER_AVG_STOP_TIMES] = round($stopFrequencies[$uri] / $handledDaysCount, 6);
    }

    if (array_key_exists($uri, $transferTimes)) {
        $updatedStation[CSV_HEADER_TRANSFER_TIME] = $transferTimes[$uri];
    } else {
        $updatedStation[CSV_HEADER_TRANSFER_TIME] = '';
    }

    $csvOutputStations[$uri] = $updatedStation;
}

foreach ($gtfsStations as $uri => $gtfsStation) {
    if (strpos($uri, "_") !== false || strpos($uri, "S8") !== false) {
        continue; // Not a normal station, but a stop (platform) or some weird duplicate stuff NMBS has in their GTFS
    }

    if (array_key_exists($uri, $csvOutputStations)) {
        continue; // Station already in the CSV file
    }

    // Available from GTFS: stop_id,stop_code,stop_name,stop_desc,stop_lat,stop_lon,zone_id,stop_url,location_type,parent_station,platform_code
    echo "Adding missing station: $uri" . PHP_EOL;
    $name = $gtfsStation['stop_name'];

    // Determine the country from (the absence of) a foreign country abbreviation: (l), (fr), (d)
    $nameLeftBracket = strpos($name, '(');
    $nameRightBracket = strpos($name, ')');
    // determine country code
    if ($nameLeftBracket !== false) {
        $countryhint = substr($name, $nameLeftBracket, $nameRightBracket - $nameLeftBracket);
        switch ($countryhint) {
            case 'l':
                $country = 'lu';
                break;
            case 'fr':
                $country = 'fr';
                break;
            case 'd':
                $country = 'de';
                break;
        }
        $name = substr($name, 0, $nameLeftBracket - 1);
    } else {
        $country = 'be';
    }

    // Language barrier runs at a latitude higher than 50.756082 or 50.754896
    // If in Flanders (approx) the Dutch name is used. If the name isn't determined correctly, this needs manual changes.
    if (array_key_exists($name, $gtfsTranslations)) {
        $translations = $gtfsTranslations[$name];
        if (array_key_exists('nl', $translations) && $translations['nl'] != $name) {
            $nl = $translations['nl'];
            if ($gtfsStation['stop_lat'] > 50.756082) {
                // If in Flanders (approx.)
                $name = $nl;
            }
        } else {
            $nl = '';
        }
        if (array_key_exists('en', $translations) && $translations['en'] != $name) {
            $en = $translations['en'];
        } else {
            $en = '';
        }
        if (array_key_exists('de', $translations) && $translations['de'] != $name) {
            $de = $translations['de'];
        } else {
            $de = '';
        }
        if (array_key_exists('fr', $translations) && $translations['fr'] != $name) {
            $fr = $translations['fr'];
        } else {
            $fr = '';
        }
    } else {
        $nl = '';
        $fr = '';
        $de = '';
        $en = '';
    }

    $station[CSV_HEADER_URI] = $uri;
    $station[CSV_HEADER_NAME] = $name;
    $station[CSV_HEADER_ALT_FR] = $fr;
    $station[CSV_HEADER_ALT_NL] = $nl;
    $station[CSV_HEADER_ALT_DE] = $de;
    $station[CSV_HEADER_ALT_EN] = $en;
    $station[CSV_HEADER_COUNTRY] = $country;
    $station[CSV_HEADER_LONGITUDE] = $gtfsStation['stop_lon'];
    $station[CSV_HEADER_LATITUDE] = $gtfsStation['stop_lat'];
    $station[CSV_HEADER_AVG_STOP_TIMES] = $gtfsStation['stop_lon'];
    $station[CSV_HEADER_AVG_STOP_TIMES] = round($stopFrequencies[$uri] / $handledDaysCount, 6);
    $station[CSV_HEADER_TRANSFER_TIME] = $transferTimes[$uri];

    $csvOutputStations[$uri] = $station;
}

$csvOutput = implode(',', CSV_WRITE_HEADERS) . PHP_EOL;

// Sorting will remove the keys and use numeric keys instead. Therefore we use a copy in order to keep the original array intact.
$csvOutputStationsSorted = $csvOutputStations;
usort($csvOutputStationsSorted, function ($a, $b) {
    if ($a[CSV_HEADER_NAME] != $b[CSV_HEADER_NAME])
        return $a[CSV_HEADER_NAME] > $b[CSV_HEADER_NAME];
    else
        // Should never occur, but fallback just in case
        return $a[CSV_HEADER_URI] > $b[CSV_HEADER_URI];

});

foreach ($csvOutputStationsSorted as $uri => $station) {
    $csvOutput .= serializeCSVLine(CSV_WRITE_HEADERS, $station);
}

echo 'Saving...' . PHP_EOL;
// Create a backup, just in case.
copy(STATIONS_CSV, STATIONS_CSV . '.bak');
echo 'A backup has been created at ' . STATIONS_CSV . '.bak' . PHP_EOL;
// Write everything to a new file
file_put_contents(STATIONS_CSV, $csvOutput);

echo 'Saved stations.csv to ' . STATIONS_CSV . '! You can make manual changes to this file. Don\'t forget to run build.js now!' . PHP_EOL;

writeStopsCsv($csvOutputStations, $gtfsStations);

echo 'Saved stops.csv to ' . STOPS_CSV . '! Manual changes to this file won\'t be preserved!' . PHP_EOL;

echo 'Don\'t forget to run web_facilities_extractor.php in order to update facility data!' . PHP_EOL;

/**
 * Download and extract the latest GTFS data set
 */
function downloadGTFS(): void
{
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
    rename(TMP_UNZIP_PATH . '/' . GTFS_STOPS, GTFS_STOPS);
    rename(TMP_UNZIP_PATH . '/' . GTFS_TRANSLATIONS, GTFS_TRANSLATIONS);
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
}

/**
 * Load the recommended transfer times per station
 * @return array
 */
function parseTransferTimes(): array
{
    // CSV Header:
    // from_stop_id,to_stop_id,transfer_type,min_transfer_time,from_trip_id,to_trip_id

    $parsedCsv = deserializeCSV(GTFS_TRANSFER_TIMES);
    $transferTimes = [];
    foreach ($parsedCsv as $key => $csvRow) {
        if ($csvRow['from_stop_id'] !== $csvRow['to_stop_id']) {
            // We only want intra-stop transfers. NMBS GTFS only includes those, but to be sure, add a check
            continue;
        }

        // Station UIC ID to HAFAS
        $uri = IRAIL_STATION_BASE_URI . $csvRow['from_stop_id'];

        // Transfer value
        $transfer = $csvRow['min_transfer_time'];

        // Store value for station id
        $transferTimes[$uri] = $transfer;
    }

    // We don't need this file anymore. Cleanup.
    unlink(GTFS_TRANSFER_TIMES);
    return $transferTimes;
}


/**
 * Get the number of stops made on each station, as well as the number of days which were handled.
 * This can be used to calculate both the stop times per station and the average stop times per station.
 * @return array
 */
function getStopTimes(): array
{
    echo 'Creating service id frequency table...' . PHP_EOL;

    $fileReadHandle = fopen(GTFS_CAL_DATES, 'r');
    if (!$fileReadHandle) {
        die(GTFS_CAL_DATES . ' could not be opened!');
    }

    // skip the first line (csv header)
    fgets($fileReadHandle);

    // Create the frequency table.
    $serviceFrequency = [];
    // The dates we've handled.
    $isDateHandled = [];
    while (($line = fgets($fileReadHandle)) !== false) {
        /*
         * File format:
         * service_id,date,exception_type
         */
        $parts = explode(',', $line);
        // Get service ID.
        $serviceId = $parts[0];
        $date = $parts[1];
        // Increase frequency.
        if (isset($serviceFrequency[$serviceId])) {
            $serviceFrequency[$serviceId]++;
        } else {
            // Set initial value if key isn't added yet.
            $serviceFrequency[$serviceId] = 1;
        }
        $isDateHandled[$date] = 1;
    }
    // Close this handle. Important!
    fclose($fileReadHandle);

    // We don't need this file anymore. Cleanup.
    unlink(GTFS_CAL_DATES);

    // Use the calender frequencies to calculate the frequency of each trip
    echo 'Creating trip id frequency table...' . PHP_EOL;
    $fileReadHandle = fopen(GTFS_TRIPS, 'r');
    if (!$fileReadHandle) {
        die(GTFS_TRIPS . ' could not be opened!');
    }

    // skip the first line (csv header)
    fgets($fileReadHandle);

    // Create the frequency table containing each trips frequency..
    $tripFrequencies = [];

    while (($line = fgets($fileReadHandle)) !== false) {
        /*
         * File format:
         * route_id,service_id,trip_id
         */
        // Get service ID.
        $parts = explode(',', $line);
        $serviceId = $parts[1];
        $tripId = trim($parts[2]);

        // Set frequency, which is the same as the service frequency.
        $tripFrequencies[$tripId] = $serviceFrequency[$serviceId];
    }
    // Close this handle. Important!
    fclose($fileReadHandle);

    // We don't need this file anymore. Cleanup.
    unlink(GTFS_TRIPS);

    // Use the
    echo 'Creating frequency table...' . PHP_EOL;
    $fileReadHandle = fopen(GTFS_STOP_TIMES, 'r');
    if (!$fileReadHandle) {
        die('GTFS stop times file could not be opened!');
    }

    // skip the first line (csv header)
    fgets($fileReadHandle);

    // Create the frequency table.
    $stopFrequencies = [];

    while (($line = fgets($fileReadHandle)) !== false) {
        /*
         * File format:
         * trip_id,arrival_time,departure_time,stop_id,stop_sequence
         * 88____:046::8821402:8400526:3:650:20181208,6:43:00,6:43:00,8821402,1,,0,1,
         */
        $parts = explode(',', $line);
        // Get stop ID.
        $uri = IRAIL_STATION_BASE_URI . $parts[3];

        $tripId = $parts[0];
        // The amount of time this trip is made.
        $tripFrequency = $tripFrequencies[$tripId];
        // Increase frequency.
        if (isset($stopFrequencies[$uri])) {
            $stopFrequencies[$uri] += $tripFrequency;
        } else {
            // Set initial value if key isn't added yet.
            $stopFrequencies[$uri] = $tripFrequency;
        }
    }
    // Close this handle. Important!
    fclose($fileReadHandle);
    unlink(GTFS_STOP_TIMES);


    // Get the number of days that were handled. We need this to calculate the average later on.
    $handledDaysCount = count($isDateHandled);


    return [$handledDaysCount, $stopFrequencies];
}

/**
 * Load a list of 'official' stops data from the GTFS dataset
 * @return array
 */
function getGTFSStops(): array
{
    // CSV Header:
    // stop_id,stop_code,stop_name,stop_desc,stop_lat,stop_lon,zone_id,stop_url,location_type,parent_station,platform_code

    $parsedCsv = deserializeCSV(GTFS_STOPS);
    usort($parsedCsv, function ($a, $b) {
        if ($a['stop_name'] != $b['stop_name'])
            return $a['stop_name'] > $b['stop_name'];
        else
            if ($a['stop_id'] != $b['stop_id'])
                return $a['stop_id'] > $b['stop_id'];
            else
                return $a['platform_code'] > $b['platform_code'];

    });

    $gtfsStations = [];
    // Go through all files.
    foreach ($parsedCsv as $key => $csvRow) {
        $uri = IRAIL_STATION_BASE_URI . $csvRow['stop_id'];
        $gtfsStations[$uri] = $csvRow;
    }


    unlink(GTFS_STOPS);
    return $gtfsStations;
}

/**
 * Load translations for station names from GTFS
 */
function getGTFSTranslations(): array
{
    // Open the GTFS translations file and read it into an associative array
    $parsedCsv = deserializeCSV(GTFS_TRANSLATIONS);

    $gtfsTranslations = [];

    foreach ($parsedCsv as $key => $translation) {
        // CSV Header:
        // trans_id,lang,translation

        // Multi dimensional array, first key being the original name, second key being the translation language
        $gtfsTranslations[$translation['trans_id']][$translation['lang']] = $translation['translation'];
    }

    unlink(GTFS_TRANSLATIONS);
    return $gtfsTranslations;
}

/**
 * Update missing translations, adding translations when:
 * - No translation is present
 * - The translation is (significantly) different from the original name
 * @param $station
 * @param $translations
 * @param $station
 * @return mixed
 */
function updateMissingTranslations($station, $translations)
{

    // Skip stations which have known low-quality translations
    $ignoredStations = ['http://irail.be/stations/NMBS/008812211'];
    if (in_array($station[CSV_HEADER_URI], $ignoredStations)) {
        return $station;
    }

    $cleanedOriginalName = cleanStationName($station[CSV_HEADER_NAME]);
    if (empty($station[CSV_HEADER_ALT_NL]) && array_key_exists('nl', $translations) && cleanStationName($translations['nl']) != $cleanedOriginalName) {
        $station[CSV_HEADER_ALT_NL] = $translations['nl'];
    }
    if (empty($station[CSV_HEADER_ALT_FR]) && array_key_exists('fr', $translations) && cleanStationName($translations['fr']) != $cleanedOriginalName) {
        $station[CSV_HEADER_ALT_FR] = $translations['fr'];
    }
    if (empty($station[CSV_HEADER_ALT_EN]) && array_key_exists('en', $translations) && cleanStationName($translations['en']) != $cleanedOriginalName) {
        $station[CSV_HEADER_ALT_EN] = $translations['en'];
    }
    if (empty($station[CSV_HEADER_ALT_DE]) && array_key_exists('de', $translations) && cleanStationName($translations['de']) != $cleanedOriginalName) {
        $station[CSV_HEADER_ALT_DE] = $translations['de'];
    }
    return $station;
}

/**
 * Add missing coordinates and validate existing coordinates
 * @param $station
 * @param $gtfsStation
 * @return array
 */
function validateCoordinates($station, $gtfsStation): array
{
    $gtfsLatitude = $gtfsStation['stop_lat'];
    $gtfsLongitude = $gtfsStation['stop_lon'];

    $latitude = $station[CSV_HEADER_LATITUDE];
    $longitude = $station[CSV_HEADER_LONGITUDE];

    // Case 1: missing location data
    if (empty($latitude) || empty($longitude)) {
        echo $station[CSV_HEADER_URI] . " updated missing location data with official coordinates: $gtfsLongitude, $gtfsLatitude! " . PHP_EOL;
        $station[CSV_HEADER_LATITUDE] = $gtfsLatitude;
        $station[CSV_HEADER_LONGITUDE] = $gtfsLongitude;
    } else if (abs($gtfsLatitude - $latitude) > 0.005 || abs($gtfsLongitude - $longitude) > 0.015) {
        // Longitude: at 60°-45°: 0,001 = 70m
        // Latitude: at 60°-45°: 0,001 = 111m
        // The user should determine what to do with this
        $station[CSV_HEADER_LATITUDE] = $gtfsLatitude;
        $station[CSV_HEADER_LONGITUDE] = $gtfsLongitude;
        echo $station[CSV_HEADER_URI] . " had an incorrect location with large deviation from GTFS. Was $longitude, $latitude but official is $gtfsLongitude, $gtfsLatitude. Updated! " . PHP_EOL;
    } else if (abs($gtfsLatitude - $latitude) > 0.002 || abs($gtfsLongitude - $longitude) > 0.004) {
        // Longitude: at 60°-45°: 0,001 = 70m
        // Latitude: at 60°-45°: 0,001 = 111m
        // The user should determine what to do with this
        echo $station[CSV_HEADER_URI] . " has an incorrect location. Current value is $longitude, $latitude but official is $gtfsLongitude, $gtfsLatitude. Consider updating the location." . PHP_EOL;
    } else if ((getNumberOfDecimalPlaces($latitude) <= 4 && getNumberOfDecimalPlaces($latitude) < getNumberOfDecimalPlaces($gtfsLatitude)) ||
        (getNumberOfDecimalPlaces($longitude) <= 4 && getNumberOfDecimalPlaces($longitude) < getNumberOfDecimalPlaces($gtfsLongitude))) {
        // Optional, requiring X digits
        // echo $station[CSV_HEADER_URI] . " has insufficient accuracy. Was $longitude, $latitude, updated to official $gtfsLongitude, $gtfsLatitude! " . PHP_EOL;
    }
    // if filled, enough digits, and no bug difference, move on. We never overwrite handmade changes without reason
    return $station;
}


/**
 * Create a stops.csv file based on stops.txt. Stops.csv should identify all platorms.
 * @param $csvStations array Stations present in the stations.csv file, used to determine the default name for a station
 * @param $gtfsStations array Stops present in the GTFS stops.txt file, used to discover all platorms.
 */
function writeStopsCsv($csvStations, $gtfsStations): void
{

    $originalStopsCsv = deserializeCSV(STOPS_CSV);
    $discoveredUris = [];

    $headerFields = ['URI', 'parent_stop', 'longitude', 'latitude', 'name', 'alternative-nl', 'alternative-fr', 'alternative-de', 'alternative-en', 'platform'];

    // Output CSV contents for stops.csv will be appended in this variable
    $stopsCsv = implode(',', $headerFields) . PHP_EOL;

    foreach ($gtfsStations as $uri => $station) {
        if (strpos($uri, '_') === false || strpos($uri, 'S8') !== false) {
            continue; // We only want stop locations (= platforms). Skip stations and weird station duplicates prefixed with S.
        }

        $parentUri = IRAIL_STATION_BASE_URI . ltrim($station['parent_station'], 'S');
        $parentName = $csvStations[$parentUri][CSV_HEADER_NAME];
        $parentNameEn = $csvStations[$parentUri][CSV_HEADER_ALT_EN] ?: $parentName;
        $parentNameNl = $csvStations[$parentUri][CSV_HEADER_ALT_NL] ?: $parentName;
        $parentNameDe = $csvStations[$parentUri][CSV_HEADER_ALT_DE] ?: $parentName;
        $parentNameFr = $csvStations[$parentUri][CSV_HEADER_ALT_FR] ?: $parentName;
        $platformCode = $station['platform_code'];
        $stopUri = $parentUri . '#' . $platformCode;

        // NMBS doesn't follow GTFS specifications and uses the location_type field incorrectly. Therefore we can't really use it.
        $stop = ['URI'            => $stopUri,
                 'parent_stop'    => $parentUri,
                 'longitude'      => $station['stop_lon'],
                 'latitude'       => $station['stop_lat'],
                 'name'           => $parentName . ' platform ' . $platformCode,
                 'alternative-nl' => $parentNameNl . ' perron ' . $platformCode,
                 'alternative-fr' => $parentNameFr . ' voie ' . $platformCode,
                 'alternative-de' => $parentNameDe . ' gleiss ' . $platformCode,
                 'alternative-en' => $parentNameEn . ' platform ' . $platformCode,
                 'platform'       => $platformCode
        ];
        $discoveredUris[] = $stopUri;
        $stopsCsv .= serializeCSVLine($headerFields, $stop);
    }

    foreach ($originalStopsCsv as $stop) {
        // Ensure all stops stay in the database, even if they're not used for a while. What hasn't been added again is added here
        if (!key_exists($stop['URI'], $discoveredUris)) {
            $stopsCsv .= serializeCSVLine($headerFields, $stop);
        }
    }

    file_put_contents(STOPS_CSV, $stopsCsv);
}

/**
 * @param $number float the number for which you want to determine the number of decimal places
 * @return int the number of decimal places
 */
function getNumberOfDecimalPlaces($number): int
{
    return strlen(strrchr($number, ".")) - 1;
}

/**
 * Clean all non A-Za-z characters from station names for better comparisons
 *
 * ! important: don't use this for output, and only for comparing, as it will remove all spaces and hyphens, making output unreadable in some cases
 * ! important: some queries will return identifiers for comparisons, again, don't use this for output
 *
 * @param $name string the name to clean
 * @return string the cleaned name
 */
function cleanStationName($name): string
{
    if (strpos($name, '/') !== false) {
        // e.g. Boondaal/Boondael
        // to prevent the NMBS translation -which always has the French name first- to pollute our data, we will ensure stations with multiple languages
        // are always considered the same
        return 'multilang';
    }

    $unwanted_array = [
        'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
        'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
        'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
        'Þ' => 'Th', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a',
        'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
        'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
        'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
        'û' => 'u', 'ý' => 'y', 'þ' => 'th', 'œ' => 'oe',
        'ÿ' => 'y',
    ];

    $name = strtolower(strtr($name, $unwanted_array));
    $name = str_replace(' ', '-', $name);
    return preg_replace('/[^A-Za-z0-9]/', '', $name);

}


/**
 * Serialize data to a CSV row
 *
 * @param $headers array The headers which should be written
 * @param $station array The data as an associative array (header => value) to serialize to CSV
 * @return string CSV representation of the data
 */
function serializeCSVLine($headers, $station): string
{
    // Resulting serialized line
    $row = '';
    // Loop over all headers
    for ($i = 0; $i < count($headers); $i++) {
        // Which value we are appending
        $header = $headers[$i];

        // Add key if it exists, otherwise leave empty
        if (key_exists($header, $station)) {
            $row .= $station[$header];
        }

        // No trailing comma
        if ($i < count($headers) - 1) {
            $row .= ',';
        }
    }
    // Return line with newline character
    return $row . PHP_EOL;
}

/**
 * Load a CSV file and store it in an associative array with the first CSV column value as key.
 * Each line is stored as an associative array using column headers as key and the fields as value.
 *
 * @param $csvPath string File path leading to the CSV file
 * @return array the deserialized data
 */
function deserializeCSV($csvPath): array
{
    // Open the GTFS stops file and read it into an associative array
    $fileReadHandle = fopen($csvPath, 'r');
    if (!$fileReadHandle) {
        die($csvPath . ' could not be opened! Run this script using stations/bin as working directory');
    }
    // Read the original headers
    $headers = trim(fgets($fileReadHandle));

    // Transform the original headers into an array
    $headers = explode(',', $headers);

    // Trim tabs, newlines, ...
    $headers = array_map('trim', $headers);

    $entries = [];

    // Go through all rows
    while (($line = fgets($fileReadHandle)) !== false) {
        $line = trim($line);

        $entry = explode(',', $line);
        $entry = array_map('trim', $entry);

        // The first column is used as key in the associative array
        $first = $entry[0];

        $entry = array_combine($headers, $entry);
        $entries[$first] = $entry;
    }
    return $entries;
}