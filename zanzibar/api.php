<?php
// A zanzibar-oldal élő adatai (az oldal mellett api.php néven): a közös keresőmotor (motor.php)
// a zanzibári beállításokkal, és a partszakaszok pótlása (ahol a forrás csak „Zanzibar”-t ír).
// Budapesti és bécsi indulás a győri CeSYS-oldalról, pozsonyi az atlastravel.sk-ról. Az
// ajánlatkérések és foglalások a győri irodának mennek levélben. A kérések leírása: motor.php.

declare(strict_types=1);

const FORRASOK = [
    'hu' => ['oldal' => 'https://www.atlasztravelgyor.hu', 'kereso' => '/utazas-kereses/1', 'cel' => ['di', 685], 'ai' => [18, 19, 24],
             'source' => 'cesys_default', 'sm' => 1, 'cimke' => 'Budapest'],
    'at' => ['oldal' => 'https://www.atlasztravelgyor.hu', 'kereso' => '/am-utazas-kereses/2', 'cel' => ['di', 454], 'ai' => [6],
             'source' => 'amadeus_connector', 'sm' => 2, 'cimke' => 'Bécs'],
    'sk' => ['oldal' => 'https://www.atlastravel.sk', 'kereso' => '/vyhladavanie-zajazdov/1', 'cel' => ['di', 685], 'ai' => [7],
             'source' => 'cesys_default', 'sm' => 1, 'cimke' => 'Pozsony'],
];
const EGYSZERRE = ['hu' => 3, 'at' => 3, 'sk' => 2];   // egyszerre legfeljebb ennyi kérés forrásonként (kíméljük őket)
const OLDAL_NEV = 'Zanzibár';
const OLDAL_MAPPA = 'zanzibar';
const REGIO_OLDAL = 'https://www.atlasztravelgyor.hu/tanzania/zanzibar';   // a győri oldal partszakasz-aloldalai
const REGIO_ERVENYES = 86400;                                              // a partszakaszok listáját naponta frissítjük
// ha egy szállodát nem találunk a partszakasz-oldalakon, a nevében szereplő helynév dönt
const HELYNEVEK = ['Stone Town', 'Nungwi', 'Kendwa', 'Kiwengwa', 'Pongwe', 'Pingwe', 'Uroa', 'Matemwe', 'Mnemba', 'Paje',
    'Jambiani', 'Bwejuu', 'Michamvi', 'Dongwe', 'Kizimkazi', 'Makunduchi', 'Chwaka', 'Mbweni', 'Marumbi', 'Kilindi', 'Mchangani'];

require __DIR__ . '/motor.php';
gyorsitotar_takaritas(['tanult-partszakaszok' => 400 * 86400, 'hibanaplo' => 400 * 86400, 'hely-' => 31 * 86400,
    'partszakaszok' => 8 * 86400, 'pozsonyi-partszakaszok' => 8 * 86400]);

// ---------------------------------------------------------------- partszakaszok

/** A motor horga: a keresés találatainak partszakasza (pozsonyi utaknál a győri pozsonyi kínálat is). */
function oldal_szallodak(array $eredmeny, array $k): array
{
    return regiok_potlasa($eredmeny, $k['src'] === 'sk');
}

/**
 * Ahol a forrás csak „Zanzibar”-t ad meg partszakasznak, ott pótoljuk (a tárolt keresési adat változatlan
 * marad). Sorrend: a kézi táblázat, a győri partszakasz-oldalak, (pozsonyi utaknál) a győri oldal pozsonyi
 * kínálata, a más forrásokban már látott azonos nevű szálloda, a névben szereplő helynév, végül a szálloda
 * győri adatlapján levő térképes hely.
 */
