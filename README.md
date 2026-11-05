# Atlasz Travel Győr – aloldalak

Az atlasztravelgyor.hu aloldalai: a központi Atlasz Travel aloldalak győri változatai.
A központ engedélyezte a tartalmak átvételét.

| Mappa | Leendő cím | Forrás |
|---|---|---|
| `oman/` | oman.atlasztravelgyor.hu | https://oman.atlasztravel.hu/ |
| `riu/` | riu.atlasztravelgyor.hu | https://riu.atlasztravel.hu/ |
| `mauritius/` | mauritius.atlasztravelgyor.hu | https://mauritius.atlasztravel.hu/ |
| `maldiv/` | maldiv.atlasztravelgyor.hu | https://maldiv.atlasztravel.hu/ |
| `vietnam/` | vietnam.atlasztravelgyor.hu | https://vietnam.atlasztravel.hu/ |
| `bahari/` | bahari.atlasztravelgyor.hu | https://bahari.atlasztravel.hu/ (élő árakkal) |
| `zanzibar/` | zanzibar.atlasztravelgyor.hu | https://zanzibar.atlasztravel.hu/ (teljes kereső élő adatokkal) |
| `mscutak/` | mscutak.atlasztravelgyor.hu | https://mscutak.atlasztravel.hu/ (MSC hajóutak; az árakat a központ az oldalban frissíti) |
| `dominika/` | dominika.atlasztravelgyor.hu | saját oldal (`sajat/dominika/`), élő árakkal és foglalási igénnyel |

**Előnézet** (a tárhely elkészültéig): https://beda8101.github.io/atlasztravelgyor-aloldalak/

## Mi hol van?

```
frissites.py        letölti a központi oldalakat, és elkészíti a győri változatot
feltoltes.py        feltölti a kész oldalakat a tárhelyre (FTP), csak a változott fájlokat
feltoltes.json      mi hova kerüljön a tárhelyen (melyik mappa melyik aldomainhez)
.github/workflows/  automatikus feltöltés és napi frissítés a GitHubon
urlap_kuldes.php    az ajánlatkérő űrlap e-mail-küldője (a program másolja az oldalak mellé)
elo/                az élő adatos oldalak saját adatlekérői (PHP), lásd lent
sajat/              a saját (nem a központtól átvett) oldalak forrása, pl. sajat/dominika/index.html
eszkozok/           a győri logó (átlátszó hátterű PNG), a program minden oldal mellé másolja
index.html          az előnézet nyitólapja (a program készíti)
oman/, riu/, …      a KÉSZ oldalak – a program készíti, kézzel ne szerkeszd őket
```

## Mit változtat a program a központi oldalon?

- A telefonszámok és e-mail-címek helyett a győri iroda elérhetősége szerepel
  (+36 20 266 8144, gyor@atlasztravel.hu), az ajánlatkérések is ide érkeznek.
- Az Atlasz Travel és az Atlasz Prémium logó helyén az Atlasz Travel Győr logó áll.
- Minden oldalon szerepel a győri iroda címe (9021 Győr, Király u. 1.) az ajánlatkérő résznél
  és a láblécben. Az irodalistában a győri iroda és a nyitvatartás áll.
- Az atlasztravel.hu-ra mutató linkek az atlasztravelgyor.hu-ra mutatnak. A foglalási rendszer
  azonos, így az ajánlatkódok a győri oldalon is működnek. A webcím mindenhol
  „www.atlasztravelgyor.hu” formában, élő linkként szerepel.
- Az oldalcímbe és a megosztási adatokba bekerül a „Győr” szó, a saját cím pedig az
  `<aldomain>.atlasztravelgyor.hu` lesz.
- Az oldalba ágyazott képeket külön fájlba teszi, így az oldalak gyorsabban töltődnek.
- Minden oldal láblécének aljára kis, halvány betűvel bekerül az általános tájékoztató (az árak
  tájékoztató jellegűek stb.). A szövege a `frissites.py` elején, a `LABLEC_TAJEKOZTATO` részben van.

