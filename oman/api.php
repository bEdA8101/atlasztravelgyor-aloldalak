<?php
// Közös élő időpontlista az egy-két szállodás aloldalakhoz (Omán, Vietnám, RIU). A frissites.py az oldal
// mellé másolja api.php néven, az oldal saját beállításaival együtt (beallitas.php, az elo/*_beallitas.php
// fájlokból). Forrás: az atlasztravelgyor.hu vagy az atlastravel.sk nyilvános időpontlistája
// (generation_dates_terms) és a CeSYS árszolgáltatása (availability-price) a pontos végösszeghez.
//
// Kérések:
//   api.php                         2 felnőtt, minden szálloda: indulási naponként a legjobb ár/fő és a szobák
//   api.php?a=csalad&h=…&ac=2&ages[]=6&ages[]=10[&df=…&dt=…]
//                                   egy szálloda adott összetételre: naponként a legolcsóbb foglalható
//                                   szoba végösszege (árszolgáltatás), a többi szoba listaárral
//   api.php?a=ar&d=…&r=…&ac=…&ages[]=…
//                                   egy szoba pontos végösszege az adott összetételre
//
// Minden válasz fél óráig tárolt; ha a forrás nem válaszol, a legutóbbi (legfeljebb egynapos) adat jön.

declare(strict_types=1);
require __DIR__ . '/cesys.php';
require __DIR__ . '/beallitas.php';

const ERVENYES = 1800;
const MAX_OLDAL = 10;          // 200 időpont / oldal
const MAX_CSALADI_NAP = 24;    // ennyi indulási napra számolunk végösszeget egy családi kérésben (a többi: df-fel)

const FORRASOK = [
    'hu' => ['oldal' => 'https://www.atlasztravelgyor.hu', 'penznem' => 'HUF',
             'ar' => 'https://api-ng.cesys.eu/online/v1.4/hu/online/availability-price',
             'ugyfel' => ['client_id' => 31307, 'customer_id' => '8198']],
    'sk' => ['oldal' => 'https://www.atlastravel.sk', 'penznem' => 'EUR',
             'ar' => 'https://api-ng.cesys.eu/online/v1.4//sk/online/availability-price',
             'ugyfel' => ['client_id' => 21656, 'customer_id' => '8116']],
];

gyorsitotar_takaritas(['hibanaplo' => 400 * 86400]);

function forras(): array
{
    return FORRASOK[FORRAS];
}

