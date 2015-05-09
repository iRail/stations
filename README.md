# All stations in Belgium

We try to maintain a list of all the stations in Belgium using CSV so everyone can help to maintain it on github.

## Fields we collect

 * `URI`: this is the URI where we can find more information (such as the real-time departures) about this station (this already contains the ID of the NMBS/SNCB as well)
 * `longitude`: the longitude of the station
 * `latitude`: the latitude of the station
 * `name`: the most neutral name of the station (e.g., in Wallonia use the French name, for Brussels use both, for Flanders use nl name)
 * `alternative-fr`: alt. name in French, if available
 * `alternative-nl`: alt. name in Dutch, if available
 * `alternative-de`: alt. name in German, if available
 * `alternative-en`: alt. name in English, if available
 * `country-code`: the code of the country the station belongs to
 * _TODO:_ `dbpedia-uri`: the URI for usage in the Linked Dataset of http://dbpedia.org, if any

## Fields we collect in pictures.csv

_Currently, this file is only a proposal_

 * `name` (primary key): name of the file of the picture in your Pictures directory
 * `URI`: http URI to station (find the URI in stations.csv)
 * `license`: add CC0, CC BY, or CC BY SA
 * `author`: the author of the image
 * `source`: add a URL to the source of the file

## Build the RDF or JSON-LD

Using scripts, we convert this to JSON-LD. In order to run the script, run this command:

First time run this in your terminal (you will need nodejs and you will need to run it from where you've cloned this repository):

```bash
npm install
```

From then on, you can always run (again, using this as the working directory):

```bash
./bin/build.js
```

For extra commands, check:

```bash
./bin/build.js --help
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
