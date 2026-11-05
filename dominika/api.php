<?php
// A dominika-oldal élő adatai (az oldal mellett api.php néven): a közös keresőmotor (motor.php)
// a dominikai beállításokkal. Bécsi indulás átszállással (a győri CeSYS-oldal Amadeus-kínálata) és
// pozsonyi indulás közvetlen járattal (atlastravel.sk); csak 4 és 5 csillagos szállodák, csak
// Punta Cana, Bávaro és Bayahibe területén. Az ajánlatkérések és a foglalási igények a győri irodának
// mennek levélben; a foglalási igényhez a program ellenőrzi a kötelező utasadatokat. A kérések
// leírása: motor.php.

declare(strict_types=1);

const FORRASOK = [
    'at' => ['oldal' => 'https://www.atlasztravelgyor.hu', 'kereso' => '/am-utazas-kereses/2', 'cel' => ['ci', 'DO'], 'ai' => [6],
             'source' => 'amadeus_connector', 'sm' => 2, 'cimke' => 'Bécs', 'extra' => [['ar', 4]],
             'ellatas' => ['ai' => [['bi', 'AllInclusive']]]],
    'sk' => ['oldal' => 'https://www.atlastravel.sk', 'kereso' => '/vyhladavanie-zajazdov/1', 'cel' => ['ci', 46], 'ai' => [7],
             'source' => 'cesys_default', 'sm' => 1, 'cimke' => 'Pozsony', 'extra' => [['ar', 4]],
             'ellatas' => ['ai' => [['bi', 8]]], 'ellatas_idopont' => ['ai' => [['bi', 8], ['bi_f[]', 8]]]],
];
const EGYSZERRE = ['at' => 3, 'sk' => 2];   // egyszerre legfeljebb ennyi kérés forrásonként (kíméljük őket)
const OLDAL_NEV = 'Dominikai Köztársaság';
const OLDAL_MAPPA = 'dominika';
const LM_FORRASOK = ['sk', 'at'];
const HELY_MAX_UJ = 12;          // egy válaszhoz legfeljebb ennyi szálloda-adatlapot nézünk meg (térképes hely)
const MIN_CSILLAG = 4;
const TERULETEK = ['Punta Cana', 'Bávaro', 'Bayahibe'];
// a győri oldal területoldalai: a felsorolt szállodák és a terület térképes középpontja
const TERULET_OLDALAK = [
    'Bávaro' => 'https://www.atlasztravelgyor.hu/dominikai-koztarsasag/punta-cana/bavaro',
    'Bayahibe' => 'https://www.atlasztravelgyor.hu/dominikai-koztarsasag/bayahibe',
    'Punta Cana' => 'https://www.atlasztravelgyor.hu/dominikai-koztarsasag/punta-cana',
];
// tartalék középpontok (a győri területoldalakról, 2026. november), ha az oldalak épp nem érhetők el
const TERULET_KOZEPPONTOK = ['Punta Cana' => [18.5601, -68.3725], 'Bávaro' => [18.6853, -68.4501], 'Bayahibe' => [18.3505, -68.8284]];
// a térképes hely szerinti besorolás: Bávaro a középpontjától legfeljebb 9 km-re, Bayahibe (Dominicus) 10 km-re,
// a többi keleti szálloda (Cap Cana, Cabeza de Toro, Macao, Uvero Alto) Punta Cana, ha 40 km-en belül van
const TERULET_TAVOLSAG = ['Bávaro' => 9.0, 'Bayahibe' => 10.0, 'Punta Cana' => 40.0];
const TERULET_ERVENYES = 86400;

require __DIR__ . '/motor.php';
gyorsitotar_takaritas(['tanult-teruletek' => 400 * 86400, 'hibanaplo' => 400 * 86400, 'hely-' => 31 * 86400,
    'teruletek' => 8 * 86400]);

// ---------------------------------------------------------------- területek, csillagok

/** A terület neve egységes alakban (Punta Cana, Bávaro, Bayahibe), vagy null, ha egyik sem. */
function terulet_nev(string $s): ?string
{
    $s = mb_strtolower(strtr($s, ['á' => 'a', 'Á' => 'a']));
    if (strpos($s, 'bavaro') !== false) {
        return 'Bávaro';
    }
    if (strpos($s, 'bayahibe') !== false || strpos($s, 'dominicus') !== false) {
        return 'Bayahibe';
    }
    if (strpos($s, 'punta cana') !== false || strpos($s, 'cap cana') !== false) {
        return 'Punta Cana';
    }
    return null;
}

