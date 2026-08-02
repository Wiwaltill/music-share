document.addEventListener('DOMContentLoaded',()=>{
  const audio=document.querySelector('#mainPlayer'),box=document.querySelector('#floatingPlayer'),label=document.querySelector('#nowPlaying');
  if(!audio)return;
  const controls=['play-large','play','progress','current-time','mute','volume'];
  const plyr=window.Plyr?new Plyr(audio,{controls,keyboard:{focused:true,global:false},tooltips:{controls:true,seek:true}}):null;
  const buttons=[...document.querySelectorAll('[data-play]')];
  let current=-1;
  let lastPositionUpdate=-1;

  const canPrevious=()=>current>0;
  const canNext=()=>current>=0&&current<buttons.length-1;

  function updateTrackButtons(){
    buttons.forEach((button,index)=>{
      const active=index===current&&!audio.paused&&!audio.ended;
      button.classList.toggle('is-playing',active);
      button.textContent=active?'❚❚':'▶';
      button.setAttribute('aria-label',active?'Titel pausieren':'Titel abspielen');
      button.setAttribute('aria-pressed',active?'true':'false');
    });
  }
  function updatePositionState(){
    if(!('mediaSession'in navigator)||!Number.isFinite(audio.duration)||audio.duration<=0)return;
    try{navigator.mediaSession.setPositionState({duration:audio.duration,playbackRate:audio.playbackRate||1,position:Math.min(Math.max(audio.currentTime||0,0),audio.duration)})}catch{}
  }
  function updateMediaSession(btn){
    if(!('mediaSession'in navigator)||!btn)return;
    const art=btn.dataset.cover?[{src:btn.dataset.cover,sizes:'512x512'}]:[];
    navigator.mediaSession.metadata=new MediaMetadata({title:btn.dataset.title||'',artist:btn.dataset.artist||'',album:document.title.split(' – ')[0],artwork:art});
    updatePositionState();
  }
  function start(index){
    index=Number(index);
    if(!Number.isInteger(index)||index<0||index>=buttons.length)return;
    const btn=buttons[index];
    current=index;
    lastPositionUpdate=-1;
    audio.src=btn.dataset.src;
    label.textContent=btn.dataset.title||'Wiedergabe';
    box.hidden=false;
    requestAnimationFrame(()=>box.classList.add('show'));
    updateTrackButtons();
    updateMediaSession(btn);
    const promise=plyr?plyr.play():audio.play();
    if(promise&&typeof promise.catch==='function')promise.catch(()=>updateTrackButtons());
  }
  function previousTrack(){
    if(current<0)return;
    if(audio.currentTime>3){audio.currentTime=0;updatePositionState();return;}
    if(canPrevious())start(current-1);
  }
  function nextTrack(){if(canNext())start(current+1)}

  buttons.forEach((btn,i)=>btn.addEventListener('click',()=>{
    if(current===i&&!audio.paused){plyr?plyr.pause():audio.pause()}
    else if(current===i&&audio.src){const promise=plyr?plyr.play():audio.play();promise?.catch?.(()=>{})}
    else start(i);
  }));

  audio.addEventListener('play',()=>{updateTrackButtons();if('mediaSession'in navigator)navigator.mediaSession.playbackState='playing'});
  audio.addEventListener('pause',()=>{updateTrackButtons();if('mediaSession'in navigator)navigator.mediaSession.playbackState='paused'});
  audio.addEventListener('loadedmetadata',()=>{updateMediaSession(buttons[current]);updatePositionState()});
  audio.addEventListener('durationchange',updatePositionState);
  audio.addEventListener('ratechange',updatePositionState);
  audio.addEventListener('timeupdate',()=>{const second=Math.floor(audio.currentTime||0);if(second!==lastPositionUpdate&&second%5===0){lastPositionUpdate=second;updatePositionState()}});
  audio.addEventListener('ended',()=>{updateTrackButtons();nextTrack()});

  if('mediaSession'in navigator){
    const handlers={
      play:()=>audio.play(),
      pause:()=>audio.pause(),
      previoustrack:previousTrack,
      nexttrack:nextTrack,
      stop:()=>{audio.pause();audio.currentTime=0;updatePositionState()}
    };
    Object.entries(handlers).forEach(([action,handler])=>{try{navigator.mediaSession.setActionHandler(action,handler)}catch{}});
  }

  document.querySelector('#closePlayer')?.addEventListener('click',()=>{plyr?plyr.pause():audio.pause();updateTrackButtons();box.classList.remove('show');setTimeout(()=>box.hidden=true,220)});
  document.querySelector('#shareAlbumButton')?.addEventListener('click',async e=>{const b=e.currentTarget,u=b.dataset.shareUrl,t=b.dataset.shareTitle;try{if(navigator.share)await navigator.share({title:t,url:u});else{await navigator.clipboard.writeText(u);const old=b.innerHTML;b.innerHTML='<i class="bi bi-check-lg me-1"></i>Link kopiert';setTimeout(()=>b.innerHTML=old,1800)}}catch{}});
  const img=document.querySelector('#coverImage');if(img){const apply=()=>{try{const c=document.createElement('canvas');c.width=c.height=40;const x=c.getContext('2d');x.drawImage(img,0,0,40,40);const d=x.getImageData(0,0,40,40).data;let r=0,g=0,b=0,n=0;for(let i=0;i<d.length;i+=16){r+=d[i];g+=d[i+1];b+=d[i+2];n++}document.body.classList.toggle('cover-light',(.299*r+.587*g+.114*b)/n>155)}catch{}};img.complete?apply():img.addEventListener('load',apply)}
});
