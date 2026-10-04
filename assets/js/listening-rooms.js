document.querySelectorAll('[data-room-select-album]').forEach(button => {
  button.addEventListener('click', () => {
    const tracks = [...button.closest('fieldset').querySelectorAll('input[name="tracks[]"]')];
    const select = tracks.some(track => !track.checked);
    tracks.forEach(track => { track.checked = select; });
  });
});

document.querySelector('[data-room-copy-link]')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  const input = document.querySelector('#roomLink');
  try {
    await navigator.clipboard.writeText(input.value);
    button.textContent = window.msT('rooms.copied', 'Link copied');
  } catch {
    input.focus();
    input.select();
  }
});
