<?php

/**
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
const GTFS_ZIP = 'https://sncb-opendata.hafas.de/gtfs/static/c21ac6758dd25af84cca5b707f3cb3de';

const TMP_UNZIP_PATH = 'nmbs-latest-gtfs';
const TMP_ZIPFILE = 'nmbs-latest-gtfs.zip';

const GTFS_STOP_TIMES = 'stop_times.txt';
const GTFS_TRIPS = 'trips.txt';
const GTFS_STOPS = 'stops.txt';
const GTFS_TRANSLATIONS = 'translations.txt';
const GTFS_CAL_DATES = 'calendar_dates.txt';
const GTFS_TRANSFER_TIMES = 'transfers.txt';
const STATIONS_CSV = '../stations.csv';

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
 * - Appending stations which aren't present yet
 * - Appending calculated or extracted data (official_transfer_time, avg_stop_times)
 * - Write the new file to disk
 */
$gtfsStations = getGTFSStations();


$gtfsTranslations = getGTFSTranslations();


// Open the CSV file that needs a patch.
$fileReadHandle = fopen(STATIONS_CSV, 'r');
if (!$fileReadHandle) {
    die('stations.csv file could not be opened!');
}

// The new CSV file will be compiled in memory, in the $result variable.
echo 'Compiling new CSV file...' . PHP_EOL;

// Update the first line (csv header)
$result = implode(',', CSV_WRITE_HEADERS) . PHP_EOL;

// Read the original headers
$originalHeaders = fgets($fileReadHandle);
// Transform the original headers into an array
$originalHeaders = explode(',', $originalHeaders);

$existingStations = [];

// Go through all files.
while (($line = fgets($fileReadHandle)) !== false) {
    // Line format:
    // http://irail.be/stations/NMBS/008821006,Antwerpen-Centraal,Anvers-Central,,,Antwerp-Central,be,4.421101,51.2172
    $line = trim($line);

    $station = explode(',', $line);
    $station = array_combine($originalHeaders, $station);

    // Get the station URI.
    $uri = $station[CSV_HEADER_URI];
    $existingStations[] = $uri;

    if (array_key_exists($uri, $gtfsStations)) {
        $gtfsStation = $gtfsStations[$uri];
    } else {
        $gtfsStation = null;
    }

    // copy existing values
    $updatedStation = $station;

    // Add missing translations
    if ($gtfsStation != null && array_key_exists($gtfsStation['stop_name'], $gtfsTranslations)) {
        $translations = $gtfsTranslations[$gtfsStation['stop_name']];

        $updatedStation = updateMissingTranslations($updatedStation, $translations);
    }

    if ($gtfsStation !=null){
        $updatedStation = validateCoordinates($updatedStation,$gtfsStation);
    }

    // overwrite avg_stop_times and transfer times
    if (!array_key_exists($uri, $stopFrequencies)) {
        $updatedStation[CSV_HEADER_AVG_STOP_TIMES] = 0;
    } else {
        $updatedStation[CSV_HEADER_AVG_STOP_TIMES] = $stopFrequencies[$uri] / $handledDaysCount;
    }

    if (array_key_exists($uri, $transferTimes)) {
        $updatedStation[CSV_HEADER_TRANSFER_TIME] = $transferTimes[$uri];
    } else {
        $updatedStation[CSV_HEADER_TRANSFER_TIME] = '';
    }

    // serialize again
    for ($i = 0; $i < count(CSV_WRITE_HEADERS); $i++) {
        $header = CSV_WRITE_HEADERS[$i];
        if (key_exists($header, $updatedStation)) {
            $result .= $updatedStation[$header];
        }
        if ($i < count(CSV_WRITE_HEADERS) - 1) {
            $result .= ',';
        }
    }
    $result .= PHP_EOL;

}

// Close this handle. Important!
fclose($fileReadHandle);


