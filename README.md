# Atlasz Travel Győr – aloldalak

Az atlasztravelgyor.hu aloldalai: a központi Atlasz Travel aloldalak győri változatai.
A központ engedélyezte a tartalmak átvételét.

| Mappa | Cím | Forrás |
|---|---|---|
| `oman/` | oman.atlasztravelgyor.hu | https://oman.atlasztravel.hu/ (élő árakkal, családi árral) |
| `riu/` | riu.atlasztravelgyor.hu | https://riu.atlasztravel.hu/ (élő árakkal mind a 17 szállodánál, családi árral) |
| `mauritius/` | mauritius.atlasztravelgyor.hu | saját oldal (`sajat/mauritius/`): a megszűnt központi oldal utolsó változata, élő ajánlatokkal |
| `maldiv/` | maldiv.atlasztravelgyor.hu | https://maldiv.atlasztravel.hu/ (élő ajánlatokkal a három resortra) |
| `vietnam/` | vietnam.atlasztravelgyor.hu | https://vietnam.atlasztravel.hu/ (élő árakkal, családi árral) |
| `bahari/` | bahari.atlasztravelgyor.hu | https://bahari.atlasztravel.hu/ (élő árakkal) |
| `zanzibar/` | zanzibar.atlasztravelgyor.hu | https://zanzibar.atlasztravel.hu/ (teljes kereső élő adatokkal) |
| `mscutak/` | mscutak.atlasztravelgyor.hu | https://mscutak.atlasztravel.hu/ (MSC hajóutak; az árakat a központ az oldalban frissíti) |
| `dominika/` | dominika.atlasztravelgyor.hu | saját oldal (`sajat/dominika/`), élő árakkal és foglalási igénnyel |
| `beszallas/` | beszallas.atlasztravelgyor.hu | gyűjtőoldal (`sajat/beszallas/`): innen érhető el a többi aloldal, ezt kell hirdetni |

Az oldalak 2026 novembere óta élesek a táblázat második oszlopában álló címeken. A tesztoldal
(tesztoldal.atlasztravelgyor.hu) ugyanezeket mutatja mappánként, a keresők elől elrejtve.

## Mi hol van?

