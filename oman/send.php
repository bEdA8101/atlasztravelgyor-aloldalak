<?php
// Az aloldalak űrlapjait (ajánlatkérés, foglalási igény) küldi el e-mailben a győri irodának.
// A frissites.py ezt a fájlt másolja az oldalak mellé azon a néven, amelyet az oldal használ
// (send.php vagy kuldes.php). PHP-s tárhelyen működik; ahol nincs PHP (pl. GitHub Pages), az oldal
// levelezőprogramot nyit helyette.
//
// A levél tárgyából és első soraiból mindig kiderül:
//   – a típus: Ajánlatkérés vagy Foglalási igény (az „urlap_tipus” mező; ha nincs, ajánlatkérés),
//   – az oldal: a program a saját helyéből állapítja meg (mappa vagy aldomain), a látogató nem írhatja át.
// Foglalási igénynél kötelező: utasonként teljes név, állampolgárság, születési dátum; a fő utas lakcíme,
// e-mail-címe és telefonszáma; az adatkezelési hozzájárulás.
// A címzett itt rögzített, a látogató nem tudja megváltoztatni. Az adatokat a program nem tárolja.

declare(strict_types=1);

const CIMZETT = 'gyor@atlasztravel.hu';
// A feladó a tárhely saját domainjén legyen, különben a levél spambe kerülhet.
const FELADO = 'weboldal@atlasztravelgyor.hu';
const HIBAUZENET = 'Az üzenetet most nem sikerült elküldeni. Kérjük, hívjon minket: +36 20 266 8144';
const MAX_MEZO = 200;
const MAX_HOSSZ = 3000;
// az oldalak neve a levélben (a mappa vagy az aldomain első része szerint)
const OLDALNEVEK = [
    'oman' => 'Omán', 'riu' => 'RIU szállodák', 'mauritius' => 'Mauritius', 'maldiv' => 'Maldív-szigetek',
    'vietnam' => 'Vietnám', 'bahari' => 'TUI BLUE Bahari, Zanzibár', 'zanzibar' => 'Zanzibár',
    'dominika' => 'Dominikai Köztársaság', 'mscutak' => 'MSC hajóutak',
];
// a mezők magyar neve a levélben (ami nincs itt, az a mező neve marad, aláhúzás helyett szóközzel)
const CIMKEK = [
    'nev' => 'Név', 'email' => 'E-mail', 'telefon' => 'Telefon', 'szalloda' => 'Szálloda', 'uticel' => 'Úti cél',
    'ajanlat' => 'Ajánlat', 'resort' => 'Resort', 'villa' => 'Villa', 'szobatipus' => 'Szobatípus', 'szoba' => 'Szoba',
    'idopont' => 'Időpont', 'idoszak' => 'Időszak', 'idotartam' => 'Időtartam', 'indulas' => 'Indulás', 'repuloter' => 'Repülőtér',
    'repulo' => 'Repülés', 'legitarsasag' => 'Légitársaság', 'ellatas' => 'Ellátás', 'felnottek' => 'Felnőttek', 'felnott' => 'Felnőttek',
    'gyerekek' => 'Gyerekek', 'gyerek' => 'Gyerekek', 'gyerekek_kora' => 'Gyerekek kora', 'ket_ev_alatti' => '2 év alatti gyerek',
    'utasok' => 'Utasok', 'szobak' => 'Szobák', 'szobak_szama' => 'Szobák száma', 'ar' => 'Ár (tájékoztató)', 'keret' => 'Keret',
    'jelleg' => 'Az utazás jellege', 'rugalmassag' => 'Rugalmasság', 'partvidek' => 'Partvidék', 'fizetes' => 'Fizetés',
    'utasbiztositas' => 'Utasbiztosítás', 'stornobiztositas' => 'Stornóbiztosítás', 'parkolas' => 'Reptéri parkolás',
    'uzenet' => 'Megjegyzés', 'megjegyzes' => 'Megjegyzés', 'adatkezeles' => 'Adatkezelési tájékoztató', 'kezdes' => 'Űrlap megnyitva',
    'oldal' => 'Oldal (az űrlap szerint)', 'hajout' => 'Hajóút', 'kabin' => 'Kabin', 'kabinok' => 'Kabinok',
    'extra' => 'Kiegészítők', 'gyermekek' => 'Gyerekek', 'tipus' => 'Űrlap',
];
// ezeket a program külön kezeli (fejléc, utaslista, lakcím)
const KULON = ['urlap_tipus', 'egyseg', 'weboldal', 'website', 'irsz', 'telepules', 'cim'];

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function valasz(bool $ok, string $uzenet = '', int $kod = 200): void
{
    http_response_code($kod);
    echo json_encode(['ok' => $ok, 'uzenet' => $uzenet], JSON_UNESCAPED_UNICODE);
    exit;
}

