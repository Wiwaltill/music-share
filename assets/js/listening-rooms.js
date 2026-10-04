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
  let order = JSON.parse(picker.dataset.order || '[]').map(String);
  const orderInputs = picker.querySelector('[data-room-order-inputs]');
  function updateSelection() {
    order = order.filter(id => boxes.some(box => box.value === id && box.checked));
    boxes.filter(box => box.checked && !order.includes(box.value)).forEach(box => order.push(box.value));
    const chosen = order.map(id => boxes.find(box => box.value === id));
    orderInputs.replaceChildren();
    order.forEach(id => {
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'track_order[]'; input.value = id;
      orderInputs.append(input);
    });
    picker.querySelector('[data-room-count]').textContent = chosen.length + ' / ' + boxes.length;
    picker.querySelector('[data-room-selection-empty]').hidden = chosen.length > 0;
    picker.querySelector('[data-room-clear]').disabled = !chosen.length;
    selected.replaceChildren();
    chosen.forEach(box => {
      const row = document.createElement('div');
      row.className = 'room-selected-item';
      row.dataset.trackId = box.value;
      const handle = document.createElement('button');
      handle.type = 'button'; handle.className = 'room-sort-handle';
      handle.textContent = '⠿';
      handle.setAttribute('aria-label', window.msT('rooms.move', 'Move') + ': ' + box.dataset.title);
      handle.addEventListener('keydown', event => {
        if (!['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const index = order.indexOf(box.value);
        const next = index + (event.key === 'ArrowUp' ? -1 : 1);
        if (next < 0 || next >= order.length) return;
        [order[index], order[next]] = [order[next], order[index]];
        updateSelection();
        selected.children[next].querySelector('.room-sort-handle').focus();
      });
      handle.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        event.preventDefault();
        handle.setPointerCapture(event.pointerId);
        row.classList.add('room-sorting');
        const move = moving => {
          const target = document.elementFromPoint(moving.clientX, moving.clientY)?.closest('.room-selected-item');
          if (target && target !== row && selected.contains(target)) {
            const rect = target.getBoundingClientRect();
            selected.insertBefore(row, moving.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
          }
          const bounds = selected.getBoundingClientRect();
          if (moving.clientY < bounds.top + 35) selected.scrollTop -= 15;
          if (moving.clientY > bounds.bottom - 35) selected.scrollTop += 15;
        };
        const finish = () => {
          handle.removeEventListener('pointermove', move);
          handle.removeEventListener('pointerup', finish);
          handle.removeEventListener('pointercancel', finish);
          handle.removeEventListener('lostpointercapture', finish);
          order = [...selected.children].map(item => item.dataset.trackId);
          updateSelection();
          [...selected.children].find(item => item.dataset.trackId === box.value)?.querySelector('.room-sort-handle').focus();
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', finish);
        handle.addEventListener('pointercancel', finish);
        handle.addEventListener('lostpointercapture', finish);
      });
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
      row.append(handle, text, remove);
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
