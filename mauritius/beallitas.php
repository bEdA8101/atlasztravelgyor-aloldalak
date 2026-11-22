<?php
// A Mauritius-oldal élő ajánlatainak beállításai (az oldal mellett beallitas.php; a program: idopontlista.php).
// Forrás: a győri CeSYS-oldal (atlasztravelgyor.hu) kínálata, budapesti és bécsi indulás, euróban.
// Az ajánlatok rész (api.php?a=ajanlatok) szállodánként a legkedvezőbb aktuális ajánlatot adja; a családi ár
// (api.php?a=csalad&h=d<szállodaazonosító>) csak az ajánlatok között szereplő szállodákra kérdezhető le.

const FORRAS = 'hu';
const PENZNEM = 'EUR';
const NAPOK_ELORE = 400;
const REPTER_NEVEK = ['Budapest' => 'Budapestről', 'Bécs' => 'Bécsből'];

const SZALLODAK = [];

const AJANLATOK = [
    // Mauritius régiói a győri CeSYS-oldalon (Rodrigues sziget nélkül)
    'celok' => [1776, 1774, 1770, 1778, 1780, 1771, 1772, 1777, 1779, 1773, 1775, 1781, 1799, 1977, 1978, 2008, 2009, 2243],
    'napok' => [8, 12], 'ejszakak' => [7, 8, 9, 10], 'min_csillag' => 4, 'repterek_nev' => ['Budapest', 'Bécs'], 'db' => 9,
];