function rovidit(string $szoveg, int $hossz): string
{
    return function_exists('mb_substr') ? mb_substr($szoveg, 0, $hossz) : substr($szoveg, 0, $hossz);
}

/** Egysoros szöveg: sortörés és vezérlőkarakter nélkül. */
function sor($v, int $hossz = 300): string
{
    if (is_array($v)) {
        $v = implode(', ', array_filter($v, 'is_string'));
    }
    return trim(rovidit((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$v), $hossz));
}

function szuletesi_datum(string $s): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return false;
    }
    return $s < date('Y-m-d') && (int)$m[1] >= (int)date('Y') - 110;
}

/** Az utasok a beküldött mezőkből: utas_1_nev, … (aloldalak) vagy utas_nev[], … (bahari). */
function utasok(array $p): array
{
    $lista = [];
    foreach ($p as $mezo => $ertek) {
        if (is_string($mezo) && preg_match('/^utas_(\d{1,2})_nev$/', $mezo, $m)) {
            $i = (int)$m[1];
            $lista[$i] = ['nev' => sor($ertek, 120), 'allampolgarsag' => sor($p["utas_{$i}_allampolgarsag"] ?? '', 60),
                'szul' => sor($p["utas_{$i}_szul"] ?? '', 10), 'szoba' => sor($p["utas_{$i}_szoba"] ?? '1', 3),
                'tipus' => sor($p["utas_{$i}_tipus"] ?? '', 40)];
        }
    }
    ksort($lista);
    if (!$lista && is_array($p['utas_nev'] ?? null)) {
        foreach (array_values($p['utas_nev']) as $i => $nev) {
            $kor = sor($p['utas_kor'][$i] ?? '', 3);
            $tipus = sor($p['utas_tipus'][$i] ?? '', 20);
            $lista[$i + 1] = ['nev' => sor($nev, 120), 'allampolgarsag' => sor($p['utas_allampolgarsag'][$i] ?? '', 60),
                'szul' => sor($p['utas_szul'][$i] ?? '', 10), 'szoba' => sor($p['utas_szoba'][$i] ?? '1', 3),
                'tipus' => $tipus . ($tipus === 'gyerek' && $kor !== '' ? ", $kor éves" : '')];
        }
    }
    return array_values($lista);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    valasz(false, 'Csak űrlapküldés engedélyezett.', 405);
}
if (count($_POST) === 0 || count($_POST, COUNT_RECURSIVE) > MAX_MEZO) {
    valasz(false, HIBAUZENET, 400);
}
// Rejtett csapdamező: ember nem tölti ki, a spamrobotok igen.
if (trim((string)($_POST['weboldal'] ?? '')) !== '' || trim((string)($_POST['website'] ?? '')) !== '') {
    valasz(true);
}

$email = trim((string)($_POST['email'] ?? ''));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    valasz(false, 'Kérjük, adjon meg érvényes e-mail-címet.', 422);
}

// a típus és az oldal
$foglalas = ($_POST['urlap_tipus'] ?? '') === 'foglalasi_igeny';
$tipusNev = $foglalas ? 'Foglalási igény' : 'Ajánlatkérés';
$host = strtolower((string)preg_replace('/[^a-z0-9.\-]/i', '', (string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))));
$utvonal = dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'));
$mappa = basename($utvonal);
$kulcs = isset(OLDALNEVEK[$mappa]) ? $mappa : explode('.', $host)[0];
$oldalNev = OLDALNEVEK[$kulcs] ?? ($mappa !== '' && $mappa !== '.' && $mappa !== '/' ? $mappa : $host);
$oldalCim = 'https://' . $host . rtrim(str_replace('\\', '/', $utvonal), '/') . '/';

