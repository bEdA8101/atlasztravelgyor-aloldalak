<?php
// Közös segédfüggvények az élő adatokhoz: lekérdezés a CeSYS-oldalakról
// (atlasztravelgyor.hu, atlastravel.sk) és a CeSYS árszolgáltatásáról, rövid
// ideig tartó tárolás (gyorsítótár), JSON-válasz. A frissites.py az élő adatos
// oldalak mellé másolja (cesys.php néven).
//
// A lekérdezések nyíltan megnevezik magukat (CESYS_UA), és a gyorsítótár miatt
// egy adott keresés legfeljebb félóránként egyszer jut el a forráshoz.

declare(strict_types=1);

const CESYS_UA = 'AtlaszTravelGyor-arfrissito/1.0 (+https://www.atlasztravelgyor.hu)';
const CESYS_IDOKORLAT = 20;          // másodperc egy lekérdezésre
const GYORSITOTAR_TARTALEK = 86400;  // ha a forrás nem elérhető, legfeljebb 1 napos adatot adunk vissza

/** Lekérdezési paraméterek összefűzése úgy, ahogy a CeSYS várja (pl. di[]=685&ai[]=7). */
function cesys_lekerdezes(array $parok): string
{
    $reszek = [];
    foreach ($parok as [$nev, $ertek]) {
        $reszek[] = rawurlencode((string)$nev) . '=' . rawurlencode((string)$ertek);
    }
    return implode('&', $reszek);
}

/** Hibanapló: a forrásoldalak kimaradásai (idő, cím lekérdezés nélkül, ok), az utolsó ~100 bejegyzés. */
function naplo(string $url, string $ok): void
{
    $fajl = gyorsitotar_fajl('hibanaplo');
    $sorok = is_file($fajl) ? (json_decode((string)file_get_contents($fajl), true) ?: []) : [];
    $sorok[] = ['ido' => date('c'), 'cim' => strtok($url, '?'), 'ok' => mb_substr($ok, 0, 120)];
    gyorsitotar_ir('hibanaplo', array_slice($sorok, -100));
}

function hibanaplo(int $db = 30): array
{
    $fajl = gyorsitotar_fajl('hibanaplo');
    $sorok = is_file($fajl) ? (json_decode((string)file_get_contents($fajl), true) ?: []) : [];
    return array_slice($sorok, -$db);
}

function cesys_kezelo(string $url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => CESYS_IDOKORLAT,
        CURLOPT_USERAGENT => CESYS_UA,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'],
    ]);
    return $ch;
}

/** A válasz feldolgozása; ha nem jó, bekerül a hibanaplóba. */
function cesys_valasz($ch, string $url, $valasz): ?array
{
    $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($valasz === false || $valasz === null || $kod !== 200) {
        naplo($url, $kod ? "HTTP $kod" : ('a kapcsolat megszakadt vagy túl lassú' . (curl_error($ch) ? ': ' . curl_error($ch) : '')));
        return null;
    }
    $adat = json_decode((string)$valasz, true);
    if (!is_array($adat)) {
        naplo($url, 'nem JSON válasz: ' . substr(preg_replace('/\s+/', ' ', strip_tags((string)$valasz)), 0, 60));
        return null;
    }
    return $adat;
}

/** Egy HTML-oldal letöltése (nem JSON), egy újrapróbálással; hiba esetén null. */
function cesys_html(string $url): ?string
{
    for ($proba = 0; $proba < 2; $proba++) {
        if ($proba) {
            usleep(800000);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => CESYS_IDOKORLAT,
            CURLOPT_USERAGENT => CESYS_UA, CURLOPT_ENCODING => '']);
        $valasz = curl_exec($ch);
        $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($valasz !== false && $kod === 200) {
            return (string)$valasz;
        }
        naplo($url, $kod ? "HTTP $kod" : 'a kapcsolat megszakadt vagy túl lassú');
    }
    return null;
}

