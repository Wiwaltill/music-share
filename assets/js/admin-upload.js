(() => {
  const cfg=window.albumUploadConfig;if(!cfg)return;
  const input=document.querySelector('#trackFiles'),choose=document.querySelector('#chooseTracks'),drop=document.querySelector('#uploadDropzone'),queue=document.querySelector('#uploadQueue'),boards=document.querySelector('#discBoards');
  choose?.addEventListener('click',()=>input.click()); input?.addEventListener('change',()=>prepare([...input.files]));
  ['dragenter','dragover'].forEach(n=>drop?.addEventListener(n,e=>{e.preventDefault();drop.classList.add('is-dragover')}));
  ['dragleave','drop'].forEach(n=>drop?.addEventListener(n,e=>{e.preventDefault();drop.classList.remove('is-dragover')}));drop?.addEventListener('drop',e=>prepare([...e.dataTransfer.files]));
  function prepare(files){files.filter(f=>f.type.startsWith('audio/')||/\.(mp3|wav|flac|m4a|ogg)$/i.test(f.name)).forEach(makeItem)}
  function tagData(file){
    return new Promise(resolve=>{
      const fallback=()=>readId3Title(file).then(title=>resolve(title?{title}:{})).catch(()=>resolve({}));
      if(!window.jsmediatags||typeof window.jsmediatags.read!=='function')return fallback();
      window.jsmediatags.read(file,{onSuccess:r=>{const tags=r&&r.tags?r.tags:{};if(tags.title)return resolve(tags);fallback()},onError:fallback});
    });
  }
  async function readId3Title(file){
    const head=new Uint8Array(await file.slice(0,Math.min(file.size,1024*1024)).arrayBuffer());
    if(head.length>=10&&ascii(head,0,3)==='ID3'){
      const version=head[3],tagSize=synchsafe(head,6),end=Math.min(head.length,10+tagSize);let pos=10;
      while(pos+(version===2?6:10)<=end){
        const id=ascii(head,pos,version===2?3:4);
        if(!id.replace(/\0/g,''))break;
        const size=version===2?be24(head,pos+3):(version===4?synchsafe(head,pos+4):be32(head,pos+4));
        const headerSize=version===2?6:10;if(size<=0||pos+headerSize+size>end)break;
        if(id==='TIT2'||id==='TT2')return decodeText(head.slice(pos+headerSize,pos+headerSize+size));
        pos+=headerSize+size;
      }
    }
    if(file.size>=128){
      const tail=new Uint8Array(await file.slice(file.size-128).arrayBuffer());
      if(ascii(tail,0,3)==='TAG')return new TextDecoder('windows-1252').decode(tail.slice(3,33)).replace(/\0+$/,'').trim();
    }
    return '';
  }
  function decodeText(bytes){
    if(!bytes.length)return '';const enc=bytes[0],data=bytes.slice(1);let text='';
    try{
      if(enc===0)text=new TextDecoder('windows-1252').decode(data);
      else if(enc===3)text=new TextDecoder('utf-8').decode(data);
      else if(enc===1){
        if(data[0]===0xFE&&data[1]===0xFF)text=new TextDecoder('utf-16be').decode(data.slice(2));
        else text=new TextDecoder('utf-16le').decode(data[0]===0xFF&&data[1]===0xFE?data.slice(2):data);
      }else if(enc===2)text=new TextDecoder('utf-16be').decode(data);
    }catch{return ''}
    return text.replace(/\0/g,'').trim();
  }
  function ascii(a,o,n){return String.fromCharCode(...a.slice(o,o+n))}
  function synchsafe(a,o){return ((a[o]&127)<<21)|((a[o+1]&127)<<14)|((a[o+2]&127)<<7)|(a[o+3]&127)}
  function be24(a,o){return (a[o]<<16)|(a[o+1]<<8)|a[o+2]}
  function be32(a,o){return ((a[o]<<24)>>>0)+(a[o+1]<<16)+(a[o+2]<<8)+a[o+3]}
  async function makeItem(file){const tags=await tagData(file),title=tags.title||file.name.replace(/\.[^.]+$/,'');const el=document.createElement('div');el.className='border rounded-3 p-3';el.innerHTML='<div class="d-flex justify-content-between gap-3"><div class="min-w-0"><div class="fw-semibold text-truncate"></div><div class="small text-body-secondary"></div></div><span class="badge text-bg-secondary status">Bereit</span></div><div class="mt-2"><label class="form-label small">Titel</label><input class="form-control form-control-sm title"></div><div class="progress mt-2" style="height:7px"><div class="progress-bar" style="width:0%"></div></div>';el.querySelector('.fw-semibold').textContent=file.name;el.querySelector('.small').textContent=format(file.size);el.querySelector('.title').value=title;queue.append(el);upload(file,el)}
  function upload(file,el){const fd=new FormData();fd.append('csrf',cfg.csrf);fd.append('album_id',cfg.albumId);fd.append('file',file);fd.append('title',el.querySelector('.title').value);const xhr=new XMLHttpRequest();xhr.open('POST',cfg.uploadUrl);xhr.upload.onprogress=e=>{if(e.lengthComputable)el.querySelector('.progress-bar').style.width=`${Math.round(e.loaded/e.total*100)}%`};xhr.onload=()=>{let r={};try{r=JSON.parse(xhr.responseText)}catch{}if(xhr.status<300&&r.ok){el.querySelector('.status').className='badge text-bg-success status';el.querySelector('.status').textContent='Fertig';addTrack(r);setTimeout(()=>el.remove(),900)}else{el.querySelector('.status').className='badge text-bg-danger status';el.querySelector('.status').textContent=r.message||'Fehler'}};xhr.onerror=()=>el.querySelector('.status').textContent='Netzwerkfehler';xhr.send(fd)}
  function firstList(){return boards.querySelector('.disc-track-list')}
  function addTrack(t){const row=document.createElement('div');row.className='track-admin-row border rounded-3 p-3 bg-body';row.draggable=true;row.dataset.id=t.id;row.innerHTML='<div class="d-flex align-items-center gap-3"><div class="drag-handle">☰</div><div class="track-auto-no badge text-bg-secondary"></div><input class="form-control form-control-sm track-title"><button class="btn btn-sm btn-outline-danger track-delete" type="button">Löschen</button></div>';row.querySelector('.track-title').value=t.title;firstList().append(row);bindRow(row);renumber()}
  function bindRow(row){row.querySelector('.track-delete')?.addEventListener('click',async()=>{if(!confirm('Titel wirklich löschen?'))return;const fd=new FormData();fd.append('csrf',cfg.csrf);fd.append('id',row.dataset.id);const r=await fetch(cfg.deleteUrl,{method:'POST',body:fd});if(r.ok){row.remove();renumber()}});row.addEventListener('dragstart',()=>row.classList.add('dragging'));row.addEventListener('dragend',()=>{row.classList.remove('dragging');renumber()})}
  document.querySelectorAll('.track-admin-row').forEach(bindRow);
  boards?.addEventListener('dragover',e=>{e.preventDefault();const list=e.target.closest('.disc-track-list');const dragging=boards.querySelector('.dragging');if(!list||!dragging)return;const rows=[...list.querySelectorAll('.track-admin-row:not(.dragging)')];const after=rows.find(x=>e.clientY<=x.getBoundingClientRect().top+x.offsetHeight/2);after?list.insertBefore(dragging,after):list.append(dragging)});
  document.querySelector('#addDisc')?.addEventListener('click',()=>{const n=boards.querySelectorAll('.disc-board').length+1;const section=document.createElement('section');section.className='disc-board border rounded-3 p-3';section.dataset.disc=n;section.innerHTML=`<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">CD <span class="disc-number">${n}</span></h3><button class="btn btn-sm btn-outline-danger remove-disc" type="button">CD entfernen</button></div><div class="disc-track-list vstack gap-2 min-disc-height"></div>`;boards.append(section)});
  boards?.addEventListener('click',e=>{const btn=e.target.closest('.remove-disc');if(!btn)return;const board=btn.closest('.disc-board'),rows=[...board.querySelectorAll('.track-admin-row')];if(rows.length&&!confirm('Die Titel werden auf CD 1 verschoben. Fortfahren?'))return;rows.forEach(r=>firstList().append(r));board.remove();normalizeDiscs();renumber()});
  function normalizeDiscs(){[...boards.querySelectorAll('.disc-board')].forEach((b,i)=>{b.dataset.disc=i+1;b.querySelector('.disc-number').textContent=i+1})}
  function renumber(){boards.querySelectorAll('.disc-track-list').forEach(list=>[...list.querySelectorAll('.track-admin-row')].forEach((r,i)=>r.querySelector('.track-auto-no').textContent=i+1))}renumber();
  document.querySelector('#saveTrackOrder')?.addEventListener('click',async e=>{const btn=e.currentTarget,items=[];normalizeDiscs();boards.querySelectorAll('.disc-board').forEach((b,di)=>b.querySelectorAll('.track-admin-row').forEach((r,i)=>items.push({id:r.dataset.id,title:r.querySelector('.track-title').value,disc_no:di+1,track_no:i+1})));const fd=new FormData();fd.append('csrf',cfg.csrf);fd.append('album_id',cfg.albumId);fd.append('items',JSON.stringify(items));btn.disabled=true;const res=await fetch(cfg.updateUrl,{method:'POST',body:fd});btn.disabled=false;btn.textContent=res.ok?'Gespeichert':'Fehler';setTimeout(()=>btn.textContent='Reihenfolge speichern',1400)});
  function format(n){return n>1073741824?(n/1073741824).toFixed(1)+' GB':(n/1048576).toFixed(1)+' MB'}
})();
