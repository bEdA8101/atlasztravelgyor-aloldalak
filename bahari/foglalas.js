/*
 * Atlasz Travel Győr – ajánlatkérés és foglalási igény az aloldalakon.
 * A frissites.py másolja az oldalak mellé (foglalas.js), és így tölti be:
 *   <script src="foglalas.js" defer data-mod="beepitett" data-urlap="#form" data-alap="ajanlatkeres"
 *           data-letszam="felnottek,gyerekek" data-egyseg="szoba"></script>
 * data-urlap több űrlapot is megadhat vesszővel (pl. "#form,#form2"); data-egyseg: szoba vagy kabin.
 *
 * data-mod="beepitett": az oldal saját űrlapja tetejére választó kerül (Ajánlatot kérek / Foglalási igényt
 *   adok le). Foglalási igénynél megjelenik a szobák és az utasok része (utasonként teljes név, ahogy az
 *   útlevélben szerepel, állampolgárság, születési dátum), a fő utas lakcíme és – ha az űrlapon nincs –
 *   az adatkezelési hozzájárulás. A beküldést az oldal saját programja végzi; a hiányos foglalási igényt
 *   ez a modul még a beküldés előtt megállítja.
 * data-mod="kulon-ajanlat": az oldal űrlapja maga a foglalási igény (pl. bahari); mellé fülekkel egy
 *   egyszerű ajánlatkérő űrlap kerül, amely a data-kuldo címre (send.php) küld.
 * A levélben az „urlap_tipus” mező mondja meg, melyik a kettő közül; az oldalt a levélküldő maga állapítja meg.
 */
