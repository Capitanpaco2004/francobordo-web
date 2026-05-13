/* francobordo-search.js
 *
 * Reemplazo de Doofinder con dos UX combinadas:
 *
 *  1) Autocomplete bajo el input (~8 resultados) — rápido para usuarios que saben
 *     exactamente qué buscan.
 *  2) Overlay grande tipo Doofinder con facetas (marca, categoría, precio, stock),
 *     paginación, sort y grid de tarjetas — se abre al pulsar la lupa o al hacer
 *     foco en el input.
 *
 * Sin dependencias. Backend: /api/search-proxy.php (Meilisearch BM25).
 */
(function () {
  'use strict';

  const PROXY_URL    = '/api/search-proxy.php?endpoint=search';
  const DEBOUNCE_MS  = 150;
  const MIN_CHARS    = 2;
  const PAGE_SIZE    = 24;
  const FALLBACK_IMG = '/images/placeholder.png';

  // ---------- helpers ----------
  function $ (sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function fmtPrice(p) {
    if (p == null) return '';
    return Number(p).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }

  // ---------- styles ----------
  function injectStyles() {
    if (document.getElementById('fb-search-styles')) return;
    const s = document.createElement('style');
    s.id = 'fb-search-styles';
    s.textContent = `
      :root { --fb-blue: #009fe3; --fb-blue-dark: #0084be; --fb-border: #e5e7eb; --fb-muted: #6b7280; --fb-text: #1f2937; --fb-bg: #f9fafb; }

      /* ===== Modal anclado bajo el buscador del header (tipo Doofinder) ===== */
      .fb-modal { position: fixed; inset: 0; z-index: 99999; display: none; background: rgba(15,23,42,0.12); pointer-events: none; }
      .fb-modal.open { display: block; }
      .fb-modal-body {
        position: absolute;
        top: var(--fb-modal-top, 80px);
        left: 4vw; right: 4vw;
        max-width: 1400px; margin: 0 auto;
        bottom: 4vh;
        background: #fff; border-radius: 12px;
        display: grid; grid-template-rows: auto 1fr;
        box-shadow: 0 24px 64px rgba(0,0,0,0.25);
        overflow: hidden; font-family: inherit; color: var(--fb-text);
        pointer-events: auto;
      }

      .fb-hd { display: flex; align-items: center; gap: 14px; padding: 8px 16px; border-bottom: 1px solid var(--fb-border); background: white; }
      .fb-hd-info { flex: 1; color: var(--fb-muted); font-size: 13px; }
      .fb-hd-mobile-input { display: none; flex: 1; padding: 10px 14px; font-size: 16px; border: 1px solid var(--fb-border); border-radius: 999px; outline: none; box-sizing: border-box; }
      .fb-hd-mobile-input:focus { border-color: var(--fb-blue); }
      .fb-hd-close { margin-left: auto; background: transparent; border: 0; cursor: pointer; padding: 6px 10px; font-size: 18px; color: var(--fb-muted); line-height: 1; }
      .fb-hd-close:hover { color: var(--fb-text); }

      .fb-content { display: grid; grid-template-columns: 280px 1fr; gap: 16px; padding: 16px 20px; overflow: hidden; background: var(--fb-bg); }
      .fb-facets { background: white; border: 1px solid var(--fb-border); border-radius: 8px; padding: 14px; overflow-y: auto; max-height: 100%; }
      .fb-facet { margin-bottom: 18px; }
      .fb-facet h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; color: var(--fb-muted); letter-spacing: .05em; display: flex; justify-content: space-between; align-items: center; }
      .fb-facet h3 .fb-facet-toggle { background: transparent; border: 0; cursor: pointer; color: var(--fb-muted); }
      .fb-facet-item { display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 14px; cursor: pointer; }
      .fb-facet-item input { accent-color: var(--fb-blue); }
      .fb-facet-item .fb-count { margin-left: auto; color: var(--fb-muted); font-size: 12px; }
      .fb-facet-item.active span:not(.fb-count) { font-weight: 600; }
      .fb-price-range { display: flex; gap: 6px; align-items: center; font-size: 13px; }
      .fb-price-range input { width: 70px; padding: 4px 6px; border: 1px solid var(--fb-border); border-radius: 4px; font-size: 13px; }
      .fb-price-range button { background: var(--fb-blue); color: white; border: 0; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
      .fb-reset { width: 100%; padding: 8px; background: white; border: 1px solid var(--fb-border); border-radius: 6px; cursor: pointer; font-size: 13px; color: var(--fb-muted); margin-top: 6px; }

      .fb-main { display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
      .fb-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
      .fb-counter { color: var(--fb-muted); font-size: 14px; }
      .fb-counter b { color: var(--fb-text); }
      .fb-timing { font-size: 11px; color: var(--fb-muted); margin-left: 4px; }
      .fb-sort { padding: 6px 10px; border: 1px solid var(--fb-border); border-radius: 6px; background: white; font-size: 14px; }

      .fb-results-wrap { flex: 1; overflow-y: auto; }
      .fb-results { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; padding-bottom: 16px; }
      .fb-card { background: white; border: 1px solid var(--fb-border); border-radius: 8px; padding: 12px; text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s; }
      .fb-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-1px); }
      .fb-card img { width: 100%; aspect-ratio: 1; object-fit: contain; background: white; border-radius: 4px; }
      .fb-card .fb-c-brand { font-size: 11px; color: var(--fb-muted); text-transform: uppercase; letter-spacing: .03em; margin-top: 6px; }
      .fb-card .fb-c-title { font-size: 14px; margin: 4px 0; line-height: 1.3; min-height: 36px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
      .fb-card em { background: #fff2a8; font-style: normal; padding: 0 2px; }
      .fb-card .fb-c-price { font-weight: 700; color: var(--fb-text); font-size: 16px; margin-top: auto; padding-top: 6px; }

      .fb-pager { display: flex; justify-content: center; gap: 8px; padding: 16px 0; }
      .fb-pager button { padding: 8px 14px; border: 1px solid var(--fb-border); background: white; border-radius: 6px; cursor: pointer; font-size: 14px; }
      .fb-pager button:disabled { opacity: .4; cursor: not-allowed; }

      .fb-empty { text-align: center; color: var(--fb-muted); padding: 60px 20px; grid-column: 1/-1; }

      /* ===== Pequeño dropdown autocomplete bajo el input (modo rápido) ===== */
      .fb-sr-wrap { position: relative; }
      .fb-sr-dd { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 9998; background: white; border: 1px solid var(--fb-border); border-radius: 8px; box-shadow: 0 12px 32px rgba(0,0,0,0.12); max-height: 360px; overflow-y: auto; display: none; }
      .fb-sr-dd.open { display: block; }
      .fb-sr-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; text-decoration: none; color: inherit; border-bottom: 1px solid #f3f4f6; cursor: pointer; }
      .fb-sr-row:hover, .fb-sr-row.active { background: #f0f9ff; }
      .fb-sr-row img { width: 40px; height: 40px; flex: 0 0 40px; object-fit: contain; }
      .fb-sr-row .fb-sr-title { flex: 1; font-size: 13px; line-height: 1.3; }
      .fb-sr-row em { background: #fff2a8; font-style: normal; padding: 0 2px; }
      .fb-sr-row .fb-sr-price { font-weight: 700; color: var(--fb-blue-dark); font-size: 13px; white-space: nowrap; }
      .fb-sr-all { padding: 10px 14px; background: var(--fb-blue-dark); color: white; font-weight: 600; font-size: 13px; text-align: center; text-decoration: none; display: block; cursor: pointer; }
      .fb-sr-all:hover { background: var(--fb-blue); }

      /* ===== RESPONSIVE ===== */
      @media (max-width: 1280px) {
        .fb-modal-body { left: 2vw; right: 2vw; }
        .fb-results { grid-template-columns: repeat(3, 1fr); }
      }
      @media (max-width: 1024px) {
        .fb-modal-body { left: 0; right: 0; bottom: 0; border-radius: 0; }
        .fb-content { grid-template-columns: 240px 1fr; gap: 12px; padding: 12px; }
        .fb-results { grid-template-columns: repeat(3, 1fr); }
      }
      @media (max-width: 768px) {
        /* Full-screen modal on mobile, ignore anchor */
        .fb-modal-body { top: 0 !important; left: 0; right: 0; bottom: 0; border-radius: 0; }
        .fb-hd-info { display: none; }                /* esconder texto descriptivo */
        .fb-hd-mobile-input { display: block; }       /* mostrar input propio */
        .fb-content { grid-template-columns: 1fr; padding: 12px; }
        .fb-facets { display: none; position: fixed; inset: 0; z-index: 100000; max-height: none; border-radius: 0; overflow-y: auto; }
        .fb-facets.open { display: block; }
        .fb-facets::before { content: "Filtros"; display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; padding-bottom: 12px; border-bottom: 2px solid var(--fb-blue); margin-bottom: 12px; }
        .fb-mobile-filter-btn { position: fixed; right: 12px; bottom: 12px; z-index: 99999; background: var(--fb-blue); color: white; border: 0; border-radius: 999px; padding: 12px 18px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,0.2); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .fb-mobile-filter-btn .fb-fc-badge { background: white; color: var(--fb-blue); border-radius: 999px; padding: 2px 8px; font-size: 12px; }
        .fb-results { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .fb-card { padding: 8px; }
        .fb-card .fb-c-title { font-size: 13px; min-height: 32px; }
        .fb-hd { padding: 8px 12px; gap: 8px; }
      }
      @media (max-width: 380px) {
        .fb-results { grid-template-columns: 1fr; }
        .fb-card { flex-direction: row; gap: 10px; }
        .fb-card img { width: 80px; height: 80px; flex: 0 0 80px; }
        .fb-card .fb-c-title { min-height: 0; }
      }
    `;
    document.head.appendChild(s);
  }

  // ---------- Meili call ----------
  async function meiliSearch(opts) {
    const body = {
      q: opts.q || '',
      limit:  opts.limit  || PAGE_SIZE,
      offset: opts.offset || 0,
      attributesToHighlight: ['title'],
      highlightPreTag: '<em>', highlightPostTag: '</em>',
    };
    if (opts.facets) body.facets = opts.facets;
    if (opts.filter) body.filter = opts.filter;
    if (opts.sort)   body.sort   = [opts.sort];
    const r = await fetch(PROXY_URL, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error('proxy ' + r.status);
    return r.json();
  }

  // ---------- State ----------
  const state = {
    q: '', page: 0, sort: '',
    filters: { brand: new Set(), category_lvl0: new Set(), availability: new Set(), price: [null, null] },
  };
  function resetFilters() {
    state.filters = { brand: new Set(), category_lvl0: new Set(), availability: new Set(), price: [null, null] };
  }
  function buildFilter() {
    const parts = [];
    for (const attr of ['brand', 'category_lvl0', 'availability']) {
      const vals = [...state.filters[attr]];
      if (vals.length) parts.push('(' + vals.map(v => `${attr} = ${JSON.stringify(v)}`).join(' OR ') + ')');
    }
    const [pmin, pmax] = state.filters.price;
    if (pmin != null) parts.push(`price >= ${pmin}`);
    if (pmax != null) parts.push(`price <= ${pmax}`);
    return parts.join(' AND ');
  }

  // ---------- Modal anclado bajo el input del header ----------
  let modalEl = null;
  let anchorInput = null;        // input externo que actúa como buscador
  let modalDebounce = null;

  function buildModal() {
    modalEl = document.createElement('div');
    modalEl.className = 'fb-modal';
    modalEl.innerHTML = `
      <div class="fb-modal-body">
        <div class="fb-hd">
          <input class="fb-hd-mobile-input" type="search" placeholder="¿Qué estás buscando?" autocomplete="off">
          <span class="fb-hd-info">Resultados de búsqueda en directo · escribe arriba</span>
          <button class="fb-hd-close" aria-label="Cerrar">✕ Cerrar</button>
        </div>
        <div class="fb-content">
          <aside class="fb-facets">
            <div class="fb-facet" data-attr="brand"><h3>Marca</h3><div class="fb-facet-list"></div></div>
            <div class="fb-facet" data-attr="category_lvl0"><h3>Categoría</h3><div class="fb-facet-list"></div></div>
            <div class="fb-facet">
              <h3>Precio (€)</h3>
              <div class="fb-price-range">
                <input type="number" class="fb-pmin" placeholder="min" min="0">
                <span>—</span>
                <input type="number" class="fb-pmax" placeholder="max" min="0">
                <button class="fb-papply">OK</button>
              </div>
            </div>
            <div class="fb-facet" data-attr="availability"><h3>Disponibilidad</h3><div class="fb-facet-list"></div></div>
            <button class="fb-reset">Limpiar filtros</button>
          </aside>
          <section class="fb-main">
            <div class="fb-toolbar">
              <div><span class="fb-counter"></span><span class="fb-timing"></span></div>
              <select class="fb-sort">
                <option value="">Relevancia</option>
                <option value="price:asc">Precio: menor a mayor</option>
                <option value="price:desc">Precio: mayor a menor</option>
              </select>
            </div>
            <div class="fb-results-wrap">
              <div class="fb-results"></div>
              <div class="fb-pager"></div>
            </div>
          </section>
        </div>
        <button class="fb-mobile-filter-btn" type="button" style="display:none">
          Filtros<span class="fb-fc-badge" style="display:none">0</span>
        </button>
      </div>`;
    document.body.appendChild(modalEl);

    // Wire events
    $('.fb-hd-close', modalEl).addEventListener('click', closeModal);
    // Click en el backdrop (zona fuera del .fb-modal-body): cerrar
    modalEl.addEventListener('click', (e) => { if (e.target === modalEl) closeModal(); });

    // Input móvil propio: dirige la búsqueda igual que el del header
    const mobileInput = $('.fb-hd-mobile-input', modalEl);
    mobileInput.addEventListener('input', () => {
      state.q = mobileInput.value.trim();
      clearTimeout(modalDebounce);
      modalDebounce = setTimeout(refreshModal, DEBOUNCE_MS);
      // Sincroniza con los inputs externos del header por si vuelve a desktop
      $$('[data-fb-search="true"]').forEach(i => i.value = mobileInput.value);
    });
    $('.fb-sort', modalEl).addEventListener('change', (e) => { state.sort = e.target.value; state.page = 0; refreshModal(); });
    $('.fb-facets', modalEl).addEventListener('change', (e) => {
      if (e.target.matches('input[type=checkbox]')) {
        const attr = e.target.dataset.attr;
        const val  = decodeURIComponent(e.target.dataset.val);
        if (e.target.checked) state.filters[attr].add(val);
        else state.filters[attr].delete(val);
        state.page = 0; refreshModal();
      }
    });
    $('.fb-papply', modalEl).addEventListener('click', () => {
      state.filters.price = [
        parseFloat($('.fb-pmin', modalEl).value) || null,
        parseFloat($('.fb-pmax', modalEl).value) || null,
      ];
      state.page = 0; refreshModal();
    });
    $('.fb-reset', modalEl).addEventListener('click', () => {
      resetFilters();
      $('.fb-pmin', modalEl).value = ''; $('.fb-pmax', modalEl).value = '';
      state.page = 0; refreshModal();
    });
    // Scroll infinito: el observer del sentinel lo gestiona en setupSentinel()
    $('.fb-mobile-filter-btn', modalEl).addEventListener('click', () => {
      $('.fb-facets', modalEl).classList.toggle('open');
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modalEl.classList.contains('open')) closeModal(); });
  }

  function positionModal() {
    if (!modalEl || !anchorInput) return;
    const rect = anchorInput.getBoundingClientRect();
    const top = Math.max(0, rect.bottom + 4);
    modalEl.style.setProperty('--fb-modal-top', `${top}px`);
  }

  function openModal(q) {
    if (!modalEl) buildModal();
    if (q != null) state.q = q;
    positionModal();
    modalEl.classList.add('open');
    // En móvil: mostrar botón filtros + sincronizar input propio del modal + dar foco
    if (window.innerWidth <= 768) {
      $('.fb-mobile-filter-btn', modalEl).style.display = 'inline-flex';
      const mobileInput = $('.fb-hd-mobile-input', modalEl);
      mobileInput.value = state.q || '';
      setTimeout(() => mobileInput.focus(), 50);
      document.body.style.overflow = 'hidden';   // evita doble scroll en móvil
    }
    // En desktop NO robamos el focus al input externo del header
    refreshModal();
  }
  function closeModal() {
    if (!modalEl) return;
    modalEl.classList.remove('open');
    $('.fb-facets', modalEl).classList.remove('open');
    document.body.style.overflow = '';
  }

  // Estado del scroll infinito
  let infinite = { loaded: 0, total: 0, loading: false, exhausted: false, sentinelObs: null };

  async function refreshModal() {
    if (!modalEl) return;
    // Reset estado del scroll infinito al hacer una nueva búsqueda/filtro
    state.page = 0;
    infinite.loaded = 0; infinite.total = 0; infinite.loading = false; infinite.exhausted = false;
    $('.fb-results', modalEl).innerHTML = '';
    $('.fb-pager', modalEl).innerHTML = '';
    await loadNextPage(true);   // primera página + facetas
    setupSentinel();
  }

  async function loadNextPage(includeFacets) {
    if (infinite.loading || infinite.exhausted) return;
    infinite.loading = true;
    const offset = state.page * PAGE_SIZE;
    const t0 = performance.now();
    let data;
    try {
      data = await meiliSearch({
        q: state.q,
        limit: PAGE_SIZE, offset,
        filter: buildFilter(),
        facets: includeFacets ? ['brand', 'category_lvl0', 'availability'] : undefined,
        sort: state.sort,
      });
    } catch (e) {
      infinite.loading = false;
      $('.fb-results', modalEl).insertAdjacentHTML('beforeend',
        `<div class="fb-empty">Error: ${escapeHtml(e.message)}</div>`);
      return;
    }
    const dt = Math.round(performance.now() - t0);
    const hits = data.hits || [];
    infinite.total  = data.estimatedTotalHits ?? 0;
    infinite.loaded += hits.length;
    state.page += 1;
    if (hits.length < PAGE_SIZE || infinite.loaded >= infinite.total) {
      infinite.exhausted = true;
    }

    // Counter + timing solo en la primera página (no spam en cada scroll)
    if (includeFacets) {
      $('.fb-counter', modalEl).innerHTML = `<b>${infinite.total.toLocaleString('es-ES')}</b> resultados`;
      $('.fb-timing', modalEl).textContent = ` · ${dt} ms (meili ${data.processingTimeMs ?? '?'} ms)`;
      renderFacets(data.facetDistribution);
      updateMobileBadge();
    }

    if (hits.length) {
      $('.fb-results', modalEl).insertAdjacentHTML('beforeend', hits.map(renderCard).join(''));
    } else if (includeFacets) {
      // primera página vacía
      $('.fb-results', modalEl).innerHTML = `<div class="fb-empty">Sin resultados para "${escapeHtml(state.q)}"</div>`;
    }

    // Footer status
    const pager = $('.fb-pager', modalEl);
    if (infinite.exhausted) {
      pager.innerHTML = infinite.loaded
        ? `<div style="color:#6b7280;font-size:13px;padding:12px;">Has visto los ${infinite.loaded.toLocaleString('es-ES')} resultados</div>`
        : '';
    } else {
      pager.innerHTML = `<div class="fb-sentinel" style="height:1px;"></div>`;
    }

    infinite.loading = false;
  }

  function setupSentinel() {
    if (infinite.sentinelObs) infinite.sentinelObs.disconnect();
    if (infinite.exhausted) return;
    const root = $('.fb-results-wrap', modalEl);
    const obs = new IntersectionObserver((entries) => {
      for (const e of entries) {
        if (e.isIntersecting && !infinite.loading && !infinite.exhausted) {
          loadNextPage(false).then(() => {
            // Re-observe (porque hemos sustituido el sentinel)
            const next = $('.fb-sentinel', modalEl);
            if (next) obs.observe(next);
          });
        }
      }
    }, { root, rootMargin: '300px' });   // pre-carga cuando faltan 300px
    const sentinel = $('.fb-sentinel', modalEl);
    if (sentinel) obs.observe(sentinel);
    infinite.sentinelObs = obs;
  }

  function renderCard(h) {
    const title = (h._formatted && h._formatted.title) || escapeHtml(h.title);
    const img   = h.image ? escapeHtml(h.image) : FALLBACK_IMG;
    const link  = escapeHtml(h.link || '#');
    return `
      <a class="fb-card" href="${link}">
        <img src="${img}" loading="lazy" onerror="this.style.visibility='hidden'">
        <div class="fb-c-brand">${escapeHtml(h.brand || '')}</div>
        <div class="fb-c-title">${title}</div>
        <div class="fb-c-price">${fmtPrice(h.price)}</div>
      </a>`;
  }

  // Etiquetas mostradas al cliente — el valor REAL en Meili sigue siendo el inglés
  // (no rompemos filtros ni hace falta re-indexar)
  const FACET_LABELS = {
    availability: {
      'in stock':     'Con stock',
      'out of stock': 'Sin stock',
    },
  };

  function labelFor(attr, val) {
    return (FACET_LABELS[attr] && FACET_LABELS[attr][val]) || val || '(sin valor)';
  }

  function renderFacets(dist) {
    if (!dist) return;
    for (const [attr, dict] of Object.entries(dist)) {
      const container = $(`.fb-facet[data-attr="${attr}"] .fb-facet-list`, modalEl);
      if (!container) continue;
      const entries = Object.entries(dict).sort((a, b) => b[1] - a[1]).slice(0, 12);
      container.innerHTML = entries.map(([val, count]) => {
        const checked = state.filters[attr].has(val);
        return `
          <label class="fb-facet-item ${checked ? 'active' : ''}">
            <input type="checkbox" data-attr="${attr}" data-val="${encodeURIComponent(val)}" ${checked ? 'checked' : ''}>
            <span>${escapeHtml(labelFor(attr, val))}</span>
            <span class="fb-count">${count}</span>
          </label>`;
      }).join('');
    }
  }

  // renderPager eliminada — usamos scroll infinito (ver setupSentinel)

  function updateMobileBadge() {
    const n = state.filters.brand.size + state.filters.category_lvl0.size + state.filters.availability.size +
              (state.filters.price[0] != null ? 1 : 0) + (state.filters.price[1] != null ? 1 : 0);
    const badge = $('.fb-fc-badge', modalEl);
    if (n > 0) { badge.textContent = n; badge.style.display = 'inline-block'; }
    else { badge.style.display = 'none'; }
  }

  // ---------- Hook al input: el usuario teclea en el input del header,
  // el modal aparece bajo él mostrando resultados en vivo (estilo Doofinder) ----------
  function attach(input) {
    if (input.dataset.fbAttached) return;
    input.dataset.fbAttached = '1';

    let t = null;
    const setAnchorAndOpen = () => {
      anchorInput = input;
      openModal(input.value.trim());
    };

    // Click/focus en el input abre el modal anclado bajo él
    input.addEventListener('focus', setAnchorAndOpen);
    input.addEventListener('mousedown', setAnchorAndOpen);

    // Tecleo: actualizar query (debounced) y refrescar resultados
    input.addEventListener('input', () => {
      anchorInput = input;
      if (!modalEl || !modalEl.classList.contains('open')) openModal(input.value.trim());
      state.q = input.value.trim();
      clearTimeout(t);
      t = setTimeout(() => { positionModal(); refreshModal(); }, DEBOUNCE_MS);
    });

    // Botón lupa / Enter en el input: si la query coincide con un EAN/MPN exacto
    // o sólo hay 1 resultado, navega DIRECTO al producto. Si no, abre el modal.
    const form = input.form || input.closest('form');
    if (form && !form.dataset.fbFormAttached) {
      form.dataset.fbFormAttached = '1';
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const q = input.value.trim();
        if (!q) { setAnchorAndOpen(); return; }
        anchorInput = input;
        try {
          const data = await meiliSearch({ q, limit: 2 });
          const hits  = data.hits || [];
          const total = data.estimatedTotalHits ?? hits.length;
          if (hits.length >= 1) {
            const h = hits[0];
            const qLow = q.toLowerCase();
            const exact = (h.ean && h.ean.toLowerCase() === qLow) ||
                          (h.mpn && h.mpn.toLowerCase() === qLow);
            // Casos en los que llevamos al usuario al producto directamente:
            //  - exact-match contra EAN o MPN (típico código de barras)
            //  - 1 único resultado total (sin ambigüedad)
            if (exact || total === 1) {
              window.location.href = h.link;
              return;
            }
          }
        } catch (_) { /* si falla la red, simplemente abrimos el modal */ }
        openModal(q);
      });
    }
  }

  // Reposicionar el modal si cambia el tamaño de la ventana o se hace scroll
  window.addEventListener('resize', () => positionModal());
  window.addEventListener('scroll', () => {
    if (modalEl && modalEl.classList.contains('open')) positionModal();
  }, { passive: true });

  // ---------- Sync entre inputs (modal y los del header) ----------
  function syncAllInputs(except) {
    const v = except ? except.value : '';
    $$('[data-fb-search="true"]').forEach(i => { if (i !== except) i.value = v; });
  }

  function init() {
    injectStyles();
    $$('[data-fb-search="true"]').forEach(attach);
    const obs = new MutationObserver(() => $$('[data-fb-search="true"]').forEach(attach));
    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
