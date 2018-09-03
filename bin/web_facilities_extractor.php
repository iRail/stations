<?php

/**
 * Scrape the NMBS website for facilities available in a station, and write the scraped facilities to facilities.csv
 * A stations.csv file should be present to provide station URIs for which data should be loaded
 *
 * @author Bertware
 **/

const CSV_HEADER_URI = 'URI';
const CSV_HEADER_NAME = 'name';
const CSV_HEADER_STREET = 'street';
const CSV_HEADER_ZIP = 'zip';
const CSV_HEADER_CITY = 'city';
const CSV_HEADER_TVM = 'ticket_vending_machine';
const CSV_HEADER_LOCKERS = 'luggage_lockers';
const CSV_HEADER_FREE_PARKING = 'free_parking';
const CSV_HEADER_TAXI = 'taxi';
const CSV_HEADER_BICYCLE_SPOTS = 'bicycle_spots';
const CSV_HEADER_BLUE_BIKE = 'blue-bike';
const CSV_HEADER_BUS = 'bus';
const CSV_HEADER_TRAM = 'tram';
const CSV_HEADER_METRO = 'metro';
const CSV_HEADER_WHEELCHAIR_AVAILABLE = 'wheelchair_available';
const CSV_HEADER_RAMP = 'ramp';
const CSV_HEADER_DISABLED_PARKING_SPOTS = 'disabled_parking_spots';
const CSV_HEADER_ELEVATED_PLATFORM = 'elevated_platform';
const CSV_HEADER_ESCALATOR_UP = 'escalator_up';
const CSV_HEADER_ESCALATOR_DOWN = 'escalator_down';
const CSV_HEADER_ELEVATOR_PLATFORM = 'elevator_platform';
const CSV_HEADER_AUDIO_INDUCTION_LOOP = 'audio_induction_loop';
const CSV_HEADER_SALES_OPEN_MONDAY = 'sales_open_monday';
const CSV_HEADER_SALES_CLOSE_MONDAY = 'sales_close_monday';
const CSV_HEADER_SALES_OPEN_TUESDAY = 'sales_open_tuesday';
const CSV_HEADER_SALES_CLOSE_TUESDAY = 'sales_close_tuesday';
const CSV_HEADER_SALES_OPEN_WEDNESDAY = 'sales_open_wednesday';
const CSV_HEADER_SALES_CLOSE_WEDNESDAY = 'sales_close_wednesday';
const CSV_HEADER_SALES_OPEN_THURSDAY = 'sales_open_thursday';
const CSV_HEADER_SALES_CLOSE_THURSDAY = 'sales_close_thursday';
const CSV_HEADER_SALES_OPEN_FRIDAY = 'sales_open_friday';
const CSV_HEADER_SALES_CLOSE_FRIDAY = 'sales_close_friday';
const CSV_HEADER_SALES_OPEN_SATURDAY = 'sales_open_saturday';
const CSV_HEADER_SALES_CLOSE_SATURDAY = 'sales_close_saturday';
const CSV_HEADER_SALES_OPEN_SUNDAY = 'sales_open_sunday';
const CSV_HEADER_SALES_CLOSE_SUNDAY = 'sales_close_sunday';
include("./includes/simple_html_dom.php");
// Don't crash by avoiding require, but give a clear explanation if include failed
if (!function_exists("str_get_html")) {
    echo PHP_EOL . 'Change your current working directory to the same location as this file before running!' . PHP_EOL;
    return;
}

const STATIONS_CSV = '../stations.csv';
const STATION_FACILITIES_CSV = "../facilities.csv";
const NMBS_BASE_URL = "http://www.belgianrail.be/Station.ashx?lang=nl&stationId=";

// Open the CSV file that needs a patch.
$handle = fopen(STATIONS_CSV, 'r');
if (!$handle) {
    die('stations.csv file could not be opened! Run this script using stations/bin as working directory');
}

// The new CSV file will be compiled in memory, in the $result variable.
echo 'Compiling new CSV file...' . PHP_EOL;

