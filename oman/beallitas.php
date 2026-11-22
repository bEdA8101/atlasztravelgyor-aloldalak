<?php
// Az ománi oldal élő időpontlistájának beállításai (az oldal mellett beallitas.php; a program: idopontlista.php).
// Forrás: atlastravel.sk (ugyanaz, amiből a központi oldal is dolgozik), Pozsony és Kassa, 7 éjszaka.

const FORRAS = 'sk';
const NAPOK_ELORE = 400;
const REPTER_NEVEK = ['Bratislava' => 'Pozsonyból', 'Košice' => 'Kassáról'];

// A szlovák szobanevek (2 felnőttes elhelyezés, „2+0 …”) és az oldal magyar szobatípusai, az oldal
// oszlopsorrendjében. Családi összetételnél a „2+1 …” stb. változatok ugyanezt a magyar nevet kapják.
const SZALLODAK = [
    'fanar' => ['id' => 40936, 'di' => 2147, 'napok' => [8, 8], 'ejszakak' => [7], 'szobak' => [
        'Deluxe szoba' => '2+0 Deluxe izba',
        'Deluxe szoba, lagúnára néző' => '2+0 Deluxe izba s výhľadom na lagúnu',
        'Deluxe szoba, tengerre néző' => '2+0 Deluxe izba s výhľadom na more',
        'Premium Deluxe szoba, tengerre néző' => '2+0 Deluxe izba s výhľadom na more a so službami premium all inclusive',
        'Családi szoba' => '2+0 Rodinná izba',
        'Premium családi szoba' => '2+0 Rodinná izba so službami premium all inclusive',
        'Deluxe lakosztály' => '2+0 Deluxe suita',
        'Jacuzzi lakosztály' => '2+0 Jacuzzi suita',
        'Premium Deluxe lakosztály' => '2+0 Deluxe suita so službami premium all inclusive',
        '1 hálószobás apartman' => '2+0 Apartmán s 1 spálňou',
        '2 hálószobás apartman' => '2+0 Apartmán s 2 spálňami',
    ]],
    'rotana' => ['id' => 33869, 'di' => 2147, 'napok' => [8, 8], 'ejszakak' => [7], 'szobak' => [
        'Standard szoba, kertre néző' => '2+0 Štandardná izba s výhľadom do záhrady',
        'Családi szoba, kertre néző' => '2+0 Rodinná izba s výhľadom do záhrady',
        'Deluxe szoba, tengerre néző' => '2+0 Deluxe izba s výhľadom na more',
        'Deluxe családi szoba, tengerre néző' => '2+0 Deluxe rodinná izba s výhľadom na more',
        'Club Rotana Deluxe szoba, tengerre néző' => '2+0 Deluxe izba s výhľadom na more Club Rotana',
        'Club Rotana Deluxe családi szoba, tengerre néző' => '2+0 Deluxe rodinná izba s výhľadom na more Club Rotana',
        'Club Rotana Premium szoba' => '2+0 Premium izba Club Rotana',
        'Club Rotana lakosztály, tengerre néző' => '2+0 Suita s výhľadom na more Club Rotana',
    ]],
];
