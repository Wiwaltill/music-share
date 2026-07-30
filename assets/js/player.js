document.addEventListener('click', e => {
  const btn = e.target.closest('[data-play]');
  if (!btn) return;
  const audio = document.querySelector('#mainPlayer');
  if (!audio) return;
  audio.src = btn.dataset.src;
  audio.play();
  const label = document.querySelector('#nowPlaying');
  if (label) label.textContent = btn.dataset.title || 'Wiedergabe';
});
