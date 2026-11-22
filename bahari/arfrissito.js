/*
 * Atlasz Travel Győr – rejtett árfrissítő az élő áras aloldalakon.
 * A frissites.py másolja az oldal mellé (arfrissito.js), és így tölti be:
 *   <script src="arfrissito.js" defer data-url="api.php?a=frissit"></script>
 * A lábléc évszáma (pl. „© 2026”) kattintható, de nem látszik rajta: rákattintva a tárhelyen futó program
 * azonnal frissíti az oldal árait a forrásból (nem csak ebben a böngészőben), majd az oldal újratöltődik.
 * Ha a láblécben nincs évszám, a lábléc végére egy halvány „© <év> Atlasz Travel Győr” sor kerül.
 * Főoldal (gyűjtőoldal): data-osszes='[{"nev":"oman","url":"https://oman…/api.php?a=frissit"},…]' – az évszámra
 * kattintva az összes élő áras aloldal frissül (egyszerre 3), az évszám helyén az állással; nincs újratöltés.
 * A program oldalanként legfeljebb 5 percenként frissít; az ütemezett frissítés (6:00 és éjfél között
 * 2 óránként) ettől függetlenül megtörténik.
 */
(() => {
  'use strict';
  const S = document.currentScript;
  const url = S && S.dataset.url;
  let osszes = null;
  try { osszes = S && S.dataset.osszes ? JSON.parse(S.dataset.osszes) : null; } catch (e) { osszes = null; }
  const footer = [...document.querySelectorAll('footer')].pop();
  if ((!url && !(osszes && osszes.length)) || !footer) return;

  // az első évszám a láblécben (a jogi tájékoztatón kívül)
  const jo = n => /\b20\d\d\b/.test(n.nodeValue) && !n.parentElement.closest('.gy-tajekoztato, script, style, a');
  const bejaro = document.createTreeWalker(footer, NodeFilter.SHOW_TEXT, {acceptNode: n => jo(n) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP});
  const n = bejaro.nextNode();
  let ev;
  if (n) {
    const m = n.nodeValue.match(/\b20\d\d\b/);
    const resz = n.splitText(m.index);
    resz.splitText(4);
    ev = document.createElement('span');
    ev.textContent = m[0];
    resz.replaceWith(ev);
  } else {
    const hova = footer.querySelector('.wrap') || footer;
    const sor = document.createElement('span');
    sor.style.opacity = '.75';
    sor.append('© ');
    ev = document.createElement('span');
    ev.textContent = String(new Date().getFullYear());
    sor.append(ev, ' Atlasz Travel Győr');
    hova.appendChild(sor);
  }

  const eredeti = ev.textContent;
  let fut = false;
  ev.style.cursor = 'default';
  // egy aloldal frissítése: true, ha sikerült (vagy épp az imént frissült)
  const egy = async cim => {
    try {
      const r = await fetch(cim, {cache: 'no-store'});
      const j = await r.json();
      return !!(j && j.ok);
    } catch (e) { return false; }
  };

  if (osszes && osszes.length) {
    ev.addEventListener('click', async () => {
      if (fut) return;
      fut = true;
      let kesz = 0;
      const hibas = [];
      const allas = () => { ev.textContent = `árak frissítése… ${kesz}/${osszes.length}`; };
      allas();
      const sor = [...osszes];
      const dolgozo = async () => {
        for (let o = sor.shift(); o; o = sor.shift()) {
          if (!(await egy(o.url))) hibas.push(o.nev);
          kesz++;
          allas();
        }
      };
      await Promise.all([dolgozo(), dolgozo(), dolgozo()]);
      ev.textContent = hibas.length ? `kész, de nem sikerült: ${hibas.join(', ')}` : `mind a ${osszes.length} aloldal árai frissültek ✓`;
      setTimeout(() => { ev.textContent = eredeti; fut = false; }, hibas.length ? 8000 : 5000);
    });
    return;
  }

  ev.addEventListener('click', async () => {
    if (fut) return;
    fut = true;
    ev.textContent = 'árak frissítése…';
    try {
      const r = await fetch(url, {cache: 'no-store'});
      const j = await r.json();
      if (!j || !j.ok) throw new Error();
      ev.textContent = j.friss ? `az árak ${j.perce} perce frissültek` : 'árak frissítve ✓';
      setTimeout(() => location.reload(), 1200);
    } catch (e) {
      ev.textContent = 'a frissítés most nem sikerült';
      setTimeout(() => { ev.textContent = eredeti; fut = false; }, 3000);
    }
  });
})();
