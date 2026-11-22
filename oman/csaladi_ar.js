/*
 * Atlasz Travel Győr – családi (és bármilyen létszámú) pontos ár az élő időpontlistás aloldalakon.
 * A frissites.py másolja az oldal mellé (csaladi_ar.js), és így tölti be:
 *   <script src="csaladi_ar.js" defer data-api="api.php" data-hely=".hotel .more"
 *           data-szallodak='{"fanar":"Fanar Hotel & Residences 5*"}' data-arfolyam="370"
 *           data-urlap="#form" data-letszam="felnottek,gyerekek" data-szalloda-mezo="szalloda"></script>
 * data-hely: ezek után az elemek után kerül egy-egy doboz; ha az elem egy [data-hotel] elemen belül van,
 *   a doboz arra a szállodára számol, különben szállodaválasztót kap.
 * Az árakat az api.php (elo/idopontlista.php) adja: a forrás élő időpontjai és az árszolgáltatás végösszege.
 * Az „Ezt kérem” gomb kitölti az oldal ajánlatkérő űrlapját (létszám, gyerekek életkora, megjegyzés).
 */
(() => {
  'use strict';
  const S = document.currentScript;
  let szallodak = {};
  try { szallodak = JSON.parse(S.dataset.szallodak || '{}'); } catch (e) { /* üres */ }
  const cfg = {
    api: S.dataset.api || 'api.php',
    hely: S.dataset.hely || '',
    arfolyam: parseFloat(S.dataset.arfolyam || '0') || 0,
    urlap: S.dataset.urlap || '#form',
    letszam: (S.dataset.letszam || '').split(',').map(s => s.trim()).filter(Boolean),
    szallodaMezo: S.dataset.szallodaMezo || '',
    utasszoveg: S.dataset.utasszoveg || '',  // szöveges utasmező az űrlapon (pl. „utasok”)
    cim: S.dataset.cim || 'Családi ár, gyerekekkel',
    szin: S.dataset.szin || '#1F4F6F',
    keret: S.dataset.keret || '',            // a doboz köré tett elem osztálya (pl. az oldal tartalomszélessége)
    tegez: S.dataset.tegez === '1'           // az oldal tegeződik
  };
  const T = cfg.tegez ? {
    bev: 'Add meg, hányan utaztok, és hány évesek lesznek a gyerekek az utazás idején. A szabad helyek alapján azonnal kiszámoljuk a teljes árat az egész társaságra.',
    nincs: 'Írj nekünk vagy hívj, más szobatípussal vagy időponttal is szívesen keresünk.',
    hiba: 'Kérjük, próbáld újra később, vagy kérj ajánlatot lent.'
  } : {
    bev: 'Adja meg, hányan utaznak, és hány évesek lesznek a gyerekek az utazás idején. A szabad helyek alapján azonnal kiszámoljuk a teljes árat az egész társaságra.',
    nincs: 'Írjon nekünk vagy hívjon, más szobatípussal vagy időponttal is szívesen keresünk.',
    hiba: 'Kérjük, próbálja újra később, vagy kérjen ajánlatot lent.'
  };
  const helyek = cfg.hely ? [...document.querySelectorAll(cfg.hely)] : [];
  if (!helyek.length || !Object.keys(szallodak).length) return;

  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const csoport = n => String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const HO = ['jan.', 'febr.', 'márc.', 'ápr.', 'máj.', 'jún.', 'júl.', 'aug.', 'szept.', 'okt.', 'nov.', 'dec.'];
  const HONAP = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
  const dt = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
  const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const szak = (a, b) => { const x = dt(a), y = dt(b); return `${x.getFullYear()}. ${HO[x.getMonth()]} ${x.getDate()}. – ${x.getMonth() !== y.getMonth() ? HO[y.getMonth()] + ' ' : ''}${y.getDate()}.`; };
  const penz = (v, p) => p === 'HUF' ? `${csoport(v)} Ft` : `${csoport(v)} €`;
  const ft = (v, p) => (p === 'EUR' && cfg.arfolyam) ? `${csoport(v * cfg.arfolyam)} Ft` : '';
  const utasok = (ac, korok) => `${ac} felnőtt` + (korok.length ? ` + ${korok.length} gyerek (${korok.map(k => k === 0 ? '1 év alatti' : k + ' éves').join(', ')})` : '');

  const stilus = document.createElement('style');
  stilus.textContent = `
.csa{border:1px solid rgba(127,127,127,.35);border-radius:16px;padding:1.2em 1.2em 1.3em;margin:1.4em 0;background:rgba(127,127,127,.05)}
.csa h3{margin:0 0 .35em;font-size:1.2em;line-height:1.3}
.csa>p{margin:0 0 1em;line-height:1.5;opacity:.9}
.csa-urlap{display:flex;flex-wrap:wrap;gap:.7em 1em;align-items:flex-end}
.csa-m{display:flex;flex-direction:column;gap:.3em;font-size:.9em;font-weight:600;min-width:7.5em}
.csa-m select{font:inherit;font-weight:400;padding:.5em .6em;border-radius:10px;border:1px solid rgba(127,127,127,.5);background:#fff;color:#222;min-height:2.6em}
.csa-korok{display:contents}
.csa-gomb{font:inherit;font-weight:700;border:0;border-radius:999px;padding:.75em 1.4em;cursor:pointer;background:${cfg.szin};color:#fff;line-height:1.2}
.csa-gomb[disabled]{opacity:.6;cursor:wait}
.csa-eredmeny{margin-top:1.1em}
.csa-allapot{margin:.4em 0;font-weight:600;line-height:1.5}
.csa-hiba{color:#B3261E}
.csa-sor{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.3em 1em;padding:.9em 0;border-top:1px solid rgba(127,127,127,.3);align-items:start}
.csa-mikor{font-weight:700;line-height:1.35}
.csa-reszlet{font-size:.88em;opacity:.85;line-height:1.45;grid-column:1}
.csa-ar{text-align:right;grid-row:1/span 2;grid-column:2;line-height:1.3}
.csa-ar b{font-size:1.15em;white-space:nowrap}
.csa-ar small{display:block;font-size:.8em;opacity:.8;white-space:nowrap}
.csa-muv{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:.4em 1.2em;align-items:center;margin-top:.3em}
.csa-muv button,.csa-tobb{font:inherit;font-size:.92em;font-weight:700;background:none;border:0;padding:.3em 0;cursor:pointer;text-decoration:underline;color:inherit}
.csa-kerem{color:${cfg.szin}!important;border:2px solid currentColor!important;border-radius:999px;padding:.35em 1em!important;text-decoration:none!important}
.csa-szobak{grid-column:1/-1;margin:.4em 0 0;padding:0;list-style:none;font-size:.9em}
.csa-szobak li{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.2em 1em;padding:.5em 0;border-top:1px dashed rgba(127,127,127,.35);line-height:1.4}
.csa-szobak .n{flex:1 1 14em}
.csa-szobak .e{text-align:right;white-space:nowrap}
.csa-megj{font-size:.85em;opacity:.8;margin:.8em 0 0;line-height:1.5}
@media (max-width:560px){.csa{padding:1em}.csa-m{min-width:calc(50% - .5em);flex:1 1 calc(50% - .5em)}.csa-gomb{width:100%}}
`;
  document.head.appendChild(stilus);

  const szamOpt = (min, max, sel) => Array.from({length: max - min + 1}, (_, i) => `<option${i + min === sel ? ' selected' : ''}>${i + min}</option>`).join('');
  const korOpt = Array.from({length: 18}, (_, a) => `<option value="${a}"${a === 6 ? ' selected' : ''}>${a === 0 ? '1 év alatti' : a + ' éves'}</option>`).join('');
  const honapok = (() => {
    const ma = new Date(); const ki = [];
    for (let i = 0; i < 13; i++) { const d = new Date(ma.getFullYear(), ma.getMonth() + i, 1); ki.push(`<option value="${iso(d).slice(0, 7)}">${d.getFullYear()}. ${HONAP[d.getMonth()]}</option>`); }
    return ki.join('');
  })();

  function doboz(hely, sorszam) {
    const fix = (hely.closest('[data-hotel]') || {}).dataset;
    const kulcsok = Object.keys(szallodak);
    const fixKulcs = fix && szallodak[fix.hotel] ? fix.hotel : (kulcsok.length === 1 ? kulcsok[0] : null);
    const el = document.createElement('section');
    el.className = 'csa';
    const id = 'csa' + sorszam;
    el.setAttribute('aria-labelledby', id + 'c');
    el.innerHTML = `<h3 id="${id}c">${esc(cfg.cim)}</h3>
      <p>${esc(T.bev)}</p>
      <div class="csa-urlap">
        ${fixKulcs ? '' : `<label class="csa-m"><span>Szálloda</span><select data-sz>${Object.entries(szallodak).map(([k, n]) => `<option value="${esc(k)}">${esc(n)}</option>`).join('')}</select></label>`}
        <label class="csa-m"><span>Felnőttek</span><select data-ac>${szamOpt(1, 4, 2)}</select></label>
        <label class="csa-m"><span>Gyerekek</span><select data-cc>${szamOpt(0, 4, 1)}</select></label>
        <div class="csa-korok" data-korok></div>
        <label class="csa-m"><span>Indulás</span><select data-ho><option value="">Bármikor</option>${honapok}</select></label>
        <button type="button" class="csa-gomb" data-kalk>Árak kiszámítása</button>
      </div>
      <div class="csa-eredmeny" aria-live="polite"></div>`;
    if (cfg.keret) { const k = document.createElement('div'); k.className = cfg.keret; k.appendChild(el); hely.after(k); }
    else hely.after(el);
    const $ = s => el.querySelector(s);
    const korok = $('[data-korok]');
    const korokRajz = () => {
      const n = +$('[data-cc]').value, regi = [...korok.querySelectorAll('select')].map(s => s.value);
      korok.innerHTML = Array.from({length: n}, (_, i) => `<label class="csa-m"><span>${i + 1}. gyerek kora</span><select data-kor>${korOpt}</select></label>`).join('');
      korok.querySelectorAll('select').forEach((s, i) => { if (regi[i] != null) s.value = regi[i]; });
    };
    $('[data-cc]').addEventListener('change', korokRajz);
    korokRajz();
    const ki = $('.csa-eredmeny');
    let utolso = null;

    const kerdes = (alap, df, dt) => {
      const p = new URLSearchParams(alap);
      if (df) p.set('df', df);
      if (dt) p.set('dt', dt);
      return fetch(cfg.api + '?' + p.toString(), {cache: 'no-store'}).then(r => r.json());
    };

    function sorRajz(g, k, penznem) {
      const ac = k.ac, kor = k.ages;
      const s = document.createElement('div');
      s.className = 'csa-sor';
      const ido = g.ido ? ` ${g.ido}-kor` : '';
      s.innerHTML = `<div class="csa-mikor">${szak(g.d, g.vege || g.d)}</div>
        <div class="csa-reszlet">${g.n} éj · indulás ${esc(g.rep.join(' vagy '))}${ido}<br>${esc(g.szoba)}, ${esc(g.ell)}</div>
        <div class="csa-ar"><b>${penz(g.ossz, penznem)}</b>${ft(g.ossz, penznem) ? `<small>${ft(g.ossz, penznem)}</small>` : ''}<small>összesen</small></div>
        <div class="csa-muv"><button type="button" class="csa-kerem" data-kerem>Ezt kérem</button>${g.szobak.length > 1 ? '<button type="button" data-tobbszoba aria-expanded="false">Más szobák</button>' : ''}</div>`;
      s.querySelector('[data-kerem]').addEventListener('click', () => kitolt(k.h, g, {nev: g.szoba, ell: g.ell, ossz: g.ossz}, ac, kor, penznem));
      const tb = s.querySelector('[data-tobbszoba]');
      if (tb) tb.addEventListener('click', () => {
        let ul = s.querySelector('.csa-szobak');
        if (ul) { ul.hidden = !ul.hidden; tb.setAttribute('aria-expanded', String(!ul.hidden)); return; }
        ul = document.createElement('ul'); ul.className = 'csa-szobak';
        ul.innerHTML = g.szobak.slice(0, 12).map((x, i) => `<li><span class="n">${esc(x.nev)}<br><small>${esc(x.ell)}</small></span>
          <span class="e" data-i="${i}">${x.ossz != null ? `<b>${penz(x.ossz, penznem)}</b> összesen` : '<button type="button" data-arkell>Pontos ár</button>'}</span></li>`).join('');
        s.appendChild(ul); tb.setAttribute('aria-expanded', 'true');
        ul.addEventListener('click', e => {
          const b = e.target.closest('[data-arkell],[data-kerem2]'); if (!b) return;
          const hely2 = b.closest('[data-i]'), x = g.szobak[+hely2.dataset.i];
          if (b.hasAttribute('data-kerem2')) { kitolt(k.h, g, x, ac, kor, penznem); return; }
          b.disabled = true; b.textContent = 'Számolás…';
          const p = new URLSearchParams({a: 'ar', h: k.h, d: x.did, r: x.rid, ac});
          kor.forEach(a => p.append('ages[]', a));
          fetch(cfg.api + '?' + p.toString(), {cache: 'no-store'}).then(r => r.json()).then(j => {
            if (j.ok && j.elerheto && j.ossz != null) { x.ossz = j.ossz; hely2.innerHTML = `<b>${penz(j.ossz, penznem)}</b> összesen · <button type="button" data-kerem2>Ezt kérem</button>`; }
            else hely2.textContent = j.ok ? 'erre a létszámra nem foglalható' : 'most nem kérdezhető le';
          }).catch(() => { hely2.textContent = 'most nem kérdezhető le'; });
        });
        ul.querySelectorAll('[data-i]').forEach(sp => { const x = g.szobak[+sp.dataset.i]; if (x.ossz != null) sp.insertAdjacentHTML('beforeend', ' · <button type="button" data-kerem2>Ezt kérem</button>'); });
      });
      return s;
    }

    function futtat(tovabb) {
      const h = fixKulcs || $('[data-sz]').value;
      const ac = +$('[data-ac]').value;
      const ages = [...korok.querySelectorAll('select')].map(s => +s.value);
      const ho = $('[data-ho]').value;
      const alap = {a: 'csalad', h, ac};
      const p = new URLSearchParams(alap); ages.forEach(a => p.append('ages[]', a));
      let df = '', dtv = '';
      if (ho) { const [y, m] = ho.split('-').map(Number); df = iso(new Date(y, m - 1, 1)); dtv = iso(new Date(y, m, 25)); }
      if (tovabb && utolso) df = iso(new Date(dt(utolso.utolsoNap).getTime() + 86400000));
      const gomb = $('[data-kalk]'); gomb.disabled = true;
      if (!tovabb) { ki.innerHTML = '<p class="csa-allapot">Az árakat most kérjük le a szabad helyek alapján, ez néhány másodperc…</p>'; }
      else { const t = ki.querySelector('.csa-tobb'); if (t) { t.disabled = true; t.textContent = 'Betöltés…'; } }
      kerdes(p, df, dtv).then(j => {
        if (!j || !j.ok) throw new Error(j && j.uzenet || '');
        let sorok = j.dates || [];
        if (ho) sorok = sorok.filter(g => g.d.slice(0, 7) === ho);
        const k = {h, ac, ages};
        if (!tovabb) ki.innerHTML = `<p class="csa-allapot">${esc(szallodak[h])} · ${esc(utasok(ac, ages))}</p>`;
        const regiTobb = ki.querySelector('.csa-tobb'); if (regiTobb) regiTobb.remove();
        if (j.egyedi && !tovabb) {
          ki.insertAdjacentHTML('beforeend', `<p class="csa-allapot">Ennél a szállodánál a szervező a gyerekes (vagy nem 2 fős) végösszeget csak egyedi lekérdezéssel adja meg. ${cfg.tegez ? 'Kérj ajánlatot lent, és elküldjük a pontos árat.' : 'Kérjen ajánlatot lent, és elküldjük a pontos árat.'}</p>`);
        } else if (!sorok.length && !tovabb) {

          ki.insertAdjacentHTML('beforeend', `<p class="csa-allapot">Erre a létszámra ${ho ? 'ebben a hónapban ' : ''}most nem találtunk szabad helyet. ${esc(T.nincs)}</p>`);
        }
        sorok.forEach(g => ki.appendChild(sorRajz(g, k, j.penznem)));
        if (j.dates && j.dates.length) utolso = {utolsoNap: j.dates[j.dates.length - 1].d};
        if (j.tobb && !ho) {
          const t = document.createElement('button'); t.type = 'button'; t.className = 'csa-tobb'; t.textContent = 'Későbbi időpontok';
          t.addEventListener('click', () => futtat(true)); ki.appendChild(t);
        }
        if (!tovabb) ki.insertAdjacentHTML('beforeend', `<p class="csa-megj">A végösszeg az egész társaságra szól (repülőjegy, szállás, ellátás, transzfer), a forrás élő árai alapján${j.penznem === 'EUR' && cfg.arfolyam ? `; a forintár tájékoztató, ${cfg.arfolyam} Ft/€ árfolyammal` : ''}. A végleges árat és a helyet foglaláskor erősítjük meg.</p>`);
      }).catch(e => {
        if (!tovabb) ki.innerHTML = `<p class="csa-allapot csa-hiba">Az árakat most nem sikerült lekérni${e.message ? ' (' + esc(e.message) + ')' : ''}. ${esc(T.hiba)}</p>`;
      }).finally(() => { gomb.disabled = false; });
    }
    $('[data-kalk]').addEventListener('click', () => futtat(false));
  }

  /* az oldal ajánlatkérő űrlapjának kitöltése a választott ajánlattal */
  function kitolt(h, g, szoba, ac, korok, penznem) {
    const form = document.querySelector(cfg.urlap);
    if (!form) return;
    if (cfg.szallodaMezo) {
      const sz = [...form.querySelectorAll(`[name="${cfg.szallodaMezo}"]`)];
      const r = sz.find(x => x.value === h);
      if (r && r.type === 'radio') { r.checked = true; r.dispatchEvent(new Event('change', {bubbles: true})); }
      else if (sz[0] && sz[0].tagName === 'SELECT') { sz[0].value = h; sz[0].dispatchEvent(new Event('change', {bubbles: true})); }
    }
    const allit = (nev, v) => {
      const el = nev && form.elements[nev];
      if (!el) return;
      if (el.tagName === 'SELECT' && ![...el.options].some(o => (o.value || o.text) === String(v))) return;
      el.value = String(v); el.dispatchEvent(new Event('change', {bubbles: true}));
    };
    if (cfg.utasszoveg && form.elements[cfg.utasszoveg]) form.elements[cfg.utasszoveg].value = utasok(ac, korok);
    allit(cfg.letszam[0], ac);
    allit(cfg.letszam[1], korok.length);
    setTimeout(() => {
      const korMezok = [...form.querySelectorAll('select')].filter(s => /gyerek_?\d+_?kor/i.test(s.name || '') && !s.disabled);
      korMezok.slice(0, korok.length).forEach((s, i) => { if ([...s.options].some(o => (o.value || o.text) === String(korok[i]))) { s.value = String(korok[i]); s.dispatchEvent(new Event('change', {bubbles: true})); } });
      const ta = form.querySelector('textarea[name="megjegyzes"], textarea[name="uzenet"], textarea[name="message"], textarea');
      const sor = `Kiválasztott ajánlat: ${szallodak[h]}, ${szak(g.d, g.vege || g.d)} (${g.n} éj, indulás ${g.rep.join(' vagy ')}), ${szoba.nev}, ${szoba.ell}, ${utasok(ac, korok)}, ${penz(szoba.ossz, penznem)} összesen (élő ár, ${new Date().toLocaleDateString('hu-HU')}).`;
      if (ta) { ta.value = ta.value.split('\n').filter(x => !x.startsWith('Kiválasztott ajánlat:')).concat(sor).join('\n').trim(); ta.dispatchEvent(new Event('input', {bubbles: true})); }
      form.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion:reduce)').matches ? 'auto' : 'smooth', block: 'start'});
      const nev = form.querySelector('input[name="nev"], input[name="name"], input[type="text"]');
      if (nev) setTimeout(() => nev.focus({preventScroll: true}), 400);
    }, 60);
  }

  helyek.forEach((h, i) => doboz(h, i));
})();
