# All stations in Belgium

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github.

## Fields we collect

 * URI: this is the URI where we can find more information (such as the real-time departures) about this station (this already contains the ID of the NMBS/SNCB as well)
 * longitude: the longitude of the station
 * latitude: the latitude of the station
 * name: the most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name)
 * alternate-fr: name in French
 * alternate-nl: name in Dutch
 * alternate-de: name in German
 * dbpedia-uri: the URI for usage in the Linked Dataset of http://dbpedia.org, if any
 * images: a JSON array of links towards images to be used for the iRail and BeTrains applications

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