/** A győri területoldalak: szállodák (névszavak -> terület) és a területek középpontja. Naponta frissül. */
function teruletek_gyorrol(): array
{
    $tarolt = gyorsitotar_olvas('teruletek', TERULET_ERVENYES);
    if ($tarolt !== null) {
        return $tarolt;
    }
    $szallodak = [];
    $helyek = [];
    $hiba = false;
    foreach (TERULET_OLDALAK as $terulet => $cim) {   // a Bávaro-oldal előbb: ott a pontosabb besorolás
        $oldal = cesys_html($cim);
        if ($oldal === null) {
            $hiba = true;
            continue;
        }
        preg_match_all('/<h2 class="hotel[^"]*">\s*<a [^>]*>(.*?)<\/a>/s', $oldal, $nevek);
        foreach ($nevek[1] as $nev) {
            $kulcs = implode(' ', nev_szavak(szoveg($nev)));
            if ($kulcs !== '' && !isset($szallodak[$kulcs])) {
                $szallodak[$kulcs] = $terulet;
            }
        }
        if (preg_match('/id="map-destination".*?data-latitude="(-?[0-9.]+)"\s*data-longitude="(-?[0-9.]+)"/s', $oldal, $k)) {
            $helyek[$terulet] = [(float)$k[1], (float)$k[2]];
        }
    }
    $adat = ['szallodak' => $szallodak, 'helyek' => $helyek + TERULET_KOZEPPONTOK];
    if ($hiba) {
        return (gyorsitotar_olvas('teruletek', 7 * 86400) ?? []) + $adat;
    }
    gyorsitotar_ir('teruletek', $adat);
    return $adat;
}

/** A kézzel szerkeszthető táblázat (regiok.json „szallodak”: szállodanév -> terület), névszavak szerint. */
function kezi_teruletek(): array
{
    $adat = is_file(__DIR__ . '/regiok.json') ? json_decode((string)file_get_contents(__DIR__ . '/regiok.json'), true) : null;
    $tabla = [];
    foreach (($adat['szallodak'] ?? []) as $nev => $terulet) {
        $t = is_string($terulet) ? terulet_nev($terulet) : null;
        $kulcs = implode(' ', nev_szavak((string)$nev));
        if ($t !== null && $kulcs !== '') {
            $tabla[$kulcs] = $t;
        }
    }
    return $tabla;
}

/** Térképes hely -> terület (Bávaro előnyt kap a Punta Cana-i középponttal szemben), vagy null. */
function terulet_helybol(array $k, array $helyek): ?string
{
    foreach (['Bávaro', 'Bayahibe', 'Punta Cana'] as $t) {
        if (isset($helyek[$t]) && legkozelebbi_hely($k, [$t => $helyek[$t]], TERULET_TAVOLSAG[$t]) !== null) {
            return $t;
        }
    }
    return null;
}

/**
 * A motor horga: csak 4–5 csillagos szállodák Punta Cana, Bávaro és Bayahibe területén. A terület
 * sorrendben: a kézi táblázat, a győri Bávaro- és Bayahibe-oldal, a szálloda győri adatlapján levő
 * térképes hely, a forrás saját besorolása, a győri Punta Cana-oldal, a szálloda neve, végül a más
 * forrásban már látott azonos nevű szálloda. Ami egyik területhez sem tartozik, az kimarad.
 */
