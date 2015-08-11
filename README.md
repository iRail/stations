# All stations in Belgium

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github.

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

Don't edit this output manually. This is the output when you request JSON at https://irail.be/stations/NMBS. For example:

```bash
curl -H "accept: application/json" https://irail.be/stations/NMBS
```

If you want to change this output, please change the CSV files over here first (we love pull requests)

## License

[CC0](https://creativecommons.org/publicdomain/zero/1.0/): This dataset belongs to the public domain. You're free to reuse it without any restrictions whatsoever.

If you contribute to this repository, you agree that your contributions will be licensed under the CC0 open data license.

We do appreciate a link back to this repository, or a mention of the [iRail project](http://hello.irail.be).