// foglalási igény: a kötelező adatok
$utasok = utasok($_POST);
$lakcim = trim(sor($_POST['irsz'] ?? '', 12) . ' ' . sor($_POST['telepules'] ?? '', 80) . ', ' . sor($_POST['cim'] ?? '', 160), ' ,');
if ($foglalas) {
    $telefon = (string)preg_replace('/\D+/', '', (string)($_POST['telefon'] ?? ''));
    if (!$utasok) {
        valasz(false, 'Kérjük, adja meg az utasok adatait.', 422);
    }
    foreach ($utasok as $i => $u) {
        if (mb_strlen($u['nev']) < 3 || strpos($u['nev'], ' ') === false || $u['allampolgarsag'] === '' || !szuletesi_datum($u['szul'])) {
            valasz(false, 'Kérjük, adja meg a(z) ' . ($i + 1) . '. utas teljes nevét, állampolgárságát és születési dátumát.', 422);
        }
        // gyereknél a születési dátum illjen a megadott életkorhoz (az utazásig legfeljebb egy év telhet el)
        if (preg_match('/gyerek, (\d+) éves/u', $u['tipus'], $k)) {
            $evek = (time() - strtotime($u['szul'] . ' 12:00:00')) / 31557600;
            if ($evek > (int)$k[1] + 1.05 || $evek < (int)$k[1] - 1.05) {
                valasz(false, 'A(z) ' . ($i + 1) . '. utas születési dátuma nem illik a megadott életkorhoz (' . (int)$k[1] . ' év).', 422);
            }
        }
    }
    if (trim((string)($_POST['irsz'] ?? '')) === '' || trim((string)($_POST['telepules'] ?? '')) === '' || trim((string)($_POST['cim'] ?? '')) === ''
        || strlen($telefon) < 8) {
        valasz(false, 'Kérjük, adja meg a fő utas lakcímét és telefonszámát.', 422);
    }
    if (trim((string)($_POST['adatkezeles'] ?? '')) === '') {
        valasz(false, 'A foglalási igényhez kérjük, fogadja el az adatkezelési tájékoztatót.', 422);
    }
}

// a levél
$sorok = ['TÍPUS: ' . mb_strtoupper($tipusNev), 'OLDAL: ' . $oldalNev . ' – ' . $oldalCim, str_repeat('-', 40)];
foreach ($_POST as $mezo => $ertek) {
    if (!is_string($mezo) || !preg_match('/^[a-z0-9_\-]{1,40}$/i', $mezo) || in_array($mezo, KULON, true)
        || preg_match('/^utas_/', $mezo) || preg_match('/^szoba_\d+_osszetetel$/', $mezo)) {
        continue;
    }
    $e = sor($ertek, MAX_HOSSZ);
    if ($e !== '') {
        $cimke = $mezo === 'szobak_szama' && ($_POST['egyseg'] ?? '') === 'kabin' ? 'Kabinok száma'
            : (CIMKEK[$mezo] ?? (preg_match('/^gyerek_(\d+)_kor$/', $mezo, $gy) ? $gy[1] . '. gyerek kora' : ucfirst(str_replace('_', ' ', $mezo))));
        $sorok[] = $cimke . ': ' . $e;
    }
}
if ($utasok) {
    $sorok[] = '';
    $sorok[] = 'UTASOK (a nevek az útlevél szerint):';
    $szobaSzam = 0;
    foreach ($utasok as $u) {
        if ($u['szoba'] !== (string)$szobaSzam) {
            $szobaSzam = $u['szoba'];
            $osszetetel = sor($_POST['szoba_' . $szobaSzam . '_osszetetel'] ?? '', 120);
            $sorok[] = '  ' . $szobaSzam . '. ' . (($_POST['egyseg'] ?? '') === 'kabin' ? 'kabin' : 'szoba') . ($osszetetel !== '' ? ' (' . $osszetetel . ')' : '') . ':';
        }
        $sorok[] = '    – ' . $u['nev'] . ($u['tipus'] !== '' ? ' (' . $u['tipus'] . ')' : '') . ', állampolgárság: '
            . ($u['allampolgarsag'] !== '' ? $u['allampolgarsag'] : '–') . ', született: ' . ($u['szul'] !== '' ? $u['szul'] : '–');
    }
}
if ($lakcim !== '') {
    $sorok[] = '';
    $sorok[] = 'A fő (szerződő) utas lakcíme: ' . $lakcim;
}

// a tárgyban a típus, az oldal és (ha van) a szálloda és az időpont
$reszlet = sor($_POST['szalloda'] ?? $_POST['uticel'] ?? $_POST['ajanlat'] ?? '', 90);
if (sor($_POST['idopont'] ?? '') !== '') {
    $ido = sor($_POST['idopont'], 200);
    $reszlet .= ($reszlet !== '' ? ', ' : '') . (mb_strlen($ido) > 50 ? rtrim(mb_substr($ido, 0, 50), ' ,:(') . '…' : $ido);
}
$targy = $tipusNev . ' – ' . $oldalNev . ' (' . $host . ')' . ($reszlet !== '' ? ' – ' . $reszlet : '');

$torzs = implode("\n", $sorok) . "\n\n--\nKüldve: " . date('Y-m-d H:i') . ' – ' . $oldalCim . "\n";
$fejlecek = implode("\r\n", [
    'From: =?UTF-8?B?' . base64_encode('Atlasz Travel Győr weboldal') . '?= <' . FELADO . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);

$elkuldve = mail(CIMZETT, '=?UTF-8?B?' . base64_encode(rovidit($targy, 200)) . '?=', $torzs, $fejlecek);
$elkuldve ? valasz(true) : valasz(false, HIBAUZENET, 500);
