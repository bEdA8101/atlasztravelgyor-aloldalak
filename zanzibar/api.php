<?php
// A zanzibar-oldal élő adatai (az oldal mellett api.php néven). Ugyanazokat a
// kérdéseket válaszolja meg, mint a központi oldal saját programja, de a győri
// CeSYS-oldalról (budapesti és bécsi indulás) és az atlastravel.sk-ról (pozsonyi
// indulás). Az ajánlatkérések és foglalások a győri irodának mennek levélben.
//
//   ?action=search&src=hu|at|sk&df=…&dt=…&uf=…&ut=…&ac=…&ages=5,8&ai=…&pt=…
//   ?action=dates&hotel=…&src=…&df=…&dt=…&uf=…&ut=…&ac=…&ages=…
//   ?action=rooms&date=…&hotel=…&ac=…&ages=…
//   ?action=price&date=…&room=…&ac=…&ages=…        (budapesti kínálat)
//   ?action=price_at&code=…&ac=…&ages=…            (bécsi kínálat)
//   ?action=img&h=…|u=…                            (szállodafotó)
//   ?action=lastminute
//   POST ?action=send | ?action=book                (JSON: ajánlatkérés, foglalás)

declare(strict_types=1);
require __DIR__ . '/cesys.php';

const GYOR = 'https://www.atlasztravelgyor.hu';
const SK = 'https://www.atlastravel.sk';
const FORRASOK = [
    'hu' => ['oldal' => GYOR, 'kereso' => '/utazas-kereses/1', 'di' => 685, 'ai' => [18, 19, 24], 'source' => 'cesys_default', 'sm' => 1, 'cimke' => 'Budapest'],
    'at' => ['oldal' => GYOR, 'kereso' => '/am-utazas-kereses/2', 'di' => 454, 'ai' => [6], 'source' => 'amadeus_connector', 'sm' => 2, 'cimke' => 'Bécs'],
    'sk' => ['oldal' => SK, 'kereso' => '/vyhladavanie-zajazdov/1', 'di' => 685, 'ai' => [7], 'source' => 'cesys_default', 'sm' => 1, 'cimke' => 'Pozsony'],
];
const AR_SZOLGALTATAS = [
    'hu' => ['url' => 'https://api-ng.cesys.eu/online/v1.4/hu/online/availability-price', 'client_id' => 31307, 'customer_id' => '8198'],
    'sk' => ['url' => 'https://api-ng.cesys.eu/online/v1.4//sk/online/availability-price', 'client_id' => 21656, 'customer_id' => '8116'],
];
const ARFOLYAM = 370;          // a pozsonyi (euróban árazott) utak forintára, ahogy az oldal is írja
const ERVENYES = 1800;         // fél óra
const MAX_KERESO_OLDAL = 6;    // legfeljebb ennyi találati oldal (oldalanként 10–12 szálloda)
const EGYSZERRE = ['hu' => 3, 'at' => 3, 'sk' => 2];   // egyszerre legfeljebb ennyi kérés forrásonként (kíméljük őket)
const RESZLEGES_ERVENYES = 300;   // ha egy keresés csak részben sikerült, csak 5 percig jegyezzük meg
const IDOPONT_CSOMAG = 200;    // ennyi időpont-sort kérünk egyszerre (a CeSYS ennyit kiad; így szállodánként általában 1 kérés elég)
const MAX_IDOPONT_OLDAL = 2;   // legfeljebb ennyi időpont-csomag
const REGIO_OLDAL = GYOR . '/tanzania/zanzibar';   // a győri oldal partszakasz-aloldalai (Kendwa, Kiwengwa, …)
const REGIO_ERVENYES = 86400;                        // a partszakaszok listáját naponta frissítjük
const HELY_ERVENYES = 30 * 86400;   // egy szálloda térképes helyét (a győri adatlapjáról) 30 napig jegyezzük meg
const HELY_EGYSZERRE = 3;           // ennyi adatlapot kérünk egyszerre …
const HELY_MAX_UJ = 9;              // … és egy válaszhoz legfeljebb ennyit (a többi a következő látogatáskor jön)
const HELY_TAVOLSAG = 6.0;          // km: ennél messzebb levő partszakaszhoz nem soroljuk a szállodát
// ha egy szállodát nem találunk a partszakasz-oldalakon, a nevében szereplő helynév dönt
const HELYNEVEK = ['Stone Town', 'Nungwi', 'Kendwa', 'Kiwengwa', 'Pongwe', 'Pingwe', 'Uroa', 'Matemwe', 'Mnemba', 'Paje',
    'Jambiani', 'Bwejuu', 'Michamvi', 'Dongwe', 'Kizimkazi', 'Makunduchi', 'Chwaka', 'Mbweni', 'Marumbi', 'Kilindi', 'Mchangani'];
const CIMZETT = 'gyor@atlasztravel.hu';
const FELADO = 'weboldal@atlasztravelgyor.hu';
const SK_JELLEMZOK = ['Priamy let' => 'Közvetlen járat', 'Wifi' => 'WiFi', 'WiFi' => 'WiFi', 'Klimatizácia' => 'Légkondicionáló',
    'Priamo na pláži' => 'Közv. tengerparton', 'Vonkajší bazén' => 'Kültéri medence', 'Bazén' => 'Medence',
    'Vhodné pre deti' => 'Gyermekbarát', 'Wellness' => 'Wellness', 'All inclusive' => 'All inclusive'];