(() => {
  'use strict';
  const S = document.currentScript;
  const cfg = {
    mod: S.dataset.mod || 'beepitett',
    urlap: S.dataset.urlap || '#form',
    alap: S.dataset.alap === 'foglalasi_igeny' ? 'foglalasi_igeny' : 'ajanlatkeres',
    letszam: (S.dataset.letszam || '').split(',').map(s => s.trim()).filter(Boolean),
    utasszoveg: S.dataset.utasszoveg || '',
    egyseg: S.dataset.egyseg === 'kabin' ? 'kabin' : 'szoba',
    kuldo: S.dataset.kuldo || 'send.php',
    telefon: S.dataset.telefon || '',
    adatvedelem: 'https://www.atlasztravelgyor.hu/adatvedelem'
  };
  const formok = [...document.querySelectorAll(cfg.urlap)];
  if (!formok.length) return;
  const form = formok[0];
  const Egyseg = cfg.egyseg === 'kabin' ? 'Kabin' : 'Szoba';

  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const ma = new Date().toISOString().slice(0, 10);
  const korOpt = sel => Array.from({length: 18}, (_, a) => `<option value="${a}"${a === sel ? ' selected' : ''}>${a === 0 ? '0–1 éves' : a + ' éves'}</option>`).join('');
  const szamOpt = (min, max, sel) => Array.from({length: max - min + 1}, (_, i) => `<option${i + min === sel ? ' selected' : ''}>${i + min}</option>`).join('');
  const NEMZET = ['magyar', 'szlovák', 'osztrák', 'román', 'német', 'szerb', 'ukrán', 'horvát'];

  const stilus = document.createElement('style');
  stilus.textContent = `
.gyf-valaszto{border:0;padding:0;margin:0 0 1.2em;display:flex;flex-wrap:wrap;gap:.6em;grid-column:1/-1}
.gyf-valaszto legend{font-weight:700;margin:0 0 .5em;padding:0;width:100%}
.gyf-valaszto label{display:inline-flex;align-items:center;gap:.5em;border:2px solid currentColor;border-radius:999px;padding:.55em 1.1em;cursor:pointer;font-weight:600;opacity:.75}
.gyf-valaszto label:has(input:checked){opacity:1;box-shadow:inset 0 0 0 1px currentColor}
.gyf-valaszto input{width:auto!important;margin:0;accent-color:currentColor}
.gyf-blokk{grid-column:1/-1;margin:.4em 0 1.2em}
.gyf-blokk[hidden]{display:none}
.gyf-info{font-size:.92em;opacity:.85;margin:0 0 1em}
.gyf-szoba{border:1px solid rgba(127,127,127,.35);border-radius:14px;padding:.9em 1em 1em;margin:0 0 1em}
.gyf-szoba legend{font-weight:700;padding:0 .4em}
.gyf-sor{display:flex;flex-wrap:wrap;gap:.8em;align-items:flex-end;margin-bottom:.6em}
.gyf-sor .gyf-m{width:9.5em}
.gyf-utas{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) minmax(0,1fr);align-items:end;gap:.6em .8em;padding:.7em 0 0;border-top:1px dashed rgba(127,127,127,.4);margin-top:.6em}
.gyf-utas .gyf-ki{grid-column:1/-1;margin:0;font-weight:700;font-size:.92em}
.gyf-m{display:flex;flex-direction:column;gap:.35em;font-size:.92em;font-weight:600;min-width:0}
.gyf-m input,.gyf-m select{font-weight:400}
.gyf-cim{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:.8em;margin:1em 0}
.gyf-cim .gyf-teljes{grid-column:1/-1}
.gyf-gomb{background:none;border:2px solid currentColor;border-radius:999px;padding:.5em 1em;font:inherit;font-weight:600;cursor:pointer;color:inherit}
.gyf-torles{background:none;border:0;padding:.4em 0;font:inherit;text-decoration:underline;cursor:pointer;color:inherit}
.gyf-hiba{color:#B3261E;font-weight:600;margin:.6em 0 0}
.gyf-hiba:empty{display:none}
.gyf-rossz{outline:2px solid #B3261E!important;outline-offset:1px}
.gyf-fulek{display:flex;flex-wrap:wrap;gap:.6em;margin:0 0 1.2em}
.gyf-fulek button{border:2px solid currentColor;background:none;color:inherit;border-radius:999px;padding:.6em 1.2em;font:inherit;font-weight:700;cursor:pointer;opacity:.7}
.gyf-fulek button[aria-selected="true"]{opacity:1;box-shadow:inset 0 0 0 1px currentColor}
.gyf-ajanlat{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9em 1em}
.gyf-ajanlat .gyf-teljes{grid-column:1/-1}
.gyf-check{display:flex;gap:.6em;align-items:flex-start;font-size:.95em}
.gyf-check input{width:20px!important;height:20px;flex:none;margin-top:.15em}
.gyf-uzenet{font-weight:600;margin:.8em 0 0}
@media (max-width:640px){.gyf-utas,.gyf-cim,.gyf-ajanlat{grid-template-columns:1fr}.gyf-cim .gyf-teljes,.gyf-ajanlat .gyf-teljes{grid-column:auto}}
`;
  document.head.appendChild(stilus);

  /* ---------- szobák és utasok (foglalási igény) ---------- */
  function szobakResz(elotag) {
    let szobak = [{ac: 2, ages: [], utas: []}];
    const doboz = document.createElement('div');
    const mentes = () => {
      doboz.querySelectorAll('.gyf-utas').forEach(u => {
        const [si, ui] = u.dataset.k.split('-').map(Number);
        if (!szobak[si]) return;
        szobak[si].utas[ui] = {n: u.querySelector('[data-n]').value, c: u.querySelector('[data-c]').value, d: u.querySelector('[data-d]').value};
      });
    };
    const rajz = () => {
      let k = 0;
      doboz.innerHTML = szobak.map((r, si) => {
        const fo = [...Array(r.ac).keys()].map(i => ({gyerek: false, i})).concat(r.ages.map((a, i) => ({gyerek: true, i, a})));
        return `<fieldset class="gyf-szoba" data-si="${si}"><legend>${si + 1}. ${cfg.egyseg}</legend>
          <input type="hidden" name="${elotag}szoba_${si + 1}_osszetetel" value="${r.ac} felnőtt${r.ages.length ? ', ' + r.ages.length + ' gyerek (' + r.ages.map(a => a + ' éves').join(', ') + ')' : ''}">
          <div class="gyf-sor">
            <label class="gyf-m"><span>Felnőttek</span><select data-ac>${szamOpt(1, 4, r.ac)}</select></label>
            <label class="gyf-m"><span>Gyerekek</span><select data-cc>${szamOpt(0, 3, r.ages.length)}</select></label>
            ${r.ages.map((a, i) => `<label class="gyf-m"><span>${i + 1}. gyerek kora</span><select data-kor="${i}">${korOpt(a)}</select></label>`).join('')}
            ${si > 0 ? '<button type="button" class="gyf-torles" data-torol>' + Egyseg + ' törlése</button>' : ''}
          </div>
          ${fo.map((p, ui) => { const v = r.utas[ui] || {}; k++; const fofo = si === 0 && ui === 0;
            return `<div class="gyf-utas" data-k="${si}-${ui}">
              <p class="gyf-ki">${p.gyerek ? (p.i + 1) + '. gyerek (' + p.a + ' éves)' : (p.i + 1) + '. felnőtt'}${fofo ? ' – fő (szerződő) utas' : ''}</p>
              <input type="hidden" name="${elotag}utas_${k}_szoba" value="${si + 1}">
              <input type="hidden" name="${elotag}utas_${k}_tipus" value="${p.gyerek ? 'gyerek, ' + p.a + ' éves' : 'felnőtt'}">
              <label class="gyf-m"><span>Teljes név, ahogy az útlevélben áll *</span><input data-n name="${elotag}utas_${k}_nev" autocomplete="${fofo ? 'name' : 'off'}" value="${esc(v.n || '')}"></label>
              <label class="gyf-m"><span>Állampolgárság *</span><input data-c name="${elotag}utas_${k}_allampolgarsag" list="gyf-nemzet" value="${esc(v.c == null ? 'magyar' : v.c)}"></label>
              <label class="gyf-m"><span>Születési dátum *</span><input data-d name="${elotag}utas_${k}_szul" type="date" max="${ma}" value="${esc(v.d || '')}"></label>
            </div>`; }).join('')}
        </fieldset>`;
      }).join('') + `<input type="hidden" name="${elotag}szobak_szama" value="${szobak.length}"><input type="hidden" name="${elotag}egyseg" value="${cfg.egyseg}">`;
    };
    doboz.addEventListener('change', e => {
      const fs = e.target.closest('[data-si]'); if (!fs) return;
      const r = szobak[+fs.dataset.si];
      mentes();
      if (e.target.matches('[data-ac]')) r.ac = +e.target.value;
      else if (e.target.matches('[data-cc]')) { const n = +e.target.value; r.ages = Array.from({length: n}, (_, i) => r.ages[i] != null ? r.ages[i] : 6); }
      else if (e.target.matches('[data-kor]')) r.ages[+e.target.dataset.kor] = +e.target.value;
      else return;
      rajz();
    });
    doboz.addEventListener('click', e => {
      if (!e.target.closest('[data-torol]')) return;
      mentes(); szobak.splice(+e.target.closest('[data-si]').dataset.si, 1); rajz();
    });
    rajz();
    return {
      doboz,
      ujSzoba() { mentes(); if (szobak.length < 4) szobak.push({ac: 2, ages: [], utas: []}); rajz(); return szobak.length; },
      elso(ac, cc) { mentes(); const r = szobak[0]; r.ac = Math.max(1, Math.min(4, ac)); r.ages = Array.from({length: Math.max(0, Math.min(3, cc))}, (_, i) => r.ages[i] != null ? r.ages[i] : 6); rajz(); },
      ures() { szobak = [{ac: 2, ages: [], utas: []}]; rajz(); },
      // hibás mezők listája (a megjelölésükkel együtt)
      ellenoriz() {
        const rossz = [];
        doboz.querySelectorAll('.gyf-utas').forEach(u => {
          const n = u.querySelector('[data-n]'), c = u.querySelector('[data-c]'), d = u.querySelector('[data-d]');
          const kor = /gyerek, (\d+) éves/.exec(u.querySelector('input[name$="_tipus"]').value);
          const evek = d.value ? (Date.now() - new Date(d.value + 'T12:00:00')) / 31557600000 : null;
          const rosszKor = kor && evek !== null && (evek > +kor[1] + 1.05 || evek < +kor[1] - 1.05);
          [[n, !/\S{2,}\s+\S{2,}/.test(n.value.trim())], [c, c.value.trim().length < 3], [d, !d.value || d.value >= ma || rosszKor]]
            .forEach(([el, hib]) => { el.classList.toggle('gyf-rossz', hib); if (hib) rossz.push(el); });
        });
        return rossz;
      }
    };
  }

  // a pirosra jelölt mezők a javításkor visszaváltanak
  document.addEventListener('input', e => { if (e.target.classList && e.target.classList.contains('gyf-rossz')) e.target.classList.remove('gyf-rossz'); });

  const nemzetLista = () => {
    if (document.getElementById('gyf-nemzet')) return;
    const dl = document.createElement('datalist'); dl.id = 'gyf-nemzet';
    dl.innerHTML = NEMZET.map(n => `<option value="${n}">`).join('');
    document.body.appendChild(dl);
  };
  const vanHozzajarulas = f => !!f.querySelector('input[type=checkbox][name="adatkezeles"], input[type=checkbox][id*="gdpr"], input[type=checkbox][id$="-ok"]');
  const hozzajarulasHTML = (nev) => `<label class="gyf-check check"><input type="checkbox" name="${nev}" value="elfogadva" data-hozzajarulas>
      <span>Elolvastam és elfogadom az <a href="${cfg.adatvedelem}" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>; hozzájárulok, hogy az iroda az utasok adatait a foglaláshoz felhasználja. *</span></label>`;

  /* ---------- 1. beépített mód: az oldal saját űrlapja kap választót és utasrészt ---------- */
  function beepit(form) {
    const valaszto = document.createElement('fieldset');
    valaszto.className = 'gyf-valaszto';
    valaszto.innerHTML = `<legend>Mit szeretne?</legend>
      <label><input type="radio" name="urlap_tipus" value="ajanlatkeres"${cfg.alap === 'ajanlatkeres' ? ' checked' : ''}> Ajánlatot kérek</label>
      <label><input type="radio" name="urlap_tipus" value="foglalasi_igeny"${cfg.alap === 'foglalasi_igeny' ? ' checked' : ''}> Foglalási igényt adok le</label>`;
    form.insertBefore(valaszto, form.firstElementChild);

    const blokk = document.createElement('div');
    blokk.className = 'gyf-blokk';
    const resz = szobakResz('');
    blokk.innerHTML = `<p class="gyf-info">A foglalási igény még nem végleges foglalás: kollégánk munkanapokon 24 órán belül visszaigazolja a pontos árat és a szabad helyeket. Minden utas nevét pontosan úgy adja meg, ahogyan az útlevelében szerepel; a gyerekek életkorát az utazás idejére számítsa.</p>`;
    blokk.appendChild(resz.doboz);
    const uj = document.createElement('button'); uj.type = 'button'; uj.className = 'gyf-gomb'; uj.textContent = '+ Újabb ' + cfg.egyseg;
    uj.addEventListener('click', () => { uj.hidden = resz.ujSzoba() >= 4; });
    blokk.appendChild(uj);
    const cim = document.createElement('div');
    cim.innerHTML = `<p class="gyf-ki" style="font-weight:700;margin:1.2em 0 .2em">A fő (szerződő) utas lakcíme</p>
      <div class="gyf-cim"><label class="gyf-m"><span>Irányítószám *</span><input name="irsz" autocomplete="postal-code" inputmode="numeric"></label>
      <label class="gyf-m"><span>Település *</span><input name="telepules" autocomplete="address-level2"></label>
      <label class="gyf-m gyf-teljes"><span>Utca, házszám *</span><input name="cim" autocomplete="street-address"></label></div>`;
    blokk.appendChild(cim);
    if (!vanHozzajarulas(form)) { const h = document.createElement('div'); h.innerHTML = hozzajarulasHTML('adatkezeles'); blokk.appendChild(h.firstElementChild); }
    const hiba = document.createElement('p'); hiba.className = 'gyf-hiba'; hiba.setAttribute('role', 'alert');
    blokk.appendChild(hiba);
    const kuld = form.querySelector('[type=submit]');
    let sor = kuld || form.lastElementChild;
    while (sor && sor.parentElement !== form) sor = sor.parentElement;
    form.insertBefore(blokk, sor || null);

    const eredetiGomb = kuld ? kuld.textContent : '';
    const letszam = () => {
      const [fn, gn] = cfg.letszam, f = fn && form.elements[fn], g = gn && form.elements[gn];
      const sz = el => el ? parseInt(el.value, 10) : NaN;
      return [sz(f), sz(g)];
    };
    const mod = () => (form.querySelector('input[name="urlap_tipus"]:checked') || {}).value || 'ajanlatkeres';
    const allit = () => {
      const fogl = mod() === 'foglalasi_igeny';
      blokk.hidden = !fogl;
      blokk.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = !fogl; });
      if (kuld) kuld.textContent = fogl ? 'Foglalási igény elküldése' : (/foglal/i.test(eredetiGomb) ? 'Ajánlatkérés elküldése' : eredetiGomb);
      if (fogl) { const [ac, cc] = letszam(); if (!isNaN(ac)) resz.elso(ac, isNaN(cc) ? 0 : cc); }
      hiba.textContent = '';
    };
    valaszto.addEventListener('change', allit);
    // a létszámmezők változását az 1. szobára is átvezetjük
    cfg.letszam.forEach(n => { const el = form.elements[n]; if (el && el.addEventListener) { el.addEventListener('change', () => { if (mod() === 'foglalasi_igeny') { const [ac, cc] = letszam(); if (!isNaN(ac)) resz.elso(ac, isNaN(cc) ? 0 : cc); } }); } });
    allit();

    // beküldés előtt: hiányos foglalási igény nem mehet el (az oldal programja előtt fut)
    document.addEventListener('submit', e => {
      if (e.target !== form || mod() !== 'foglalasi_igeny') return;
      const rossz = resz.ellenoriz();
      blokk.querySelectorAll('[name="irsz"],[name="telepules"],[name="cim"]').forEach(el => { const h = !el.value.trim(); el.classList.toggle('gyf-rossz', h); if (h) rossz.push(el); });
      const hj = blokk.querySelector('[data-hozzajarulas]') || form.querySelector('input[type=checkbox][name="adatkezeles"], input[type=checkbox][id*="gdpr"]');
      const hjHiba = hj && !hj.checked;
      if (!rossz.length && !hjHiba) {
        hiba.textContent = '';
        // az oldal saját létszámmezői (felnőttek, gyerekek, utasok) a szobák összesített létszámát kapják
        const fo = [...blokk.querySelectorAll('.gyf-szoba')].map(sz => [+sz.querySelector('[data-ac]').value, +sz.querySelector('[data-cc]').value]);
        const ossz = fo.reduce((a, [f, g]) => [a[0] + f, a[1] + g], [0, 0]);
        const [fn, gn] = cfg.letszam;
        [[fn, ossz[0]], [gn, ossz[1]]].forEach(([n, v]) => {
          const el = n && form.elements[n];
          if (!el || el.disabled) return;
          if (el.tagName === 'SELECT') { if ([...el.options].some(o => o.value === String(v))) el.value = String(v); }
          else el.value = String(v);
        });
        const usz = cfg.utasszoveg && form.elements[cfg.utasszoveg];
        if (usz) usz.value = ossz[0] + ' felnőtt' + (ossz[1] ? ', ' + ossz[1] + ' gyerek' : '') + ' (' + fo.length + ' ' + cfg.egyseg + ', részletek az utaslistában)';
        return;
      }
      e.preventDefault(); e.stopImmediatePropagation();
      hiba.textContent = rossz.length
        ? 'Kérjük, adja meg minden utas teljes nevét, állampolgárságát és születési dátumát (a gyerekeknél a megadott életkornak megfelelően), valamint a fő utas lakcímét.'
        : 'A foglalási igényhez kérjük, fogadja el az adatkezelési tájékoztatót.';
      (rossz[0] || hj).focus();
    }, true);

    // sikeres küldés után az oldal saját köszönőszövegét a foglalási igényhez igazítjuk
    const koszono = 'Köszönjük, foglalási igényét megkaptuk! Kollégánk munkanapokon 24 órán belül visszaigazolja a pontos árat és a szabad helyeket.';
    let utolsoMod = mod();
    form.addEventListener('submit', () => { utolsoMod = mod(); }, true);
    new MutationObserver(ms => {
      if (utolsoMod !== 'foglalasi_igeny') return;
      for (const m of ms) {
        const el = m.target.nodeType === 3 ? m.target.parentElement : m.target;
        const sajat = el && (el.closest('form') === form || !el.closest('form'));
        if (sajat && !blokk.contains(el) && /megkaptuk/i.test(el.textContent) && el.textContent !== koszono && el.children.length === 0) el.textContent = koszono;
      }
    }).observe(form.parentElement || form, {subtree: true, childList: true, characterData: true});
    form.addEventListener('reset', () => setTimeout(() => {
      resz.ures(); uj.hidden = false;
      form.querySelectorAll('input[name="urlap_tipus"]').forEach(r => { r.checked = r.value === cfg.alap; });
      allit();
    }, 0));
  }
  if (cfg.mod === 'beepitett') {
    nemzetLista();
    formok.forEach(beepit);
    return;
  }

  /* ---------- 2. külön ajánlatkérő: az oldal űrlapja a foglalási igény, mellé ajánlatkérés kerül ---------- */
  if (cfg.mod === 'kulon-ajanlat') {
    nemzetLista();
    // a foglalási igény (az oldal saját űrlapja) csak az adatkezelési hozzájárulással mehet el
    document.addEventListener('submit', e => {
      if (e.target !== form) return;
      const hj = form.querySelector('input[type=checkbox][name="adatkezeles"]');
      if (!hj || hj.checked) return;
      e.preventDefault(); e.stopImmediatePropagation();
      let h = form.querySelector('.gyf-hiba');
      if (!h) { h = document.createElement('p'); h.className = 'gyf-hiba'; h.setAttribute('role', 'alert'); hj.closest('label').after(h); }
      h.textContent = 'A foglalási igényhez kérjük, fogadja el az adatkezelési tájékoztatót.';
      hj.focus();
    }, true);
    form.addEventListener('change', e => { if (e.target.name === 'adatkezeles') { const h = form.querySelector('.gyf-hiba'); if (h) h.textContent = ''; } });
    const fulek = document.createElement('div');
    fulek.className = 'gyf-fulek'; fulek.setAttribute('role', 'tablist');
    fulek.innerHTML = '<button type="button" role="tab" aria-selected="true" data-ful="f">Foglalási igény</button><button type="button" role="tab" aria-selected="false" data-ful="a">Csak ajánlatot kérek</button>';
    const aj = document.createElement('form');
    aj.className = 'gyf-ajanlat'; aj.noValidate = true; aj.hidden = true;
    aj.innerHTML = `<input type="hidden" name="urlap_tipus" value="ajanlatkeres">
      <label class="gyf-m"><span>Név *</span><input name="nev" autocomplete="name"></label>
      <label class="gyf-m"><span>E-mail-cím *</span><input name="email" type="email" autocomplete="email"></label>
      <label class="gyf-m"><span>Telefonszám *</span><input name="telefon" type="tel" autocomplete="tel"></label>
      <label class="gyf-m"><span>Időpont</span><select name="idopont" data-masol="idopont"><option value="">Rugalmas / még nem tudom</option></select></label>
      <label class="gyf-m"><span>Szobatípus</span><select name="szoba" data-masol="szoba"><option value="">Mindegy</option></select></label>
      <label class="gyf-m"><span>Szobák száma</span><select name="szobak_szama">${szamOpt(1, 4, 1)}</select></label>
      <label class="gyf-m"><span>Felnőttek</span><select name="felnott">${szamOpt(1, 8, 2)}</select></label>
      <label class="gyf-m"><span>Gyerekek</span><select name="gyerek" data-gy>${szamOpt(0, 4, 0)}</select></label>
      <div class="gyf-teljes gyf-sor" data-korok></div>
      <label class="gyf-m gyf-teljes"><span>Megjegyzés</span><textarea name="uzenet" placeholder="Például szobaigény, rugalmas időpont, reptéri parkolás"></textarea></label>
      <input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px">
      <div class="gyf-teljes">${hozzajarulasHTML('adatkezeles').replace('a foglaláshoz', 'az ajánlathoz')}</div>
      <div class="gyf-teljes"><button type="submit" class="btn">Ajánlatkérés elküldése</button><p class="gyf-uzenet" role="status"></p></div>`;
    form.parentElement.insertBefore(fulek, form);
    form.parentElement.insertBefore(aj, form.nextSibling);
    const masol = () => aj.querySelectorAll('[data-masol]').forEach(s => {
      const eredeti = form.elements[s.dataset.masol];
      if (!eredeti || !eredeti.options) return;
      const elso = s.options[0].outerHTML, val = s.value;
      s.innerHTML = elso + [...eredeti.options].filter(o => o.value).map(o => `<option value="${esc(o.textContent.trim())}">${esc(o.textContent.trim())}</option>`).join('');
      s.value = val;
    });
    fulek.addEventListener('click', e => {
      const b = e.target.closest('[data-ful]'); if (!b) return;
      const ajanlat = b.dataset.ful === 'a';
      fulek.querySelectorAll('[data-ful]').forEach(x => x.setAttribute('aria-selected', x === b));
      form.hidden = ajanlat; aj.hidden = !ajanlat;
      if (ajanlat) masol();
    });
    const korok = aj.querySelector('[data-korok]');
    aj.querySelector('[data-gy]').addEventListener('change', e => {
      const n = +e.target.value;
      korok.innerHTML = Array.from({length: n}, (_, i) => `<label class="gyf-m"><span>${i + 1}. gyerek kora</span><select name="gyerek_${i + 1}_kor">${korOpt(6)}</select></label>`).join('');
    });
    const uz = aj.querySelector('.gyf-uzenet');
    aj.addEventListener('submit', async e => {
      e.preventDefault();
      const el = n => aj.elements[n];
      const rossz = [[el('nev'), el('nev').value.trim().length < 3], [el('email'), !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(el('email').value.trim())],
        [el('telefon'), el('telefon').value.replace(/\D/g, '').length < 8]].filter(([x, h]) => { x.classList.toggle('gyf-rossz', h); return h; });
      const hj = aj.querySelector('[data-hozzajarulas]');
      if (rossz.length || !hj.checked) {
        uz.style.color = '#B3261E';
        uz.textContent = rossz.length ? 'Kérjük, adja meg a nevét, az e-mail-címét és a telefonszámát.' : 'Az ajánlatkéréshez kérjük, fogadja el az adatkezelési tájékoztatót.';
        (rossz.length ? rossz[0][0] : hj).focus(); return;
      }
      const gomb = aj.querySelector('[type=submit]'); gomb.disabled = true; uz.style.color = ''; uz.textContent = 'Küldés…';
      try {
        const r = await fetch(cfg.kuldo, {method: 'POST', body: new FormData(aj)});
        const j = await r.json(); if (!j.ok) throw new Error(j.uzenet || '');
        aj.reset(); korok.innerHTML = '';
        uz.textContent = 'Köszönjük, ajánlatkérését megkaptuk! Kollégánk hamarosan jelentkezik.';
      } catch (err) {
        uz.style.color = '#B3261E';
        uz.innerHTML = esc(err.message || 'Az ajánlatkérést most nem sikerült elküldeni.') + (cfg.telefon ? ' Hívjon minket: <a href="tel:' + esc(cfg.telefon.replace(/\s/g, '')) + '">' + esc(cfg.telefon) + '</a>.' : '');
      } finally { gomb.disabled = false; }
    });
  }
})();