function oldal_szallodak(array $eredmeny, array $kereses): array
{
    $kezi = kezi_teruletek();
    $gyor = teruletek_gyorrol();
    $tanult = gyorsitotar_olvas('tanult-teruletek', 365 * 86400) ?? [];
    $ujTanult = false;
    $teruletek = [];
    $kellHely = [];
    foreach ($eredmeny['hotels'] as $i => $h) {
        if ($h['stars'] > 0 && $h['stars'] < MIN_CSILLAG) {
            continue;
        }
        $kulcs = implode(' ', nev_szavak($h['name']));
        $gyori = $gyor['szallodak'][$kulcs] ?? regio_nevbol($h['name'], $gyor['szallodak']);
        if (isset($kezi[$kulcs])) {
            $teruletek[$i] = $kezi[$kulcs];
        } elseif ($gyori === 'Bávaro' || $gyori === 'Bayahibe') {
            $teruletek[$i] = $gyori;
        } else {
            $teruletek[$i] = null;
            if (strpos((string)$h['url'], GYOR . '/') === 0 && $h['hotelId']) {
                $kellHely[$i] = $h;   // a győri adatlap térképes helye dönt (ha még nincs meg, a többi szabály)
            }
        }
    }
    $helyek = $kellHely ? szalloda_helyek($kellHely) : [];
    foreach ($teruletek as $i => $t) {
        $h = $eredmeny['hotels'][$i];
        $kulcs = implode(' ', nev_szavak($h['name']));
        $biztos = $t !== null;   // kézi táblázat vagy győri Bávaro-/Bayahibe-oldal
        if ($t === null && !empty($helyek[$i])) {
            $t = terulet_helybol($helyek[$i], $gyor['helyek']) ?? '-';   // '-': a térkép szerint máshol van
            $biztos = true;
        }
        if ($t === null) {
            $forras = terulet_nev($h['region']);
            $nevbol = terulet_nev($h['name']);
            if ($forras === 'Punta Cana' && $nevbol === 'Bávaro') {
                $forras = 'Bávaro';   // Bávaro Punta Cana része: a név pontosít
            }
            $biztos = $forras !== null && $kereses['src'] === 'sk';   // a pozsonyi kínálat pontosan besorol
            $t = $forras ?? (($gyor['szallodak'][$kulcs] ?? null) === 'Punta Cana' ? 'Punta Cana' : null) ?? $nevbol ?? ($tanult[$kulcs] ?? null);
        }
        if ($biztos && $t !== null && $kulcs !== '' && ($tanult[$kulcs] ?? null) !== $t) {
            $tanult[$kulcs] = $t;
            $ujTanult = true;
        }
        $teruletek[$i] = $t === '-' ? null : $t;
    }
    if ($ujTanult) {
        gyorsitotar_ir('tanult-teruletek', $tanult);
    }
    $maradt = [];
    foreach ($teruletek as $i => $t) {
        if ($t !== null) {
            $maradt[] = ['region' => $t] + $eredmeny['hotels'][$i];
        }
    }
    $eredmeny['hotels'] = $maradt;
    return $eredmeny;
}

// ---------------------------------------------------------------- ajánlatkérés, foglalási igény

/**
 * A motor horga: az ajánlatkérés és a foglalási igény ellenőrzése, a levél sorai. Ajánlatkéréshez elég
 * a név, az elérhetőség és az utasok száma (gyerekek életkora). Foglalási igényhez szobánként minden
 * utas teljes neve (ahogy az úti okmányban szerepel), állampolgársága és születési dátuma, valamint a fő
 * (szerződő) utas lakcíme, e-mail-címe és telefonszáma kötelező, és el kell fogadni az adatkezelést.
 */