/** Több weboldal (HTML) párhuzamosan, kulcs szerint; ami nem jött meg, az null (újrapróbálás nélkül). */
function cesys_html_tobb(array $cimek, int $egyszerre = 3): array
{
    $eredmeny = [];
    foreach (array_chunk($cimek, $egyszerre, true) as $csomag) {
        $multi = curl_multi_init();
        $kezelok = [];
        foreach ($csomag as $kulcs => $cim) {
            $ch = curl_init($cim);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => CESYS_IDOKORLAT,
                CURLOPT_USERAGENT => CESYS_UA, CURLOPT_ENCODING => '']);
            curl_multi_add_handle($multi, $ch);
            $kezelok[$kulcs] = $ch;
        }
        do {
            $allapot = curl_multi_exec($multi, $fut);
            if ($fut) {
                curl_multi_select($multi, 1.0);
            }
        } while ($fut && $allapot === CURLM_OK);
        foreach ($kezelok as $kulcs => $ch) {
            $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $eredmeny[$kulcs] = $kod === 200 ? (string)curl_multi_getcontent($ch) : null;
            if ($kod !== 200) {
                naplo($csomag[$kulcs], $kod ? "HTTP $kod" : 'a kapcsolat megszakadt vagy túl lassú');
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
    }
    return $eredmeny;
}

/** Egy JSON-GET; ha nem sikerül, egyszer újrapróbálja. */
function cesys_get(string $url): ?array
{
    for ($proba = 0; $proba < 2; $proba++) {
        if ($proba) {
            usleep(800000);
        }
        $ch = cesys_kezelo($url);
        $adat = cesys_valasz($ch, $url, curl_exec($ch));
        curl_close($ch);
        if ($adat !== null) {
            return $adat;
        }
    }
    return null;
}

/** Több JSON-GET párhuzamosan (pl. a találati lista több oldala). Kulcs szerint adja vissza a válaszokat. */
function cesys_get_tobb(array $cimek, int $egyszerre = 4): array
{
    $eredmeny = [];
    foreach (array_chunk($cimek, $egyszerre, true) as $csomag) {
        $multi = curl_multi_init();
        $kezelok = [];
        foreach ($csomag as $kulcs => $cim) {
            $ch = cesys_kezelo($cim);
            curl_multi_add_handle($multi, $ch);
            $kezelok[$kulcs] = $ch;
        }
        do {
            $allapot = curl_multi_exec($multi, $fut);
            if ($fut) {
                curl_multi_select($multi, 1.0);
            }
        } while ($fut && $allapot === CURLM_OK);
        foreach ($kezelok as $kulcs => $ch) {
            $eredmeny[$kulcs] = cesys_valasz($ch, $csomag[$kulcs], curl_multi_getcontent($ch));
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
    }
    // ami nem jött meg, azt egyenként, kis szünettel még egyszer megpróbáljuk
    foreach ($eredmeny as $kulcs => $adat) {
        if ($adat === null) {
            usleep(800000);
            $ch = cesys_kezelo($cimek[$kulcs]);
            $eredmeny[$kulcs] = cesys_valasz($ch, $cimek[$kulcs], curl_exec($ch));
            curl_close($ch);
        }
    }
    return $eredmeny;
}

/** Több JSON-POST párhuzamosan (a CeSYS árszolgáltatásához). Kulcs szerint adja vissza a válaszokat. */
function cesys_post_tobb(string $url, array $torzsek, int $egyszerre = 10): array
{
    $eredmeny = [];
    foreach (array_chunk($torzsek, $egyszerre, true) as $csomag) {
        $multi = curl_multi_init();
        $kezelok = [];
        foreach ($csomag as $kulcs => $torzs) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => CESYS_IDOKORLAT,
                CURLOPT_USERAGENT => CESYS_UA,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($torzs),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            ]);
            curl_multi_add_handle($multi, $ch);
            $kezelok[$kulcs] = $ch;
        }
        do {
            $allapot = curl_multi_exec($multi, $fut);
            if ($fut) {
                curl_multi_select($multi, 1.0);
            }
        } while ($fut && $allapot === CURLM_OK);
        foreach ($kezelok as $kulcs => $ch) {
            $adat = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 ? json_decode((string)curl_multi_getcontent($ch), true) : null;
            $eredmeny[$kulcs] = is_array($adat) ? $adat : null;
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
    }
    return $eredmeny;
}

