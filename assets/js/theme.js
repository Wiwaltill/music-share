(() => {
  'use strict';
  const storageKey = 'musicshare-theme';
  const modes = ['auto', 'light', 'dark'];
  const labels = { auto: 'Automatisch', light: 'Hell', dark: 'Dunkel' };
  const icons = { auto: '◐', light: '☀', dark: '☾' };
  const media = window.matchMedia('(prefers-color-scheme: dark)');

  function currentMode() {
    const saved = localStorage.getItem(storageKey);
    return modes.includes(saved) ? saved : 'auto';
  }

  function apply(mode) {
    const actual = mode === 'auto' ? (media.matches ? 'dark' : 'light') : mode;
    document.documentElement.setAttribute('data-bs-theme', actual);
    document.documentElement.dataset.themePreference = mode;
    localStorage.setItem(storageKey, mode);
    const button = document.getElementById('themeToggle');
    if (button) {
      button.textContent = icons[mode];
      button.title = 'Darstellung: ' + labels[mode];
      button.setAttribute('aria-label', 'Darstellung: ' + labels[mode]);
    }
  }

  function init() {
    let mode = currentMode();
    apply(mode);
    const button = document.getElementById('themeToggle');
    if (button) {
      button.addEventListener('click', () => {
        mode = modes[(modes.indexOf(mode) + 1) % modes.length];
        apply(mode);
      });
    }
    const onChange = () => { if (mode === 'auto') apply(mode); };
    if (typeof media.addEventListener === 'function') media.addEventListener('change', onChange);
    else if (typeof media.addListener === 'function') media.addListener(onChange);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
