const msT=window.msT||((key,fallback)=>fallback??key);
(() => {
  'use strict';
  const key = 'musicshare-theme';
  const allowed = ['auto', 'light', 'dark'];
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  let mode = allowed.includes(localStorage.getItem(key)) ? localStorage.getItem(key) : 'auto';

  function apply(next) {
    mode = allowed.includes(next) ? next : 'auto';
    const actual = mode === 'auto' ? (media.matches ? 'dark' : 'light') : mode;
    document.documentElement.setAttribute('data-bs-theme', actual);
    document.documentElement.dataset.themePreference = mode;
    localStorage.setItem(key, mode);
    document.querySelectorAll('.theme-option').forEach((button) => {
      const active = button.dataset.theme === mode;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      const check = button.querySelector('.theme-check');
      if (check) check.innerHTML = active ? '<i class="bi bi-check2"></i>' : '';
    });
  }

  function init() {
    apply(mode);
    document.querySelectorAll('.theme-option').forEach((button) => {
      button.addEventListener('click', () => apply(button.dataset.theme));
    });
    const refreshAuto = () => { if (mode === 'auto') apply(mode); };
    if (typeof media.addEventListener === 'function') media.addEventListener('change', refreshAuto);
    else if (typeof media.addListener === 'function') media.addListener(refreshAuto);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
