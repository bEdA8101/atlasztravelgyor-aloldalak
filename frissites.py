#!/usr/bin/env python3
"""Az Atlasz Travel központi aloldalainak győri változata.

Letölti a központ aloldalait (pl. https://oman.atlasztravel.hu/) a képeikkel
együtt, a központi elérhetőségeket a győri irodáéra cseréli, és az eredményt
a <név>/ mappába írja (pl. oman/index.html). Ha a központ frissíti az oldalt
(árak, időpontok), elég újra lefuttatni.

Használat:
    python frissites.py              # az összes oldal
    python frissites.py oman riu     # csak a megnevezettek

A program leáll és hibát jelez, ha a központ oldalán valami úgy megváltozott,
hogy egy csere nem talál, vagy a kész oldalon központi elérhetőség maradna.
"""

import base64
import hashlib
import html as html_lib
import re
import shutil
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

# --- Győri adatok: itt kell módosítani, ha valami változik -------------------

GYOR = {
    "nev": "Atlasz Travel Győr",
    "cim": "9021 Győr, Király u. 1.",
    "nyitvatartas": "hétfő–péntek 10:00–17:00",
    "telefon": "06 20 266 8144",
    "telefon_link": "+36202668144",
    "email": "gyor@atlasztravel.hu",
    "weboldal": "https://www.atlasztravelgyor.hu",
    "weboldal_felirat": "atlasztravelgyor.hu",
    "domain": "atlasztravelgyor.hu",
    "terkep": "https://www.google.com/maps/search/?api=1&query=Atlasz+Travel+Gy%C5%91r+9021+Gy%C5%91r+Kir%C3%A1ly+u.+1.",
}

# A klónozott aloldalak: név -> a központi aloldal címe.
OLDALAK = {
    "oman": "https://oman.atlasztravel.hu/",
    "riu": "https://riu.atlasztravel.hu/",
    "mauritius": "https://mauritius.atlasztravel.hu/",
    "maldiv": "https://maldiv.atlasztravel.hu/",
    "vietnam": "https://vietnam.atlasztravel.hu/",
}

# Előnézet: amíg nincs saját tárhely, az oldalak a GitHub Pages-en láthatók. Ilyenkor
# „noindex” jelölést kapnak, hogy a keresők ne listázzák az ideiglenes címet.
# Élesítés előtt állítsd False-ra, és futtasd újra a programot.
ELONEZET = True

ALAP = Path(__file__).resolve().parent
KIMENET = ALAP
URLAP_SABLON = ALAP / "urlap_kuldes.php"

TEL_SZOVEG = r"0\d \d\d \d{3} ?\d{2} ?\d{2}|0\d \d\d \d{3} \d{4}"
# a beviteli mezők kitöltési mintái (placeholder) nem valódi számok, azokat nem kell cserélni
MINTA_VAGY_TEL = r'(placeholder="[^"]*")|' + f"(?:{TEL_SZOVEG})"


class CsereHiba(Exception):
    pass


def letolt(url, probalkozas=4):
    kerest = urllib.request.Request(url, headers={"User-Agent": "AtlaszTravelGyor-frissites/1.0"})
    for i in range(probalkozas):
        try:
            with urllib.request.urlopen(kerest, timeout=90) as valasz:
                return valasz.read()
        except (urllib.error.URLError, ConnectionError, TimeoutError):
            if i == probalkozas - 1:
                raise
            time.sleep(2 ** (i + 1))


def csere(szoveg, minta, uj, leiras, legalabb=1, pontosan=None, flags=0):
    """Regex-csere, amely hibát jelez, ha a minta nem a várt számban fordul elő."""
    eredmeny, db = re.subn(minta, uj, szoveg, flags=flags)
    if pontosan is not None and db != pontosan:
        raise CsereHiba(f"{leiras}: {pontosan} találat helyett {db}")
    if db < legalabb:
        raise CsereHiba(f"{leiras}: nincs találat")
    return eredmeny


