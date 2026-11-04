#!/usr/bin/env python3
"""Feltölti a kész oldalakat a tárhelyre FTP-n (titkosított kapcsolattal).

A feltoltes.json mondja meg, melyik helyi mappa melyik tárhelymappába kerüljön.
Csak a megváltozott fájlokat tölti fel: minden célmappában egy állapotfájl
(.feltoltes-allapot.json) jegyzi, mit töltött fel legutóbb. Azokat a fájlokat
törli, amelyeket korábban ő töltött fel, de már nincsenek meg; a tárhelyen
lévő más fájlokhoz nem nyúl.

A belépési adatokat környezeti változókból veszi (a GitHubon titkos mezők):
    FTP_SZERVER, FTP_FELHASZNALO, FTP_JELSZO, (nem kötelező: FTP_PORT)

Használat:
    python feltoltes.py           # feltöltés
    python feltoltes.py --proba   # csak kiírja, mit töltene fel
"""

import ftplib
import hashlib
import io
import json
import os
import posixpath
import ssl
import sys
import time
from pathlib import Path

ALAP = Path(__file__).resolve().parent
BEALLITAS = ALAP / "feltoltes.json"
ALLAPOTFAJL = ".feltoltes-allapot.json"


class FeltoltesHiba(Exception):
    pass


class TitkositottFTP(ftplib.FTP_TLS):
    """Az adatkapcsolat is ugyanazt a titkosított munkamenetet használja (sok szerver megköveteli)."""

    def ntransfercmd(self, cmd, rest=None):
        kapcsolat, meret = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            kapcsolat = self.context.wrap_socket(kapcsolat, server_hostname=self.host, session=self.sock.session)
        return kapcsolat, meret


def beallitas_adatok():
    hianyzik = [n for n in ("FTP_SZERVER", "FTP_FELHASZNALO", "FTP_JELSZO") if not os.environ.get(n)]
    if hianyzik:
        raise FeltoltesHiba("Hiányzó titkos beállítás: " + ", ".join(hianyzik)
                            + ". A GitHubon: Settings → Secrets and variables → Actions.")
    return {
        "szerver": os.environ["FTP_SZERVER"].strip(),
        "felhasznalo": os.environ["FTP_FELHASZNALO"].strip(),
        "jelszo": os.environ["FTP_JELSZO"],
        "port": int(os.environ.get("FTP_PORT") or 21),
        # csak helyi kipróbáláshoz: FTP_TITKOSITAS=nem
        "titkositas": os.environ.get("FTP_TITKOSITAS", "igen") != "nem",
    }


def kapcsolodik(adatok):
    if adatok["titkositas"]:
        ftp = TitkositottFTP(context=ssl.create_default_context(), timeout=90)
    else:
        ftp = ftplib.FTP(timeout=90)
    ftp.connect(adatok["szerver"], adatok["port"])
    ftp.login(adatok["felhasznalo"], adatok["jelszo"])
    if adatok["titkositas"]:
        ftp.prot_p()
    ftp.set_pasv(True)
    return ftp


class Tarhely:
    """FTP-kapcsolat, amely akadozó hálózatnál újracsatlakozik."""

    def __init__(self, adatok):
        self.adatok = adatok
        self.ftp = kapcsolodik(adatok)
        self.gyoker = self.ftp.pwd()

    def _ujra(self, muvelet):
        # a művelet mindig az aktuális kapcsolatot használja (újracsatlakozás után az újat)
        for proba in range(4):
            try:
                return muvelet()
            except ftplib.error_perm:
                raise
            except (*ftplib.all_errors, ssl.SSLError) as hiba:
                if proba == 3:
                    raise
                print(f"  (kapcsolati hiba: {hiba}; újracsatlakozás…)")
                time.sleep(3 * (proba + 1))
                try:
                    self.ftp.close()
                finally:
                    self.ftp = kapcsolodik(self.adatok)

    def utvonal(self, *reszek):
        return posixpath.join(self.gyoker, *reszek)

    def lista(self, mappa):
        return self._ujra(lambda: [posixpath.basename(n) for n in self.ftp.nlst(mappa)])

    def letezik_mappa(self, mappa):
        def probal():
            regi = self.ftp.pwd()
            try:
                self.ftp.cwd(mappa)
                return True
            except ftplib.error_perm:
                return False
            finally:
                self.ftp.cwd(regi)
        return self._ujra(probal)

    def mappat_letrehoz(self, mappa, kesz):
        if mappa in kesz:
            return
        if not self.letezik_mappa(mappa):
            self.mappat_letrehoz(posixpath.dirname(mappa), kesz)
            self._ujra(lambda: self.ftp.mkd(mappa))
        kesz.add(mappa)

    def letolt(self, fajl):
        def probal():
            puffer = io.BytesIO()  # minden próbálkozás tiszta lappal indul
            self.ftp.retrbinary(f"RETR {fajl}", puffer.write)
            return puffer.getvalue()
        try:
            return self._ujra(probal)
        except ftplib.error_perm:
            return None

    def feltolt(self, fajl, adat):
        self._ujra(lambda: self.ftp.storbinary(f"STOR {fajl}", io.BytesIO(adat)))

    def torol(self, fajl):
        try:
            self._ujra(lambda: self.ftp.delete(fajl))
        except ftplib.error_perm:
            pass

    def bezar(self):
        try:
            self.ftp.quit()
        except Exception:
            self.ftp.close()


