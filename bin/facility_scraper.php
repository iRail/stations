<?php
require("./includes/simple_html_dom.php");

/**
 * Scrape the NMBS website for facilities available in a station
 *
 * @author Bertware
 **/

const STATIONS_CSV = '../stations.csv';
const STATION_FACILITIES_CSV = "../facilities.csv";
const NMBS_BASE_URL = "http://www.belgianrail.be/Station.ashx?lang=nl&stationId=";


// Open the CSV file that needs a patch.
$handle = fopen(STATIONS_CSV, 'r');
if (! $handle) {
    die('stations.csv file could not be opened!');
}

// The new CSV file will be compiled in memory, in the $result variable.
echo 'Compiling new CSV file...' . PHP_EOL;

// Skip the first line in the stations file (csv header)
fgets($handle);

// Create header for new file
$result = 'URI,name,street,zip,city,ticket_vending_machine,luggage_lockers,free_parking,taxi,bicycle_spots,blue-bike,bus,tram,metro,wheelchair_available,ramp,disabled_parking_spots,elevated_platform,escalator_up,escalator_down,elevator_platform,hearing_aid_signal,sales_open_monday,sales_close_monday,sales_open_tuesday,sales_close_tuesday,sales_open_wednesday,sales_close_wednesday,sales_open_thursday,sales_close_thursday,sales_open_friday,sales_close_friday,sales_open_saturday,sales_close_saturday,sales_open_sunday,sales_close_sunday,' . PHP_EOL;

// Go over all lines.
while (($line = fgets($handle)) !== false) {
    // Line format:
    // http://irail.be/stations/NMBS/008821006,Antwerpen-Centraal,Anvers-Central,,,Antwerp-Central,be,4.421101,51.2172
    $line = trim($line);

    // Get the name and URI.
    $uri = explode(',', $line)[0];
    $name = explode(',', $line)[1];
    $id = substr($uri, -7);

    $newLine = $uri . ',' . $name . ',' . get_station_facilities_line($id) . PHP_EOL;
    print $newLine;

    // Create a CSV line for this station
    $result .= $newLine;
}
// Close this handle.
fclose($handle);

echo 'Saving...' . PHP_EOL;

// Write everything to a new file
file_put_contents(STATION_FACILITIES_CSV, $result);

echo 'Done!';

function get_station_facilities_line($id)
{

    $url = NMBS_BASE_URL . $id;
    $webpage = file_get_contents($url);

    // Start by checking if the id of the "opening hours sales points" is included. If the id is not included, the site might have changed, and a warning should be shown!
    if (! string_contains($webpage,
        "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl04_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_tabLink")
    ) {
        print "WARNING: The NMBS website seems to have changed! Station $id is being skipped!";
        return ',,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,';
    }
    $result = "";

    // Address

    $html = str_get_html($webpage);
    $result .= str_replace(',', '', trim($html->find(".title-address")[0]->find("span")[0]->plaintext)) . ',';
    $result .= str_replace(',', '', substr(trim($html->find(".title-address")[0]->find("span")[1]->plaintext),0,4)) . ',';
    $result .= str_replace(',', '', substr(trim($html->find(".title-address")[0]->find("span")[1]->plaintext),5)) . ',';

    // Facilities

    // ticket machines
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/ticket_machines.ashx?h=42&amp;w=42") . ",";

    // lockers
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/luggage_lockers.ashx?h=26&amp;w=16") . ",";

    // free parking
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/parking.ashx?h=26&amp;w=26") . ",";

    // taxi
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/taxi.ashx?h=26&amp;w=26") . ",";

    // bicycle storage
    // No icon, so use the id of the list element instead.
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl10_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_allCriteriaUC_criteriaList_ctl02_BooleanTypeCriteria1_visibleCriteria") . ",";

    // blue bikes
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl10_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_allCriteriaUC_criteriaList_ctl03_BooleanTypeCriteria1_visibleCriteria") . ",";

    // bus
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/Criteria/bus.ashx?h=42&amp;w=42") . ",";

    // tram
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/tram.ashx?h=42&amp;w=42") . ",";

    // metro
    $result .= string_contains_csvout($webpage,
            "http://www.belgianrail.be//~/media/Images/Station/CriteriaGroup/metro.ashx?h=42&amp;w=42") . ",";

    // wheelchair available
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl01_BooleanTypeCriteria1_visibleCriteria") . ",";

    // ramp
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl02_BooleanTypeCriteria1_visibleCriteria") . ",";

    // disabled parking spots

    preg_match('/Aantal plaatsen voor gehandicapten:(.*?)(\d+)/is', $webpage, $matches);
    if ($matches && count($matches ) == 2){
        $result .= $matches[2] . ',';
    } else {
        $result .= '0,';
    }


    // elevated platform
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl05_BooleanTypeCriteria1_visibleCriteria") . ",";

    // escalator (up)
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl09_BooleanTypeCriteria1_visibleCriteria") . ",";

    // escalator (down)
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl10_BooleanTypeCriteria1_visibleCriteria") . ",";

    // elevator
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl08_BooleanTypeCriteria1_visibleCriteria") . ",";

    // hearing aid
    $result .= string_contains_csvout($webpage,
            "ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl03_AllCriteriaGroup_criteriaGroupList_ctl01_ReducedMobilityCriteria1_allCriteriaUC_criteriaList_ctl12_BooleanTypeCriteria1_visibleCriteria") . ",";

    // Next: opening hours of sales. When different hours are available for different types of sales, sales for national traffic are used. This is the first table of the section.
    $opening_hours_div = $html->getElementById("ctl00_ctl00_bodyPlaceholder_bodyPlaceholder_AllStationCriteriaGroupsList_ctl04_AllCriteriaGroup_criteriaGroupList_ctl01_DefaultCriteriaGroup1_titleModBlue")
        ->parent();

    // The number of scraped days
    $opening_hours_scraped = 0;

    foreach ($opening_hours_div->find(".criteria-opening-hours") as $table) {
        if ($opening_hours_scraped > 0) {
            continue;
        }
        foreach ($table->find("li.hoursTable div div") as $value) {
            $hours = trim($value->find("span")[1]->plaintext);
            $open = substr($hours, 0, 5);
            $close = substr($hours, -5);

            // print "Found $hours open $open and close $close\n";
            $result .= $open . "," . $close . ",";

            // We found a table containing hours
            $opening_hours_scraped++;
        }
    }
    while ($opening_hours_scraped < 7){
        $result .= ',,';
        $opening_hours_scraped++;
    }

    return $result;
}

function string_contains($haystack, $needle)
{
    if (strpos($haystack, $needle) !== false) {
        return true;
    }

    return false;
}

function string_contains_csvout($haystack, $needle)
{
    return string_contains($haystack, $needle) ? "1" : "0";
}