def kicsomagol(html):
    """Ha az oldal egy második, teljes HTML-dokumentumot csomagol be, azt veszi ki."""
    dokumentumok = [m.start() for m in re.finditer(r"(?i)<!doctype html>", html)]
    if len(dokumentumok) < 2:
        return html
    eleje = dokumentumok[1]
    vege = html.lower().index("</html>", eleje) + len("</html>")
    return html[eleje:vege] + "\n"


def eszkozok(html, nev):
    """Az oldal által hivatkozott helyi fájlok (képek stb.) listája."""
    talalat = set(re.findall(
        r"""["'(\s,]((?![a-z]+:)(?:\./)?[A-Za-z0-9_\-/]+\.(?:jpe?g|png|webp|gif|svg|avif|mp4|webm|ico|pdf|woff2?))""",
        html))
    for url in re.findall(rf"https://{nev}\.atlasztravel\.hu/([A-Za-z0-9_\-/.]+)", html):
        talalat.add(url)
    # a riu oldal a kisképeket a JavaScriptben állítja elő (kep.webp -> kep-kis.webp)
    if "-kis.webp" in html:
        talalat |= {t[:-5] + "-kis.webp" for t in talalat if t.startswith("kepek/") and t.endswith(".webp")}
    return sorted(t.lstrip("./") for t in talalat if t and not t.startswith("-"))


def beagyazott_kepek_ki(html, cel):
    """A HTML-be ágyazott (base64) képeket külön fájlba írja, hogy az oldal gyorsabban töltődjön."""
    def ki(m):
        eleje, tipus, adat, vege = m.groups()
        nyers = base64.b64decode(adat)
        kit = {"jpeg": "jpg", "png": "png", "webp": "webp"}[tipus]
        fajlnev = f"img/beagyazott-{hashlib.sha1(nyers).hexdigest()[:12]}.{kit}"
        (cel / "img").mkdir(parents=True, exist_ok=True)
        (cel / fajlnev).write_bytes(nyers)
        return eleje + fajlnev + vege
    # <img src="data:..."> és CSS url(data:...)
    return re.sub(r'(src="|url\(["\']?)data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)(["\']?\)?|")', ki, html)


def kozos_cserek(html, nev):
    g = GYOR
    # saját cím (canonical, og:url, og:image)
    html = csere(html, rf"https://{nev}\.atlasztravel\.hu", f"https://{nev}.{g['domain']}", "saját cím", legalabb=0)
    # a központi weboldal és ajánlatai helyett a győri (azonos foglalási rendszer, azonos ajánlatkódok)
    html = re.sub(r"https://(?:www\.)?atlasztravel\.hu", g["weboldal"], html)
    html = re.sub(r"(?<![@\w.\-/])atlasztravel\.hu", g["weboldal_felirat"], html)

    # telefonszámok
    html = re.sub(r"tel:\+36\d{8,9}", "tel:" + g["telefon_link"], html)
    html = re.sub(MINTA_VAGY_TEL, lambda m: m.group(1) or g["telefon"], html)
    tel_a = r'<a [^>]*href="tel:' + re.escape(g["telefon_link"]) + r'"[^>]*>' + re.escape(g["telefon"]) + r"</a>"
    html = re.sub(rf"({tel_a})(?:\s*(?:,|·)?\s*{tel_a})+", r"\1", html)
    html = re.sub(re.escape(g["telefon"]) + r"(?:,? (?:vagy|és) " + re.escape(g["telefon"]) + r")+", g["telefon"], html)

    # e-mail-címek
    html = re.sub(r"[\w.+\-]+@atlasztravel\.hu", g["email"], html)
    mail_a = r'<a href="mailto:' + re.escape(g["email"]) + r'">' + re.escape(g["email"]) + r"</a>"
    html = re.sub(rf"({mail_a})(?:\s*(?:<br>|,|·)?\s*{mail_a})+", r"\1", html)
    html = re.sub(r'const RECIPIENTS\s*=\s*"[^"]*"', f'const RECIPIENTS="{g["email"]}"', html)

    # cégnév a láblécben, a címben és a megosztási adatokban
    html = re.sub(r"(©\s*\d{4}\s*Atlasz Travel)(?! Győr)", r"\1 Győr", html)
    html = csere(html, r"(<title>[^<]*\| Atlasz [^<]*?)</title>", r"\1 – Győr</title>", "oldalcím", pontosan=1)
    html = re.sub(r'(<meta property="og:site_name" content="[^"]*?)"', r'\1 – Győr"', html)
    return html


