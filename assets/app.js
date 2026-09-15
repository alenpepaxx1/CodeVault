/**
 * CodeVault client interactions.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const loader = document.getElementById('app-loader');
  const percentLabel = loader?.querySelector('[data-loader-percent]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let returning = false;
  try { returning = sessionStorage.getItem('codevault_loaded') === '1'; } catch (_) {}
  const duration = reducedMotion ? 80 : (returning ? 320 : 900);
  const started = performance.now();
  let finished = false;

  const paintProgress = (value) => {
    const safe = Math.max(0, Math.min(100, Math.round(value)));
    if (percentLabel) percentLabel.textContent = `${safe}%`;
    loader?.style.setProperty('--loader-progress', `${safe}%`);
  };
  const animateProgress = (now) => {
    if (finished) return;
    const elapsed = now - started;
    paintProgress(Math.min(93, (elapsed / duration) * 100));
    requestAnimationFrame(animateProgress);
  };
  requestAnimationFrame(animateProgress);

  const completeLoader = () => {
    if (finished) return;
    const remaining = Math.max(0, duration - (performance.now() - started));
    window.setTimeout(() => {
      finished = true;
      paintProgress(100);
      loader?.classList.add('is-complete');
      document.body.classList.remove('is-loading');
      try { sessionStorage.setItem('codevault_loaded', '1'); } catch (_) {}
      window.setTimeout(() => { if (loader) { loader.hidden = true; loader.setAttribute('aria-hidden', 'true'); } }, reducedMotion ? 80 : 650);
    }, remaining);
  };
  if (document.readyState === 'complete') completeLoader(); else window.addEventListener('load', completeLoader, {once: true});

  const menu = document.querySelector('.menu');
  menu?.addEventListener('click', () => {
    const nav = document.querySelector('.nav nav');
    const open = nav?.classList.toggle('open') ?? false;
    menu.setAttribute('aria-expanded', String(open));
  });

  const cards = [...document.querySelectorAll('.card[data-category]')];
  let activeCategory = 'all';
  const searchInput = document.querySelector('[data-catalog-search]');
  const sortSelect = document.querySelector('[data-catalog-sort]');
  const statusLabel = document.querySelector('[data-catalog-status]');
  const emptyState = document.querySelector('[data-catalog-empty]');
  const grid = document.querySelector('[data-catalog-grid]');
  const applyCatalog = () => {
    const query = (searchInput?.value || '').trim().toLowerCase();
    const sort = sortSelect?.value || 'newest';
    cards.sort((a, b) => {
      if (sort === 'low' || sort === 'high') {
        const delta = Number(a.dataset.price || 0) - Number(b.dataset.price || 0);
        return sort === 'low' ? delta : -delta;
      }
      return String(b.dataset.created || '').localeCompare(String(a.dataset.created || ''));
    }).forEach((card) => grid?.appendChild(card));
    let visible = 0;
    cards.forEach((card) => {
      const categoryMatch = activeCategory === 'all' || card.dataset.category === activeCategory;
      const searchMatch = !query || (card.dataset.search || '').includes(query);
      card.hidden = !(categoryMatch && searchMatch);
      if (!card.hidden) visible += 1;
    });
    if (statusLabel) statusLabel.textContent = `${visible} ${statusLabel.dataset.label || 'products available'}`;
    if (emptyState) emptyState.hidden = visible !== 0;
  };
  document.querySelectorAll('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelector('.filters .active')?.classList.remove('active');
      button.classList.add('active'); activeCategory = button.dataset.filter || 'all'; applyCatalog();
    });
  });
  searchInput?.addEventListener('input', applyCatalog);
  sortSelect?.addEventListener('change', applyCatalog);
  applyCatalog();

  const syncPayment = () => {
    const method = document.querySelector('[name="payment_method"]:checked')?.value;
    const reference = document.querySelector('.manual-ref');
    if (reference) reference.hidden = method !== 'manual';
  };
  document.querySelectorAll('[name="payment_method"]').forEach((radio) => radio.addEventListener('change', syncPayment));
  syncPayment();

  document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const language = document.documentElement.lang;
      const archive = {sq: 'Ta arkivojmë këtë produkt?', en: 'Archive this product?', de: 'Dieses Produkt archivieren?'};
      const messages = {sq: 'Ta pastrojmë shportën?', en: 'Clear your cart?', de: 'Warenkorb leeren?'};
      const message = form.dataset.confirm === 'Delete this product?' ? (archive[language] || archive.en) : form.dataset.confirm === 'clear-cart' ? (messages[language] || messages.en) : (form.dataset.confirm || 'Confirm?');
      if (!window.confirm(message)) event.preventDefault();
    });
  });
  document.querySelectorAll('[data-autosubmit]').forEach((select) => select.addEventListener('change', () => select.form?.requestSubmit()));
});
