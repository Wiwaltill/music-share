(() => {
  const form = document.querySelector('[data-live-search]');
  if (!form) return;
  const input = form.querySelector('input[name="q"]');
  const panel = form.querySelector('#albumSearchResults');
  const status = form.querySelector('[data-search-status]');
  let timer;
  let controller;
  let revision = 0;
  let composing = false;
  const translate = key => window.msT(key, key);
  const hide = () => {
    panel.hidden = true;
    input.setAttribute('aria-expanded', 'false');
  };
  const cancel = () => {
    clearTimeout(timer);
    controller?.abort();
    revision++;
  };
  function message(key) {
    const text = translate(key);
    const element = document.createElement('p');
    element.className = 'album-search-message';
    element.textContent = text;
    panel.replaceChildren(element);
    status.textContent = text;
  }
  async function search(currentRevision) {
    const query = input.value.trim();
    if (!query) return;
    controller = new AbortController();
    const url = new URL(form.dataset.liveSearch, location.href);
    url.searchParams.set('q', query);
    panel.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    message('search.loading');
    try {
      const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}});
      if (!response.ok) throw new Error('Search failed');
      const data = await response.json();
      if (currentRevision !== revision) return;
      if (!Array.isArray(data.albums)) throw new Error('Invalid search response');
      panel.replaceChildren();
      if (!data.albums.length) message('search.empty');
      for (const album of data.albums) {
        const link = document.createElement('a');
        link.className = 'album-search-result';
        link.href = album.url;
        if (album.cover) {
          const cover = document.createElement('img');
          cover.src = album.cover;
          cover.alt = '';
          link.append(cover);
        } else {
          const icon = document.createElement('span');
          icon.className = 'album-search-cover';
          icon.textContent = '♪';
          link.append(icon);
        }
        const text = document.createElement('span');
        text.className = 'album-search-copy';
        const title = document.createElement('strong');
        title.textContent = album.title;
        const artist = document.createElement('small');
        artist.textContent = album.artist;
        text.append(title, artist);
        link.append(text);
        panel.append(link);
      }
      const all = document.createElement('a');
      const destination = new URL(form.action);
      destination.searchParams.set('q', query);
      all.href = destination.href;
      all.className = 'album-search-all';
      all.textContent = translate('search.all');
      panel.append(all);
      status.textContent = translate('search.count').replace('{count}', data.albums.length);
    } catch (error) {
      if (error.name !== 'AbortError' && currentRevision === revision) message('search.failed');
    }
  }
  function schedule() {
    cancel();
    hide();
    status.textContent = '';
    if (!composing && input.value.trim()) {
      const currentRevision = revision;
      timer = setTimeout(() => search(currentRevision), 250);
    }
  }
  input.addEventListener('input', schedule);
  input.addEventListener('focus', schedule);
  input.addEventListener('compositionstart', () => { composing = true; cancel(); hide(); });
  input.addEventListener('compositionend', () => { composing = false; schedule(); });
  form.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      cancel(); hide(); input.focus();
      // Focusing the field can schedule a request; keep Escape closed.
      cancel();
      event.preventDefault();
      return;
    }
    if (panel.hidden || !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
    const links = [...panel.querySelectorAll('a')];
    if (!links.length) return;
    const index = links.indexOf(document.activeElement);
    const next = event.key === 'ArrowDown' ? (index + 1) % links.length : (index < 0 ? links.length - 1 : index - 1);
    if (next < 0) input.focus(); else links[next].focus();
    event.preventDefault();
  });
  form.addEventListener('focusout', event => {
    if (!form.contains(event.relatedTarget)) { cancel(); hide(); }
  });
  document.addEventListener('pointerdown', event => {
    if (!form.contains(event.target)) { cancel(); hide(); }
  });
  form.addEventListener('submit', () => { cancel(); hide(); });
})();