// ---------------------------------------------------------------- segédek

function hiba(string $uzenet, int $kod = 200, array $tobb = []): void
{
    json_valasz(['ok' => false, 'message' => $uzenet] + $tobb, $kod);
}

function datum(?string $s): ?string
{
    return ($s && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) ? $s : null;
}

function egesz($v, int $min, int $max, int $alap): int
{
    return is_numeric($v) ? max($min, min($max, (int)$v)) : $alap;
}

/** Utasok: felnőttek és a gyerekek életkora (az oldal vesszővel elválasztva küldi). */
function utasok(): array
{
    $felnott = egesz($_GET['ac'] ?? 2, 1, 8, 2);
    $korok = [];
    foreach (explode(',', (string)($_GET['ages'] ?? '')) as $k) {
        if ($k !== '' && is_numeric($k) && (int)$k >= 0 && (int)$k <= 17) {
            $korok[] = (int)$k;
        }
    }
    return [$felnott, array_slice($korok, 0, 4)];
}

/** A kérés keresési paraméterei, ellenőrizve. */
function keresesi_adatok(): array
{
    $src = (string)($_GET['src'] ?? 'hu');
    if (!isset(FORRASOK[$src])) {
        $src = 'hu';
    }
    $holnap = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
    $df = datum($_GET['df'] ?? null) ?? $holnap;
    $dt = datum($_GET['dt'] ?? null) ?? (new DateTimeImmutable($df))->modify('+185 days')->format('Y-m-d');
    if ($df < $holnap) {
        $df = $holnap;
    }
    $uf = egesz($_GET['uf'] ?? 5, 1, 30, 5);
    $ut = egesz($_GET['ut'] ?? 21, $uf, 30, max($uf, 21));
    $ai = [];
    foreach (explode(',', (string)($_GET['ai'] ?? '')) as $x) {
        if (ctype_digit($x)) {
            $ai[] = (int)$x;
        }
    }
    [$felnott, $korok] = utasok();
    return [
        'src' => $src, 'df' => $df, 'dt' => $dt, 'uf' => $uf, 'ut' => $ut,
        'ai' => $ai ?: FORRASOK[$src]['ai'], 'pt' => egesz($_GET['pt'] ?? 15000000, 0, 99000000, 15000000),
        'ac' => $felnott, 'ages' => $korok,
    ];
}

function kulcs(string $elotag, array $adat): string
{
    return $elotag . '-' . substr(sha1(json_encode($adat)), 0, 16);
}