/** A szlovák szobanév magyarul (a bahari-oldal szótárával), a „2+1 ” jellegű előtag nélkül. */
function szobanev(string $nev): string
{
    $nev = trim(str_replace("\u{a0}", ' ', html_entity_decode($nev, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (FORRAS !== 'sk') {
        return $nev;
    }
    $csere = [
        '/\ba\b/u' => 'és', '/s možnosťou zdieľaného lôžka pre (\d)\. dieťa/iu' => '(a $1. gyerek a szülőkkel közös ágyon)',
        '/Jednolôžkov[áéý]/iu' => 'Egyágyas', '/Dvojlôžkov[áéý]/iu' => 'Kétágyas', '/Trojlôžkov[áéý]/iu' => 'Háromágyas',
        '/Štvorlôžkov[áéý]/iu' => 'Négyágyas', '/Rodinn[áéý]/iu' => 'Családi', '/\bizba\b/iu' => 'szoba', '/štandard/iu' => 'standard',
        '/apartmán/iu' => 'apartman', '/štúdio/iu' => 'stúdió', '/bungalov/iu' => 'bungaló', '/\bsuit[ae]\b/iu' => 'lakosztály', '/(\d)-bedroom apartment/iu' => '$1 hálószobás apartman',
        '/s bočným výhľadom na more/iu' => 'oldalról tengerre néző', '/s výhľadom na more/iu' => 'tengerre néző',
        '/s výhľadom na lagúnu/iu' => 'lagúnára néző', '/výhľad (na )?lagúna/iu' => 'lagúnára néző', '/výhľad (na )?more/iu' => 'tengerre néző',
        '/s výhľadom do záhrady/iu' => 'kertre néző', '/s výhľadom na bazén/iu' => 'medencére néző',
        '/s (\d+) prístelkami/iu' => '$1 pótággyal', '/s prístelkou/iu' => 'pótággyal', '/\bVila\b/u' => 'villa', '/\bTyp ([A-Z])\b/u' => '$1 típus',
        '/Bez balkóna/iu' => 'erkély nélkül', '/Prízemie/iu' => 'földszint', '/Vyššie poschodie/iu' => 'felső szint',
        '/s (\d+) spálňou/iu' => '$1 hálószobás', '/s (\d+) spálňami/iu' => '$1 hálószobás',
        '/so službami premium all inclusive/iu' => 'prémium all inclusive szolgáltatással',
    ];
    return trim((string)preg_replace(array_keys($csere), array_values($csere), $nev));
}

/** Az oldal szobatípusa (ha a beállítás megfeleltetést ad), különben a fordított név. */
function oldal_szobaneve(array $sz, string $eredeti): ?string
{
    $nev = trim(str_replace("\u{a0}", ' ', html_entity_decode($eredeti, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $alap = preg_replace('/^\d\+\d\s+/', '', $nev);   // „2+1 Deluxe izba” -> „Deluxe izba”
    foreach ($sz['szobak'] ?? [] as $magyar => $forrasnev) {
        if (strcasecmp(preg_replace('/^\d\+\d\s+/', '', $forrasnev), $alap) === 0) {
            return $magyar;
        }
    }
    return szobanev($alap);
}

function ellatas_neve(string $s): string
{
    $t = ['Raňajky' => 'reggeli', 'Polpenzia' => 'félpanzió', 'Plná penzia' => 'teljes ellátás', 'All inclusive' => 'all inclusive',
          'All inclusive Premium' => 'prémium all inclusive', 'Bez stravy' => 'ellátás nélkül', 'Ultra all inclusive' => 'ultra all inclusive'];
    return $t[$s] ?? mb_strtolower($s);
}

/** Egy szálloda időpontjai a megadott összetételre, oldalanként; hiba esetén null. */
function idopontok(array $sz, int $felnott, array $korok, string $df, string $dt): ?array
{
    $f = forras();
    $p = [['di[]', $sz['di']], ['df', $df], ['dt', $dt], ['ac', $felnott], ['cc', count($korok)]];
    foreach (array_values($korok) as $i => $kor) {
        $p[] = ['ca' . ($i + 1), $kor];
    }
    $napok = $sz['napok'] ?? null;   // az út hossza napokban (pl. 8 = 7 éj)
    $p = array_merge($p, [['ti', 1]], $napok ? [['uf', $napok[0]], ['ut', $napok[1]]] : []);
    foreach ($sz['repterek'] ?? [] as $x) {
        $p[] = ['ai[]', $x];
    }
    $p = array_merge($p, [['sp', 'false'], ['ti_f', 1]], $napok ? [['uf_f', $napok[0]], ['ut_f', $napok[1]]] : [], [
        ['ns_f', 'false'], ['sp_f', 'false'], ['rop', 200], ['hotel', $sz['id']], ['skip', 0], ['source', 'cesys_default'],
        ['sm', 1], ['sort', 'date_from'], ['direction', 'asc'], ['currency', $f['penznem']],
    ]);
    $osszes = [];
    for ($oldal = 1; $oldal <= MAX_OLDAL; $oldal++) {
        $adat = cesys_get($f['oldal'] . '/searchmasks/generation_dates_terms?' . cesys_lekerdezes(array_merge($p, [['pg', $oldal]])));
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

/** Egy forrásbeli időpont az oldal számára, vagy null, ha nem illik a beállításhoz. */
function idopont_sor(array $sz, array $x): ?array
{
    $penz = forras()['penznem'];
    $ar = $x['price_from'][$penz] ?? null;
    $nap = (string)($x['date_from'] ?? '');
    $ej = (int)($x['duration_night'] ?? 0);
    $ellatas = (string)($x['boarding'] ?? '');
    if (!is_numeric($ar) || !preg_match('/^\d{4}-\d\d-\d\d$/', $nap)
        || (isset($sz['ejszakak']) && !in_array($ej, $sz['ejszakak'], true))
        || (isset($sz['ellatasok']) && !in_array($ellatas, $sz['ellatasok'], true))) {
        return null;
    }
    $eredeti = trim(str_replace("\u{a0}", ' ', html_entity_decode((string)($x['room_original_name'] ?? $x['room_name'] ?? ''),
        ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $repter = (string)($x['airport'] ?? '');
    $ido = $x['outbound_flight_time'] ?? null;
    return [
        'd' => $nap, 'n' => $ej, 'vege' => (string)($x['date_to'] ?? ''),
        'rep' => REPTER_NEVEK[$repter] ?? $repter, 'ido' => is_string($ido) && preg_match('/^\d\d:\d\d$/', $ido) ? $ido : null,
        'ell' => ellatas_neve($ellatas), 'szoba' => oldal_szobaneve($sz, $eredeti), 'eredeti' => $eredeti,
        'ar' => round((float)$ar, 2), 'did' => (int)($x['id'] ?? 0), 'rid' => (int)($x['room_id'] ?? 0),
        'lm' => !empty($x['last_minute']),
    ];
}

/** Az időpontok indulási naponként (és éjszakaszámonként) csoportosítva, a napokon belül ár szerint. */
function csoportosit(array $sorok): array
{
    $napok = [];
    foreach ($sorok as $s) {
        $k = $s['d'] . '|' . $s['n'];
        $napok[$k] ??= ['d' => $s['d'], 'n' => $s['n'], 'vege' => $s['vege'], 'rep' => [], 'ido' => null, 'lm' => false, 'szobak' => []];
        $g = &$napok[$k];
        $g['rep'][$s['rep']] = true;
        $g['ido'] ??= $s['ido'];
        $g['lm'] = $g['lm'] || $s['lm'];
        $kulcs = $s['szoba'] . '|' . $s['ell'];
        if (!isset($g['szobak'][$kulcs]) || $s['ar'] < $g['szobak'][$kulcs]['ar']) {
            $g['szobak'][$kulcs] = ['nev' => $s['szoba'], 'ell' => $s['ell'], 'ar' => $s['ar'], 'did' => $s['did'], 'rid' => $s['rid'],
                                    'rep' => $s['rep']];
        }
        unset($g);
    }
    ksort($napok);
    foreach ($napok as &$g) {
        $g['rep'] = array_keys($g['rep']);
        $g['szobak'] = array_values($g['szobak']);
        usort($g['szobak'], fn($a, $b) => $a['ar'] <=> $b['ar']);
        $g['ar'] = $g['szobak'][0]['ar'];
    }
    unset($g);
    return array_values($napok);
}

function idoszak(): array
{
    $holnap = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Budapest'));
    $datum = fn($s) => is_string($s) && preg_match('/^\d{4}-\d\d-\d\d$/', $s) ? $s : null;
    $df = max($datum($_GET['df'] ?? null) ?? '', $holnap->format('Y-m-d'));
    $dt = $datum($_GET['dt'] ?? null) ?? $holnap->modify('+' . NAPOK_ELORE . ' days')->format('Y-m-d');
    return [$df, max($df, $dt)];
}

/** Pontos végösszegek az árszolgáltatásból: [kulcs => [did, rid]] -> [kulcs => ['ok'=>bool, 'ossz'=>?float]] */
function vegosszegek(array $parok, int $felnott, array $korok): array
{
    $f = forras();
    $eredmeny = [];
    $kerendo = [];
    foreach ($parok as $k => [$did, $rid]) {
        $tarolt = gyorsitotar_olvas('ar-' . FORRAS . "-$did-$rid-$felnott-" . implode('_', $korok), ERVENYES);
        if ($tarolt !== null) {
            $eredmeny[$k] = $tarolt;
        } else {
            $kerendo[$k] = $f['ugyfel'] + ['composition' => ['adults' => $felnott, 'children' => $korok],
                                            'date_id' => $did, 'room_id' => $rid, 'debug' => 0];
        }
    }
    foreach (cesys_post_tobb($f['ar'], $kerendo) as $k => $valasz) {
        $d = $valasz['data'] ?? null;
        if (!is_array($d)) {
            continue;   // nem jött válasz: nem tároljuk, legközelebb újra megkérdezzük
        }
        $ossz = $d['total_price'][$f['penznem']] ?? null;
        $e = ['ok' => in_array($d['availability'] ?? '', ['OK', 'RQ'], true) && is_numeric($ossz),
              'ossz' => is_numeric($ossz) ? round((float)$ossz, 2) : null];
        [$did, $rid] = $parok[$k];
        gyorsitotar_ir('ar-' . FORRAS . "-$did-$rid-$felnott-" . implode('_', $korok), $e);
        $eredmeny[$k] = $e;
    }
    return $eredmeny;
}

function valasz_tartalekkal(string $kulcs, callable $szamol): void
{
    $friss = gyorsitotar_olvas($kulcs, ERVENYES);
    if ($friss !== null) {
        json_valasz($friss);
    }
    $e = $szamol();
    if ($e === null) {
        $regi = gyorsitotar_olvas($kulcs, GYORSITOTAR_TARTALEK);
        json_valasz($regi ? $regi + ['elavult' => true] : ['ok' => false, 'uzenet' => 'Az élő árak most nem tölthetők be.'], $regi ? 200 : 503);
    }
    gyorsitotar_ir($kulcs, $e);
    json_valasz($e);
}

$muvelet = (string)($_GET['a'] ?? 'lista');

if ($muvelet === 'lista') {
    valasz_tartalekkal('lista', function () {
        [$df, $dt] = idoszak();
        $e = ['ok' => true, 'frissitve' => date('c'), 'penznem' => forras()['penznem'], 'hotels' => []];
        foreach (SZALLODAK as $kulcs => $sz) {
            $x = idopontok($sz, 2, [], $df, $dt);
            if ($x === null) {
                continue;
            }
            $sorok = array_filter(array_map(fn($y) => idopont_sor($sz, $y), $x));
            if (isset($sz['szobak'])) {
                // 2 felnőttnél az oldal szobatípusai („2+0 …”); a pótágyas és 2+2-es változatok a családi árnál jönnek
                $sorok = array_filter($sorok, fn($s) => in_array($s['eredeti'], $sz['szobak'], true));
            }
            $napok = csoportosit($sorok);
            if ($napok) {
                $e['hotels'][$kulcs] = ['cols' => isset($sz['szobak']) ? array_keys($sz['szobak']) : null, 'dates' => $napok];
            }
        }
        return $e['hotels'] ? $e : null;
    });
}

[$felnott, $korok] = utasok_a_kerelembol();

if ($muvelet === 'csalad') {
    $h = (string)($_GET['h'] ?? '');
    if (!isset(SZALLODAK[$h])) {
        json_valasz(['ok' => false, 'uzenet' => 'Ismeretlen szálloda.'], 400);
    }
    [$df, $dt] = idoszak();
    valasz_tartalekkal("csalad-$h-$felnott-" . implode('_', $korok) . "-$df-$dt", function () use ($h, $felnott, $korok, $df, $dt) {
        $sz = SZALLODAK[$h];
        $x = idopontok($sz, $felnott, $korok, $df, $dt);
        if ($x === null) {
            return null;
        }
        $napok = array_slice(csoportosit(array_filter(array_map(fn($y) => idopont_sor($sz, $y), $x))), 0, MAX_CSALADI_NAP);
        // naponként a legolcsóbb szoba végösszege; ha az erre az összetételre nem foglalható, a következőé
        // (legfeljebb 3 körben), így egy kérés nagyjából annyi árlekérdezés, ahány indulási nap
        $ossz = [];
        for ($kor = 0; $kor < 3; $kor++) {
            $parok = [];
            foreach ($napok as $i => $g) {
                $van = false;
                for ($j = 0; $j < $kor; $j++) {
                    $van = $van || !empty($ossz["$i-$j"]['ok']);
                }
                if (!$van && isset($g['szobak'][$kor])) {
                    $parok["$i-$kor"] = [$g['szobak'][$kor]['did'], $g['szobak'][$kor]['rid']];
                }
            }
            if (!$parok) {
                break;
            }
            $ossz += vegosszegek($parok, $felnott, $korok);
        }
        $ki = [];
        foreach ($napok as $i => $g) {
            foreach ($g['szobak'] as $j => &$s) {
                $o = $ossz["$i-$j"] ?? null;
                $s['ossz'] = $o && $o['ok'] ? $o['ossz'] : null;
                $s['nincs'] = $o && !$o['ok'];   // erre az összetételre nem foglalható
            }
            unset($s);
            $g['szobak'] = array_values(array_filter($g['szobak'], fn($s) => !$s['nincs']));
            $jo = array_values(array_filter($g['szobak'], fn($s) => $s['ossz'] !== null));
            if (!$jo) {
                continue;   // a legolcsóbb szobák egyike sem foglalható erre az összetételre
            }
            $g['ossz'] = $jo[0]['ossz'];
            $g['szoba'] = $jo[0]['nev'];
            $g['ell'] = $jo[0]['ell'];
            $ki[] = $g;
        }
        return ['ok' => true, 'frissitve' => date('c'), 'penznem' => forras()['penznem'], 'ac' => $felnott, 'ages' => $korok,
                'dates' => $ki, 'tobb' => count($napok) >= MAX_CSALADI_NAP];
    });
}

if ($muvelet === 'ar') {
    $did = (int)($_GET['d'] ?? 0);
    $rid = (int)($_GET['r'] ?? 0);
    if ($did <= 0 || $rid <= 0) {
        json_valasz(['ok' => false, 'uzenet' => 'Hiányzó időpont vagy szoba.'], 400);
    }
    $e = vegosszegek([0 => [$did, $rid]], $felnott, $korok)[0] ?? null;
    json_valasz($e ? ['ok' => true, 'elerheto' => $e['ok'], 'ossz' => $e['ossz'], 'penznem' => forras()['penznem']]
                   : ['ok' => false, 'uzenet' => 'Az ár most nem kérdezhető le.']);
}

json_valasz(['ok' => false, 'uzenet' => 'Ismeretlen kérés.'], 400);
