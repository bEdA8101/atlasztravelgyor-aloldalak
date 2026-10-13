<?php
// Az aloldalak ajánlatkérő űrlapját küldi el e-mailben a győri irodának.
// A frissites.py ezt a fájlt másolja az oldalak mellé azon a néven, amelyet
// az oldal használ (send.php vagy kuldes.php). PHP-s tárhelyen működik; ahol
// nincs PHP (pl. GitHub Pages), az oldal levelezőprogramot nyit helyette.
//
// A címzett itt rögzített, a látogató nem tudja megváltoztatni.

declare(strict_types=1);

const CIMZETT = 'gyor@atlasztravel.hu';
// A feladó a tárhely saját domainjén legyen, különben a levél spambe kerülhet.
const FELADO = 'weboldal@atlasztravelgyor.hu';
const HIBAUZENET = 'Az ajánlatkérést most nem sikerült elküldeni. Kérjük, hívjon minket: 06 20 266 8144';
const MAX_MEZO = 60;
const MAX_HOSSZ = 3000;

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    valasz(false, 'Csak űrlapküldés engedélyezett.', 405);
}
if (count($_POST) === 0 || count($_POST) > MAX_MEZO) {
    valasz(false, HIBAUZENET, 400);
}
// Rejtett csapdamező: ember nem tölti ki, a spamrobotok igen.
if (trim((string)($_POST['weboldal'] ?? '')) !== '') {
    valasz(true);
}

$email = trim((string)($_POST['email'] ?? ''));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    valasz(false, 'Kérjük, adjon meg érvényes e-mail-címet.', 422);
}

$sorok = [];
foreach ($_POST as $mezo => $ertek) {
    if (!is_string($mezo) || !preg_match('/^[a-z0-9_\-]{1,40}$/i', $mezo) || $mezo === 'weboldal') {
        continue;
    }
    if (is_array($ertek)) {
        $ertek = implode(', ', array_filter($ertek, 'is_string'));
    }
    $ertek = trim(rovidit((string)$ertek, MAX_HOSSZ));
    if ($ertek !== '') {
        $sorok[] = $mezo . ': ' . $ertek;
    }
}

$targyResz = (string)($_POST['szalloda'] ?? $_POST['uticel'] ?? $_POST['oldal'] ?? 'weboldal');
$targy = 'Ajánlatkérés – ' . rovidit(preg_replace('/[\r\n\t]+/', ' ', $targyResz) ?? '', 120);
$oldal = preg_replace('/[^a-z0-9.:\-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? ''));

$torzs = implode("\n", $sorok) . "\n\n--\nKüldve: " . date('Y-m-d H:i') . ($oldal !== '' ? " – $oldal" : '') . "\n";
$fejlecek = implode("\r\n", [
    'From: =?UTF-8?B?' . base64_encode('Atlasz Travel Győr weboldal') . '?= <' . FELADO . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);

$elkuldve = mail(CIMZETT, '=?UTF-8?B?' . base64_encode($targy) . '?=', $torzs, $fejlecek);
$elkuldve ? valasz(true) : valasz(false, HIBAUZENET, 500);
