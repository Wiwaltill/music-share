document.addEventListener('DOMContentLoaded', () => {
  const audio = document.querySelector('#mainPlayer');
  const playAll = document.querySelector('[data-room-play]');
  if (!audio || !playAll) return;
  const tracks = [...document.querySelectorAll('[data-row]')];
  const nowCover = document.querySelector('[data-room-now-cover]');
  const nowArtist = document.querySelector('[data-room-now-artist]');
  const update = () => {
    const playing = !audio.paused && !audio.ended;
    playAll.querySelector('span').textContent = window.msT(playing ? 'text.titel.pausieren' : 'rooms.play_all');
    playAll.querySelector('i').className = playing ? 'bi bi-pause-fill' : 'bi bi-play-fill';
    playAll.setAttribute('aria-pressed', String(playing));
    tracks.forEach(row => {
      const button = row.querySelector('[data-play]');
      const active = button.dataset.src === audio.src;
      row.classList.toggle('room-track-active', active);
      if (active) {
        nowArtist.textContent = button.dataset.artist || '';
        nowCover.hidden = !button.dataset.cover;
        if (button.dataset.cover) nowCover.src = button.dataset.cover;
      }
    });
  };
  playAll.addEventListener('click', () => {
    const active = tracks.find(row => row.querySelector('[data-play]').dataset.src === audio.src);
    (active || tracks[0])?.querySelector('[data-play]').click();
  });
  ['play', 'pause', 'ended', 'loadedmetadata'].forEach(event => audio.addEventListener(event, update));
});