TERKEP_LINK = f'href="{html_lib.escape(GYOR["terkep"])}" target="_blank" rel="noopener"'
GOMBOSTU = '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>'
BORITEK = '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>'


def premium_cserek(html):
    """A Mauritius és a Maldív oldal közös (Atlasz Prémium) sablonja."""
    g = GYOR
    html = csere(html, r'<div class="nm">[^<]*</div>', f'<div class="nm">{g["nev"]}</div>',
                 "tanácsadó neve", pontosan=1)
    # az ajánlatkérő kártyán a telefon és az e-mail alá az iroda címe
    html = csere(html, r'(<div class="contact">.*?<a href="mailto:[^"]+">[^<]+</a>)(\s*)(</div>)',
                 lambda m: f'{m.group(1)}\n        <a {TERKEP_LINK}>{g["cim"]}</a>{m.group(2)}{m.group(3)}',
                 "iroda címe az ajánlatkérő kártyán", pontosan=1, flags=re.S)
    irodak = (
        f'\n      <p><b>Győri irodánk</b>{g["cim"]}</p>'
        f'\n      <p><b>Nyitvatartás</b>{g["nyitvatartas"]}</p>'
        f'\n      <p><b>Időpontfoglalás</b><a href="tel:{g["telefon_link"]}">{g["telefon"]}</a>'
        f' · <a href="mailto:{g["email"]}">{g["email"]}</a></p>\n    '
    )
    html = csere(html, r'(<div class="offrow">)(?:(?!</div>).)*(</div>)', lambda m: m.group(1) + irodak + m.group(2),
                 "irodák listája", pontosan=1, flags=re.S)
    html = csere(html, r'(<h2 id="iroda-cim">)[^<]*(</h2>)', r"\1Személyesen is várjuk győri irodánkban\2",
                 "irodák címsora", pontosan=1)
    # lábléc: a tanácsadó neve helyett az iroda címe
    html = csere(html, r'(<br>\s*)[^<>\n]+? · (<a href="tel:)', rf'\g<1>{g["cim"]} · \2', "lábléc neve", pontosan=1)
    return html


def oman_cserek(html):
    g = GYOR
    html = csere(html, r'(\n[ \t]*)(<a href="https://www\.atlasztravelgyor\.hu" target="_blank" rel="noopener">)',
                 rf'\1<p class="mails">Irodánk: {g["cim"]}<br>Nyitva: {g["nyitvatartas"]}</p>\1\2',
                 "iroda címe az ajánlatkérő kártyán", pontosan=1)
    return csere(html, r'(<span>Telefon: .*?<br>E-mail: <a href="mailto:[^"]+">[^<]+</a>)(</span>)',
                 rf'\1<br>Iroda: {g["cim"]}\2', "iroda címe a láblécben", pontosan=1)


def lablec_cim(html):
    """A riu és a vietnam láblécének utolsó eleme a webcím; elé kerül az iroda címe."""
    g = GYOR
    return csere(html, r"<span>" + re.escape(g["weboldal_felirat"]) + r"</span>",
                 f'<span>{g["cim"]} · {g["weboldal_felirat"]}</span>', "iroda címe a láblécben", pontosan=1)


