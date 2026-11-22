"""Az élő áras aloldalak árainak frissítése a tárhelyen (az elo_oldalak.json alapján).

A GitHub munkafolyamatai futtatják: az ütemezett frissítés (arfrissites.yml, 6:00 és éjfél között 2 óránként)
és a feltöltés utáni frissítés (feltoltes.yml). Ugyanazt a címet kéri, amit a lábléc évszáma
(pl. https://oman.atlasztravelgyor.hu/api.php?a=frissit). Ütemezett futásnál csak a budapesti
6, 8, … 22 és 24 órakor fut le (az óraátállítás hetében a másik ütemezési sor is elindul, azt kihagyja).
"""
import json
import os
import sys
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

ORAK = {0, 6, 8, 10, 12, 14, 16, 18, 20, 22}

if os.environ.get("GITHUB_EVENT_NAME") == "schedule":
    most = datetime.now(ZoneInfo("Europe/Budapest"))
    # a GitHub néha késve indít: a páros óra utáni első 59 percben még lefut
    if most.hour not in ORAK:
        print(f"Budapesti idő: {most:%H:%M} – most nincs ütemezett frissítés.")
        sys.exit(0)

oldalak = json.loads((Path(__file__).resolve().parent.parent / "elo_oldalak.json").read_text(encoding="utf-8"))


def frissit(o):
    kezd = time.time()
    try:
        keres = urllib.request.Request(o["url"], headers={"User-Agent": "AtlaszTravelGyor-arfrissito-utemezo/1.0"})
        with urllib.request.urlopen(keres, timeout=290) as v:
            adat = json.loads(v.read().decode("utf-8") or "{}")
        allapot = "frissült" if adat.get("ok") and not adat.get("friss") else ("épp most frissült" if adat.get("ok") else "hiba")
    except Exception as hiba:  # egy aloldal hibája miatt a többi még frissül
        allapot = f"hiba ({hiba})"
    return f"{o['nev']}: {allapot}, {time.time() - kezd:.0f} mp"


with ThreadPoolExecutor(max_workers=3) as ex:
    for sor in ex.map(frissit, oldalak):
        print(sor)