A győri adatok a `frissites.py` elején, a `GYOR` részben vannak. A program futtatásához a
Python mellett a Pillow csomag kell (`pip install pillow`).

## Élő adatok (árak, időpontok, szobák)

Néhány központi aloldal (bahari, zanzibar, mscutak) az árakat és időpontokat a központ
szerverén futó kis programtól kéri. A győri változatban ezt a saját programunk végzi, amely a
frissites.py az oldal mellé másol az `elo/` mappából:

- `elo/cesys.php` – közös rész: lekérdezés a CeSYS-oldalakról, rövid tárolás, válasz.
- `elo/kereso_motor.php` – közös keresőmotor (az oldal mellett `motor.php` néven): szállodák,
  időpontok, szobák, pontos ár, last minute, levél. A zanzibar- és a dominika-oldal használja,
  mindkettő a saját beállításaival (indulási helyek, célterület, szűrések).
- `elo/bahari_proxy.php` – a bahari-oldal adatai (az oldal mellett `proxy.php` néven): a
  TUI BLUE Bahari pozsonyi, 7 éjszakás, all inclusive útjai az atlastravel.sk kínálatából.
  Gyerekes utazásnál a pontos végösszeget a CeSYS árszolgáltatása adja.
- `elo/zanzibar_api.php` – a zanzibar-oldal keresője (az oldal mellett `api.php` néven): szállodák,
  időpontok, szobák, pontos ár és last minute ajánlatok budapesti és bécsi indulással a győri
  CeSYS-oldalról (a bécsi kínálat a CeSYS Amadeus-kapcsolatából), pozsonyi indulással az
  atlastravel.sk-ról. Az ajánlatkérést és a foglalást levélben küldi a gyor@atlasztravel.hu címre
  (az ügyfélnek nem küld másolatot). Forrásonként egyszerre legfeljebb 2–3 kérést küld.

A zanzibar-oldal szállodafotói közvetlenül a CeSYS képtárából (i.ccdn.hu, i.ccdn.sk, f.ccdn.hu)
töltődnek, nem a tárhelyen keresztül.

**Partszakaszok (régiók) a zanzibar-oldalon.** A bécsi és a pozsonyi kínálatban a forrás sokszor
csak „Zanzibar”-t ír. Ilyenkor a program így pótolja a partszakaszt, ebben a sorrendben:

1. a kézzel szerkeszthető táblázat: `elo/zanzibar_regiok.json`, „szallodak” rész;
2. a győri oldal partszakasz-aloldalai (www.atlasztravelgyor.hu/tanzania/zanzibar/…);
3. pozsonyi utaknál a győri oldal pozsonyi indulású kínálata, ahol a partszakasz szerepel;
4. ugyanaz a szálloda egy másik forrásban, illetve a névben szereplő helynév;
5. a szálloda győri adatlapján levő térképes hely: a legközelebbi partszakasz, legfeljebb 6 km-re.
   A partszakaszok középpontját a győri aloldalakról veszi, a többit a táblázat „helyek” részéből.

**Last minute doboz a zanzibar-oldalon.** Indulási helyenként a 3 legolcsóbb, a következő
14 napban **induló** 6–21 éjszakás út (a hazaérkezés lehet később is). A lista fél óráig
érvényes; ha ennél régebbi, de 3 óránál frissebb, a látogató azonnal ezt kapja, és a program a
válasz után frissíti (ha a tárhely ezt engedi). A tárolt fájlokat a program magától törli
(a legtöbbet 2 nap után).

A két szállodás utaknál mindkét partszakasz látszik („Jambiani / Kiwengwa”), és a szűrő
mindkettőnél megtalálja. Ha egy szállodánál rossz vagy hiányzó partszakaszt látsz, írd be a
táblázat „szallodak” részébe (`"Szálloda neve": "Partszakasz"`), vagy kérd Claude-tól.

