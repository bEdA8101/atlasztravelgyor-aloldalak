<?php
// A RIU-oldal élő időpontlistájának beállításai (az oldal mellett beallitas.php; a program: idopontlista.php).
// Forrás: a győri CeSYS-oldal (atlasztravelgyor.hu) kínálata, a két dominikai RIU-nál az atlastravel.sk (Pozsony).
// Szállodánként a központi oldal kínálata szerint: éjszakák, ellátás és indulási hely. Ahol Budapestről most
// nincs indulás (pl. Maldív), a bécsi is látszik; az oldal ezt az időpontnál megnevezi.

const FORRAS = 'hu';
const NAPOK_ELORE = 400;
const MAX_NAP = 40;   // szállodánként legfeljebb ennyi indulási nap a listában (a legközelebbiek)
const REPTER_NEVEK = ['Budapest' => 'Budapestről', 'Debrecen' => 'Debrecenből', 'Bécs' => 'Bécsből', 'Pozsony' => 'Pozsonyból',
                      'Bratislava' => 'Pozsonyból', 'Kassa' => 'Kassáról'];

const AI = ['All inclusive'];
const FP = ['Félpanzió'];
const BP = ['Budapest'];
const BP_BECS = ['Budapest', 'Bécs'];

const SZALLODAK = [
    'riu-kaya-palazzo' => ['id' => 70083, 'di' => 720, 'napok' => [8, 8], 'ejszakak' => [7], 'ellatasok' => AI,
                           'repterek_nev' => ['Budapest', 'Debrecen']],
    'riu-madeira' => ['id' => 86215, 'di' => 1928, 'napok' => [8, 9], 'ejszakak' => [7], 'ellatasok' => AI, 'repterek_nev' => BP],
    'riu-palace-maspalomas' => ['id' => 83754, 'di' => 1800, 'napok' => [8, 11], 'ejszakak' => [7, 9], 'ellatasok' => FP, 'repterek_nev' => BP],
    'riu-palace-tenerife' => ['id' => 83635, 'di' => 1786, 'napok' => [4, 6], 'ejszakak' => [3, 4], 'ellatasok' => FP, 'repterek_nev' => BP],
    'riu-palace-meloneras' => ['id' => 86217, 'di' => 1798, 'napok' => [5, 7], 'ejszakak' => [4, 5], 'ellatasok' => FP, 'repterek_nev' => BP],
    'riu-buena-vista' => ['id' => 70508, 'di' => 659, 'napok' => [4, 6], 'ejszakak' => [3, 4], 'ellatasok' => AI, 'repterek_nev' => BP],
    'riu-arecas' => ['id' => 83685, 'di' => 1786, 'napok' => [5, 6], 'ejszakak' => [4], 'ellatasok' => FP, 'repterek_nev' => BP],
    'riu-dubai' => ['id' => 115732, 'di' => 601, 'napok' => [8, 12], 'ejszakak' => [7, 10], 'ellatasok' => AI, 'repterek_nev' => BP_BECS],
    'riu-baobab' => ['id' => 137073, 'di' => 541, 'napok' => [8, 12], 'ejszakak' => [7, 10], 'ellatasok' => AI, 'repterek_nev' => BP_BECS],
    'riu-turquoise' => ['id' => 97330, 'di' => 1777, 'napok' => [8, 10], 'ejszakak' => [7], 'ellatasok' => AI, 'repterek_nev' => BP],
    'riu-palace-swahili' => ['id' => 135754, 'di' => 1947, 'napok' => [6, 9], 'ejszakak' => [5, 6, 7], 'ellatasok' => AI, 'repterek_nev' => BP],
    'riu-palace-zanzibar' => ['id' => 103366, 'di' => 1947, 'napok' => [6, 9], 'ejszakak' => [5, 6, 7], 'ellatasok' => AI, 'repterek_nev' => BP],
    'riu-jambo' => ['id' => 119944, 'di' => 1947, 'napok' => [6, 9], 'ejszakak' => [5, 6, 7], 'ellatasok' => AI, 'repterek_nev' => BP],
    'riu-palace-maldives' => ['id' => 114322, 'di' => 1980, 'napok' => [8, 10], 'ejszakak' => [7], 'ellatasok' => AI, 'repterek_nev' => BP_BECS],
    'riu-atoll' => ['id' => 112832, 'di' => 1980, 'napok' => [6, 10], 'ejszakak' => [5, 7], 'ellatasok' => AI, 'repterek_nev' => BP_BECS],
    'riu-republica' => ['forras' => 'sk', 'id' => 59710, 'di' => 140, 'ejszakak' => [7, 14], 'ellatasok' => AI, 'repterek_nev' => ['Bratislava']],
    'riu-bambu' => ['forras' => 'sk', 'id' => 21760, 'di' => 131, 'ejszakak' => [7, 14], 'ellatasok' => AI, 'repterek_nev' => ['Bratislava']],
];
