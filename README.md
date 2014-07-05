# All stations in Belgium

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github.

## Fields we collect in stations.csv

 * `URI`: this is the URI where we can find more information (such as the real-time departures) about this station (this already contains the ID of the NMBS/SNCB as well)
 * `longitude`: the longitude of the station
 * `latitude`: the latitude of the station
 * `name`: the most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name)
 * `alternate-fr`: name in French, only if other name available
 * `alternate-nl`: name in Dutch, only if other name available
 * `alternate-de`: name in German, only if other name available
 * `alternate-en`: name in English, only if other name available
 * dbpedia-uri: the URI for usage in the Linked Dataset of http://dbpedia.org, if any

## Fields we collect in pictures.csv

 * `name` (primary key): name of the file of the picture in your Pictures directory
 * `URI`: http URI to station (find the URI in stations.csv)
 * `license`: add CC0, CC BY, or CC BY SA
 * `author`: the author of the image
 * `source`: add a URL to the source of the file


## Build

Using scripts, we convert this to JSON-LD. In order to run the script, run this command:

```bash
./bin/build.js
```

The resulting JSON is put in the build/ directory. Don't edit this file manually. This is the output when you request JSON at https://irail.be/stations/NMBS. For example:

```bash
curl -H "accept: application/json" https://irail.be/stations/NMBS
```

## Other scripts for reconciliation

### dbpedia

Under construction, but we will build scripts which check long and lats against e.g., the dbpedia URIs and get more information about them.

### Open Refine

Because this is a CSV file, you can also use it easily with Open Refine. More info here: http://openrefine.org.
