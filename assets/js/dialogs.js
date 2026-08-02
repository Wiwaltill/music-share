(() => {
  let modalEl;
  let modal;
  let resolver = null;

  function ensureModal() {
    if (modalEl) return modalEl;
    modalEl = document.createElement('div');
    modalEl.className = 'modal fade';
    modalEl.id = 'musicShareDialog';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
          <div class="modal-header">
            <h2 class="modal-title fs-5">Hinweis</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body"><p class="mb-0 dialog-message"></p></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary dialog-cancel" data-bs-dismiss="modal">Abbrechen</button>
            <button type="button" class="btn btn-primary dialog-confirm">Bestätigen</button>
          </div>
        </div>
      </div>`;
    document.body.append(modalEl);
    modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalEl.querySelector('.dialog-confirm').addEventListener('click', () => {
      if (resolver) resolver(true);
      resolver = null;
      modal.hide();
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
      if (resolver) resolver(false);
      resolver = null;
    });
    return modalEl;
  }

  function show({title = 'Hinweis', message = '', confirmText = 'Bestätigen', cancelText = 'Abbrechen', variant = 'primary', cancel = true} = {}) {
    return new Promise(resolve => {
      ensureModal();
      resolver = resolve;
      modalEl.querySelector('.modal-title').textContent = title;
      modalEl.querySelector('.dialog-message').textContent = message;
      const confirmButton = modalEl.querySelector('.dialog-confirm');
      const cancelButton = modalEl.querySelector('.dialog-cancel');
      confirmButton.textContent = confirmText;
      confirmButton.className = `btn btn-${variant} dialog-confirm`;
      cancelButton.textContent = cancelText;
      cancelButton.classList.toggle('d-none', !cancel);
      modalEl.querySelector('.btn-close').classList.toggle('d-none', !cancel);
      modal.show();
    });
  }

  window.MusicShareDialog = {
    alert(message, title = 'Hinweis') {
      return show({title, message, confirmText: 'OK', cancel: false});
    },
    confirm(message, options = {}) {
      return show({
        title: options.title || 'Bitte bestätigen',
        message,
        confirmText: options.confirmText || 'Bestätigen',
        cancelText: options.cancelText || 'Abbrechen',
        variant: options.variant || 'primary',
        cancel: true,
      });
    }
  };

  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmBypass === '1') return;
    event.preventDefault();
    const ok = await window.MusicShareDialog.confirm(form.dataset.confirm || 'Fortfahren?', {
      title: form.dataset.confirmTitle || 'Bitte bestätigen',
      confirmText: form.dataset.confirmButton || 'Bestätigen',
      variant: form.dataset.confirmVariant || 'danger'
    });
    if (!ok) return;
    form.dataset.confirmBypass = '1';
    form.requestSubmit(event.submitter || undefined);
  }, true);

  document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-confirm]');
    if (!button || button.dataset.confirmBypass === '1') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const ok = await window.MusicShareDialog.confirm(button.dataset.confirm || 'Fortfahren?', {
      title: button.dataset.confirmTitle || 'Bitte bestätigen',
      confirmText: button.dataset.confirmButton || 'Bestätigen',
      variant: button.dataset.confirmVariant || 'danger'
    });
    if (!ok) return;
    button.dataset.confirmBypass = '1';
    if (button.form) button.form.requestSubmit(button);
    else button.click();
  }, true);
})();