**A dominika-oldal** (`elo/dominika_api.php`, az oldal mellett `api.php`): pozsonyi indulás közvetlen
járattal (atlastravel.sk) és bécsi indulás átszállással (a győri oldal Amadeus-kínálata), csak 4 és
5 csillagos szállodák, alapból all inclusive ellátással, és csak Punta Cana, Bávaro és Bayahibe
területén. A területet a program a győri oldal területoldalaiból, a szálloda térképes helyéből és a
forrás besorolásából állapítja meg; ha rosszul sorol be egy szállodát, írd be az
`elo/dominika_regiok.json` táblázatba (`"Szálloda neve": "Bávaro"`).
- **Ajánlatkérés:** név, e-mail, (telefon), az utasok száma, a gyerekek életkora, időszak, kérés.
- **Foglalási igény:** szobánként (több szoba is lehet) minden utas teljes neve, ahogy az úti
  okmányában szerepel, állampolgársága és születési dátuma; a fő (szerződő) utas lakcíme, e-mail-címe
  és telefonszáma; az adatkezelési tájékoztató elfogadása. Ezeket az oldal és a program is ellenőrzi.
  A levél a gyor@atlasztravel.hu címre megy, a program sehol nem tárolja az utasadatokat.

A dominika-oldal saját oldal: a forrása a `sajat/dominika/index.html`. Ebben a `{{telefon}}`,
`{{email}}`, `{{cim}}` stb. helyére a `frissites.py` a `GYOR` rész adatait írja, és ugyanúgy
hozzáteszi a lábléc-tájékoztatót, mint a többi oldalhoz. Szöveget, képet itt kell módosítani.

A források: az atlasztravelgyor.hu és az atlastravel.sk nyilvános adatszolgáltató címei
(ugyanazok, amelyeket a böngésző is használ), valamint a CeSYS árszolgáltatása. A lekérdezések
nyíltan megnevezik magukat („AtlaszTravelGyor-arfrissito”), és egy keresés eredményét a program
fél óráig megjegyzi (a tárhelyen az oldal melletti `.gyorsitotar` mappában), így a forrásokat
nem terheli. Ha a forrás épp nem elérhető, a legutóbbi (legfeljebb egynapos) adatot adja (a válaszban
`"elavult": true`), vagy az oldal a saját tartalék áraira vált. A meg nem jött kéréseket egyszer
újrapróbálja, a kimaradásokat hibanaplóba írja; a zanzibar-oldalon ez így nézhető meg:
`https://<aloldal>/zanzibar/api.php?action=allapot` (az utolsó 30 kimaradás ideje, címe és oka).

## Ajánlatkérés és foglalási igény

Minden aloldalon beadható ajánlatkérés és foglalási igény is. Minden levél a gyor@atlasztravel.hu
címre megy, és a tárgyából, illetve az első két sorából mindig kiderül:

- **TÍPUS:** Ajánlatkérés vagy Foglalási igény,
- **OLDAL:** az oldal neve és címe (a levélküldő program a saját helyéből állapítja meg, a látogató
  nem írhatja át).

Foglalási igénynél kötelező: szobánként minden utas teljes neve (ahogy az útlevelében áll),
állampolgársága és születési dátuma; a fő (szerződő) utas lakcíme, e-mail-címe és telefonszáma;
az adatkezelési tájékoztató elfogadása (www.atlasztravelgyor.hu/adatvedelem). Ezt az oldal a
beküldés előtt, a tárhelyen futó program pedig a levél elküldése előtt is ellenőrzi. Több szoba is
megadható. Az adatokat a program nem tárolja, csak továbbítja levélben.

- Omán, RIU, Mauritius, Maldív, Vietnám, MSC hajóutak (mindkét űrlap): az oldal saját űrlapja tetején választható: „Ajánlatot
  kérek” vagy „Foglalási igényt adok le” (`eszkozok/foglalas.js`, a `frissites.py`
  `FOGLALAS_MODUL` beállítása szerint). A levelet az `urlap_kuldes.php` küldi.
