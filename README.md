# All stations in Belgium
[![Build Status](https://travis-ci.org/iRail/stations.svg)](https://travis-ci.org/iRail/stations)
[![Dependency Status](https://david-dm.org/iRail/stations.svg)](https://david-dm.org/iRail/stations.svg)
[![Software License](https://img.shields.io/badge/license-CC0-brightgreen.svg?style=flat)](https://creativecommons.org/publicdomain/zero/1.0/)

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github. Furthermore, we have a PHP composer/packagist library for you to go from station name to ID and vice versa and we convert the CSV file to JSON-LD for maximum semantic interoperability.

## Fields we collect

### stations.csv

 * `URI`: this is the URI where we can find more information (such as the real-time departures) about this station (this already contains the ID of the NMBS/SNCB as well)
 * `longitude`: the longitude of the station
 * `latitude`: the latitude of the station
 * `name`: the most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name)
 * `alternative-fr`: alt. name in French, if available
 * `alternative-nl`: alt. name in Dutch, if available
 * `alternative-de`: alt. name in German, if available
 * `alternative-en`: alt. name in English, if available
 * `country-code`: the code of the country the station belongs to
 * `avg_stop_times`: the average stop times per day in this station
 
### stops.csv

 * `URI`: this is the URI where we can find more information about this stop/platform (exists out of URI of the parent station + '#' + platform code)
 * `parent_stop`: this is the URI of the parent stop defined in stations.csv
 * `longitude`: the longitude of the stop
 * `latitude`: the latitude of the stop
 * `name`: parent station name
 * `platform`: the platform code

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
