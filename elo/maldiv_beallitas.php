<?php
// A Maldív-oldal élő ajánlatainak beállításai (az oldal mellett beallitas.php; a program: idopontlista.php).
// Forrás: a győri CeSYS-oldal (atlasztravelgyor.hu) kínálata, bécsi indulás, 7 éjszaka, teljes panzió és
// all inclusive, euróban (ahogy a központi oldal is mutatja).

const FORRAS = 'hu';
const PENZNEM = 'EUR';
const NAPOK_ELORE = 400;
const MAX_NAP = 60;
const REPTER_NEVEK = ['Bécs' => 'Bécsből'];

const MALDIV = ['napok' => [8, 10], 'ejszakak' => [7], 'ellatasok' => ['Teljes ellátás', 'All inclusive'], 'repterek_nev' => ['Bécs']];

const SZALLODAK = [
    'embudu' => ['id' => 79843, 'di' => 383] + MALDIV,
    'dhonveli' => ['id' => 71559, 'di' => 389] + MALDIV,
    'royal' => ['id' => 73986, 'di' => 381] + MALDIV,
];
