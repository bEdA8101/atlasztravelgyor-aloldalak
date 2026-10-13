# Utasítások a Claude Code számára (Atlasz Travel Győr aloldalak)

Ez a tároló az atlasztravelgyor.hu aloldalait tartalmazza: a központi Atlasz Travel aloldalak
(pl. oman.atlasztravel.hu) győri változatait. Olvasd el a `README.md`-t is.

## A felhasználóról

- Magyarul kommunikálj.
- Teljesen kezdő a GitHub és a weboldalak terén. Minden lépést egyszerűen, szakszavak nélkül
  vagy azok megmagyarázásával írj le.

## Munkaszabályok

- A kész oldalakat (`oman/`, `riu/`, `mauritius/`, `maldiv/`, `vietnam/`, `index.html`) a
  `frissites.py` állítja elő. **Ne szerkeszd őket kézzel**: minden változtatást a
  `frissites.py` cseréiben vezess, majd futtasd: `python frissites.py`.
- A győri adatok (telefon, e-mail, cím, nyitvatartás) a `frissites.py` `GYOR` részében vannak.
  Az oldalakon csak a győri iroda elérhetősége szerepeljen, személyes munkatársi elérhetőség ne.
- Változtatás után ellenőrizd böngészőben is az érintett oldalt (asztali és mobil szélesség).
- `ELONEZET = True`, amíg az oldalak csak a GitHub Pages előnézetben futnak; élesítéskor `False`.
- Külön, kis commitokat készíts, érthető magyar leírással.
- Soha ne commitolj jelszót, API-kulcsot vagy ügyféladatot (például tesztűrlapból származó adatot).
