<?php
// Omán (Salalah) élő árai az oman.atlasztravelgyor.hu oldalhoz. A frissites.py az oldal mellé másolja
// (api.php néven). Forrás: az atlastravel.sk nyilvános időpontlistája (ugyanaz, amit a központi oldal is
// átvesz): a Fanar és a Salalah Rotana 7 éjszakás, 2 felnőttes, all inclusive időpontjai Pozsonyból és
// Kassáról, szobatípusonként a legkedvezőbb ár/fő (€).
//
// Válasz: {ok, frissitve, hotels: {fanar: {cols: [...], dates: [[dátum, indulási idő, legjobb ár,
// [árak a cols sorrendjében], indulási hely], ...]}, rotana: {...}}}. Ugyanaz a forma, mint az oldal
// beágyazott HOTELS táblázata; ha ez a program nem válaszol, az oldal a beágyazott árakat mutatja.

declare(strict_types=1);
require __DIR__ . '/cesys.php';

const FORRAS = 'https://www.atlastravel.sk/searchmasks/generation_dates_terms';
const UTICEL = 2147;        // Salalah
const ERVENYES = 1800;      // fél óra
const NAPOK_ELORE = 400;
const MAX_OLDAL = 8;        // 200 időpont / oldal
const REPTEREK = ['Bratislava' => 'Pozsonyból', 'Košice' => 'Kassáról'];

// A szlovák szobanevek (2 felnőttes elhelyezés, „2+0 …”) és az oldal magyar szobatípusai. A felsorolás
// sorrendje az oldal oszlopsorrendje; ami itt nincs (pl. pótágyas vagy 2+2-es változat), az kimarad.
const SZALLODAK = [
    'fanar' => ['id' => 40936, 'szobak' => [
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
    'rotana' => ['id' => 33869, 'szobak' => [
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

$kulcs = 'oman-2';
$friss = gyorsitotar_olvas($kulcs, ERVENYES);
if ($friss !== null) {
    json_valasz($friss);
}

/** Egy szálloda összes időpontja (2 felnőtt), oldalanként; hiba esetén null. */
function szalloda_idopontjai(int $szalloda): ?array
{
    $ma = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Budapest'));
    $alap = [
        ['di[]', UTICEL], ['df', $ma->format('Y-m-d')], ['dt', $ma->modify('+' . NAPOK_ELORE . ' days')->format('Y-m-d')],
        ['ac', 2], ['cc', 0], ['ti', 1], ['uf', 8], ['ut', 8], ['sp', 'false'], ['ti_f', 1], ['uf_f', 8], ['ut_f', 8],
        ['ns_f', 'false'], ['sp_f', 'false'], ['rop', 200], ['hotel', $szalloda], ['skip', 0],
        ['source', 'cesys_default'], ['sm', 1], ['sort', 'date_from'], ['direction', 'asc'], ['currency', 'EUR'],
    ];
    $osszes = [];
    for ($oldal = 1; $oldal <= MAX_OLDAL; $oldal++) {
        $adat = cesys_get(FORRAS . '?' . cesys_lekerdezes(array_merge($alap, [['pg', $oldal]])));
        if ($adat === null || !isset($adat['dates']) || !is_array($adat['dates'])) {
            return $oldal === 1 ? null : $osszes;
        }
        array_push($osszes, ...$adat['dates']);
        if (empty($adat['more_exists'])) {
            break;
        }
    }
    return $osszes;
}

/** Az időpontok az oldal formájában: indulási naponként egy sor, szobatípusonként a legkisebb ár/fő. */
function oldal_sorai(array $idopontok, array $szobak): array
{
    $oszlop = array_flip(array_values($szobak));   // szlovák név => oszlopszám
    $napok = [];
    foreach ($idopontok as $x) {
        $ar = $x['price_from']['EUR'] ?? null;
        $nev = html_entity_decode((string)($x['room_original_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hely = REPTEREK[$x['airport'] ?? ''] ?? null;
        $nap = (string)($x['date_from'] ?? '');
        if (!is_numeric($ar) || $hely === null || (int)($x['duration_night'] ?? 0) !== 7
            || !isset($oszlop[$nev]) || !preg_match('/^\d{4}-\d\d-\d\d$/', $nap)
            || ($x['composition']['adults'] ?? 2) !== 2 || !empty($x['composition']['children'])) {
            continue;
        }
        $n = &$napok[$nap];
        $n['arak'] ??= array_fill(0, count($szobak), null);
        $i = $oszlop[$nev];
        $ar = round((float)$ar, 2);
        if ($n['arak'][$i] === null || $ar < $n['arak'][$i]) {
            $n['arak'][$i] = $ar;
        }
        $n['helyek'][$hely] = true;
        $ido = $x['outbound_flight_time'] ?? null;
        if (is_string($ido) && preg_match('/^\d\d:\d\d$/', $ido) && ($hely === 'Pozsonyból' || empty($n['ido']))) {
            $n['ido'] = $ido;
        }
        unset($n);
    }
    ksort($napok);
    $sorok = [];
    foreach ($napok as $nap => $n) {
        $arak = array_filter($n['arak'], fn($v) => $v !== null);
        if (!$arak) {
            continue;
        }
        $helyek = array_keys($n['helyek']);
        $hely = count($helyek) > 1 ? 'Pozsonyból vagy Kassáról' : $helyek[0];
        $sor = [$nap, $n['ido'] ?? null, min($arak), $n['arak']];
        if ($hely !== 'Pozsonyból') {
            $sor[] = $hely;
        }
        $sorok[] = $sor;
    }
    return $sorok;
}

$eredmeny = ['ok' => true, 'frissitve' => date('c'), 'hotels' => []];
foreach (SZALLODAK as $nev => $sz) {
    $idopontok = szalloda_idopontjai($sz['id']);
    if ($idopontok === null) {
        continue;
    }
    $sorok = oldal_sorai($idopontok, $sz['szobak']);
    if ($sorok) {
        $eredmeny['hotels'][$nev] = ['cols' => array_keys($sz['szobak']), 'dates' => $sorok];
    }
}

if (!$eredmeny['hotels']) {
    // a forrás most nem érhető el: a legutóbbi (legfeljebb egynapos) adat, ha van
    $regi = gyorsitotar_olvas($kulcs, GYORSITOTAR_TARTALEK);
    json_valasz($regi ?? ['ok' => false, 'uzenet' => 'Az élő árak most nem tölthetők be.'], $regi ? 200 : 503);
}
gyorsitotar_ir($kulcs, $eredmeny);
json_valasz($eredmeny);