function regiok_potlasa(array $eredmeny, bool $pozsonyi = false): array
{
    $kezi = regio_tablazat();
    $gyor = regio_terkep();
    $tanult = gyorsitotar_olvas('tanult-partszakaszok', 365 * 86400) ?? [];
    $ujTanult = false;
    $altalanos = fn($r) => $r === '' || strcasecmp($r, 'Zanzibar') === 0;
    // amit a forrás pontosan megad, azt megjegyezzük a többi forrás azonos nevű szállodáihoz
    foreach ($eredmeny['hotels'] as $h) {
        $szavak = nev_szavak($h['name']);
        if ($szavak && !$altalanos($h['region']) && !kombinacio($h['name']) && !isset($tanult[implode(' ', $szavak)])) {
            $tanult[implode(' ', $szavak)] = $h['region'];
            $ujTanult = true;
        }
    }
    $terkep = $kezi['szallodak'] + $gyor['szallodak'];   // a kézi táblázat elsőbbséget élvez
    if ($pozsonyi) {
        $terkep += pozsonyi_gyori_terkep();
    }
    $terkep += $tanult;
    $keresd = function (string $nev) use ($terkep): ?string {
        $reszek = kombinacio($nev);
        if (!$reszek) {
            return regio_nevbol($nev, $terkep);
        }
        $regiok = [];   // két szállodás út: mindkét rész partszakasza (ha mindkettő ismert)
        foreach ($reszek as $resz) {
            $r = regio_nevbol($resz, $terkep);
            if ($r === null) {
                return null;
            }
            $regiok[$r] = true;
        }
        return implode(' / ', array_keys($regiok));
    };
    $hianyzik = [];
    foreach ($eredmeny['hotels'] as $i => $h) {
        if ($altalanos($h['region'])) {
            $r = $keresd($h['name']);
            if ($r !== null) {
                $eredmeny['hotels'][$i]['region'] = $r;
            } elseif (!kombinacio($h['name']) && strpos((string)$h['url'], GYOR . '/') === 0 && $h['hotelId']) {
                $hianyzik[$i] = $h;
            }
        }
    }
    // a még ismeretlenekhez: a győri adatlap térképes helye, és a legközelebbi partszakasz
    if ($hianyzik) {
        $helyek = $kezi['helyek'] + $gyor['helyek'];
        foreach (szalloda_helyek($hianyzik) as $i => $k) {
            $r = $k ? legkozelebbi_hely($k, $helyek) : null;
            if ($r !== null) {
                $eredmeny['hotels'][$i]['region'] = $r;
                $tanult[implode(' ', nev_szavak($hianyzik[$i]['name']))] = $r;
                $ujTanult = true;
            }
        }
    }
    if ($ujTanult) {
        gyorsitotar_ir('tanult-partszakaszok', $tanult);
    }
    return $eredmeny;
}

/**
 * A győri oldal pozsonyi indulású zanzibári kínálata (névszavak -> partszakasz). Az atlastravel.sk
 * kártyáin sokszor csak „Zanzibar” áll, a győri oldal ugyanezeknél a szállodáknál megadja a partszakaszt.
 * Naponta egyszer kérjük le (alapbeállítású keresés pozsonyi indulással).
 */
function pozsonyi_gyori_terkep(): array
{
    $tarolt = gyorsitotar_olvas('pozsonyi-partszakaszok', REGIO_ERVENYES);
    if ($tarolt !== null) {
        return $tarolt;
    }
    $holnap = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Budapest'));
    $e = szallodak_nyers(['src' => 'hu', 'df' => $holnap->format('Y-m-d'), 'dt' => $holnap->modify('+185 days')->format('Y-m-d'),
        'uf' => 5, 'ut' => 21, 'ai' => FORRASOK['sk']['ai'], 'pt' => 15000000, 'ac' => 2, 'ages' => []]);
    if ($e === null) {
        return gyorsitotar_olvas('pozsonyi-partszakaszok', 7 * 86400) ?? [];
    }
    $terkep = [];
    foreach (regiok_potlasa($e)['hotels'] as $h) {
        $szavak = nev_szavak($h['name']);
        if ($szavak && $h['region'] !== '' && strcasecmp($h['region'], 'Zanzibar') !== 0) {
            $terkep[implode(' ', $szavak)] = $h['region'];
        }
    }
    gyorsitotar_ir('pozsonyi-partszakaszok', $terkep);
    return $terkep;
}

/** „Hotel A & Hotel B” (két szállodás út) -> ['Hotel A', 'B'], egyébként null. */
function kombinacio(string $nev): ?array
{
    $reszek = preg_split('/\s+&\s+(?=Hotel\s)/u', $nev);
    return count($reszek) > 1 ? $reszek : null;
}

/**
 * A győri oldal partszakasz-aloldalaiból: szállodák (névszavak -> partszakasz) és a partszakaszok
 * térképes középpontja (név -> [szélesség, hosszúság]). Naponta frissül.
 */
function regio_terkep(): array
{
    $tarolt = gyorsitotar_olvas('partszakaszok', REGIO_ERVENYES);
    if ($tarolt !== null) {
        return $tarolt;
    }
    $fo = cesys_html(REGIO_OLDAL);
    if ($fo === null) {
        return gyorsitotar_olvas('partszakaszok', 7 * 86400) ?? ['szallodak' => [], 'helyek' => []];
    }
    preg_match_all('#href="/tanzania/zanzibar/([a-z0-9-]+)"[^>]*>([^<]+)<#', $fo, $m, PREG_SET_ORDER);
    $reszek = [];
    foreach ($m as [, $utvonal, $nev]) {
        $reszek[$utvonal] = szoveg($nev);
    }
    $szallodak = [];
    $helyek = [];
    foreach (array_slice($reszek, 0, 20, true) as $utvonal => $regio) {
        $oldal = cesys_html(REGIO_OLDAL . '/' . $utvonal);
        if ($oldal === null) {
            continue;
        }
        preg_match_all('/<h2 class="hotel[^"]*">\s*<a [^>]*>(.*?)<\/a>/s', $oldal, $nevek);
        foreach ($nevek[1] as $nev) {
            $szavak = nev_szavak(szoveg($nev));
            if ($szavak) {
                $szallodak[implode(' ', $szavak)] = $regio;
            }
        }
        if (preg_match('/id="map-destination".*?data-latitude="(-?[0-9.]+)"\s*data-longitude="(-?[0-9.]+)"/s', $oldal, $k)) {
            $helyek[$regio] = [(float)$k[1], (float)$k[2]];
        }
    }
    $terkep = ['szallodak' => $szallodak, 'helyek' => $helyek];
    gyorsitotar_ir('partszakaszok', $terkep);
    return $terkep;
}