foreach ($gtfsStations as $uri => $gtfsStation) {
    if (strpos($uri, "_") !== false || strpos($uri, "S8") !== false) {
        continue; // Not a normal station, but a stop (platform) or some weird duplicate stuff NMBS has in their GTFS
    }

    if (in_array($uri, $existingStations)) {
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
    $station[CSV_HEADER_AVG_STOP_TIMES] = $stopFrequencies[$uri] / $handledDaysCount;
    $station[CSV_HEADER_TRANSFER_TIME] = $transferTimes[$uri];

    // serialize again
    $result .= serializeCSV($station);
    $result .= PHP_EOL;

    $existingStations[] = $uri;
}

echo 'Saving...' . PHP_EOL;
// Create a backup, just in case.
copy(STATIONS_CSV, STATIONS_CSV . '.bak');
echo 'A backup has been created at ' . STATIONS_CSV . '.bak' . PHP_EOL;
// Write everything to a new file
file_put_contents(STATIONS_CSV, $result);

echo 'Saved to ' . STATIONS_CSV . '! Don\'t forget to run build.js now!' . PHP_EOL;

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
    $rawTransferTimes = file_get_contents(GTFS_TRANSFER_TIMES);

    $rawTransferTimes = explode("\n", $rawTransferTimes);

    /**
     * @var $transferTimes array Associative array containing key-value pairs of station URIs and the minimum recommended transfer time by the NMBS
     */
    $transferTimes = [];
    foreach ($rawTransferTimes as $line => $value) {
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
        $uri = IRAIL_STATION_BASE_URI . $fields[0];
        // Transfer value
        $transfer = $fields[3];
        if (strpos($uri, "_") === false && strpos($uri, "S8") === false) {
            // Store value for station id
            $transferTimes[$uri] = $transfer;
        }
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
 * Load a list of 'official' station data from the GTFS dataset
 * @return array
 */
function getGTFSStations(): array
{
// Open the GTFS stops file and read it into an associative array
    $fileReadHandle = fopen(GTFS_STOPS, 'r');
    if (!$fileReadHandle) {
        die('GTFS stations file could not be opened!');
    }
// Read the original headers
    $originalGtfsHeaders = fgets($fileReadHandle);
// Transform the original headers into an array
    $originalGtfsHeaders = explode(',', $originalGtfsHeaders);
    $gtfsStations = [];
// Go through all files.
    while (($line = fgets($fileReadHandle)) !== false) {
        // Line format:
        // http://irail.be/stations/NMBS/008821006,Antwerpen-Centraal,Anvers-Central,,,Antwerp-Central,be,4.421101,51.2172
        $line = trim($line);

        $station = explode(',', $line);
        $station = array_combine($originalGtfsHeaders, $station);
        $uri = IRAIL_STATION_BASE_URI . $station['stop_id'];
        $gtfsStations[$uri] = $station;
    }

    fclose($fileReadHandle);
    unlink(GTFS_STOPS);
    return $gtfsStations;
}

/**
 * Load translations for station names from GTFS
 */
function getGTFSTranslations(): array
{
// Open the GTFS translations file and read it into an associative array
    $fileReadHandle = fopen(GTFS_TRANSLATIONS, 'r');
    if (!$fileReadHandle) {
        die('GTFS translations file could not be opened!');
    }

    $gtfsTranslations = [];
// Go through all files.
    while (($line = fgets($fileReadHandle)) !== false) {
        // Line format:
        // http://irail.be/stations/NMBS/008821006,Antwerpen-Centraal,Anvers-Central,,,Antwerp-Central,be,4.421101,51.2172
        $line = trim($line);

        // trans_id,lang,translation
        $translation = explode(',', $line);

        // Multi dimensional array, first key being the original name, second key being the translation language
        $gtfsTranslations[$translation[0]][$translation[1]] = $translation[2];
    }

    fclose($fileReadHandle);
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
 * Add missing coordinates and validate existing coördinates
 * @param $station
 * @param $gtfsStation
 * @return array
 */
function validateCoordinates($station, $gtfsStation) : array
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
 * Serialize data to CSV, using the headers defined in CSV_WRITE_HEADERS
 * @param $station array The data as associative array to serialize to CSV
 * @return string CSV representation of the data
 */
function serializeCSV($station): string
{
    $row = '';
    for ($i = 0; $i < count(CSV_WRITE_HEADERS); $i++) {
        $header = CSV_WRITE_HEADERS[$i];
        if (key_exists($header, $station)) {
            $row .= $station[$header];
        }
        if ($i < count(CSV_WRITE_HEADERS) - 1) {
            $row .= ',';
        }
    }
    return $row;
}