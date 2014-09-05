#!/usr/bin/env node
/* Pieter Colpaert */

var fs = require('fs');
var N3 = require('n3');
var csv = require('ya-csv');
var jsonld = require('jsonld');
var N3Util = N3.util;

var context = {
  "name": "http://www.w3.org/2000/01/rdf-schema#label",
  "longitude":"http://www.w3.org/2003/01/geo/wgs84_pos#long",
  "latitude":"http://www.w3.org/2003/01/geo/wgs84_pos#lat",
  "alternative":"http://purl.org/dc/terms/alternative"
};

var reader = csv.createCsvFileReader('stations.csv', {columnsFromHeader:true, 'separator': ','});

var graph = [];

var parser = N3.Parser();
parser.parse(function (error, triple, prefixes) { 
  if (triple) {
    graph.push(triple);
  } else if(error) {
    console.error(error);
  }
});


parser.addChunk('@prefix foaf: <http://xmlns.com/foaf/0.1/>.\n');
parser.addChunk('@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#>.\n');
parser.addChunk('@prefix dcterms: <http://purl.org/dc/terms/>.\n');
parser.addChunk('@prefix geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>.\n');

reader.addListener('data', function (data) {
  var turtlestring = "<" + data["URI"] + ">" + " rdfs:label \"" + data["name"] + "\" .\n";
  if (data["alternative-en"] !== "") {
    turtlestring += "<" + data["URI"] + ">" + " dcterms:alternative \"" + data["alternative-en"] + "\"@en .\n";
  }

  if (data["alternative-fr"] !== "") {
    turtlestring += "<" + data["URI"] + ">" + " dcterms:alternative \"" + data["alternative-fr"] + "\"@fr .\n";
  }
  if (data["alternative-nl"] !== "") {
    turtlestring += "<" + data["URI"] + ">" + " dcterms:alternative \"" + data["alternative-nl"] + "\"@nl .\n";
  }
  if (data["alternative-de"] !== "") {
    turtlestring += "<" + data["URI"] + ">" + " dcterms:alternative \"" + data["alternative-de"] + "\"@de .\n";
  }

  turtlestring += "<" + data["URI"] + ">" + " geo:long \"" + data["longitude"] + "\" .\n";
  turtlestring += "<" + data["URI"] + ">" + " geo:lat \"" + data["latitude"] + "\" .\n";

  parser.addChunk(turtlestring);
});

reader.addListener('end', function () {
  parser.end();
  var nquads = "";
  for (var i = 0; i < graph.length ; i++ ) {
    if (graph[i].object.substr(0,4) === "http") {
      nquads += "<" + graph[i].subject + "> <" + graph[i].predicate + "> <" + graph[i].object + "> <http://irail.be/stations/NMBS/> .\n";
    } else {
      nquads += "<" + graph[i].subject + "> <" + graph[i].predicate + "> " + graph[i].object + " <http://irail.be/stations/NMBS/> .\n";
    }
  }
  jsonld.fromRDF(nquads, {format: 'application/nquads'}, function(err, doc) {
    jsonld.compact(doc, context, function(err, compacted) {
      var jsonresult = JSON.stringify(compacted);
      //ugly fix for https://github.com/iRail/stations/issues/8
      jsonresult = jsonresult.replace(/"alternative":({.*?})/gi,"\"alternative\":[$1]");
      console.log(jsonresult);
    });
  });
});
