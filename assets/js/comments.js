(() => {
  const panel = document.querySelector('[data-comments]');
  if (!panel) return;
  const form = panel.querySelector('[data-comment-form]');
  const track = panel.querySelector('[data-comment-track]');
  const list = panel.querySelector('[data-comment-list]');
  const status = panel.querySelector('[data-comment-status]');
  const audio = document.querySelector('#mainPlayer');
  const time = seconds => Math.floor(seconds / 60) + ':' + String(Math.floor(seconds % 60)).padStart(2, '0');
  const translate = key => window.msT(key, key);
  let revision = 0;
  const parameters = () => ({scope: panel.dataset.scope, token: panel.dataset.token, track_id: track.value});
  function render(data) {
    list.replaceChildren();
    if (!data.comments.length) {
      const empty = document.createElement('p');empty.className = 'comment-empty';empty.textContent = translate('comments.empty');list.append(empty);
    }
    for (const comment of data.comments) {
      const row = document.createElement('article');row.className = 'timestamp-comment';
      const header = document.createElement('div');header.className = 'comment-heading';
      const seek = document.createElement('button');seek.type = 'button';seek.className = 'comment-timestamp';seek.textContent = '▶ ' + time(Number(comment.position_seconds));
      seek.title = translate('comments.jump');
      seek.setAttribute('aria-label', translate('comments.jump') + ' ' + time(Number(comment.position_seconds)));
      const trackId = Number(track.value);
      seek.addEventListener('click', () => document.dispatchEvent(new CustomEvent('musicshare:seek', {detail:{trackId, seconds:Number(comment.position_seconds)}})));
      const author = document.createElement('strong');author.textContent = comment.author;
      const date = document.createElement('span');date.className = 'comment-date';
      const created = new Date(String(comment.created_at).replace(' ', 'T'));
      date.textContent = Number.isNaN(created.getTime()) ? comment.created_at : new Intl.DateTimeFormat(document.documentElement.lang || 'de', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'}).format(created);
      date.title = comment.created_at;
      header.append(seek, author, date);
      if (data.can_moderate) {
        const remove = document.createElement('button');remove.type = 'button';remove.className = 'comment-delete';remove.setAttribute('aria-label', translate('comments.delete'));remove.title = translate('comments.delete');
        const icon = document.createElement('i');icon.className = 'bi bi-trash3';icon.setAttribute('aria-hidden','true');remove.append(icon);
        remove.addEventListener('click', async () => {
          remove.disabled = true;
          try { await send({action:'delete', id:comment.id}, trackId); await load(); }
          catch(error) {status.textContent = error.message;remove.disabled = false;}
        });header.append(remove);
      }
      const body = document.createElement('p');body.className = 'comment-body';body.textContent = comment.body;
      row.append(header, body);list.append(row);
    }
  }
  async function send(fields, trackId = track.value) {
    const body = new URLSearchParams({...parameters(), track_id:trackId, csrf:form.elements.csrf.value, ...fields});
    const response = await fetch(panel.dataset.endpoint, {method:'POST', headers:{Accept:'application/json'}, body});
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || translate('comments.failed'));
    return data;
  }
  async function load() {
    const request = ++revision;
    const url = new URL(panel.dataset.endpoint, location.href);
    Object.entries(parameters()).forEach(([key,value]) => url.searchParams.set(key,value));
    try {
      const response = await fetch(url, {headers:{Accept:'application/json'}});
      const data = await response.json();
      if (request !== revision) return;
      if (!response.ok || !data.ok) throw new Error(data.message || translate('comments.failed'));
      render(data);status.textContent = '';return true;
    } catch(error) {if (request === revision) {list.replaceChildren();status.textContent = error.message;}return false;}
  }
  document.querySelectorAll('[data-comment-open]').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.closest('[data-row]')?.dataset.trackId;
      if (![...track.options].some(option => option.value === id)) return;
      track.value = id;
      const active = button.closest('[data-row]').querySelector('[data-play]');
      form.elements.timestamp.value = time(active?.dataset.src === audio?.src ? audio.currentTime || 0 : 0);
      load();
      panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
      form.elements.body.focus({preventScroll: true});
    });
  });
  track.addEventListener('change', load);
  panel.querySelector('[data-comment-now]').addEventListener('click', () => {
    const active = [...document.querySelectorAll('[data-play]')].find(button => button.dataset.src === audio?.src);
    if (!active) {status.textContent = translate('comments.start_track');return;}
    track.value = active.closest('[data-row]').dataset.trackId;
    form.elements.timestamp.value = time(audio.currentTime || 0);
    load();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const match = /^(\d+):([0-5]\d)$/.exec(form.elements.timestamp.value.trim());
    if (!match) {status.textContent = translate('comments.invalid');return;}
    const button = form.querySelector('[type="submit"]');button.disabled = true;
    try {
      await send({author:form.elements.author.value, body:form.elements.body.value, position_seconds:Number(match[1])*60+Number(match[2])});
      form.elements.body.value = '';if(await load())status.textContent = translate('comments.saved');
    } catch(error) {status.textContent = error.message;}
    finally {button.disabled = false;}
  });
  load();
})();