```
frissites.py        letölti a központi oldalakat, és elkészíti a győri változatot
feltoltes.py        feltölti a kész oldalakat a tárhelyre (FTP), csak a változott fájlokat
feltoltes.json      mi hova kerüljön a tárhelyen (melyik mappa melyik aldomainhez)
.github/workflows/  automatikus feltöltés és napi frissítés a GitHubon
urlap_kuldes.php    az ajánlatkérő űrlap e-mail-küldője (a program másolja az oldalak mellé)
elo/                az élő adatos oldalak saját adatlekérői (PHP), lásd lent
sajat/              a saját (nem a központtól átvett) oldalak forrása, pl. sajat/dominika/index.html,
                    sajat/beszallas/ (gyűjtőoldal; az ajánlatok listája: ajanlatok.json)
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
  A végösszeget a CeSYS árszolgáltatása adja minden olyan utasösszetételre, ami nem pontosan
  2 felnőtt (2 felnőttnél az ár/fő × 2 megegyezik a forrás végösszegével).
- `elo/zanzibar_api.php` – a zanzibar-oldal keresője (az oldal mellett `api.php` néven): szállodák,
  időpontok, szobák, pontos ár és last minute ajánlatok budapesti indulással a győri
  CeSYS-oldalról, pozsonyi indulással az atlastravel.sk-ról. Az ajánlatkérést és a foglalást levélben küldi a gyor@atlasztravel.hu címre
  (az ügyfélnek nem küld másolatot). Forrásonként egyszerre legfeljebb 2–3 kérést küld.

- `elo/idopontlista.php` – közös élő időpontlista (az oldal mellett `api.php` néven) az Omán, a Vietnám, a
  RIU, a Maldív és a Mauritius oldalhoz. Oldalanként egy beállítófájl mondja meg, mit kérdezzen (az oldal mellett
  `beallitas.php` néven): `elo/oman_beallitas.php`, `elo/vietnam_beallitas.php`, `elo/riu_beallitas.php`,
  `elo/maldiv_beallitas.php`, `elo/mauritius_beallitas.php`. Bennük: a forrás (győri CeSYS-oldal vagy atlastravel.sk),
  a szállodák azonosítói, az éjszakák, az ellátás és az indulási hely (a központi oldal kínálata szerint), az Omán-
  oldalnál a szlovák szobanevek magyar megfelelője is. Kérések: `api.php` (2 felnőtt, minden szálloda),
  `api.php?a=csalad&h=…&ac=2&ages[]=6` (bármilyen létszámra a pontos végösszeg naponként), `api.php?a=ar&…` (egy
  szoba pontos ára), és Mauritiuson `api.php?a=ajanlatok` (szállodánként a legjobb aktuális ajánlat a győri oldal
  keresőjéből, a szálloda nevével és képével az adatlapjáról). A forrást egyszerre legfeljebb 4 kéréssel kérdezi; a
  látogató a legutóbbi (legfeljebb egynapos) adatot azonnal kapja, a frissítés a válasz után fut. A frissítés
  ütemezését és a kézi frissítést lásd lent („Mikor frissülnek az árak?”).
- `eszkozok/csaladi_ar.js` – a „Családi ár, gyerekekkel” doboz (az oldal mellett `csaladi_ar.js` néven, a
  `frissites.py` `CSALADI_AR` része szerint): felnőttek, gyerekek és életkoruk, indulási hónap; naponként a
  legolcsóbb foglalható szoba végösszege a forrás árszolgáltatásából, a többi szoba pontos ára kattintásra, és az
  „Ezt kérem” gomb kitölti az ajánlatkérő űrlapot (létszám, gyerekek kora, megjegyzés a választott ajánlattal).
  Ha a szervező (pl. egy dinamikus csomagnál) csak egyedi lekérdezéssel ad végösszeget, a doboz ezt megmondja, és
  ajánlatkérést kínál; ilyenkor nem becsül. A Maldív-oldal resortjainál ez a helyzet, ezért ott nincs családi doboz.

**Ahol nincs élő ár:** az MSC-oldal hajóútjai egyik CeSYS-kínálatban sem szerepelnek, ezért az a központi oldal
napi frissítésű másolata marad.

**Árak forintban (RIU):** a győri forrás a fejenkénti forintárat másképp kerekíti, mint a végösszeget, ezért a
„2 főre összesen” érték néhány tíz forinttal eltérhet a foglaláskori végösszegtől (euróban nincs ilyen eltérés).

A zanzibar-oldal szállodafotói közvetlenül a CeSYS képtárából (i.ccdn.hu, i.ccdn.sk, f.ccdn.hu)
töltődnek, nem a tárhelyen keresztül.

**Partszakaszok (régiók) a zanzibar-oldalon.** A pozsonyi kínálatban a forrás sokszor
csak „Zanzibar”-t ír. Ilyenkor a program így pótolja a partszakaszt, ebben a sorrendben:

1. a kézzel szerkeszthető táblázat: `elo/zanzibar_regiok.json`, „szallodak” rész;
2. a győri oldal partszakasz-aloldalai (www.atlasztravelgyor.hu/tanzania/zanzibar/…);
3. pozsonyi utaknál a győri oldal pozsonyi indulású kínálata, ahol a partszakasz szerepel;
4. ugyanaz a szálloda egy másik forrásban, illetve a névben szereplő helynév;
5. a szálloda győri adatlapján levő térképes hely: a legközelebbi partszakasz, legfeljebb 6 km-re.
   A partszakaszok középpontját a győri aloldalakról veszi, a többit a táblázat „helyek” részéből.

**Last minute doboz a zanzibar-oldalon.** Indulási helyenként a 3 legolcsóbb, a következő
14 napban **induló** 6–21 éjszakás út (a hazaérkezés lehet később is). A lista a
következő ütemezett frissítésig érvényes; ha ennél régebbi, de 8 óránál frissebb, a látogató azonnal ezt kapja,
és a program a válasz után frissíti (ha a tárhely ezt engedi). A tárolt fájlokat a program magától törli
(a legtöbbet 2 nap után).

A két szállodás utaknál mindkét partszakasz látszik („Jambiani / Kiwengwa”), és a szűrő
mindkettőnél megtalálja. Ha egy szállodánál rossz vagy hiányzó partszakaszt látsz, írd be a
táblázat „szallodak” részébe (`"Szálloda neve": "Partszakasz"`), vagy kérd Claude-tól.

**A dominika-oldal** (`elo/dominika_api.php`, az oldal mellett `api.php`): pozsonyi indulás közvetlen
járattal (atlastravel.sk) és budapesti
indulás átszállással (a győri oldal saját kínálata, `di=130`; jellemzően Lufthansa, Frankfurton át),
csak 4 és 5 csillagos szállodák, alapból all inclusive ellátással (a budapesti keresőben ez nem
szűrhető, ezért a program a kártyák ellátása szerint szűr, a körutazásokat pedig kihagyja), és csak
Punta Cana, Bávaro és Bayahibe területén. A területet a program a győri oldal területoldalaiból, a szálloda térképes helyéből és a
forrás besorolásából állapítja meg; ha rosszul sorol be egy szállodát, írd be az
`elo/dominika_regiok.json` táblázatba (`"Szálloda neve": "Bávaro"`).
- **Ajánlatkérés:** név, e-mail, (telefon), az utasok száma, a gyerekek életkora, időszak, kérés.
- **Foglalási igény:** szobánként (több szoba is lehet) minden utas teljes neve, ahogy az úti
  okmányában szerepel, állampolgársága és születési dátuma; a fő (szerződő) utas lakcíme, e-mail-címe
  és telefonszáma; az adatkezelési tájékoztató elfogadása. Ezeket az oldal és a program is ellenőrzi.
  A levél a gyor@atlasztravel.hu címre megy, a program sehol nem tárolja az utasadatokat.

A dominika-oldal saját oldal: a forrása a `sajat/dominika/index.html` (színvilága „modern amerikai
üdülőmárka”: fehér, óceánkék, türkiz, naplementenarancs; betűi az Oswald és az Inter, a
`sajat/dominika/fonts` mappából, a tárhelyről töltődnek). Ebben a `{{telefon}}`,
`{{email}}`, `{{cim}}` stb. helyére a `frissites.py` a `GYOR` rész adatait írja, és ugyanúgy
hozzáteszi a lábléc-tájékoztatót, mint a többi oldalhoz. Szöveget, képet itt kell módosítani.

A teljes árat (végösszeget) az élő adatos oldalak mindig a forrás árszolgáltatásától kérik, nem
szorzással számolják: egy felnőttnél egyágyas felár, a harmadik felnőttnél és a gyerekeknél
kedvezmény lehet, és a forrás azt is megmondja, ha egy időpont az adott utaslétszámra nem
foglalható (ilyenkor a kártya a következő foglalható időpontot mutatja).

A források: az atlasztravelgyor.hu és az atlastravel.sk nyilvános adatszolgáltató címei
(ugyanazok, amelyeket a böngésző is használ), valamint a CeSYS árszolgáltatása. A lekérdezések
nyíltan megnevezik magukat („AtlaszTravelGyor-arfrissito”), és egy keresés eredményét a program
a következő frissítésig megjegyzi (a tárhelyen az oldal melletti `.gyorsitotar` mappában), így a forrásokat
nem terheli.

**Mikor frissülnek az árak?** 6:00 és éjfél között minden páros órában (6, 8, 10, … 22 és 24 órakor, budapesti
idő szerint); éjfél és 6:00 között nem. Ezekben az időpontokban az `arfrissites.yml` munkafolyamat le is kéri
mindegyik élő áras oldalt, így a frissítés akkor is megtörténik, ha épp nincs látogató (a GitHub az ütemezett
indítást néha néhány perccel késve végzi). **Kézi frissítés:** az élő áras oldalak láblécében az évszámra
(„© 2026”) kattintva a tárhelyen futó program azonnal frissíti az oldal árait, majd az oldal újratöltődik
(`eszkozok/arfrissito.js`; oldalanként legfeljebb 5 percenként). Ahol a láblécben nem volt évszám (Vietnám,
RIU), oda a program egy halvány „© 2026 Atlasz Travel Győr” sort tesz. A következő ütemezett frissítés ettől
függetlenül megtörténik. **A gyűjtőoldal (beszallas) évszámára kattintva az összes élő áras aloldal frissül**
(egyszerre három; az évszám helyén látszik az állás, pl. „árak frissítése… 5/8”, a végén pedig, ha valamelyik
nem sikerült).

Az élő áras aloldalak listáját a `frissites.py` állítja össze az `ARFRISSITO` részből és a `feltoltes.json`
aldomainjeiből; ebből készül a gyűjtőoldal árfrissítője és az `elo_oldalak.json`, amelyből az ütemezett
frissítés (`.github/arfrissites.py`, `arfrissites.yml`) és a feltöltés utáni frissítés dolgozik. **Új élő áras
aloldalnál elég az `ARFRISSITO`-ba felvenni** (ha kimarad, a `frissites.py` hibát jelez). Ha a forrás épp nem elérhető, a legutóbbi (legfeljebb egynapos) adatot adja (a válaszban
`"elavult": true`), vagy az oldal a saját tartalék áraira vált. A meg nem jött kéréseket egyszer
újrapróbálja, a kimaradásokat hibanaplóba írja; a zanzibar-oldalon ez így nézhető meg:
`https://<aloldal>/zanzibar/api.php?action=allapot` (az utolsó 30 kimaradás ideje, címe és oka).

