(() => {
  const body = document.body;
  if (!body.classList.contains('public-album') || body.dataset.albumColors !== '1') return;
  const img = document.getElementById('coverImage');
  if (!img) return;
  const apply = () => {
    try {
      const c = document.createElement('canvas'); c.width = c.height = 32;
      const x = c.getContext('2d', {willReadFrequently:true}); x.drawImage(img,0,0,32,32);
      const d=x.getImageData(0,0,32,32).data; let r=0,g=0,b=0,n=0;
      for(let i=0;i<d.length;i+=16){ if(d[i+3]<128) continue; r+=d[i];g+=d[i+1];b+=d[i+2];n++; }
      if(!n) return; r=Math.round(r/n);g=Math.round(g/n);b=Math.round(b/n);
      body.style.setProperty('--album-accent',`rgb(${r} ${g} ${b})`);
      body.classList.add('album-colors-enabled');
    } catch(e) {}
  };
  img.complete ? apply() : img.addEventListener('load',apply,{once:true});
})();