/**
 * A kézzel szerkeszthető táblázat (regiok.json): „szallodak” (szállodanév -> partszakasz, névszavak
 * szerint) és „helyek” (partszakasz -> [szélesség, hosszúság], a győri oldalon nem szereplő helyekhez).
 */
function regio_tablazat(): array
{
    static $tabla = null;
    if ($tabla === null) {
        $tabla = ['szallodak' => [], 'helyek' => []];
        $adat = is_file(__DIR__ . '/regiok.json') ? json_decode((string)file_get_contents(__DIR__ . '/regiok.json'), true) : null;
        foreach (($adat['szallodak'] ?? []) as $nev => $regio) {
            $szavak = nev_szavak((string)$nev);
            if ($szavak && is_string($regio) && $regio !== '') {
                $tabla['szallodak'][implode(' ', $szavak)] = $regio;
            }
        }
        foreach (($adat['helyek'] ?? []) as $nev => $k) {
            if (is_array($k) && count($k) === 2 && is_numeric($k[0]) && is_numeric($k[1])) {
                $tabla['helyek'][(string)$nev] = [(float)$k[0], (float)$k[1]];
            }
        }
    }
    return $tabla;
}

// ---------------------------------------------------------------- foglalási igény

/**
 * A motor horga: foglalási igénynél utasonként teljes név (ahogy az útlevélben szerepel), állampolgárság és
 * születési dátum, valamint a megrendelő lakcíme és telefonszáma kötelező. Ajánlatkérésnél az alap levél.
 */
function oldal_level(array $adat, string $fajta): ?array
{
    if ($fajta !== 'book') {
        return null;
    }
    $utasok = is_array($adat['travellersDetail'] ?? null) ? array_values($adat['travellersDetail']) : [];
    if (!$utasok || count($utasok) > 12) {
        return ['hiba' => 'Kérjük, adja meg az utasok adatait.'];
    }
    $lista = [];
    foreach ($utasok as $i => $u) {
        $nev = sor($u['name'] ?? '', 120);
        $allampolgarsag = sor($u['nationality'] ?? '', 60);
        $szul = sor($u['birth'] ?? '', 10);
        if (mb_strlen($nev) < 3 || strpos($nev, ' ') === false || $allampolgarsag === '' || !szuletesi_datum($szul)) {
            return ['hiba' => 'Kérjük, adja meg a(z) ' . ($i + 1) . '. utas teljes nevét, állampolgárságát és születési dátumát.'];
        }
        $lista[] = '    – ' . $nev . ' (' . sor($u['label'] ?? '', 30) . '), állampolgárság: ' . $allampolgarsag . ', született: ' . $szul;
    }
    if (sor($adat['address'] ?? '') === '' || strlen((string)preg_replace('/\D+/', '', (string)($adat['phone'] ?? ''))) < 8) {
        return ['hiba' => 'Kérjük, adja meg a lakcímét és a telefonszámát.'];
    }
    $sorok = [];
    foreach ([['hotel', 'Szálloda'], ['term', 'Időpont'], ['room', 'Szoba és ellátás'], ['from', 'Indulás'], ['price', 'Ár az oldalon']] as [$m, $c]) {
        if (sor($adat[$m] ?? '') !== '') {
            $sorok[] = $c . ': ' . sor($adat[$m], 300);
        }
    }
    $sorok[] = '';
    $sorok[] = 'Megrendelő: ' . sor($adat['name'] ?? '', 120);
    $sorok[] = 'Telefon: ' . sor($adat['phone'] ?? '', 40);
    $sorok[] = 'E-mail: ' . sor($adat['email'] ?? '', 160);
    $sorok[] = 'Lakcím: ' . sor($adat['address'] ?? '', 200);
    $sorok[] = '';
    $sorok[] = 'UTASOK (a nevek az útlevél szerint): ' . sor($adat['adults'] ?? '', 3) . ' felnőtt, ' . sor($adat['children'] ?? '', 3) . ' gyerek';
    array_push($sorok, ...$lista);
    if (sor($adat['message'] ?? '') !== '') {
        $sorok[] = '';
        $sorok[] = 'Megjegyzés: ' . sor($adat['message'], 3000);
    }
    $sorok[] = 'Adatkezelési hozzájárulás: megadva';
    return ['targy' => 'Foglalási igény', 'sorok' => $sorok];
}

// ---------------------------------------------------------------- kérések

kereso_kerelmek();
