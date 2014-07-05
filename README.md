# All stations in Belgium

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github.

## Fields we collect

 * URI: this is the URI where we can find more information (such as the real-time departures) about this station (this already contains the ID of the NMBS/SNCB as well)
 * longitude: the longitude of the station
 * latitude: the latitude of the station
 * name: the most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name)
 * alternative-fr: alt. name in French, if available
 * alternative-nl: alt. name in Dutch, if available
 * alternative-de: alt. name in German, if available
 * alternative-en: alt. name in English, if available
 * dbpedia-uri: the URI for usage in the Linked Dataset of http://dbpedia.org, if any
 * images: a JSON array of links towards images to be used for the iRail and BeTrains applications

## Build the RDF/ JSON-LD

Using scripts, we convert this to JSON-LD. In order to run the script, run this command:

First time run (you will need nodejs):
```bash
npm install
```

From then on, you can always run:

```bash
./bin/build.js
```

The resulting JSON appears on stdout. Don't edit this output manually. This is the output when you request JSON at https://irail.be/stations/NMBS. For example:

```bash
curl -H "accept: application/json" https://irail.be/stations/NMBS
```

If you want to change this output, please change the CSV files over here first (hint: we love pull requests)

## Data tools and links

### Open Refine

Because this is a CSV file, you can also use it easily with Open Refine. More info here: http://openrefine.org.

### dbpedia

Under construction, but we will build scripts which check long and lats against e.g., the dbpedia URIs and get more information about them.

