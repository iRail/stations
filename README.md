# All stations in Belgium
[![Build Status](https://travis-ci.org/iRail/stations.svg)](https://travis-ci.org/iRail/stations)
[![Dependency Status](https://david-dm.org/iRail/stations.svg)](https://david-dm.org/iRail/stations)
[![Software License](https://img.shields.io/badge/license-CC0-brightgreen.svg?style=flat)](https://creativecommons.org/publicdomain/zero/1.0/)

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github. Furthermore, we have a PHP composer/packagist library for you to go from station name to ID and vice versa and we convert the CSV file to JSON-LD for maximum semantic interoperability.

## Fields we collect

### stations.csv
This file describes all NMBS/SNCB stations in Belgium. A station can have multiple platforms (stops), which are described in `stops.csv`.

 * `URI`: this is the URI where we can find more information (such as the real-time departures) about this station (this already contains the ID of the NMBS/SNCB as well)
 * `longitude`: the longitude of the station
 * `latitude`: the latitude of the station
 * `name`: the most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name)
 * `alternative-fr`: alt. name in French, if available
 * `alternative-nl`: alt. name in Dutch, if available
 * `alternative-de`: alt. name in German, if available
 * `alternative-en`: alt. name in English, if available
 * `country-code`: the code of the country the station belongs to
 * `avg_stop_times`: the average number of vehicles stopping each day in this station (_computed field_)
 * `official_transfer_time`: the time needed for an average person to make a transfer in this station, according to official sources (NMBS/SNCB) (_computed field_)
 
### stops.csv
This file describes all NMBS/SNCB stops in Belgium. Each platform is a separate stop location. All fields are computed using `gtfs_data_extractor.php`.
 * `URI`: this is the URI where we can find more information about this stop/platform (exists out of URI of the parent station + '#' + platform code) 
 * `parent_stop`: this is the URI of the parent stop defined in stations.csv
 * `longitude`: the longitude of the stop
 * `latitude`: the latitude of the stop
 * `name`: stop name
 * `alternative-fr`: alt. name in French, if available
 * `alternative-nl`: alt. name in Dutch, if available
 * `alternative-de`: alt. name in German, if available
 * `alternative-en`: alt. name in English, if available
 * `platform`: the platform code (can also consist of letters, so do not treat this as a number!)

### facilities.csv
This file describes facilities available in NMBS/SNCB stations. All fields are computed using `web_facilities_extractor.php`.

 * `URI`: The URI identifying this station.
 * `name`: The name of this station.
 * `street`: The street of this station's address.
 * `zip`: The postal code of this station's address.
 * `city`: The city of this station's address.
 * `ticket_vending_machine`: Whether or not ticket vending machines are available. Note: Ticket vending machines might be located inside a building (and can be locked when the station is closed). 
 * `luggage_lockers`: Whether or not luggage lockers are available.
 * `free_parking`:  Whether or not free parking spots are available. 
 * `taxi`:  Whether or not parking spots for taxis / waiting taxis are available.
 * `bicycle_spots`:  Whether or not bicycle parking spots are available.
 * `blue-bike`:  Whether or not the has blue-bikes (rental bikes).
 * `bus`: Whether or not transferring to a bus line is possible in this station.
 * `tram`: Whether or not transferring to a tram line is possible in this station.
 * `metro`: Whether or not transferring to a metro line is possible in this station.
 * `wheelchair_available`: Whether or not the station has wheelchairs available.
 * `ramp`: Whether or not the station has a ramp for wheelchair users to board a train.
 * `disabled_parking_spots`: The number of reserved parking spots for travellers with a disability.
 * `elevated_platform`: Whether or not the station has elevated platforms.
 * `escalator_up`: Whether or not the station has an ascending escalator from or to the platform(s).
 * `escalator_down`: Whether or not the station has a descending escalator from or to the platform(s).
 * `elevator_platform`: Whether or not the station has an elevator to the platform(s).
 * `audio_induction_loop `: Whether or not an Audio induction loop (Dutch: Ringleiding) is available.
 * `sales_open_monday` - `sales_open_sunday`: The time at which ticket boots open on this day of the week.
 * `sales_close_monday` -`sales_close_sunday`:The time at which ticket boots close on this day of the week.

## How we collect data

This repository contains two PHP scripts which can load all data from the NMBS GTFS public data and the NMBS website. These scripts can be used to generate all CSV files from scratch, and to update existing files.

Manual changes and corrections can be made to `stations.csv`. It is recommended to use the `stations.csv` file in this repository as a starting point instead of using the scripts to generate this file, as the repository versions includes manual fixes to station names and translations.

**Any changes made to `stops.csv` or `facilities.csv` will be overwritten by the scripts.** Therefore, any pull requests with the sole purpose of updating/modifying these files won't be accepted 

Missing stations and missing fields in `stations.csv` are automatically added when the gtfs_data_extractor tool runs.

### How to make a correction
Corrections to names, translations and locations can be made by adjusting fields in `stations.csv`:

* Names or translations will never be overwritten by the scripts.
* Names in `facilities.csv` or `stops.csv` are derived from the names in stations.csv, meaning you only need to update `stations.csv`.
* The GTFS data extractor script will warn on wrong locations, but won't correct them.

If you want to make a correction to `facilities.csv` or `stops.csv`, don't fix the files, but fix the scripts instead, and let these scripts run to update the file for you.

## Build the RDF or JSON-LD

Using scripts, we convert this to JSON-LD. In order to run the script, run this command:

First time run this in your terminal (nodejs needs to be installed on your system):

```bash
npm install
```

Or install it globally using the [npm package](https://www.npmjs.com/package/irail-stations) (you will need to run this again when there's an update to the stations file):
```bash
npm install -g irail-stations
```

From then on, you can always run:

```bash
# using this repo
./bin/build.js
# or with the global package:
irail-stations
```

For extra commands, check:

```bash
./bin/build.js --help
# or
irail-stations --help
```

We currently support the output formats __TRiG__, __N-Quads__ and __JSON-LD__ (default)

## In case you just want to reuse the data

### Latest update over HTTP

JSON-LD is available at https://irail.be/stations/NMBS if you add the right accept header. For example, using curl on the command line, you would do this:

```bash
curl -H "accept: application/json" https://irail.be/stations/NMBS
```

If you want to change this output, please change the CSV files over here first (we love pull requests)

### In PHP project

Using composer (mind that we also require nodejs to be installed on your system):
```bash
composer require irail/stations
```

Then you can use the stations in your code as follows:
```php
use irail\stations\Stations;
// getStations() returns a json-ld document
$brusselsnorth = Stations::getStations("Brussels North")->{"@graph"}[0];
// getStationByID($id) returns a simple object with the station or null
$ghentstpieters = Stations::getStationByID("http://irail.be/stations/NMBS/008892007");
```

Don't forget to do a `composer update` from time to time to update the data

## License

[CC0](https://creativecommons.org/publicdomain/zero/1.0/): This dataset belongs to the public domain. You're free to reuse it without any restrictions whatsoever.

If you contribute to this repository, you agree that your contributions will be licensed under the CC0 open data license.

We do appreciate a link back to this repository, or a mention of the [iRail project](http://hello.irail.be).
