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

**Előnézet** (a tárhely elkészültéig): https://beda8101.github.io/atlasztravelgyor-aloldalak/

## Mi hol van?

```
frissites.py        letölti a központi oldalakat, és elkészíti a győri változatot
urlap_kuldes.php    az ajánlatkérő űrlap e-mail-küldője (a program másolja az oldalak mellé)
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

A győri adatok a `frissites.py` elején, a `GYOR` részben vannak. A program futtatásához a
Python mellett a Pillow csomag kell (`pip install pillow`).

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

## Élesítés (ha megvan a tárhely)

1. A `frissites.py`-ban az `ELONEZET = True` sort írd át `False`-ra, és futtasd újra a programot
   (így lekerül a „ne listázza a Google” jelölés).
2. Az `urlap_kuldes.php` elején a `FELADO` címét igazítsd a tárhely domainjéhez.
3. Minden aldomain gyökérmappájába a saját mappájának teljes tartalma kerül
   (például az `oman/` mappa tartalma az oman.atlasztravelgyor.hu gyökerébe).

PHP-s tárhelyen az ajánlatkérő űrlap e-mailt küld (`send.php`, illetve a RIU- és a
Vietnám-oldalon `kuldes.php`). PHP nélkül (például az előnézetben) az Omán-, a Mauritius- és a
Maldív-oldal a levelezőprogramot nyitja meg, a RIU- és a Vietnám-oldal hibaüzenetet és
telefonszámot mutat.

## Új aloldal felvétele

Vedd fel a `frissites.py` `OLDALAK` listájába (például `"dubai": "https://dubai.atlasztravel.hu/"`),
majd futtasd a programot. Ha az oldal új felépítésű, egyedi cserék is kellhetnek (`OLDAL_CSEREK`).