// Create header for new file
$headers = [CSV_HEADER_URI, CSV_HEADER_NAME, CSV_HEADER_STREET, CSV_HEADER_ZIP, CSV_HEADER_CITY, CSV_HEADER_TVM, CSV_HEADER_LOCKERS,
            CSV_HEADER_FREE_PARKING, CSV_HEADER_TAXI, CSV_HEADER_BICYCLE_SPOTS, CSV_HEADER_BLUE_BIKE, CSV_HEADER_BUS, CSV_HEADER_TRAM, CSV_HEADER_METRO,
            CSV_HEADER_WHEELCHAIR_AVAILABLE, CSV_HEADER_RAMP, CSV_HEADER_DISABLED_PARKING_SPOTS,
            CSV_HEADER_ELEVATED_PLATFORM, CSV_HEADER_ESCALATOR_UP, CSV_HEADER_ESCALATOR_DOWN, CSV_HEADER_ELEVATOR_PLATFORM, CSV_HEADER_AUDIO_INDUCTION_LOOP,
            CSV_HEADER_SALES_OPEN_MONDAY, CSV_HEADER_SALES_CLOSE_MONDAY,
            CSV_HEADER_SALES_OPEN_TUESDAY, CSV_HEADER_SALES_CLOSE_TUESDAY,
            CSV_HEADER_SALES_OPEN_WEDNESDAY, CSV_HEADER_SALES_CLOSE_WEDNESDAY,
            CSV_HEADER_SALES_OPEN_THURSDAY, CSV_HEADER_SALES_CLOSE_THURSDAY,
            CSV_HEADER_SALES_OPEN_FRIDAY, CSV_HEADER_SALES_CLOSE_FRIDAY,
            CSV_HEADER_SALES_OPEN_SATURDAY, CSV_HEADER_SALES_CLOSE_SATURDAY,
            CSV_HEADER_SALES_OPEN_SUNDAY, CSV_HEADER_SALES_CLOSE_SUNDAY];

$stations = deserializeCSV(STATIONS_CSV);
$facilities = [];

$i = 0;
$total = count($stations);
foreach ($stations as $uri => $station) {
    $name = $station[CSV_HEADER_NAME];
    echo 'Updating facilities for ' . $name . ' ( ' . round(100 * ++$i / $total) . '% )' . PHP_EOL;
    $facilities[] = get_station_facilities_line($uri, $name);
}


echo 'Saving...' . PHP_EOL;
$result = implode(',', $headers) . PHP_EOL;
// Write everything to a new file
foreach ($facilities as $facility) {
    $result .= serializeCSVLine($headers, $facility);
}
file_put_contents(STATION_FACILITIES_CSV, $result);
echo 'Done!';

