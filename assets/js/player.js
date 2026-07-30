document.addEventListener('DOMContentLoaded',()=>{
  const audio=document.querySelector('#mainPlayer'),box=document.querySelector('#floatingPlayer'),label=document.querySelector('#nowPlaying');
  if(!audio)return;
  const controls=['play-large','play','progress','current-time','mute','volume'];
  const plyr=window.Plyr?new Plyr(audio,{controls,keyboard:{focused:true,global:false},tooltips:{controls:true,seek:true}}):null;
  const buttons=[...document.querySelectorAll('[data-play]')];let current=-1;
  function start(index){const btn=buttons[index];if(!btn)return;buttons.forEach(b=>b.classList.remove('is-playing'));btn.classList.add('is-playing');current=index;audio.src=btn.dataset.src;label.textContent=btn.dataset.title||'Wiedergabe';box.hidden=false;requestAnimationFrame(()=>box.classList.add('show'));(plyr?plyr.play():audio.play()).catch?.(()=>{});}
  buttons.forEach((btn,i)=>btn.addEventListener('click',()=>{if(current===i&&!audio.paused){plyr?plyr.pause():audio.pause();btn.classList.remove('is-playing')}else start(i)}));
  audio.addEventListener('play',()=>{if(current>=0)buttons[current]?.classList.add('is-playing')});
  audio.addEventListener('pause',()=>buttons[current]?.classList.remove('is-playing'));
  audio.addEventListener('ended',()=>{buttons[current]?.classList.remove('is-playing');if(current+1<buttons.length)start(current+1)});
  document.querySelector('#closePlayer')?.addEventListener('click',()=>{plyr?plyr.pause():audio.pause();box.classList.remove('show');setTimeout(()=>box.hidden=true,220)});
  const img=document.querySelector('#coverImage');if(img){const apply=()=>{try{const c=document.createElement('canvas');c.width=c.height=40;const x=c.getContext('2d');x.drawImage(img,0,0,40,40);const d=x.getImageData(0,0,40,40).data;let r=0,g=0,b=0,n=0;for(let i=0;i<d.length;i+=16){r+=d[i];g+=d[i+1];b+=d[i+2];n++}document.body.classList.toggle('cover-light',(.299*r+.587*g+.114*b)/n>155)}catch{}};img.complete?apply():img.addEventListener('load',apply)}
});
