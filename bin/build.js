#!/usr/bin/env node
/* Pieter Colpaert */
var N3 = require('n3');
var { namedNode, literal } = N3.DataFactory;
var csv = require('ya-csv');
var jsonld = require('jsonld');
var { Command, Option } = require('commander');
var program = new Command();

console.error("irail-stations by Pieter Colpaert <pieter@iRail.be> - http://hello.irail.be - use --help to discover more functions");
program
  .version('0.1.5')
  .addOption(new Option('-f, --format <format>', 'Output format')
    .argParser(value => value.toLowerCase())
    .choices(['json', 'trig', 'nquads'])
    .default('json'))
  .parse(process.argv);

var format = program.opts().format;

//Prefixes used within this code
var prefixes = {
  "rdfs": "http://www.w3.org/2000/01/rdf-schema#",
  "foaf": "http://xmlns.com/foaf/0.1/",
  "dcterms": "http://purl.org/dc/terms/",
  "geo": "http://www.w3.org/2003/01/geo/wgs84_pos#",
  "gn": "http://www.geonames.org/ontology#",
  "gtfs": "http://vocab.gtfs.org/terms#",
  "st":"http://semweb.mmlab.be/ns/stoptimes#"
};

//JSON-LD context for the JSON-LD serialisation
var context = {
  "name": "http://xmlns.com/foaf/0.1/name",
  "longitude": {
    "@id":"http://www.w3.org/2003/01/geo/wgs84_pos#long",
    "@type":"http://www.w3.org/2001/XMLSchema#float"
  },
  "latitude": {
    "@id":"http://www.w3.org/2003/01/geo/wgs84_pos#lat",
    "@type":"http://www.w3.org/2001/XMLSchema#float"
  },
  "alternative":"http://purl.org/dc/terms/alternative",
  "country":{
    "@type" : "@id",
    "@id" : "http://www.geonames.org/ontology#parentCountry"
  },
  "avgStopTimes":"http://semweb.mmlab.be/ns/stoptimes#avgStopTimes"
};

//Hardcoded array to be able to map country codes to geonames' URIs
var countryURIs = {
  "hu": "http://sws.geonames.org/719819/",
  "sk": "http://sws.geonames.org/3057568/",
  "gb": "http://sws.geonames.org/2635167/",
  "es": "http://sws.geonames.org/2510769/",
  "se": "http://sws.geonames.org/2661886/",
  "no": "http://sws.geonames.org/3144096/",
  "hr": "http://sws.geonames.org/3202326/",
  "sl": "http://sws.geonames.org/3190538/",
  "de": "http://sws.geonames.org/2921044/",
  "at": "http://sws.geonames.org/2782113/",
  "lu": "http://sws.geonames.org/2960313/",
  "it": "http://sws.geonames.org/3175395/",
  "nl": "http://sws.geonames.org/2750405/",
  "ch": "http://sws.geonames.org/2658434/",
  "dk": "http://sws.geonames.org/2623032/",
  "fr": "http://sws.geonames.org/3017382/",
  "be": "http://sws.geonames.org/2802361/",
  "pt": "http://sws.geonames.org/2264397/",
  "cz": "http://sws.geonames.org/3077311/",
};

var filename = __dirname + "/../stations.csv";
var reader = csv.createCsvFileReader(filename, {columnsFromHeader:true, 'separator': ','});

var writer;
if (format !== "trig") {
  writer = new N3.Writer({ format: 'N-Quads' });
} else {
  writer = new N3.Writer({ format: 'TriG', prefixes: prefixes });
}

//CSV reader: processes a row and add the triples to the n-quads or trig writer
reader.addListener('data', function (data) {
  var station = namedNode(data["URI"]);
  var graph = namedNode("http://irail.be/stations/NMBS");
  var float = namedNode("http://www.w3.org/2001/XMLSchema#float");
  writer.addQuad(station, namedNode(prefixes.foaf + "name"), literal(data["name"]), graph);
  for (var language of ['en', 'fr', 'nl', 'de']) {
    if (data["alternative-" + language]) {
      writer.addQuad(station, namedNode(prefixes.dcterms + "alternative"), literal(data["alternative-" + language], language), graph);
    }
  }
  writer.addQuad(station, namedNode(prefixes.gn + "parentCountry"), namedNode(countryURIs[data["country-code"]]), graph);
  writer.addQuad(station, namedNode(prefixes.geo + "long"), literal(data["longitude"], float), graph);
  writer.addQuad(station, namedNode(prefixes.geo + "lat"), literal(data["latitude"], float), graph);
  writer.addQuad(station, namedNode(prefixes.st + "avgStopTimes"), literal(data["avg_stop_times"]), graph);
});

//When the CSV processing is done: print the requested serialisation
reader.addListener('end', function () {
  writer.end(async function (error, output) {
    if (error) {
      console.error("Problem: " + error);
      process.exitCode = 1;
    } else {
      if (format !== "json") {
        console.log(output);
      } else {
        try {
        const doc = await jsonld.fromRDF(output, {format: 'application/n-quads'});
        const compacted = await jsonld.compact(doc, context);
        var jsonresult = JSON.stringify(compacted);
        //ugly fix for https://github.com/iRail/stations/issues/8
        jsonresult = jsonresult.replace(/"alternative":({.*?})/gi, "\"alternative\":[$1]");
        console.log(jsonresult);
        } catch (error) {
          console.error("Problem: " + error);
          process.exitCode = 1;
        }
      }
    }
  });
});