function get_station_facilities_line($uri, $name): array
{
    $data[CSV_HEADER_URI] = $uri;
    $data[CSV_HEADER_NAME] = $name;

    // UIC station ID
    $id = substr($uri, -7);

    $url = NMBS_BASE_URL . $id;

    // Foreign information isn't available, so don't bother trying for stations which don't have an ID starting with the Belgian UIC country code (88)
    if (substr($id, 0, 2) != "88") {
        return $data;
    }

    $webpage = file_get_contents($url);

    // Start by checking if the id of the "opening hours sales points" is included.
    // If the id is not included, the site might have changed, and a warning should be shown!
    // Note: this will fail on stations which are located outside of belgium, in this case the warning can be ignored.
    if (!string_contains($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl04_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_tabLink")
    ) {
        print "WARNING: This station doesn't exist anymore, data isn't available, or the NMBS website has changed! Facilities for station $id can't be parsed." . PHP_EOL;
        return $data;
    }

    // Address
    $html = str_get_html($webpage);
    // Street
    $data[CSV_HEADER_STREET] = ucwords(strtolower(str_replace(',', '', trim($html->find(".title-address")[0]->find("span")[0]->plaintext))));
    // Zip
    $data[CSV_HEADER_ZIP] = str_replace(',', '', substr(trim($html->find(".title-address")[0]->find("span")[1]->plaintext), 0, 4));
    // City
    $data[CSV_HEADER_CITY] = ucwords(strtolower(str_replace(',', '', substr(trim($html->find(".title-address")[0]->find("span")[1]->plaintext), 5))));

    // Facilities

    // ticket machines
    $data[CSV_HEADER_TVM] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/ticket_machines.ashx?h=42&amp;w=42");

    // lockers
    $data[CSV_HEADER_LOCKERS] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/luggage_lockers.ashx?h=26&amp;w=16");

    // free parking
    $data[CSV_HEADER_FREE_PARKING] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/parking.ashx?h=26&amp;w=26");

    // taxi
    $data[CSV_HEADER_TAXI] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/taxi.ashx?h=26&amp;w=26");

    // bicycle storage
    // No icon, so use the id of the list element instead.
    $data[CSV_HEADER_BICYCLE_SPOTS] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl10_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_allCriteriaUC_criteriaList_ctl02_BooleanTypeCriteria1_visibleCriteria");

    // blue bikes
    $data[CSV_HEADER_BLUE_BIKE] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl10_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_allCriteriaUC_criteriaList_ctl03_BooleanTypeCriteria1_visibleCriteria");

    // bus
    $data[CSV_HEADER_BUS] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/Criteria/bus.ashx?h=42&amp;w=42");

    // tram
    $data[CSV_HEADER_TRAM] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/Criteria/tram.ashx?h=42&amp;w=42");

    // metro
    $data[CSV_HEADER_METRO] = string_contains_csvout($webpage,
        "http://www.belgianrail.be//~/media/Images/Station/Criteria/metro.ashx?h=42&amp;w=42");

    // wheelchair available
    $data[CSV_HEADER_WHEELCHAIR_AVAILABLE] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl01_BooleanTypeCriteria1_visibleCriteria");

    // ramp
    $data[CSV_HEADER_RAMP] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl02_BooleanTypeCriteria1_visibleCriteria");

    // disabled parking spots

    preg_match('/Aantal plaatsen voor gehandicapten:(.*?)(\d+)/si', $webpage, $matches);

    if ($matches != null && count($matches) == 3) {
        $data[CSV_HEADER_DISABLED_PARKING_SPOTS] = $matches[2];
    } else {
        $data[CSV_HEADER_DISABLED_PARKING_SPOTS] = '0';
    }

    // elevated platform
    $data[CSV_HEADER_ELEVATED_PLATFORM] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl05_BooleanTypeCriteria1_visibleCriteria");

    // escalator (up)
    $data[CSV_HEADER_ESCALATOR_UP] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl09_BooleanTypeCriteria1_visibleCriteria");

    // escalator (down)
    $data[CSV_HEADER_ESCALATOR_DOWN] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl10_BooleanTypeCriteria1_visibleCriteria");

    // elevator
    $data[CSV_HEADER_ELEVATOR_PLATFORM] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl08_BooleanTypeCriteria1_visibleCriteria");

    // hearing aid
    $data[CSV_HEADER_AUDIO_INDUCTION_LOOP] = string_contains_csvout($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl12_BooleanTypeCriteria1_visibleCriteria");

    // Next: opening hours of sales. When different hours are available for different types of sales, sales for national traffic are used. This is the first table of the section.
    $opening_hours_div = $html->getElementById("ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl04_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_titleModBlue")
        ->parent();

    // The number of scraped days
    $opening_hours_scraped = 0;

    $opening_hours_fields = [CSV_HEADER_SALES_OPEN_MONDAY, CSV_HEADER_SALES_CLOSE_MONDAY, CSV_HEADER_SALES_OPEN_TUESDAY, CSV_HEADER_SALES_CLOSE_TUESDAY, CSV_HEADER_SALES_OPEN_WEDNESDAY, CSV_HEADER_SALES_CLOSE_WEDNESDAY,
                             CSV_HEADER_SALES_OPEN_THURSDAY, CSV_HEADER_SALES_CLOSE_THURSDAY, CSV_HEADER_SALES_OPEN_FRIDAY, CSV_HEADER_SALES_CLOSE_FRIDAY, CSV_HEADER_SALES_OPEN_SATURDAY,
                             CSV_HEADER_SALES_CLOSE_SATURDAY, CSV_HEADER_SALES_OPEN_SUNDAY, CSV_HEADER_SALES_CLOSE_SUNDAY];

    foreach ($opening_hours_div->find(".criteria-opening-hours") as $table) {
        if ($opening_hours_scraped > 0) {
            continue;
        }
        foreach ($table->find("li.hoursTable div div") as $value) {
            $hours = trim($value->find("span")[1]->plaintext);
            $open = substr($hours, 0, 5);
            $close = substr($hours, -5);

            $data[$opening_hours_fields[$opening_hours_scraped]] = $open;
            $opening_hours_scraped++;

            $data[$opening_hours_fields[$opening_hours_scraped]] = $close;
            $opening_hours_scraped++;
        }
    }
    while ($opening_hours_scraped < 14) {
        $data[$opening_hours_fields[$opening_hours_scraped]] = '';
        $opening_hours_scraped++;
        $data[$opening_hours_fields[$opening_hours_scraped]] = '';
        $opening_hours_scraped++;
    }

    return $data;
}

/**
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @return bool            True if the needle was found
 */
function string_contains($haystack, $needle)
{
    return (strpos($haystack, $needle) !== false);
}

/**
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @return string          String representation, 1 if the needle was found, 0 if not.
 */
function string_contains_csvout($haystack, $needle)
{
    return string_contains($haystack, $needle) ? "1" : "0";
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
        die($csvPath . ' could not be opened!');
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