- Bahari: az oldal űrlapja a foglalási igény (utasonként állampolgársággal), mellette a „Csak
  ajánlatot kérek” fülön egyszerű ajánlatkérő.
- Zanzibár, Dominika: a szállodaablakból foglalási igény, illetve ajánlatkérés (a levelet a
  keresőmotor küldi).

## Módosítás

A kész oldalakat a program minden futáskor újra előállítja a központi oldalból. Ezért a kézi
módosítás a `oman/index.html`-ben és a többi oldalon a következő frissítéskor elveszne. Ha
valamit másképp szeretnél, a `frissites.py`-ban kell előírni (ezt kérd Claude-tól), és
újrafuttatni a programot.

## Frissítés, ha a központ megváltoztatja az oldalt

```
python frissites.py              # mind az öt oldal
python frissites.py oman         # csak egy oldal
```

Ha a központ oldala úgy változik, hogy egy csere nem talál, vagy a kész oldalon központi
telefonszám vagy e-mail-cím maradna, a program **hibát jelez, és nem írja felül** a meglévő
oldalt. Ilyenkor a cseréket kell igazítani.

## Feltöltés a tárhelyre (automatikus)

A tárhely a tarhely.com-on (MagyarHosting) van, minden aloldal egy saját mappában
(például `/home/atlaszt1/tesztoldal.atlasztravelgyor.hu`). A feltöltést a GitHub végzi:

- **Minden változtatás után** (push a main ágra) lefut a „Feltöltés a tárhelyre” munkafolyamat.
- **Minden reggel** lefut a „Napi frissítés a központi oldalakból”: átveszi a központ
  változásait, elmenti őket a tárolóba, és feltölti a tárhelyre.
- Mindkettő kézzel is indítható: Actions fül → a munkafolyamat neve → **Run workflow**.

Az FTP-belépési adatok a GitHubon titkos mezőkben vannak (Settings → Secrets and variables →
Actions): `FTP_SZERVER`, `FTP_FELHASZNALO`, `FTP_JELSZO`. A kapcsolat titkosított (FTPS).

Melyik helyi mappa melyik tárhelymappába kerül, azt a `feltoltes.json` írja le. Új aloldalnál
előbb a tárhely felületén létre kell hozni az aldomaint (Domains Management), utána fel kell
venni a `feltoltes.json`-ba. A feltöltő csak a saját maga által feltöltött fájlokat frissíti
vagy törli; a tárhelyen kézzel elhelyezett más fájlokhoz nem nyúl.

## Élesítés

1. A `frissites.py`-ban az `ELONEZET = True` sort írd át `False`-ra, és futtasd újra a programot
   (így lekerül a „ne listázza a Google” jelölés).
2. Az `urlap_kuldes.php` elején a `FELADO` címét igazítsd a tárhely domainjéhez.
3. A `feltoltes.json`-ban minden aldomainhez a saját mappája kerüljön
   (például `{"cel": "oman.atlasztravelgyor.hu", "forras": "oman"}`).

PHP-s tárhelyen az ajánlatkérő űrlap e-mailt küld (`send.php`, illetve a RIU- és a
Vietnám-oldalon `kuldes.php`). PHP nélkül (például a GitHub-előnézetben) az Omán-, a Mauritius-
és a Maldív-oldal a levelezőprogramot nyitja meg, a RIU- és a Vietnám-oldal hibaüzenetet és
telefonszámot mutat.

## Új aloldal felvétele

Vedd fel a `frissites.py` `OLDALAK` listájába (például `"dubai": "https://dubai.atlasztravel.hu/"`),
majd futtasd a programot. Ha az oldal új felépítésű, egyedi cserék is kellhetnek (`OLDAL_CSEREK`).