function oldal_level(array $adat, string $fajta): array
{
    $sorok = [];
    $ut = [['hotel', 'Szálloda'], ['area', 'Terület'], ['source', 'Indulás'], ['term', 'Időpont'], ['room', 'Szoba'],
        ['board', 'Ellátás'], ['price', 'Ár (tájékoztató)']];
    foreach ($ut as [$mezo, $cimke]) {
        if (sor($adat[$mezo] ?? '') !== '') {
            $sorok[] = $cimke . ': ' . sor($adat[$mezo], 300);
        }
    }
    if ($fajta !== 'book') {
        $nev = sor($adat['name'] ?? '', 120);
        $felnott = (int)($adat['adults'] ?? 0);
        if ($nev === '' || $felnott < 1 || $felnott > 20) {
            return ['hiba' => 'Kérjük, adja meg a nevét és a felnőttek számát.'];
        }
        $korok = array_values(array_filter((array)($adat['childrenAges'] ?? []), fn($k) => is_numeric($k) && $k >= 0 && $k <= 17));
        $sorok[] = '';
        $sorok[] = 'Név: ' . $nev;
        $sorok[] = 'E-mail: ' . sor($adat['email'] ?? '', 160);
        if (sor($adat['phone'] ?? '') !== '') {
            $sorok[] = 'Telefon: ' . sor($adat['phone'], 40);
        }
        $sorok[] = 'Utasok: ' . $felnott . ' felnőtt' . ($korok ? ', ' . count($korok) . ' gyerek (' . implode(', ', array_map(fn($k) => (int)$k . ' éves', $korok)) . ')' : '');
        foreach ([['rooms', 'Szobák száma'], ['period', 'Időszak'], ['nights', 'Éjszakák'], ['departure', 'Indulási hely'],
                  ['areaWish', 'Terület'], ['budget', 'Keret'], ['message', 'Megjegyzés']] as [$mezo, $cimke]) {
            if (sor($adat[$mezo] ?? '') !== '') {
                $sorok[] = $cimke . ': ' . sor($adat[$mezo], $mezo === 'message' ? 3000 : 200);
            }
        }
        return ['targy' => 'Ajánlatkérés', 'sorok' => $sorok];
    }

    // foglalási igény
    $szobak = $adat['roomsDetail'] ?? null;
    if (!is_array($szobak) || count($szobak) < 1 || count($szobak) > 6) {
        return ['hiba' => 'Kérjük, adja meg a szobákat és az utasokat.'];
    }
    if (empty($adat['consent'])) {
        return ['hiba' => 'A foglalási igényhez kérjük, fogadja el az adatkezelési tájékoztatót.'];
    }
    $sorok[] = '';
    $sorok[] = 'Szobák száma: ' . count($szobak);
    $utasSzam = 0;
    foreach (array_values($szobak) as $si => $szoba) {
        $utasok = is_array($szoba['travellers'] ?? null) ? array_values($szoba['travellers']) : [];
        $felnott = (int)($szoba['adults'] ?? 0);
        $korok = array_values((array)($szoba['childrenAges'] ?? []));
        if ($felnott < 1 || $felnott > 6 || count($korok) > 4 || count($utasok) !== $felnott + count($korok)) {
            return ['hiba' => 'A(z) ' . ($si + 1) . '. szoba utasainak adatai hiányosak.'];
        }
        $sorok[] = '';
        $sorok[] = ($si + 1) . '. szoba: ' . $felnott . ' felnőtt' . ($korok ? ', ' . count($korok) . ' gyerek' : '')
            . (sor($szoba['room'] ?? '') !== '' ? ' – ' . sor($szoba['room'], 200) : '');
        foreach ($utasok as $ui => $u) {
            $nev = sor($u['name'] ?? '', 120);
            $allampolgarsag = sor($u['nationality'] ?? '', 60);
            $szul = sor($u['birth'] ?? '', 10);
            if (mb_strlen($nev) < 3 || strpos($nev, ' ') === false || $allampolgarsag === '' || !szuletesi_datum($szul)) {
                return ['hiba' => 'Kérjük, adja meg a(z) ' . ($si + 1) . '. szoba ' . ($ui + 1) . '. utasának teljes nevét, állampolgárságát és születési dátumát.'];
            }
            $sorok[] = '   ' . ($ui + 1) . '. ' . ($ui < $felnott ? 'felnőtt' : 'gyerek') . ': ' . $nev . ' – ' . $allampolgarsag . ' – szül.: ' . $szul;
            $utasSzam++;
        }
    }
    $f = is_array($adat['contact'] ?? null) ? $adat['contact'] : [];
    $lakcim = sor($f['address'] ?? '', 200);
    $telefon = sor($f['phone'] ?? '', 40);
    if ($lakcim === '' || strlen((string)preg_replace('/\D+/', '', $telefon)) < 8) {
        return ['hiba' => 'Kérjük, adja meg a fő utas lakcímét és telefonszámát.'];
    }
    $sorok[] = '';
    $sorok[] = 'Fő (szerződő) utas: ' . sor($szobak[array_key_first($szobak)]['travellers'][0]['name'] ?? '', 120);
    $sorok[] = 'Lakcím: ' . $lakcim;
    $sorok[] = 'E-mail: ' . sor($adat['email'] ?? '', 160);
    $sorok[] = 'Telefon: ' . $telefon;
    $sorok[] = 'Utasok összesen: ' . $utasSzam;
    if (sor($adat['message'] ?? '') !== '') {
        $sorok[] = 'Megjegyzés: ' . sor($adat['message'], 3000);
    }
    $sorok[] = 'Adatkezelési tájékoztató: elfogadva';
    return ['targy' => 'Foglalási igény', 'sorok' => $sorok];
}

// ---------------------------------------------------------------- kérések

kereso_kerelmek();
