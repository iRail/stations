#!/usr/bin/env node
/* Pieter Colpaert */
var fs = require('fs');
var N3 = require('n3');
var N3Util = require('n3').Util;
var csv = require('ya-csv');
var jsonld = require('jsonld');
var program = require('commander');

console.error("irail-stations by Pieter Colpaert <pieter@iRail.be> - http://hello.irail.be - use --help to discover more functions");
program
  .version('0.1.5')
  .option('-f --format [json,trig,nquads]', 'Format', /^(json|trig|nquads)$/i)
  .parse(process.argv);

var format;

if (!process.argv.slice(2).length) {
  format = "json";
} else {
  format = program.format;
}

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
  "fr" : "http://sws.geonames.org/3017382/",
  "be" : "http://sws.geonames.org/2802361/",
  "nl" : "http://sws.geonames.org/2750405/",
  "de" : "http://sws.geonames.org/2921044/",
  "lu" : "http://sws.geonames.org/2960313/",
  "gb" : "http://sws.geonames.org/2635167/",
  "ch" : "http://sws.geonames.org/2658434/"
};

var filename = __dirname + "/../stations.csv";
var reader = csv.createCsvFileReader(filename, {columnsFromHeader:true, 'separator': ','});

var writer;
if (format !== "trig") {
  writer = N3.Writer({ format: 'N-Triples', prefixes: prefixes });
} else {
  writer = N3.Writer({ prefixes: prefixes });
}

//CSV reader: processes a row and add the triples to the n-quads or trig writer
reader.addListener('data', function (data) {
  //writer.addTriple(data["URI"], "rdfs:type","gtfs:Station", "http://irail.be/stations/NMBS");
  writer.addTriple(data["URI"], N3Util.expandPrefixedName("foaf:name", prefixes),'"' + data["name"] + '"', "http://irail.be/stations/NMBS");
  if (data["alternative-en"] !== "") {
    writer.addTriple(data["URI"], N3Util.expandPrefixedName("dcterms:alternative", prefixes),'"' + data["alternative-en"] + '"@en', "http://irail.be/stations/NMBS");
  }

  if (data["alternative-fr"] !== "") {
    writer.addTriple(data["URI"], N3Util.expandPrefixedName("dcterms:alternative", prefixes),'"' + data["alternative-fr"] + '"@fr', "http://irail.be/stations/NMBS");
  }
  if (data["alternative-nl"] !== "") {
    writer.addTriple(data["URI"], N3Util.expandPrefixedName("dcterms:alternative", prefixes),'"' + data["alternative-nl"] + '"@nl', "http://irail.be/stations/NMBS");
  }
  if (data["alternative-de"] !== "") {
    writer.addTriple(data["URI"], N3Util.expandPrefixedName("dcterms:alternative", prefixes),'"' + data["alternative-de"] + '"@de', "http://irail.be/stations/NMBS");
  }
  writer.addTriple(data["URI"], N3Util.expandPrefixedName("gn:parentCountry", prefixes), countryURIs[data["country-code"]], "http://irail.be/stations/NMBS");
  writer.addTriple(data["URI"], N3Util.expandPrefixedName("geo:long", prefixes),'"' + data["longitude"] + '"', "http://irail.be/stations/NMBS");
  writer.addTriple(data["URI"], N3Util.expandPrefixedName("geo:lat", prefixes),'"' + data["latitude"] + '"', "http://irail.be/stations/NMBS");
  writer.addTriple(data["URI"], N3Util.expandPrefixedName("st:avgStopTimes",prefixes),'"' + data["avg_stop_times"] + '"', "http://irail.be/stations/NMBS");
});

//When the CSV processing is done: print the requested serialisation
reader.addListener('end', function () {
  writer.end(function (error, output) {
    if (error) {
      console.error("Problem: " + error);
    } else {
      if (format !== "json") {
        console.log(output);
      } else {
        jsonld.fromRDF(output, {format: 'application/nquads'}, function(err, doc) {
          jsonld.compact(doc, context, function(err, compacted) {
            var jsonresult = JSON.stringify(compacted);
            //ugly fix for https://github.com/iRail/stations/issues/8
            jsonresult = jsonresult.replace(/"alternative":({.*?})/gi,"\"alternative\":[$1]");
            console.log(jsonresult);
          });   
        });
      }
    }
  });
});