def riu_cserek(html):
    g = GYOR
    # az ajánlatkérő rész kapcsolati csempéi közé (telefon, e-mail) az iroda címe
    minta = (r'(<a href="mailto:[^"]+"><span class="ico">(<svg[^>]*>).*?</svg></span>'
             r'<div><span>E-mail</span><strong>[^<]+</strong></div></a>)')
    html = csere(html, minta, lambda m: (
        f'{m.group(1)}\n          <a {TERKEP_LINK}><span class="ico">{m.group(2)}{GOMBOSTU}</svg></span>'
        f'<div><span>Iroda</span><strong>{g["cim"]}</strong></div></a>'), "iroda címe a kapcsolatoknál", pontosan=1, flags=re.S)
    return lablec_cim(html)


def vietnam_cserek(html):
    g = GYOR
    # a „Hívjon minket” kártya alá e-mail- és irodakártya
    minta = r'(<a class="call-card" href="tel:[^"]+">\s*<span class="ico">(<svg[^>]*>).*?</a>)'

    def kartyak(m):
        svg, kozt = m.group(2), '\n        '
        return (f'{m.group(1)}{kozt}<a class="call-card" style="margin-top:.8rem" href="mailto:{g["email"]}">'
                f'<span class="ico">{svg}{BORITEK}</svg></span><div><span>Írjon nekünk</span>'
                f'<strong style="font-size:1.15rem">{g["email"]}</strong></div></a>'
                f'{kozt}<a class="call-card" style="margin-top:.8rem" {TERKEP_LINK}>'
                f'<span class="ico">{svg}{GOMBOSTU}</svg></span><div><span>Győri irodánk</span>'
                f'<strong style="font-size:1.15rem">{g["cim"]}</strong></div></a>')
    html = csere(html, minta, kartyak, "e-mail és iroda kártya", pontosan=1, flags=re.S)
    # lábléc: a telefon mellé e-mail is
    html = csere(html, r'(<span>Telefon: <a href="tel:[^"]+">[^<]+</a></span>)(\s*)',
                 rf'\1\2<span>E-mail: <a href="mailto:{g["email"]}">{g["email"]}</a></span>\2',
                 "e-mail a láblécben", pontosan=1)
    return lablec_cim(html)


OLDAL_CSEREK = {
    "oman": [oman_cserek],
    "riu": [riu_cserek],
    "mauritius": [premium_cserek],
    "maldiv": [premium_cserek],
    "vietnam": [vietnam_cserek],
}


def ellenoriz(html, nev):
    """Hibát jelez, ha a kész oldalon központi elérhetőség maradt."""
    g = GYOR
    hibak = []
    for tel in set(re.findall(r"tel:\+\d+", html)) - {"tel:" + g["telefon_link"]}:
        hibak.append(f"idegen telefonlink: {tel}")
    for tel in {m.group(0) for m in re.finditer(MINTA_VAGY_TEL, html) if not m.group(1)} - {g["telefon"]}:
        hibak.append(f"idegen telefonszám: {tel}")
    for mail in set(re.findall(r"[\w.+\-]+@atlasztravel\.hu", html)) - {g["email"]}:
        hibak.append(f"idegen e-mail: {mail}")
    for kotelezo in (g["telefon"], g["email"], g["cim"]):
        if kotelezo not in html:
            hibak.append(f"hiányzik a győri adat: {kotelezo}")
    maradt = re.findall(r".{0,40}(?<!gyor@)atlasztravel\.hu.{0,40}", html)
    hibak += [f"központi cím maradt: …{m}…" for m in maradt]
    if hibak:
        raise CsereHiba(f"{nev}: " + "; ".join(hibak))


def urlapkuldo_nev(html):
    m = re.search(r'fetch\("([\w\-]+\.php)"', html) or re.search(r'KULDO_CIM\s*=\s*"([\w\-]+\.php)"', html)
    return m.group(1) if m else None