## Amadeus (bécsi) kínálat: kikapcsolva

2026. november 25-től a CeSYS szerződéses tiltásra hivatkozva nem engedi az Amadeus-rendszerből történő gépi
lekérdezést a saját rendszerén kívül. Ezért a győri oldal bécsi Amadeus-keresője (`am-utazas-kereses`,
`amadeus_connector`) és az Amadeus pontos ára (`check_date_amadeus.php`) ki van kapcsolva: a Zanzibár- és a
Dominika-oldal keresője és last minute doboza csak a budapesti (győri CeSYS) és a pozsonyi (atlastravel.sk)
kínálatot mutatja, az `action=price_at` kérés hibát ad. Minden oldal csak a CeSYS-adatbázisból (győri és szlovák)
kérdez. A többi oldalon előforduló bécsi indulások (pl. Maldív, Mauritius, RIU) a CeSYS saját adatbázisából
jönnek (utazásszervezők csomagjai, pl. Čedok), nem az Amadeusból. A foglalási és ajánlatkérő űrlapokon a
„Bécs” választás megmaradt: azt az iroda kézzel intézi.

**Védelem (2026. 11. 26.):** a német és osztrák szervezők ajánlatait, amelyek a győri oldal német–osztrák
keresőjén érhetők el, a CeSYS az Amadeus-rendszerből kéri le; ezekből egyetlen élő árlekérdezés sem vehet adatot.
Ezért az `elo/cesys.php` minden lekérő függvénye (`cesys_get`, `cesys_get_tobb`, `cesys_html`, `cesys_html_tobb`,
`cesys_post_tobb`) el sem küldi az olyan kérést, amely a német–osztrák keresőre vagy az Amadeusra mutat
(`cesys_tiltott()`: `am-utazas-kereses`, `amadeus`, `sm=2`), csak a hibanaplóba írja. Időpontlistát csak akkor
fogad el, ha a válasz a CeSYS saját adatbázisából jön (`origin: database`, `cesys_adatbazisbol()`); a győri kereső
szolgáltatásának sorai közül csak a `source: national` jelölésűeket használja, a keresőmotor pedig kihagyja az
Amadeus-szállodákat (az adatlapjuk címe „…aa” végű). Felmérés (2026. 11. 26.): az összes élő áras oldal minden
válasza a CeSYS-adatbázisból jött; a szervezők Čedok, Fischer CK, Itaka, Hydrotour, Satur, Kartago, Viasale
Travel, Sun & Fun Holidays, Anubis Travel és Jetwing Travel, német vagy osztrák szervező nincs köztük. Ha a tiltást feloldják, a régi beállítás a
git-előzményekben megvan (2026. november 24-i változat: `elo/zanzibar_api.php`, `elo/dominika_api.php`,
`elo/kereso_motor.php`, a `frissites.py` `zanzibar_amadeus_nelkul` cseréi és a `sajat/dominika` oldal).

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