function szoveg(string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function szam(string $s): float
{
    $s = str_replace(["\xc2\xa0", ' ', '&nbsp;'], '', $s);
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {   // 1.234,56 alak
        $s = str_replace('.', '', $s);
    }
    return (float)str_replace(',', '.', $s);
}

/** A CeSYS „napokban” érti az út hosszát; az oldal éjszakában kérdez (a zanzibári utak +2 nap). */
function napok(int $ejMin, int $ejMax): array
{
    return [$ejMin, $ejMax + 2];
}

// ---------------------------------------------------------------- keresés (szállodák)

function kereso_cim(array $k, int $oldal, bool $ajax): string
{
    $f = FORRASOK[$k['src']];
    [$napMin, $napMax] = napok($k['uf'], $k['ut']);
    $utasok = implode(',', array_merge(array_fill(0, $k['ac'], 18), $k['ages']));
    $p = [];
    if ($k['src'] === 'sk') {
        $p = [['di', $f['di']], ['df', $k['df']], ['dt', $k['dt']], ['ac', $k['ac']], ['ti', 1], ['uf', $napMin], ['ut', $napMax],
              ['pt', (int)ceil($k['pt'] / ARFOLYAM)], ['ai', implode(',', $k['ai'])], ['es', 0], ['cc', count($k['ages'])], ['rooms[0]', $utasok]];
    } else {
        $p = [['di[0]', $f['di']], ['df', $k['df']], ['dt', $k['dt']], ['uf', $napMin], ['ut', $napMax], ['ac', $k['ac']],
              ['cc', count($k['ages'])], ['rooms[0]', $utasok], ['ti', 1]];
        foreach ($k['ai'] as $i => $x) {
            $p[] = ["ai[$i]", $x];
        }
        $p[] = ['pt', $k['pt']];
    }
    foreach (array_values($k['ages']) as $i => $kor) {
        $p[] = ['ca' . ($i + 1), $kor];
    }
    $p[] = ['sort', 'price'];
    $p[] = ['direction', 'asc'];
    if ($ajax) {
        $p[] = ['ajaxPaging', 'true'];
    }
    return $f['oldal'] . $f['kereso'] . ($oldal > 1 ? "/page:$oldal" : '') . '?' . cesys_lekerdezes($p);
}

/** Egy találati kártyából a szálloda adatai. */
function kartya(string $c, string $src): ?array
{
    $f = FORRASOK[$src];
    if (!preg_match('/<h2 class="hotel[^"]*">\s*<a href="([^"]+)"[^>]*>(.*?)<\/a>/s', $c, $m)) {
        return null;
    }
    $url = $f['oldal'] . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $nev = szoveg($m[2]);
    preg_match('/data-slider-id="(\d+)"/', $c, $id);
    preg_match('/title="(\d)\*"/', $c, $csillag);
    $ertekeles = 0;
    $ertekelesSzoveg = '';
    if (preg_match('/class="e-rating[^"]*">\s*<(?:b|strong)>\s*(\d+)/', $c, $r)) {
        $ertekeles = (int)$r[1];
        if (preg_match('/class="e-rating[^"]*">\s*<(?:b|strong)>[^<]*<\/(?:b|strong)>\s*<span>([^<]*)<\/span>/', $c, $rs)) {
            $ertekelesSzoveg = szoveg($rs[1]);
        }
    }
    $kep = '';
    $kepCim = '';
    if (preg_match('#https://i\.ccdn\.(hu|sk)/acm/t/([a-f0-9]{32})\.webp#', $c, $k)) {
        $kep = $k[1] === 'hu' ? $k[2] : '';
        $kepCim = $k[1] === 'hu' ? '' : $k[0];
    } elseif (preg_match('#https://f\.ccdn\.(hu|sk)/ai/(?:m/)?[a-f0-9]{40}#', $c, $k)) {
        $kepCim = $k[0];   // a bécsi (Amadeus) kínálat képei
    }
    $regio = '';
    if (preg_match('/<div class="tree[^"]*">(.*?)<\/div>/s', $c, $t) && preg_match_all('/<a [^>]*>([^<]*)<\/a>/', $t[1], $ta)) {
        $regio = szoveg(end($ta[1]));
    }
    $honnan = '';
    if (preg_match('/<div class="transport[^"]*">(.*?)<\/div>/s', $c, $tr) && preg_match('/\|\s*([^<]+)/', $tr[1], $h)) {
        $honnan = szoveg($h[1]);
    }
    $ellatas = preg_match('/<div class="boarding[^"]*">(.*?)<\/div>/s', $c, $b) ? szoveg($b[1]) : '';
    $jellemzok = [];
    if (preg_match_all('/<span class="parameters__item (?!parameters__item-distance)[^"]*"[^>]*>.*?parameters__text">([^<]+)</s', $c, $pj)) {
        foreach ($pj[1] as $j) {
            $j = szoveg($j);
            $j = SK_JELLEMZOK[$j] ?? $j;
            if ($j !== '' && !in_array($j, $jellemzok, true)) {
                $jellemzok[] = $j;
            }
        }
    }
    $arHuf = 0;
    $arEur = 0.0;
    if (preg_match('/<div class="price">(.*?)<\/div>|class="price">(.*?)<\/a>/s', $c, $ar)) {
        $arSzoveg = html_entity_decode($ar[1] . ($ar[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $arSzoveg = strip_tags($arSzoveg);
        if (preg_match('/([\d\s\x{a0}.]+)\s*Ft/u', $arSzoveg, $ft)) {
            $arHuf = (int)round(szam($ft[1]));
        }
        if (preg_match('/([\d\s\x{a0}.,]+)\s*€/u', $arSzoveg, $eu)) {
            $arEur = round(szam($eu[1]), 2);
        }
    }
    if ($src === 'sk' && $arEur > 0) {
        $arHuf = (int)round($arEur * ARFOLYAM);
    }
    return [
        'name' => $nev, 'url' => $url, 'stars' => isset($csillag[1]) ? (int)$csillag[1] : 0,
        'rating' => $ertekeles, 'ratingText' => $ertekelesSzoveg, 'img' => $kep, 'imgSrc' => $kepCim,
        'region' => $regio, 'from' => $honnan, 'board' => $ellatas, 'features' => $jellemzok,
        'priceHuf' => $arHuf, 'priceEur' => $arEur, 'dateId' => null, 'roomId' => null,
        'tip' => (bool)preg_match('/<div class="tip position-absolute\s*">/', $c),
        'hotelId' => isset($id[1]) ? (int)$id[1] : null,
    ];
}

/** A keresés találatai, a hiányzó partszakaszok pótlásával. */
function szallodak(array $k): ?array
{
    $e = szallodak_nyers($k);
    return $e === null ? null : regiok_potlasa($e, $k['src'] === 'sk');
}

/** A keresés találatai úgy, ahogy a forrás adja (fél óráig tárolva). */
function szallodak_nyers(array $k): ?array
{
    $kulcs = kulcs('kereses', $k);
    $tarolt = gyorsitotar_olvas($kulcs, ERVENYES);
    if ($tarolt !== null && !(!empty($tarolt['_reszleges']) && time() - (int)($tarolt['_ido'] ?? 0) > RESZLEGES_ERVENYES)) {
        unset($tarolt['_reszleges'], $tarolt['_ido']);
        return $tarolt;
    }
    $cimek = [];
    for ($o = 1; $o <= MAX_KERESO_OLDAL; $o++) {
        $cimek[$o] = kereso_cim($k, $o, true);
    }
    $elso = cesys_get($cimek[1]);
    if ($elso === null || !isset($elso['htmlResult'])) {
        $regi = gyorsitotar_olvas($kulcs, GYORSITOTAR_TARTALEK);
        if ($regi === null) {
            return null;
        }
        unset($regi['_reszleges'], $regi['_ido']);
        return $regi + ['elavult' => true];
    }
    // további oldalak csomagonként, amíg a válaszban van „Továbbiak” link (a CeSYS a végén túl az utolsó oldalt ismételné)
    $vanTobb = fn($adat) => is_array($adat) && strpos((string)($adat['paging'] ?? ''), 'page:') !== false;
    $oldalak = [1 => $elso];
    $kovetkezo = 2;
    $tovabb = $vanTobb($elso);
    while ($tovabb && $kovetkezo <= MAX_KERESO_OLDAL) {
        $csomag = array_slice($cimek, $kovetkezo - 1, EGYSZERRE[$k['src']], true);
        $valaszok = cesys_get_tobb($csomag, EGYSZERRE[$k['src']]);
        foreach (array_keys($csomag) as $o) {
            $oldalak[$o] = $valaszok[$o] ?? null;
            if (is_array($oldalak[$o]) && !$vanTobb($oldalak[$o])) {
                $tovabb = false;   // ez volt az utolsó oldal
                break;
            }
        }
        $kovetkezo += count($csomag);
    }
    $szallodak = [];
    $latott = [];
    $reszleges = false;
    ksort($oldalak);
    foreach ($oldalak as $adat) {
        if (!is_array($adat)) {
            $reszleges = true;   // egy oldal újrapróbálás után sem jött meg: a többit megtartjuk, de rövidebb ideig
            continue;
        }
        if (empty($adat['htmlResult']) || strpos((string)$adat['htmlResult'], 'sm-result') === false) {
            break;               // nincs több találat
        }
        foreach (preg_split('/(?=<div class="sm-result)/', (string)$adat['htmlResult']) as $c) {
            $h = strpos($c, 'sm-result') !== false ? kartya($c, $k['src']) : null;
            if ($h && $h['hotelId'] && !isset($latott[$h['hotelId']])) {
                $latott[$h['hotelId']] = true;
                $szallodak[] = $h;
            }
        }
    }
    $eredmeny = ['hotels' => $szallodak, 'searchUrl' => kereso_cim($k, 1, false), 'frissitve' => date('c')];
    gyorsitotar_ir($kulcs, $eredmeny + ['_reszleges' => $reszleges, '_ido' => time()]);
    return $eredmeny;
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
 * A szállodák térképes helye a győri adatlapjukról: index -> [szélesség, hosszúság] vagy null.
 * Szállodánként 30 napig tároljuk; egy válaszhoz legfeljebb HELY_MAX_UJ új adatlapot kérünk, egyszerre HELY_EGYSZERRE-t.
 */
function szalloda_helyek(array $szallodak): array
{
    $eredmeny = [];
    $kell = [];
    foreach ($szallodak as $i => $h) {
        $tarolt = gyorsitotar_olvas('hely-' . $h['hotelId'], HELY_ERVENYES);
        if ($tarolt !== null && (empty($tarolt['hiba']) || time() - (int)$tarolt['ido'] < 3600)) {
            $eredmeny[$i] = isset($tarolt['lat']) ? [$tarolt['lat'], $tarolt['lon']] : null;
        } elseif (count($kell) < HELY_MAX_UJ) {
            $kell[$i] = strtok((string)$h['url'], '?');
        }
    }
    foreach (cesys_html_tobb($kell, HELY_EGYSZERRE) as $i => $oldal) {
        $id = $szallodak[$i]['hotelId'];
        if ($oldal === null) {
            gyorsitotar_ir('hely-' . $id, ['hiba' => true, 'ido' => time()]);   // egy óra múlva újra megpróbáljuk
            $eredmeny[$i] = null;
        } elseif (preg_match('/"latitude":\s*(-?[0-9.]+),\s*"longitude":\s*(-?[0-9.]+)/', $oldal, $k)) {
            gyorsitotar_ir('hely-' . $id, ['lat' => (float)$k[1], 'lon' => (float)$k[2]]);
            $eredmeny[$i] = [(float)$k[1], (float)$k[2]];
        } else {
            gyorsitotar_ir('hely-' . $id, ['nincs' => true]);
            $eredmeny[$i] = null;
        }
    }
    return $eredmeny;
}

/** A legközelebbi ismert partszakasz (legfeljebb HELY_TAVOLSAG km-re), különben null. */
function legkozelebbi_hely(array $k, array $helyek): ?string
{
    $legjobb = null;
    $tav = HELY_TAVOLSAG;
    foreach ($helyek as $nev => [$sz, $h]) {
        $d = 6371 * sqrt((deg2rad($sz - $k[0])) ** 2 + (cos(deg2rad(($sz + $k[0]) / 2)) * deg2rad($h - $k[1])) ** 2);
        if ($d < $tav) {
            $tav = $d;
            $legjobb = $nev;
        }
    }
    return $legjobb;
}

// ---------------------------------------------------------------- partszakaszok

/** A szállodanév „szavai”, ékezet és általános szavak (hotel, resort, spa, …) nélkül, sorba rendezve. */
function nev_szavak(string $nev): array
{
    $n = mb_strtolower(html_entity_decode($nev, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'ô' => 'o', 'ä' => 'a', 'ľ' => 'l', 'š' => 's', 'č' => 'c', 'ť' => 't', 'ž' => 'z', 'ý' => 'y', 'ň' => 'n', 'ŕ' => 'r', '&' => ' ']);
    $szavak = array_filter(explode(' ', preg_replace('/[^a-z0-9]+/', ' ', $n)));
    $szavak = array_values(array_unique(array_diff($szavak, ['hotel', 'resort', 'resorts', 'spa', 'and', 'the', 'zanzibar', 'by', 'a'])));
    sort($szavak);
    return $szavak;
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

/** Partszakasz egy szállodanévhez a megadott táblázatból (névszavak szerint), végül a névben szereplő helynévből. */
function regio_nevbol(string $nev, array $terkep): ?string
{
    $szavak = nev_szavak($nev);
    if ($szavak) {
        $kulcs = implode(' ', $szavak);
        if (isset($terkep[$kulcs])) {
            return $terkep[$kulcs];
        }
        $osszevont = implode('', $szavak);   // „Myblue” = „My Blue”
        foreach ($terkep as $k => $regio) {
            if (str_replace(' ', '', $k) === $osszevont) {
                return $regio;
            }
        }
        foreach ($terkep as $k => $regio) {   // részleges egyezés: legalább 2 közös szó, és az egyik név a másik része
            $m = explode(' ', $k);
            $kozos = count(array_intersect($szavak, $m));
            if ($kozos >= 2 && ($kozos === count($szavak) || $kozos === count($m))) {
                return $regio;
            }
        }
    }
    foreach (HELYNEVEK as $hely) {
        if (stripos($nev, $hely) !== false) {
            return $hely;
        }
    }
    return null;
}

// ---------------------------------------------------------------- időpontok és szobák

function idopont_cim(array $k, int $szalloda, int $oldal): string
{
    $f = FORRASOK[$k['src']];
    [$napMin, $napMax] = napok($k['uf'], $k['ut']);
    $p = [['di[]', $f['di']], ['df', $k['df']], ['dt', $k['dt']], ['ac', $k['ac']], ['cc', count($k['ages'])]];
    foreach (array_values($k['ages']) as $i => $kor) {
        $p[] = ['ca' . ($i + 1), $kor];
    }
    $p = array_merge($p, [['ti', 1], ['uf', $napMin], ['ut', $napMax]]);
    foreach ($k['ai'] as $x) {
        $p[] = ['ai[]', $x];
    }
    $p = array_merge($p, [['sp', 'false'], ['ti_f', 1]]);
    foreach ($k['ai'] as $x) {
        $p[] = ['ai_f[]', $x];
    }
    $p = array_merge($p, [['uf_f', $napMin], ['ut_f', $napMax], ['ns_f', 'false'], ['sp_f', 'false'], ['rop', IDOPONT_CSOMAG],
        ['hotel', $szalloda], ['skip', 0], ['source', $f['source']], ['sm', $f['sm']], ['sort', 'price'], ['direction', 'asc'],
        ['pg', $oldal], ['currency', $k['src'] === 'sk' ? 'EUR' : 'HUF']]);
    return $f['oldal'] . '/searchmasks/generation_dates_terms?' . cesys_lekerdezes($p);
}

/** A szoba neve; a pótágyas változatot külön jelöljük (különben két azonos nevű szoba látszana). */
function szobanev(array $x): string
{
    $nev = html_entity_decode((string)($x['room_name'] ?? 'Szoba'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $potagy = (int)($x['extra_beds'] ?? 0);
    if ($potagy > 0 && !preg_match('/pótágy|prístel/iu', $nev)) {
        $nev .= $potagy > 1 ? ", $potagy pótággyal" : ', pótággyal';
    }
    return $nev;
}

function jarat(array $x): ?array
{
    $fi = $x['flight_info'] ?? null;
    if (!is_array($fi)) {
        return null;
    }
    $oda = $fi['flights_to_destination'] ?? [];
    $vissza = $fi['flights_from_destination'] ?? [];
    $tarsasag = $oda[0]['transport_company']['name'] ?? null;
    $atszallas = isset($fi['stop_overs_to_destination']) ? (int)$fi['stop_overs_to_destination'] : max(0, count($oda) - 1);
    return [
        'out' => $oda[0]['departure_date'] ?? ($fi['departure_to_destination'] ?? null),
        'outArr' => $oda ? ($oda[count($oda) - 1]['arrival_date'] ?? null) : ($fi['arrival_to_destination'] ?? null),
        'back' => $vissza[0]['departure_date'] ?? ($fi['departure_from_destination'] ?? null),
        'backArr' => $vissza ? ($vissza[count($vissza) - 1]['arrival_date'] ?? null) : ($fi['arrival_from_destination'] ?? null),
        'airline' => $tarsasag, 'stops' => $atszallas,
    ];
}

function ar_par(array $x, string $src): array
{
    $ar = $x['price_from'] ?? [];
    $eur = isset($ar['EUR']) ? round((float)$ar['EUR'], 2) : 0.0;
    $huf = isset($ar['HUF']) ? (int)round((float)$ar['HUF']) : 0;
    if ($src === 'sk' || !$huf) {
        $huf = (int)round($eur * ARFOLYAM);
    }
    return [$huf, $eur];
}

/** Egy szálloda időpontjai: időpontonként a legolcsóbb szoba, és a szobák listája (a rooms kérdéshez eltárolva). */
function idopontok(array $k, int $szalloda): ?array
{
    $kulcs = kulcs('idopontok-' . $szalloda, $k);
    $tarolt = gyorsitotar_olvas($kulcs, ERVENYES);
    if ($tarolt !== null) {
        return $tarolt;
    }
    $cimek = [];
    for ($o = 1; $o <= MAX_IDOPONT_OLDAL; $o++) {
        $cimek[$o] = idopont_cim($k, $szalloda, $o);
    }
    $elso = cesys_get($cimek[1]);
    if ($elso === null || !isset($elso['dates'])) {
        $regi = gyorsitotar_olvas($kulcs, GYORSITOTAR_TARTALEK);
        return $regi === null ? null : $regi + ['elavult' => true];
    }
    $sorok = $elso['dates'];
    if (!empty($elso['more_exists'])) {
        foreach (cesys_get_tobb(array_slice($cimek, 1, null, true), EGYSZERRE[$k['src']]) as $adat) {
            if (!is_array($adat) || empty($adat['dates'])) {
                break;
            }
            array_push($sorok, ...$adat['dates']);
        }
    }
    $amadeus = FORRASOK[$k['src']]['source'] === 'amadeus_connector';
    $csoportok = [];
    foreach ($sorok as $x) {
        $ej = (int)($x['duration_night'] ?? 0);
        if ($ej < $k['uf'] || $ej > $k['ut'] || empty($x['date_from']) || empty($x['date_to'])) {
            continue;
        }
        [$huf, $eur] = ar_par($x, $k['src']);
        if ($huf <= 0) {
            continue;
        }
        // CeSYS-adatbázis: az időpont azonosítója közös, a szobák külön sorok; Amadeus: minden ajánlat külön
        $csoportKulcs = $amadeus ? $x['date_from'] . '|' . $x['date_to'] : (string)$x['id'];
        $szoba = $amadeus
            ? ['id' => (string)$x['code'], 'name' => (string)($x['room']['type'] ?? $x['room']['name'] ?? 'Szoba'),
               'board' => (string)($x['boarding'] ?? ''), 'transfer' => ($x['transfer'] ?? '0') === '1',
               'priceHuf' => $huf, 'priceEur' => $eur, 'discPct' => 0,
               '_cid' => (string)$x['id'], '_hash' => (string)($x['verify_hash'] ?? '')]
            : ['id' => (int)$x['room_id'], 'name' => szobanev($x),
               'board' => (string)($x['boarding'] ?? ''), 'transfer' => false,
               'priceHuf' => $huf, 'priceEur' => $eur, 'discPct' => (float)($x['discount_percent'] ?? 0)];
        if (!isset($csoportok[$csoportKulcs])) {
            $j = jarat($x);
            $csoportok[$csoportKulcs] = [
                'id' => $amadeus ? (string)$x['id'] : (int)$x['id'],
                'from' => $x['date_from'], 'to' => $x['date_to'], 'nights' => $ej,
                'board' => (string)($x['boarding'] ?? ''), 'airport' => (string)($x['airport'] ?? ''),
                'direct' => $j ? $j['stops'] === 0 : false, 'priceHuf' => $huf, 'priceEur' => $eur,
                'room' => $szoba['id'], 'flight' => $j ? ['airline' => $j['airline'], 'stops' => $j['stops']] : null,
                'eur' => $k['src'] === 'sk', 'lm' => !empty($x['last_minute']),
                '_jarat' => $j, '_szobak' => [],
            ];
        }
        $cs = &$csoportok[$csoportKulcs];
        $cs['_szobak'][] = $szoba;
        if ($huf < $cs['priceHuf']) {
            $cs['priceHuf'] = $huf;
            $cs['priceEur'] = $eur;
            $cs['room'] = $szoba['id'];
            $cs['board'] = $szoba['board'];
        }
        unset($cs);
    }
    $lista = [];
    foreach ($csoportok as $cs) {
        usort($cs['_szobak'], fn($a, $b) => $a['priceHuf'] <=> $b['priceHuf']);
        // a szobák és a bécsi ajánlatok ellenőrző adatai a rooms és a price_at kérdéshez
        $szobaKulcs = 'szobak-' . $szalloda . '-' . $cs['id'] . '-' . $k['ac'] . '-' . implode('_', $k['ages']);
        $nyilvanos = array_map(fn($s) => array_diff_key($s, ['_cid' => 1, '_hash' => 1]), $cs['_szobak']);
        gyorsitotar_ir($szobaKulcs, ['ok' => true, 'rooms' => $nyilvanos, 'flight' => $cs['_jarat'], 'from' => $cs['from'], 'to' => $cs['to']]);
        foreach ($cs['_szobak'] as $s) {
            if (isset($s['_cid'])) {
                gyorsitotar_ir('amadeus-' . substr(sha1($s['id']), 0, 20), ['cid' => $s['_cid'], 'hash' => $s['_hash'], 'from' => $cs['from'], 'to' => $cs['to']]);
            }
        }
        unset($cs['_jarat'], $cs['_szobak']);
        $lista[] = $cs;
    }
    usort($lista, fn($a, $b) => [$a['from'], $a['priceHuf']] <=> [$b['from'], $b['priceHuf']]);
    $eredmeny = ['ok' => true, 'dates' => $lista, 'frissitve' => date('c')];
    gyorsitotar_ir($kulcs, $eredmeny);
    return $eredmeny;
}

// ---------------------------------------------------------------- pontos ár

function cesys_ar(string $src, int $datum, int $szoba, int $felnott, array $korok): array
{
    $sz = AR_SZOLGALTATAS[$src];
    $valasz = cesys_post_tobb($sz['url'], [0 => [
        'client_id' => $sz['client_id'], 'customer_id' => $sz['customer_id'],
        'composition' => ['adults' => $felnott, 'children' => $korok], 'date_id' => $datum, 'room_id' => $szoba, 'debug' => 0,
    ]])[0] ?? null;
    $d = $valasz['data'] ?? null;
    if (!is_array($d)) {
        return ['ok' => false, 'message' => 'Az ár most nem kérdezhető le.'];
    }
    $elerheto = in_array($d['availability'] ?? '', ['OK', 'RQ'], true);
    $ti = $d['travel_info'] ?? [];
    return [
        'ok' => true, 'available' => $elerheto,
        'totalHuf' => isset($d['total_price']['HUF']) ? (int)round((float)$d['total_price']['HUF'])
            : (isset($d['total_price']['EUR']) ? (int)round((float)$d['total_price']['EUR'] * ARFOLYAM) : null),
        'totalEur' => isset($d['total_price']['EUR']) ? round((float)$d['total_price']['EUR'], 2) : null,
        // a járat adatait az időpontlista adja; az árszolgáltatás néha technikai leszállást is átszállásnak számol
        'airline' => null, 'stops' => null,
        'start' => $ti['outbound_arrival_date'] ?? null, 'end' => $ti['inbound_departure_date'] ?? null,
    ];
}

function amadeus_ar(string $kod): array
{
    $adat = gyorsitotar_olvas('amadeus-' . substr(sha1($kod), 0, 20), 86400);
    if (!$adat) {
        return ['ok' => false, 'message' => 'Az ajánlat lejárt, kérjük, keressen újra.'];
    }
    $cim = GYOR . '/online/availability/cesys/check_date_amadeus.php?' . cesys_lekerdezes([
        ['lang', 'hu'], ['cache', 'true'], ['cid', $adat['cid']], ['hash', $adat['hash']], ['code', $kod], ['source', 'amadeus_connector'],
    ]);
    $d = cesys_get($cim);
    if (!is_array($d)) {
        return ['ok' => false, 'message' => 'Az ár most nem kérdezhető le.'];
    }
    $jarat = is_array($d['flight_info'] ?? null) ? jarat(['flight_info' => $d['flight_info']]) : null;
    return [
        'ok' => true, 'available' => in_array($d['availability'] ?? '', ['OK', 'RQ'], true),
        'totalHuf' => isset($d['total_price']['HUF']) ? (int)round((float)$d['total_price']['HUF']) : null,
        'totalEur' => isset($d['total_price']['EUR']) ? round((float)$d['total_price']['EUR'], 2) : null,
        'airline' => $jarat['airline'] ?? null, 'stops' => $jarat['stops'] ?? null,
        'start' => $adat['from'], 'end' => $adat['to'],
    ];
}

// ---------------------------------------------------------------- levél (ajánlatkérés, foglalás)

function level(string $fajta): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_valasz(['ok' => false, 'error' => 'Csak űrlapküldés engedélyezett.'], 405);
    }
    $adat = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
    if (!is_array($adat)) {
        json_valasz(['ok' => false, 'error' => 'Hibás kérés.'], 400);
    }
    if (trim((string)($adat['website'] ?? '')) !== '') {
        json_valasz(['ok' => true]);   // rejtett csapdamező: robot
    }
    $email = trim((string)($adat['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        json_valasz(['ok' => false, 'error' => 'Kérjük, adjon meg érvényes e-mail-címet.'], 422);
    }
    $cimkek = ['hotel' => 'Szálloda', 'source' => 'Kínálat', 'board' => 'Ellátás', 'term' => 'Időpont', 'room' => 'Szoba',
        'travellers' => 'Utasok', 'adults' => 'Felnőttek', 'children' => 'Gyerekek', 'from' => 'Indulás', 'price' => 'Ár',
        'name' => 'Név', 'phone' => 'Telefon', 'email' => 'E-mail', 'address' => 'Lakcím', 'message' => 'Megjegyzés'];
    $sorok = [];
    foreach ($cimkek as $mezo => $cimke) {
        $ertek = $adat[$mezo] ?? '';
        if (is_scalar($ertek) && trim((string)$ertek) !== '') {
            $sorok[] = $cimke . ': ' . mb_substr(trim((string)$ertek), 0, 3000);
        }
    }
    $szalloda = preg_replace('/[\r\n\t]+/', ' ', mb_substr((string)($adat['hotel'] ?? ''), 0, 100));
    $targy = ($fajta === 'book' ? 'Foglalás' : 'Ajánlatkérés') . ' – Zanzibár' . ($szalloda !== '' ? ', ' . $szalloda : '');
    $fejlecek = implode("\r\n", [
        'From: =?UTF-8?B?' . base64_encode('Atlasz Travel Győr weboldal') . '?= <' . FELADO . '>',
        'Reply-To: ' . $email, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit',
    ]);
    $torzs = implode("\n", $sorok) . "\n\n--\nKüldve: " . date('Y-m-d H:i') . ' – zanzibar oldal' . "\n";
    $ok = mail(CIMZETT, '=?UTF-8?B?' . base64_encode($targy) . '?=', $torzs, $fejlecek);
    json_valasz($ok ? ['ok' => true] : ['ok' => false, 'error' => 'A levél most nem ment el.'], $ok ? 200 : 500);
}

// ---------------------------------------------------------------- kérések

$muvelet = (string)($_GET['action'] ?? '');

switch ($muvelet) {
    case 'search':
        $k = keresesi_adatok();
        $napok = (int)((new DateTimeImmutable($k['dt']))->diff(new DateTimeImmutable($k['df']))->days);
        if ($k['dt'] < $k['df'] || $napok < $k['uf']) {
            json_valasz(['ok' => false, 'error' => 'range']);
        }
        $e = szallodak($k);
        $e === null ? hiba('Az élő árak most nem érhetők el.') : json_valasz(['ok' => true] + $e);
        break;

    case 'dates':
        $k = keresesi_adatok();
        $szalloda = egesz($_GET['hotel'] ?? 0, 0, PHP_INT_MAX, 0);
        if (!$szalloda) {
            hiba('Hiányzó szálloda.');
        }
        $e = idopontok($k, $szalloda);
        $e === null ? hiba('Az időpontok most nem tölthetők be.') : json_valasz($e);
        break;

    case 'rooms':
        [$felnott, $korok] = utasok();
        $szalloda = egesz($_GET['hotel'] ?? 0, 0, PHP_INT_MAX, 0);
        $datum = preg_replace('/[^0-9A-Za-z_-]/', '', (string)($_GET['date'] ?? ''));
        $e = gyorsitotar_olvas('szobak-' . $szalloda . '-' . $datum . '-' . $felnott . '-' . implode('_', $korok), 86400);
        $e === null ? hiba('A szobák most nem tölthetők be, kérjük, nyissa meg újra a szállodát.') : json_valasz($e);
        break;

    case 'price':
        [$felnott, $korok] = utasok();
        $src = ($_GET['src'] ?? '') === 'sk' ? 'sk' : 'hu';
        json_valasz(cesys_ar($src, egesz($_GET['date'] ?? 0, 0, PHP_INT_MAX, 0), egesz($_GET['room'] ?? 0, 0, PHP_INT_MAX, 0), $felnott, $korok));
        break;

    case 'price_at':
        json_valasz(amadeus_ar(substr((string)($_GET['code'] ?? ''), 0, 400)));
        break;

    case 'allapot':
        // a forrásoldalak legutóbbi kimaradásai (hibakereséshez)
        json_valasz(['ok' => true, 'most' => date('c'), 'hibak' => hibanaplo(30)]);
        break;

    case 'img':
        $u = (string)($_GET['u'] ?? '');
        $h = (string)($_GET['h'] ?? '');
        if (preg_match('#^https://(i|f)\.ccdn\.(hu|sk|cz)/[A-Za-z0-9/._-]+$#', $u)) {
            $cel = $u;
        } elseif (preg_match('/^[a-f0-9]{32}$/', $h)) {
            $cel = 'https://i.ccdn.hu/acm/t/' . $h . '.webp';
        } else {
            http_response_code(404);
            exit;
        }
        header('Cache-Control: public, max-age=86400');
        header('Location: ' . $cel, true, 302);
        exit;

    case 'lastminute':
        $ma = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Budapest'));
        $df = $ma->format('Y-m-d');
        $dt = $ma->modify('+13 days')->format('Y-m-d');
        $csoportok = [];
        foreach (['sk', 'hu', 'at'] as $src) {
            $k = ['src' => $src, 'df' => $df, 'dt' => $dt, 'uf' => 3, 'ut' => 21, 'ai' => FORRASOK[$src]['ai'],
                  'pt' => 15000000, 'ac' => 2, 'ages' => []];
            $e = szallodak($k);
            $lista = array_values(array_filter($e['hotels'] ?? [], fn($h) => $h['priceHuf'] > 0));
            usort($lista, fn($a, $b) => $a['priceHuf'] <=> $b['priceHuf']);
            $csoportok[] = ['label' => FORRASOK[$src]['cimke'], 'src' => $src, 'ai' => FORRASOK[$src]['ai'],
                'hotels' => array_slice($lista, 0, 3), 'error' => $e === null ? 'nem elérhető' : null,
                'found' => count($e['hotels'] ?? []), 'url' => $e['searchUrl'] ?? null];
        }
        json_valasz(['ok' => true, 'df' => $df, 'dt' => $dt, 'uf' => 3, 'ut' => 21, 'groups' => $csoportok]);
        break;

    case 'send':
    case 'book':
        level($muvelet);
        break;

    default:
        hiba('Ismeretlen kérés.', 400);
}