def frissit(nev):
    forras = OLDALAK[nev]
    print(f"{nev}: letöltés innen: {forras}")
    html = letolt(forras).decode("utf-8")
    html = kicsomagol(html)

    # Ideiglenes mappába épít, és csak a sikeres végén cseréli le a régit.
    cel = KIMENET / nev
    uj = KIMENET / f".{nev}.uj"
    if uj.exists():
        shutil.rmtree(uj)
    uj.mkdir(parents=True)
    try:
        fajlok = eszkozok(html, nev)
        for i, utvonal in enumerate(fajlok, 1):
            hely = uj / utvonal
            hely.parent.mkdir(parents=True, exist_ok=True)
            hely.write_bytes(letolt(forras + utvonal))
            print(f"\r  képek: {i}/{len(fajlok)}", end="", flush=True)
        if fajlok:
            print()

        html = beagyazott_kepek_ki(html, uj)
        if ELONEZET:
            html = csere(html, r'(<meta charset="?utf-?8"?>)', r'\1\n<meta name="robots" content="noindex, nofollow">',
                         "előnézeti jelölés", pontosan=1, flags=re.I)
        html = kozos_cserek(html, nev)
        for fuggveny in OLDAL_CSEREK.get(nev, []):
            html = fuggveny(html)
        ellenoriz(html, nev)

        (uj / "index.html").write_text(html, encoding="utf-8")
        kuldo = urlapkuldo_nev(html)
        if kuldo:
            shutil.copyfile(URLAP_SABLON, uj / kuldo)
    except BaseException:
        shutil.rmtree(uj, ignore_errors=True)
        raise
    if cel.exists():
        shutil.rmtree(cel)
    uj.rename(cel)
    print(f"  kész: {nev}/index.html (űrlapküldő: {kuldo or 'nincs'})")


def nyitolap():
    """Előnézeti nyitólap a tároló gyökerébe: linkek az elkészült oldalakra."""
    sorok = []
    for nev, forras in OLDALAK.items():
        oldal = KIMENET / nev / "index.html"
        if not oldal.exists():
            continue
        cim = re.search(r"<title>([^<]*)</title>", oldal.read_text(encoding="utf-8"))
        felirat = html_lib.escape(cim.group(1) if cim else nev)
        sorok.append(
            f'  <li><a href="{nev}/">{felirat}</a><br><small>Leendő cím: https://{nev}.{GYOR["domain"]}/ · '
            f'forrás: <a href="{forras}">{html_lib.escape(forras)}</a></small></li>')
    tartalom = f"""<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Atlasz Travel Győr – aloldalak előnézete</title>
<style>
body{{margin:0;font:17px/1.6 system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#1d2b36;background:#f8f5ef}}
main{{max-width:760px;margin:0 auto;padding:40px 16px}}
h1{{font-size:28px;line-height:1.2;margin:0 0 12px}}
li{{margin:0 0 18px}}
a{{color:#0b4a63}}
small{{color:#5b6873;overflow-wrap:anywhere}}
</style>
</head>
<body>
<main>
<h1>Atlasz Travel Győr – aloldalak előnézete</h1>
<p>Ideiglenes előnézet a tárhely elkészültéig. Az oldalakat a keresők nem listázzák.
Az ajánlatkérő űrlap itt nem küld e-mailt (ehhez PHP-s tárhely kell): a legtöbb oldal
helyette a levelezőprogramot nyitja meg a kitöltött adatokkal.</p>
<ul>
{chr(10).join(sorok)}
</ul>
</main>
</body>
</html>
"""
    (KIMENET / "index.html").write_text(tartalom, encoding="utf-8")


def main(nevek):
    ismeretlen = [n for n in nevek if n not in OLDALAK]
    if ismeretlen:
        sys.exit(f"Ismeretlen oldal: {', '.join(ismeretlen)}. Választható: {', '.join(OLDALAK)}")
    for nev in nevek or list(OLDALAK):
        try:
            frissit(nev)
        except CsereHiba as hiba:
            sys.exit(f"HIBA – {hiba}\nAz oldal nem frissült. A központi oldal valószínűleg megváltozott, "
                     "a frissites.py cseréit igazítani kell.")
    nyitolap()


if __name__ == "__main__":
    main(sys.argv[1:])