## Élesítés (2026. november, elvégezve)

1. A `frissites.py`-ban `ELONEZET = False`: az oldalakon nincs „ne listázza a Google” jelölés.
2. A `FELADO` (az `urlap_kuldes.php` és az `elo/kereso_motor.php` elején)
   weboldal@atlasztravelgyor.hu, a tárhely domainjén.
3. A `feltoltes.json`-ban minden aldomainhez a saját mappája tartozik
   (például `{"cel": "oman.atlasztravelgyor.hu", "forras": "oman"}`). A tesztoldalra emellett
   a `robots.txt` is felkerül, hogy a keresők ne a tesztoldalt listázzák.

PHP-s tárhelyen az ajánlatkérő űrlap e-mailt küld (`send.php`, illetve a RIU- és a
Vietnám-oldalon `kuldes.php`). PHP nélkül (például a GitHub-előnézetben) az Omán-, a Mauritius-
és a Maldív-oldal a levelezőprogramot nyitja meg, a RIU- és a Vietnám-oldal hibaüzenetet és
telefonszámot mutat.

## Gyűjtőoldal (beszallas.atlasztravelgyor.hu)

Ezt az egy oldalt kell hirdetni: innen érhető el minden aktuális aloldal (indulási tábla, kártyák
típus szerinti szűréssel), a www.atlasztravelgyor.hu főoldal és a győri iroda elérhetősége.

