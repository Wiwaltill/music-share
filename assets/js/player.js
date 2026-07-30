document.addEventListener('DOMContentLoaded',()=>{
  const audio=document.querySelector('#mainPlayer'),box=document.querySelector('#floatingPlayer'),label=document.querySelector('#nowPlaying');
  if(!audio)return;
  const controls=['play-large','play','progress','current-time','mute','volume'];
  const plyr=window.Plyr?new Plyr(audio,{controls,keyboard:{focused:true,global:false},tooltips:{controls:true,seek:true}}):null;
  const buttons=[...document.querySelectorAll('[data-play]')];let current=-1;
  function updateTrackButtons(){
    buttons.forEach((button,index)=>{
      const isPlaying=index===current&&!audio.paused&&!audio.ended;
      button.classList.toggle('is-playing',isPlaying);
      button.textContent=isPlaying?'❚❚':'▶';
      button.setAttribute('aria-label',isPlaying?'Titel pausieren':'Titel abspielen');
      button.setAttribute('aria-pressed',isPlaying?'true':'false');
    });
  }
  function start(index){const btn=buttons[index];if(!btn)return;current=index;audio.src=btn.dataset.src;label.textContent=btn.dataset.title||'Wiedergabe';box.hidden=false;requestAnimationFrame(()=>box.classList.add('show'));updateTrackButtons();(plyr?plyr.play():audio.play()).catch?.(()=>{updateTrackButtons()});}
  buttons.forEach((btn,i)=>btn.addEventListener('click',()=>{if(current===i&&!audio.paused){plyr?plyr.pause():audio.pause()}else if(current===i&&audio.src){(plyr?plyr.play():audio.play()).catch?.(()=>{})}else start(i)}));
  audio.addEventListener('play',updateTrackButtons);
  audio.addEventListener('pause',updateTrackButtons);
  audio.addEventListener('ended',()=>{updateTrackButtons();if(current+1<buttons.length)start(current+1)});
  document.querySelector('#closePlayer')?.addEventListener('click',()=>{plyr?plyr.pause():audio.pause();updateTrackButtons();box.classList.remove('show');setTimeout(()=>box.hidden=true,220)});
  const img=document.querySelector('#coverImage');if(img){const apply=()=>{try{const c=document.createElement('canvas');c.width=c.height=40;const x=c.getContext('2d');x.drawImage(img,0,0,40,40);const d=x.getImageData(0,0,40,40).data;let r=0,g=0,b=0,n=0;for(let i=0;i<d.length;i+=16){r+=d[i];g+=d[i+1];b+=d[i+2];n++}document.body.classList.toggle('cover-light',(.299*r+.587*g+.114*b)/n>155)}catch{}};img.complete?apply():img.addEventListener('load',apply)}
});
