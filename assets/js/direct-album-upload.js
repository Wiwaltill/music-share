(() => {
  function initDirectAlbumUpload() {
  const cfg = window.directAlbumUploadConfig;
  const modalEl = document.getElementById('directUploadModal');
  if (!cfg || !modalEl || typeof bootstrap === 'undefined') return false;
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const filesInput = document.getElementById('directAlbumFiles');
  const choose = document.getElementById('chooseDirectAlbum');
  const dropzone = document.getElementById('directAlbumDropzone');
  const start = document.getElementById('startDirectAlbum');
  const summary = document.getElementById('directAlbumSummary');
  const progress = document.getElementById('directAlbumProgress');
  const status = document.getElementById('directAlbumStatus');
  const titleInput = document.getElementById('directAlbumTitle');
  const artistInput = document.getElementById('directAlbumArtist');
  const albumArtistInput = document.getElementById('directAlbumAlbumArtist');
  const yearInput = document.getElementById('directAlbumYear');
  const genreInput = document.getElementById('directAlbumGenre');
  let entries = [], coverBlob = null, coverName = '';

  document.querySelector('[data-open-direct-upload]')?.addEventListener('click', () => modal.show());
  choose?.addEventListener('click', () => filesInput.click());
  filesInput?.addEventListener('change', () => inspect([...filesInput.files]));

  const inspectDroppedFiles = files => {
    const mp3Files = [...files].filter(file =>
      file.type === 'audio/mpeg' || file.name.toLowerCase().endsWith('.mp3')
    );
    if (!mp3Files.length) {
      status.textContent = 'Bitte nur MP3-Dateien auswählen oder ablegen.';
      return;
    }
    inspect(mp3Files);
  };

  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone?.addEventListener(eventName, event => {
      event.preventDefault();
      event.stopPropagation();
      dropzone.classList.add('is-dragover');
    });
  });
  ['dragleave', 'drop'].forEach(eventName => {
    dropzone?.addEventListener(eventName, event => {
      event.preventDefault();
      event.stopPropagation();
      dropzone.classList.remove('is-dragover');
    });
  });
  dropzone?.addEventListener('drop', event => inspectDroppedFiles(event.dataTransfer?.files || []));
  dropzone?.addEventListener('click', () => filesInput.click());
  dropzone?.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      filesInput.click();
    }
  });

  function firstNumber(value) { const m = String(value ?? '').match(/^\s*(\d+)/); return m ? parseInt(m[1],10) : 0; }
  function mostCommon(values) {
    const map = new Map();
    values.filter(Boolean).forEach(v => map.set(v, (map.get(v)||0)+1));
    return [...map.entries()].sort((a,b)=>b[1]-a[1])[0]?.[0] || '';
  }
  function readTags(file) {
    return new Promise(resolve => {
      if (!window.jsmediatags) return resolve({});
      window.jsmediatags.read(file, {
        onSuccess: result => resolve(result.tags || {}),
        onError: () => resolve({})
      });
    });
  }
  function duration(file) {
    return new Promise(resolve => {
      const audio=document.createElement('audio'), url=URL.createObjectURL(file);
      const done=v=>{URL.revokeObjectURL(url);audio.remove();resolve(Number.isFinite(v)?Math.round(v):0)};
      audio.preload='metadata';audio.onloadedmetadata=()=>done(audio.duration);audio.onerror=()=>done(0);audio.src=url;
    });
  }
  function pictureToBlob(picture) {
    if (!picture?.data?.length) return null;
    return new Blob([new Uint8Array(picture.data)], {type: picture.format || 'image/jpeg'});
  }
  async function inspect(files) {
    entries=[]; coverBlob=null; coverName=''; start.disabled=true; status.textContent='MP3-Tags werden gelesen …'; summary.innerHTML='';
    for (const file of files) {
      const tags=await readTags(file); const d=await duration(file);
      const disc=firstNumber(tags.disc || tags.TPOS || 1) || 1;
      const track=firstNumber(tags.track || tags.TRCK || 0);
      entries.push({file,tags,duration:d,disc,track,title:tags.title || file.name.replace(/\.[^.]+$/,'')});
      if (!coverBlob && tags.picture) { coverBlob=pictureToBlob(tags.picture); coverName='embedded-cover.' + ((tags.picture.format||'image/jpeg').includes('png')?'png':'jpg'); }
    }
    entries.sort((a,b)=>a.disc-b.disc || (a.track||9999)-(b.track||9999) || a.file.name.localeCompare(b.file.name));
    titleInput.value=mostCommon(entries.map(e=>e.tags.album));
    artistInput.value=mostCommon(entries.map(e=>e.tags.artist));
    albumArtistInput.value=mostCommon(entries.map(e=>e.tags.albumartist || e.tags.albumArtist));
    yearInput.value=firstNumber(mostCommon(entries.map(e=>e.tags.year)));
    genreInput.value=mostCommon(entries.map(e=>e.tags.genre));
    summary.innerHTML=entries.map(e=>`<div class="d-flex justify-content-between border-bottom py-2 gap-3"><span class="text-truncate">${escapeHtml(e.file.name)}</span><span class="text-body-secondary text-nowrap">CD ${e.disc} · ${e.track ? 'Titel '+e.track : 'ohne TRACK'}</span></div>`).join('');
    status.textContent=`${entries.length} Dateien erkannt${coverBlob?' · eingebettetes Cover gefunden':''}.`;
    start.disabled=entries.length===0 || !titleInput.value.trim();
  }
  function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v);return d.innerHTML;}
  async function postForm(url, fd) { const r=await fetch(url,{method:'POST',body:fd}); const j=await r.json().catch(()=>({})); if(!r.ok||!j.ok) throw new Error(j.message||'Aktion fehlgeschlagen.'); return j; }
  function uploadTrack(entry, albumId) {
    return new Promise(resolve => {
      const fd=new FormData(); fd.append('csrf',cfg.csrf);fd.append('album_id',albumId);fd.append('file',entry.file);fd.append('title',entry.title);fd.append('disc_no',entry.disc);fd.append('track_no',entry.track);fd.append('duration',entry.duration);
      const xhr=new XMLHttpRequest();xhr.open('POST',cfg.trackUploadUrl);
      xhr.onload=()=>{let j={};try{j=JSON.parse(xhr.responseText)}catch{} resolve(xhr.status<300&&j.ok);};xhr.onerror=()=>resolve(false);xhr.send(fd);
    });
  }
  start?.addEventListener('click', async () => {
    if (!entries.length || !titleInput.value.trim()) return;
    start.disabled=true; choose.disabled=true; dropzone?.classList.add('is-disabled'); progress.style.width='0%'; status.textContent='Album wird angelegt …';
    try {
      const fd=new FormData();fd.append('csrf',cfg.csrf);fd.append('title',titleInput.value.trim());fd.append('artist',artistInput.value.trim());fd.append('album_artist',albumArtistInput.value.trim());fd.append('release_year',yearInput.value);fd.append('genre',genreInput.value.trim());
      const copyright=mostCommon(entries.map(e=>e.tags.copyright)); if(copyright) fd.append('copyright_text',copyright);
      if(coverBlob) fd.append('cover',coverBlob,coverName);
      const created=await postForm(cfg.createUrl,fd); let done=0, failed=0;
      for(const entry of entries){status.textContent=`Titel ${done+1} von ${entries.length} wird hochgeladen …`;if(!(await uploadTrack(entry,created.album_id)))failed++;done++;progress.style.width=`${Math.round(done/entries.length*100)}%`;}
      if(failed) status.textContent=`Upload abgeschlossen, ${failed} Datei(en) fehlgeschlagen.`; else status.textContent='Album vollständig angelegt.';
      setTimeout(()=>location.href=`album_edit.php?id=${created.album_id}`,700);
    } catch(e) {status.textContent=e.message||'Direktupload fehlgeschlagen.';start.disabled=false;choose.disabled=false;dropzone?.classList.remove('is-disabled');}
  });
  return true;
  }

  const startInit = () => {
    if (initDirectAlbumUpload()) return;
    let attempts = 0;
    const timer = window.setInterval(() => {
      attempts += 1;
      if (initDirectAlbumUpload() || attempts >= 40) window.clearInterval(timer);
    }, 100);
  };

  if (document.readyState === 'complete') startInit();
  else window.addEventListener('load', startInit, { once: true });
})();