- Az ajánlatok a `sajat/beszallas/ajanlatok.json`-ban vannak, abban a sorrendben, ahogy az
  oldalon megjelennek. Szöveg, címke, kiemelés itt módosítható.
- **Hívó kifejezés** (`"cimke"`): az indulási táblán zöld jelzőfénnyel és a kártya képén látszik.
  **Repülőtéri felirat** (`"allapotok"`): valódi határidőhöz kötve, a megadott időszakban a hívó
  kifejezés helyére kerül (például Vietnám: „Kapunyitás: dec. 16.” december 15-ig; Omán: sárga,
  villogó „Utolsó hívás: ápr. 3.” 2027. március 6-tól). Csak valós határidőhöz szabad használni.
- **Ideiglenes elrejtés:** `"lathato": false`. **Szezonális megjelenés:** `"latszik_tol"` és
  `"latszik_ig"` (ÉÉÉÉ-HH-NN). A program minden éjjel újraépíti az oldalt, így a dátumok maguktól
  érvényesülnek (az omán ajánlat például 2027. április 3. után magától eltűnik).
- **Új ajánlat:** új blokk a listában, mellé kép a `sajat/beszallas/img` mappába
  (`<kep>.webp` 1400 px és `<kep>-kis.webp` 760 px széles). A kártyák elrendezése magától igazodik
  a darabszámhoz (és szűréskor is), nem marad félig üres sor.
- A betűtípusok (Barlow Condensed, Montserrat, OFL-licenc) a tárhelyről töltődnek, nem a Google-tól.

## Új aloldal felvétele

Vedd fel a `frissites.py` `OLDALAK` listájába (például `"dubai": "https://dubai.atlasztravel.hu/"`),
majd futtasd a programot. Ha az oldal új felépítésű, egyedi cserék is kellhetnek (`OLDAL_CSEREK`).
Utána: aldomain a tárhelyen, új cél a `feltoltes.json`-ban, és új blokk a gyűjtőoldal
`ajanlatok.json`-jában.
