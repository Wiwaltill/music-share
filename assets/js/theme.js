(() => {
 const btn=document.getElementById('themeToggle'); if(!btn) return;
 const modes=['auto','light','dark'];
 const apply=(m)=>{const actual=m==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):m;document.documentElement.setAttribute('data-bs-theme',actual);localStorage.setItem('musicshare-theme',m);btn.title='Darstellung: '+({auto:'Automatisch',light:'Hell',dark:'Dunkel'}[m]);btn.textContent=({auto:'◐',light:'☀',dark:'☾'}[m]);};
 let mode=localStorage.getItem('musicshare-theme')||'auto';apply(mode);
 btn.addEventListener('click',()=>{mode=modes[(modes.indexOf(mode)+1)%modes.length];apply(mode)});
 matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change',()=>{if(mode==='auto')apply(mode)});
})();
