<?php
// A bahari-oldal élő adatai: a TUI BLUE Bahari Zanzibar pozsonyi indulású, 7 éjszakás,
// all inclusive útjai az atlastravel.sk kínálatából (időpontok, szobák, járatidők,
// árak). Gyerekes utazásnál a végösszeget a CeSYS árszolgáltatása adja.
// A frissites.py proxy.php néven másolja a bahari-oldal mellé; az oldal így hívja:
//   proxy.php?ac=2            (2 felnőtt)
//   proxy.php?ac=2&ages[]=5   (2 felnőtt + egy 5 éves gyerek)
// Válasz: {"ok":true,"kids_ok":true,"rows":[{f,t,n,lm,out,in,arr,back,room,room_sk,price,orig,total}]}

declare(strict_types=1);
require __DIR__ . '/cesys.php';

const FORRAS = 'https://www.atlastravel.sk/searchmasks/generation_dates_terms';
const AR_SZOLGALTATAS = 'https://api-ng.cesys.eu/online/v1.4//sk/online/availability-price';
const UGYFEL = ['client_id' => 21656, 'customer_id' => '8116'];   // atlastravel.sk a CeSYS-ben
const SZALLODA = 8255;          // TUI BLUE Bahari (atlastravel.sk)
const UTICEL = 685;             // Zanzibár
const REPTER = 7;               // Pozsony
const ELLATAS = 8;              // all inclusive
const ERVENYES = 1800;          // a tárolt adat fél óráig friss
const NAPOK_ELORE = 330;        // ennyi napra előre keresünk
const MAX_OLDAL = 2;            // legfeljebb ennyi találati oldal (oldalanként 200 időpont; általában 1 elég)

[$felnott, $korok] = utasok_a_kerelembol();
$kulcs = 'bahari-' . $felnott . '-' . implode('_', $korok);

$friss = gyorsitotar_olvas($kulcs, ERVENYES);
if ($friss !== null) {
    json_valasz($friss);
}

function idopontok_lekerdezese(int $felnott, array $korok): ?array
{
    $ma = new DateTimeImmutable('today', new DateTimeZone('Europe/Budapest'));
    $alap = [
        ['di[]', UTICEL], ['df', $ma->format('Y-m-d')], ['dt', $ma->modify('+' . NAPOK_ELORE . ' days')->format('Y-m-d')],
        ['ac', $felnott], ['cc', count($korok)],
    ];
    foreach (array_values($korok) as $i => $kor) {
        $alap[] = ['ca' . ($i + 1), $kor];
    }
    $alap = array_merge($alap, [
        ['ti', 1], ['uf', 7], ['ut', 9], ['bi', ELLATAS], ['pt', 15000], ['ai[]', REPTER], ['ar', 4], ['sp', 'false'],
        ['bi_f[]', ELLATAS], ['ti_f', 1], ['ai_f[]', REPTER], ['lm_f', ''], ['fm_f', ''], ['uf_f', 7], ['ut_f', 9],
        ['pf_f', ''], ['pt_f', 15000], ['ri_f', ''], ['rv_f', ''], ['ns_f', 'false'], ['sp_f', 'false'],
        ['rop', 200], ['hotel', SZALLODA], ['skip', 0], ['source', 'cesys_default'], ['sm', 1],
        ['sort', 'date_from'], ['direction', 'asc'], ['currency', 'EUR'],
    ]);
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

function sorra_alakit(array $x): ?array
{
    $ar = $x['price_from']['EUR'] ?? null;
    if (!is_numeric($ar) || empty($x['date_from']) || empty($x['date_to'])) {
        return null;
    }
    $kedvezmeny = $x['discount']['EUR'] ?? 0;
    $jarat = $x['flight_info'] ?? [];
    return [
        'f' => $x['date_from'],
        't' => $x['date_to'],
        'n' => (int)($x['duration_night'] ?? 0),
        'lm' => !empty($x['last_minute']),
        'out' => $x['outbound_flight_time'] ?? null,
        'in' => $x['inbound_flight_time'] ?? null,
        'arr' => ido_resz($jarat['arrival_to_destination'] ?? null),
        'back' => ido_resz($jarat['arrival_from_destination'] ?? null),
        'room' => html_entity_decode((string)($x['room_original_name'] ?? $x['room_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'room_sk' => html_entity_decode((string)($x['room_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'price' => (int)round((float)$ar),
        'orig' => (int)round((float)$ar + (is_numeric($kedvezmeny) && $kedvezmeny > 0 ? (float)$kedvezmeny : 0)),
        'total' => null,
        '_date_id' => $x['id'] ?? null,
        '_room_id' => $x['room_id'] ?? null,
    ];
}

$idopontok = idopontok_lekerdezese($felnott, $korok);
if ($idopontok === null) {
    // a forrás most nem érhető el: a legutóbbi (legfeljebb egynapos) adat, ha van
    $regi = gyorsitotar_olvas($kulcs, GYORSITOTAR_TARTALEK);
    json_valasz($regi ?? ['ok' => false, 'uzenet' => 'Az árak most nem tölthetők be.'], $regi ? 200 : 503);
}

$sorok = array_values(array_filter(array_map('sorra_alakit', $idopontok)));

// Gyerekes utazásnál a pontos végösszeg az árszolgáltatásból (a lista csak „-tól” árat ad).
if ($korok) {
    $torzsek = [];
    foreach ($sorok as $i => $sor) {
        if ($sor['_date_id'] && $sor['_room_id']) {
            $torzsek[$i] = UGYFEL + [
                'composition' => ['adults' => $felnott, 'children' => $korok],
                'date_id' => $sor['_date_id'], 'room_id' => $sor['_room_id'], 'debug' => 0,
            ];
        }
    }
    foreach (cesys_post_tobb(AR_SZOLGALTATAS, $torzsek) as $i => $valasz) {
        $adat = $valasz['data'] ?? null;
        if (!is_array($adat)) {
            continue;
        }
        if (($adat['availability'] ?? '') !== 'OK' && ($adat['availability'] ?? '') !== 'RQ') {
            $sorok[$i] = null;  // erre az összetételre nem foglalható
            continue;
        }
        $osszeg = $adat['total_price']['EUR'] ?? null;
        if (is_numeric($osszeg)) {
            $sorok[$i]['total'] = (int)round((float)$osszeg);
        }
    }
    $sorok = array_values(array_filter($sorok));
}

foreach ($sorok as &$sor) {
    unset($sor['_date_id'], $sor['_room_id']);
}
unset($sor);

$eredmeny = ['ok' => true, 'kids_ok' => true, 'rows' => $sorok, 'frissitve' => date('c')];
gyorsitotar_ir($kulcs, $eredmeny);
json_valasz($eredmeny);
