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

const picker = document.querySelector('[data-room-picker]');
if (picker) {
  const boxes = [...picker.querySelectorAll('input[name="tracks[]"]')];
  const albums = [...picker.querySelectorAll('[data-room-album]')];
  const selected = picker.querySelector('[data-room-selected]');
  function updateSelection() {
    const chosen = boxes.filter(box => box.checked);
    picker.querySelector('[data-room-count]').textContent = chosen.length + ' / ' + boxes.length;
    picker.querySelector('[data-room-selection-empty]').hidden = chosen.length > 0;
    picker.querySelector('[data-room-clear]').disabled = !chosen.length;
    selected.replaceChildren();
    chosen.forEach(box => {
      const row = document.createElement('div');
      row.className = 'room-selected-item';
      const text = document.createElement('span');
      const title = document.createElement('strong');
      title.textContent = box.dataset.title;
      const album = document.createElement('small');
      album.textContent = box.dataset.album;
      text.append(title, album);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-sm btn-outline-secondary';
      remove.textContent = '×';
      remove.setAttribute('aria-label', window.msT('rooms.remove', 'Remove') + ': ' + box.dataset.title);
      remove.addEventListener('click', () => {
        const index = [...selected.children].indexOf(row);
        box.checked = false;
        updateSelection();
        const next = selected.children[Math.min(index, selected.children.length - 1)];
        (next?.querySelector('button') || picker.querySelector('[data-room-filter]')).focus();
      });
      row.append(text, remove);
      selected.append(row);
    });
    albums.forEach(album => {
      const inputs = [...album.querySelectorAll('input[name="tracks[]"]')];
      album.querySelector('[data-album-count]').textContent = inputs.filter(box => box.checked).length + ' / ' + inputs.length;
    });
  }
  picker.addEventListener('change', updateSelection);
  picker.querySelector('[data-room-clear]').addEventListener('click', () => { boxes.forEach(box => { box.checked = false; }); updateSelection(); });
  picker.querySelectorAll('[data-room-select-album]').forEach(button => {
    button.addEventListener('click', () => {
      const inputs = [...button.closest('[data-room-album]').querySelectorAll('input[name="tracks[]"]')];
      const select = inputs.some(box => !box.checked);
      inputs.forEach(box => { box.checked = select; });
      updateSelection();
    });
  });
  picker.querySelector('[data-room-filter]').addEventListener('input', event => {
    const query = event.target.value.trim().toLocaleLowerCase();
    let visible = 0;
    albums.forEach(album => {
      if (query && !album.dataset.search.includes(query)) {
        const matches = [...album.querySelectorAll('.room-picker-track')].some(row => row.dataset.search.includes(query));
        album.hidden = !matches;
      } else album.hidden = false;
      // Keep all tracks visible within matching albums to avoid partial album selection.
      album.open = Boolean(query && !album.hidden);
      if (!album.hidden) visible++;
    });
    picker.querySelector('[data-room-no-matches]').hidden = visible > 0;
  });
  updateSelection();
}