function gyorsitotar_fajl(string $kulcs): string
{
    $mappa = __DIR__ . '/.gyorsitotar';
    if (!is_dir($mappa)) {
        @mkdir($mappa, 0700, true);
    }
    return $mappa . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $kulcs) . '.json';
}

/** A tárolt adat, ha nem régebbi $maxKor másodpercnél; különben null. */
function gyorsitotar_olvas(string $kulcs, int $maxKor): ?array
{
    $fajl = gyorsitotar_fajl($kulcs);
    if (!is_file($fajl) || time() - (int)filemtime($fajl) > $maxKor) {
        return null;
    }
    $adat = json_decode((string)file_get_contents($fajl), true);
    return is_array($adat) ? $adat : null;
}

function gyorsitotar_ir(string $kulcs, array $adat): void
{
    $fajl = gyorsitotar_fajl($kulcs);
    $ideiglenes = $fajl . '.' . getmypid();
    if (@file_put_contents($ideiglenes, json_encode($adat, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($ideiglenes, $fajl);
    }
}

/**
 * A tárolt válaszok közül törli a régieket (alapból a 2 napnál régebbieket; $kivetelek: kulcs-eleje -> másodperc),
 * hogy a tárhelyen ne gyűljenek a fájlok. Óránként legfeljebb egyszer fut.
 */
function gyorsitotar_takaritas(array $kivetelek = [], int $alap = 2 * 86400): void
{
    $mappa = __DIR__ . '/.gyorsitotar';
    $jel = $mappa . '/.takaritas';
    if (!is_dir($mappa) || (is_file($jel) && time() - (int)filemtime($jel) < 3600)) {
        return;
    }
    @touch($jel);
    foreach (glob($mappa . '/*.json') ?: [] as $fajl) {
        $nev = basename($fajl);
        $maxKor = $alap;
        foreach ($kivetelek as $eleje => $kor) {
            if (strncmp($nev, $eleje, strlen($eleje)) === 0) {
                $maxKor = $kor;
                break;
            }
        }
        if (time() - (int)@filemtime($fajl) > $maxKor) {
            @unlink($fajl);
        }
    }
}

function json_valasz(array $adat, int $kod = 200): void
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($adat, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Igaz, ha a tárhely engedi, hogy a program a válasz elküldése után tovább dolgozzon (PHP-FPM vagy LiteSpeed). */
function valasz_utan_folytathato(): bool
{
    return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
}

/** Elküldi a választ, lezárja a kapcsolatot, és a program tovább fut (pl. a tárolt adat frissítésére). */
function valasz_es_folytatas(array $adat): void
{
    ignore_user_abort(true);
    @set_time_limit(180);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($adat, JSON_UNESCAPED_UNICODE);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
}

/** Felnőttek száma és gyerekek életkora a kérésből, ellenőrizve. */
function utasok_a_kerelembol(): array
{
    $felnott = (int)($_GET['ac'] ?? 2);
    $felnott = max(1, min(4, $felnott));
    $korok = [];
    foreach ((array)($_GET['ages'] ?? []) as $kor) {
        if (is_numeric($kor) && (int)$kor >= 0 && (int)$kor <= 17) {
            $korok[] = (int)$kor;
        }
    }
    return [$felnott, array_slice($korok, 0, 4)];
}

/** „2026-11-22T14:20:00.000” -> „14:20” */
function ido_resz(?string $datumIdo): ?string
{
    return ($datumIdo && strlen($datumIdo) >= 16) ? substr($datumIdo, 11, 5) : null;
}