def helyi_fajlok(cel):
    """A feltöltendő fájlok: {tárhelyen belüli útvonal: helyi útvonal}."""
    forras = (ALAP / cel["forras"]).resolve()
    if not forras.is_dir():
        raise FeltoltesHiba(f"A helyi forrásmappa nem létezik: {cel['forras']}")
    elemek = [forras / n for n in cel["csak"]] if cel.get("csak") else sorted(forras.iterdir())
    fajlok = {}
    for elem in elemek:
        if not elem.exists():
            raise FeltoltesHiba(f"Hiányzik a feltöltendő elem: {elem.relative_to(ALAP)}")
        utak = [elem] if elem.is_file() else sorted(p for p in elem.rglob("*") if p.is_file())
        for ut in utak:
            rel = ut.relative_to(forras).as_posix()
            if any(r.startswith(".") or r == "__pycache__" for r in rel.split("/")):
                continue
            fajlok[rel] = ut
    return fajlok


def cel_feltoltese(tarhely, cel, proba):
    nev = cel["cel"].strip("/")
    print(f"\n=== {nev}")
    celmappa = tarhely.utvonal(nev)
    if not tarhely.letezik_mappa(celmappa):
        tartalom = ", ".join(sorted(tarhely.lista(tarhely.gyoker))) or "(üres)"
        raise FeltoltesHiba(f"A tárhelyen nincs '{nev}' mappa. Az FTP-fiók gyökerében ezek vannak: {tartalom}. "
                            "Ha itt nem az aldomainek mappái látszanak, az FTP-fiók mappáját a tárhely felületén "
                            "(FTP Accounts) a fő mappára (/home/<felhasználónév>) kell állítani. Ha a mappa hiányzik, "
                            "hozd létre az aldomaint a tárhely felületén, vagy javítsd a feltoltes.json-t.")

    regi_nyers = tarhely.letolt(posixpath.join(celmappa, ALLAPOTFAJL))
    regi = json.loads(regi_nyers) if regi_nyers else {}
    fajlok = helyi_fajlok(cel)
    uj = {rel: hashlib.sha256(ut.read_bytes()).hexdigest() for rel, ut in fajlok.items()}

    feltoltendo = sorted(rel for rel in uj if regi.get(rel) != uj[rel])
    torlendo = sorted(rel for rel in regi if rel not in uj)
    print(f"  {len(uj)} fájl, ebből új vagy változott: {len(feltoltendo)}, törlendő: {len(torlendo)}")
    if proba:
        for rel in feltoltendo:
            print(f"  feltöltené: {rel}")
        for rel in torlendo:
            print(f"  törölné: {rel}")
        return

    mappak = {celmappa}
    # az index.html-ek a végére: így a látogató nem lát félig frissült oldalt
    for i, rel in enumerate(sorted(feltoltendo, key=lambda r: (r.endswith("index.html"), r)), 1):
        tavoli = posixpath.join(celmappa, rel)
        tarhely.mappat_letrehoz(posixpath.dirname(tavoli), mappak)
        tarhely.feltolt(tavoli, fajlok[rel].read_bytes())
        print(f"  [{i}/{len(feltoltendo)}] feltöltve: {rel}")
    for rel in torlendo:
        tarhely.torol(posixpath.join(celmappa, rel))
        print(f"  törölve: {rel}")
    tarhely.feltolt(posixpath.join(celmappa, ALLAPOTFAJL), json.dumps(uj, indent=0, sort_keys=True).encode())


def main():
    proba = "--proba" in sys.argv[1:]
    beallitas = json.loads(BEALLITAS.read_text(encoding="utf-8"))
    try:
        tarhely = Tarhely(beallitas_adatok())
    except FeltoltesHiba:
        raise
    except ssl.SSLCertVerificationError as hiba:
        raise FeltoltesHiba(f"A tárhely tanúsítványa nem egyezik a megadott szervernévvel ({hiba}). "
                            "Az FTP_SZERVER-be a tárhely saját szervernevét írd (például we054.tarhely.com).")
    except (*ftplib.all_errors, ssl.SSLError) as hiba:
        raise FeltoltesHiba(f"Nem sikerült csatlakozni a tárhelyhez: {hiba}")
    print(f"Csatlakozva. Az FTP-fiók gyökere: {tarhely.gyoker}")
    try:
        for cel in beallitas["celok"]:
            cel_feltoltese(tarhely, cel, proba)
    finally:
        tarhely.bezar()
    print("\nKész." if not proba else "\nPróbafuttatás vége, semmi sem változott.")


if __name__ == "__main__":
    try:
        main()
    except FeltoltesHiba as hiba:
        sys.exit(f"HIBA – {hiba}")
