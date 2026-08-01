(() => {
  const cfg = window.albumUploadConfig;
  if (!cfg) return;

  const input = document.querySelector('#trackFiles');
  const choose = document.querySelector('#chooseTracks');
  const drop = document.querySelector('#uploadDropzone');
  const queue = document.querySelector('#uploadQueue');
  const boards = document.querySelector('#discBoards');

  choose?.addEventListener('click', () => input.click());
  input?.addEventListener('change', () => prepare([...input.files]));
  ['dragenter', 'dragover'].forEach(name => drop?.addEventListener(name, event => {
    event.preventDefault();
    drop.classList.add('is-dragover');
  }));
  ['dragleave', 'drop'].forEach(name => drop?.addEventListener(name, event => {
    event.preventDefault();
    drop.classList.remove('is-dragover');
  }));
  drop?.addEventListener('drop', event => prepare([...event.dataTransfer.files]));

  async function prepare(files) {
    const audioFiles = files.filter(file => file.type.startsWith('audio/') || /\.(mp3|wav|flac|m4a|ogg)$/i.test(file.name));
    const tagged = await Promise.all(audioFiles.map(async (file, index) => ({
      file,
      index,
      tags: await tagData(file),
    })));

    tagged.sort((a, b) => {
      const discA = positiveNumber(a.tags.disc) || Number.MAX_SAFE_INTEGER;
      const discB = positiveNumber(b.tags.disc) || Number.MAX_SAFE_INTEGER;
      if (discA !== discB) return discA - discB;
      const trackA = positiveNumber(a.tags.track) || Number.MAX_SAFE_INTEGER;
      const trackB = positiveNumber(b.tags.track) || Number.MAX_SAFE_INTEGER;
      if (trackA !== trackB) return trackA - trackB;
      return a.index - b.index;
    });

    // Sequential uploads keep DISCNUMBER/TRACK order deterministic.
    for (const item of tagged) {
      await makeItem(item.file, item.tags);
    }
    if (input) input.value = '';
  }

  function tagData(file) {
    return new Promise(resolve => {
      const finishWithFallback = async (libraryTags = {}) => {
        let fallbackTags = {};
        try { fallbackTags = await readId3Data(file); } catch {}
        const filenameTags = readFilenameOrder(file.name);
        resolve({
          title: cleanText(libraryTags.title) || cleanText(fallbackTags.title),
          track: positiveNumber(libraryTags.track) || positiveNumber(fallbackTags.track) || filenameTags.track,
          disc: positiveNumber(libraryTags.disc) || positiveNumber(fallbackTags.disc) || filenameTags.disc,
        });
      };

      if (!window.jsmediatags || typeof window.jsmediatags.read !== 'function') {
        finishWithFallback();
        return;
      }

      window.jsmediatags.read(file, {
        onSuccess: result => {
          const tags = result && result.tags ? result.tags : {};
          const raw = result && result.tags ? result.tags : {};
          finishWithFallback({
            title: frameText(tags.title ?? raw.TIT2 ?? raw.TT2),
            track: frameText(tags.track ?? tags.trackNumber ?? tags.TRACK ?? raw.TRCK ?? raw.TRK),
            disc: frameText(tags.disk ?? tags.disc ?? tags.discNumber ?? tags.DISCNUMBER ?? raw.TPOS ?? raw.TPA),
          });
        },
        onError: () => finishWithFallback(),
      });
    });
  }

  function frameText(value) {
    if (typeof value === 'string' || typeof value === 'number') return value;
    if (!value || typeof value !== 'object') return '';
    if (typeof value.data === 'string' || typeof value.data === 'number') return value.data;
    if (Array.isArray(value.data) && value.data.length) return value.data[0];
    if (typeof value.text === 'string' || typeof value.text === 'number') return value.text;
    if (Array.isArray(value.text) && value.text.length) return value.text[0];
    return '';
  }

  async function readId3Data(file) {
    const result = {};
    const head = new Uint8Array(await file.slice(0, Math.min(file.size, 1024 * 1024)).arrayBuffer());
    if (head.length >= 10 && ascii(head, 0, 3) === 'ID3') {
      const version = head[3];
      const tagSize = synchsafe(head, 6);
      const end = Math.min(head.length, 10 + tagSize);
      let pos = 10;
      while (pos + (version === 2 ? 6 : 10) <= end) {
        const id = ascii(head, pos, version === 2 ? 3 : 4);
        if (!id.replace(/\0/g, '')) break;
        const size = version === 2 ? be24(head, pos + 3) : (version === 4 ? synchsafe(head, pos + 4) : be32(head, pos + 4));
        const headerSize = version === 2 ? 6 : 10;
        if (size <= 0 || pos + headerSize + size > end) break;
        const value = decodeText(head.slice(pos + headerSize, pos + headerSize + size));
        if (id === 'TIT2' || id === 'TT2') result.title = value;
        if (id === 'TRCK' || id === 'TRK') result.track = positiveNumber(value);
        if (id === 'TPOS' || id === 'TPA') result.disc = positiveNumber(value);
        pos += headerSize + size;
      }
    }
    if (!result.title && file.size >= 128) {
      const tail = new Uint8Array(await file.slice(file.size - 128).arrayBuffer());
      if (ascii(tail, 0, 3) === 'TAG') {
        result.title = new TextDecoder('windows-1252').decode(tail.slice(3, 33)).replace(/\0+$/, '').trim();
        // ID3v1.1 stores track number in the final byte when byte 125 is zero.
        if (!result.track && tail[125] === 0 && tail[126] > 0) result.track = tail[126];
      }
    }
    return result;
  }

  function decodeText(bytes) {
    if (!bytes.length) return '';
    const enc = bytes[0];
    const data = bytes.slice(1);
    let text = '';
    try {
      if (enc === 0) text = new TextDecoder('windows-1252').decode(data);
      else if (enc === 3) text = new TextDecoder('utf-8').decode(data);
      else if (enc === 1) {
        if (data[0] === 0xFE && data[1] === 0xFF) text = new TextDecoder('utf-16be').decode(data.slice(2));
        else text = new TextDecoder('utf-16le').decode(data[0] === 0xFF && data[1] === 0xFE ? data.slice(2) : data);
      } else if (enc === 2) text = new TextDecoder('utf-16be').decode(data);
    } catch {
      return '';
    }
    return text.replace(/\0/g, '').trim();
  }

  function cleanText(value) {
    return typeof value === 'string' ? value.replace(/\0/g, '').trim() : '';
  }

  function positiveNumber(value) {
    if (typeof value === 'number') return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
    if (typeof value !== 'string') return 0;
    const match = value.trim().match(/^(\d+)/);
    return match ? Math.max(0, parseInt(match[1], 10)) : 0;
  }

  function readFilenameOrder(filename) {
    const base = String(filename || '').replace(/\.[^.]+$/, '').trim();
    const match = base.match(/^(\d{1,2})\s*[-_.]\s*(\d{1,3})(?:\s*[-_. ]\s*|$)/);
    return match ? { disc: parseInt(match[1], 10), track: parseInt(match[2], 10) } : { disc: 0, track: 0 };
  }

  function ascii(array, offset, length) { return String.fromCharCode(...array.slice(offset, offset + length)); }
  function synchsafe(array, offset) { return ((array[offset] & 127) << 21) | ((array[offset + 1] & 127) << 14) | ((array[offset + 2] & 127) << 7) | (array[offset + 3] & 127); }
  function be24(array, offset) { return (array[offset] << 16) | (array[offset + 1] << 8) | array[offset + 2]; }
  function be32(array, offset) { return ((array[offset] << 24) >>> 0) + (array[offset + 1] << 16) + (array[offset + 2] << 8) + array[offset + 3]; }

  async function makeItem(file, tags) {
    const title = tags.title || file.name.replace(/\.[^.]+$/, '');
    const disc = positiveNumber(tags.disc) || 1;
    const track = positiveNumber(tags.track);
    const el = document.createElement('div');
    el.className = 'border rounded-3 p-3';
    el.innerHTML = '<div class="d-flex justify-content-between gap-3"><div class="min-w-0"><div class="fw-semibold text-truncate"></div><div class="small text-body-secondary"></div></div><span class="badge text-bg-secondary status">Bereit</span></div><div class="mt-2"><label class="form-label small">Titel</label><input class="form-control form-control-sm title"></div><div class="small text-body-secondary mt-2 tag-order"></div><div class="progress mt-2" style="height:7px"><div class="progress-bar" style="width:0%"></div></div>';
    el.querySelector('.fw-semibold').textContent = file.name;
    el.querySelector('.small').textContent = format(file.size);
    el.querySelector('.title').value = title;
    el.querySelector('.tag-order').textContent = track ? `MP3-Reihenfolge: CD ${disc}, Titel ${track}` : `CD ${disc} · keine TRACK-Nummer erkannt`;
    queue.append(el);
    await upload(file, el, disc, track);
  }

  function upload(file, el, disc, track) {
    return new Promise(resolve => {
      const fd = new FormData();
      fd.append('csrf', cfg.csrf);
      fd.append('album_id', cfg.albumId);
      fd.append('file', file);
      fd.append('title', el.querySelector('.title').value);
      fd.append('disc_no', String(disc));
      fd.append('track_no', String(track));
      const xhr = new XMLHttpRequest();
      xhr.open('POST', cfg.uploadUrl);
      xhr.upload.onprogress = event => {
        if (event.lengthComputable) el.querySelector('.progress-bar').style.width = `${Math.round(event.loaded / event.total * 100)}%`;
      };
      xhr.onload = () => {
        let response = {};
        try { response = JSON.parse(xhr.responseText); } catch {}
        if (xhr.status < 300 && response.ok) {
          el.querySelector('.status').className = 'badge text-bg-success status';
          el.querySelector('.status').textContent = 'Fertig';
          addTrack(response);
          setTimeout(() => el.remove(), 900);
        } else {
          el.querySelector('.status').className = 'badge text-bg-danger status';
          el.querySelector('.status').textContent = response.message || 'Fehler';
        }
        resolve();
      };
      xhr.onerror = () => {
        el.querySelector('.status').className = 'badge text-bg-danger status';
        el.querySelector('.status').textContent = 'Netzwerkfehler';
        resolve();
      };
      xhr.send(fd);
    });
  }

  function firstList() { return boards.querySelector('.disc-track-list'); }

  function createDisc(number) {
    const section = document.createElement('section');
    section.className = 'disc-board border rounded-3 p-3';
    section.dataset.disc = number;
    section.innerHTML = `<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">CD <span class="disc-number">${number}</span></h3>${number > 1 ? '<button class="btn btn-sm btn-outline-danger remove-disc" type="button">CD entfernen</button>' : ''}</div><div class="disc-track-list vstack gap-2 min-disc-height"></div>`;
    boards.append(section);
    return section;
  }

  function ensureDisc(number) {
    let board = boards.querySelector(`.disc-board[data-disc="${number}"]`);
    while (!board && boards.querySelectorAll('.disc-board').length < number) {
      createDisc(boards.querySelectorAll('.disc-board').length + 1);
      board = boards.querySelector(`.disc-board[data-disc="${number}"]`);
    }
    return board || boards.querySelector('.disc-board');
  }

  function addTrack(track) {
    const row = document.createElement('div');
    row.className = 'track-admin-row border rounded-3 p-3 bg-body';
    row.draggable = true;
    row.dataset.id = track.id;
    row.innerHTML = '<div class="d-flex align-items-center gap-3"><div class="drag-handle">☰</div><div class="track-auto-no badge text-bg-secondary"></div><input class="form-control form-control-sm track-title"><button class="btn btn-sm btn-outline-danger track-delete" type="button">Löschen</button></div>';
    row.querySelector('.track-title').value = track.title;
    const list = ensureDisc(Math.max(1, Number(track.disc_no) || 1)).querySelector('.disc-track-list');
    const position = Math.max(1, Number(track.track_no) || list.children.length + 1);
    const before = list.querySelector(`.track-admin-row:nth-child(${position})`);
    before ? list.insertBefore(row, before) : list.append(row);
    bindRow(row);
    renumber();
  }

  function bindRow(row) {
    row.querySelector('.track-delete')?.addEventListener('click', async () => {
      if (!confirm('Titel wirklich löschen?')) return;
      const fd = new FormData();
      fd.append('csrf', cfg.csrf);
      fd.append('id', row.dataset.id);
      const response = await fetch(cfg.deleteUrl, { method: 'POST', body: fd });
      if (response.ok) { row.remove(); renumber(); }
    });
    row.addEventListener('dragstart', () => row.classList.add('dragging'));
    row.addEventListener('dragend', () => { row.classList.remove('dragging'); renumber(); });
  }

  document.querySelectorAll('.track-admin-row').forEach(bindRow);
  boards?.addEventListener('dragover', event => {
    event.preventDefault();
    const list = event.target.closest('.disc-track-list');
    const dragging = boards.querySelector('.dragging');
    if (!list || !dragging) return;
    const rows = [...list.querySelectorAll('.track-admin-row:not(.dragging)')];
    const after = rows.find(row => event.clientY <= row.getBoundingClientRect().top + row.offsetHeight / 2);
    after ? list.insertBefore(dragging, after) : list.append(dragging);
  });

  document.querySelector('#addDisc')?.addEventListener('click', () => createDisc(boards.querySelectorAll('.disc-board').length + 1));

  boards?.addEventListener('click', event => {
    const btn = event.target.closest('.remove-disc');
    if (!btn) return;
    const board = btn.closest('.disc-board');
    const rows = [...board.querySelectorAll('.track-admin-row')];
    if (rows.length && !confirm('Die Titel werden auf CD 1 verschoben. Fortfahren?')) return;
    rows.forEach(row => firstList().append(row));
    board.remove();
    normalizeDiscs();
    renumber();
  });

  function normalizeDiscs() {
    [...boards.querySelectorAll('.disc-board')].forEach((board, index) => {
      board.dataset.disc = index + 1;
      board.querySelector('.disc-number').textContent = index + 1;
    });
  }

  function renumber() {
    boards.querySelectorAll('.disc-track-list').forEach(list => [...list.querySelectorAll('.track-admin-row')].forEach((row, index) => {
      row.querySelector('.track-auto-no').textContent = index + 1;
    }));
  }
  renumber();

  document.querySelector('#saveTrackOrder')?.addEventListener('click', async event => {
    const btn = event.currentTarget;
    const items = [];
    normalizeDiscs();
    boards.querySelectorAll('.disc-board').forEach((board, discIndex) => board.querySelectorAll('.track-admin-row').forEach((row, index) => items.push({
      id: row.dataset.id,
      title: row.querySelector('.track-title').value,
      disc_no: discIndex + 1,
      track_no: index + 1,
    })));
    const fd = new FormData();
    fd.append('csrf', cfg.csrf);
    fd.append('album_id', cfg.albumId);
    fd.append('items', JSON.stringify(items));
    btn.disabled = true;
    const response = await fetch(cfg.updateUrl, { method: 'POST', body: fd });
    btn.disabled = false;
    btn.textContent = response.ok ? 'Gespeichert' : 'Fehler';
    setTimeout(() => btn.textContent = 'Reihenfolge speichern', 1400);
  });

  function format(bytes) {
    return bytes > 1073741824 ? `${(bytes / 1073741824).toFixed(1)} GB` : `${(bytes / 1048576).toFixed(1)} MB`;
  }
})();
