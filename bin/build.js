#!/usr/bin/env node
/* Pieter Colpaert */

var fs = require('fs');
var N3 = require('n3');
var csv = require('ya-csv');
var jsonld = require('jsonld');


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


parser.addChunk('@prefix foaf: <http://xmlns.com/foaf/spec/#>.\n');
parser.addChunk('@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#>.\n');
parser.addChunk('@prefix dcterms: <http://purl.org/dc/terms/>.\n');

reader.addListener('data', function (data) {
  var turtlestring = "<" + data["URI"] + ">" + " rdfs:label \"" + data["name"] + "\" .\n";
  if (data["alternative-nl"]
  
  parser.addChunk(turtlestring);
});
reader.addListener('end', function () {
  parser.end();
  console.log(graph);
});

