# Utasítások a Claude Code számára (Atlasz Travel Győr aloldalak)

Ez a tároló az atlasztravelgyor.hu aloldalait tartalmazza: a központi Atlasz Travel aloldalak
(pl. oman.atlasztravel.hu) győri változatait. Olvasd el a `README.md`-t is.

## A felhasználóról

- Magyarul kommunikálj, tegeződve (a felhasználó kérése).
- Teljesen kezdő a GitHub és a weboldalak terén. Minden lépést egyszerűen, szakszavak nélkül
  vagy azok megmagyarázásával írj le.

## Munkaszabályok

- A kész oldalakat (`oman/`, `riu/`, `mauritius/`, `maldiv/`, `vietnam/`, `index.html`) a
  `frissites.py` állítja elő. **Ne szerkeszd őket kézzel**: minden változtatást a
  `frissites.py` cseréiben vezess, majd futtasd: `python frissites.py`.
- A győri adatok (telefon, e-mail, cím, nyitvatartás) a `frissites.py` `GYOR` részében vannak.
  Az oldalakon csak a győri iroda elérhetősége szerepeljen, személyes munkatársi elérhetőség ne.
- A telefonszám formája: +36 20 266 8144. A webcím látható szövege mindenhol
  www.atlasztravelgyor.hu, és mindig élő link (a program ellenőrzi).
- Logó: `eszkozok/atlasz-travel-gyor-logo.png` (átlátszó hátterű); az oldalakon az Atlasz Travel
  és az Atlasz Prémium logók helyére ez kerül.
- Változtatás után ellenőrizd böngészőben is az érintett oldalt (asztali és mobil szélesség).
- Feltöltés: a `feltoltes.py` és a `.github/workflows/` munkafolyamatai végzik (minden push után
  és naponta). Az FTP-adatok csak GitHub-titkokban lehetnek, soha ne kerüljenek fájlba vagy naplóba.
  Új aldomain: a felhasználó létrehozza a tárhelyen, utána vedd fel a `feltoltes.json`-ba.
- Élő adatok: az `elo/` mappa PHP-programjai (a frissites.py `ELO_FAJLOK` szerint másolja őket).
  A forrás a CeSYS nyilvános adatszolgáltató címei (atlasztravelgyor.hu, atlastravel.sk,
  api-ng.cesys.eu). A lekérdezés mindig nevezze meg magát (CESYS_UA), használja a gyorsítótárat,
  és soha ne próbálja megkerülni a robotszűrőket (álcázott böngésző, ellenőrzés kijátszása).
- `ELONEZET = True`, amíg az oldalak csak a GitHub Pages előnézetben futnak; élesítéskor `False`.
- Külön, kis commitokat készíts, érthető magyar leírással.
- Soha ne commitolj jelszót, API-kulcsot vagy ügyféladatot (például tesztűrlapból származó adatot).
