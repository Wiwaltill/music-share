var msT=window.msT||((key,fallback)=>fallback??key);
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
            <h2 class="modal-title fs-5">${msT('text.hinweis', 'Hinweis')}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${msT('text.schlieen', 'Schließen')}"></button>
          </div>
          <div class="modal-body"><p class="mb-0 dialog-message"></p></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary dialog-cancel" data-bs-dismiss="modal">${msT('text.abbrechen', 'Abbrechen')}</button>
            <button type="button" class="btn btn-primary dialog-confirm">${msT('text.bestatigen', 'Bestätigen')}</button>
          </div>
        </div>
      </div>`;
    document.body.append(modalEl);
    if (!window.bootstrap?.Modal) throw new Error('Bootstrap Modal is unavailable.');
    modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
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

  function show({title = msT('text.hinweis', 'Hinweis'), message = '', confirmText = msT('text.bestatigen', 'Bestätigen'), cancelText = msT('text.abbrechen', 'Abbrechen'), variant = 'primary', cancel = true} = {}) {
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
    alert(message, title = msT('text.hinweis', 'Hinweis')) {
      return show({title, message, confirmText: 'OK', cancel: false});
    },
    confirm(message, options = {}) {
      return show({
        title: options.title || msT('dialog.confirm_title', 'Bitte bestätigen'),
        message,
        confirmText: options.confirmText || msT('text.bestatigen', 'Bestätigen'),
        cancelText: options.cancelText || msT('text.abbrechen', 'Abbrechen'),
        variant: options.variant || 'primary',
        cancel: true,
      });
    }
  };

  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmBypass === '1') return;
    event.preventDefault();
    const ok = await window.MusicShareDialog.confirm(form.dataset.confirm || msT('dialog.continue', 'Fortfahren?'), {
      title: form.dataset.confirmTitle || msT('dialog.confirm_title', 'Bitte bestätigen'),
      confirmText: form.dataset.confirmButton || msT('text.bestatigen', 'Bestätigen'),
      variant: form.dataset.confirmVariant || 'danger'
    });
    if (!ok) return;
    form.dataset.confirmBypass = '1';
    HTMLFormElement.prototype.submit.call(form);
  }, true);

  document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-confirm]');
    if (!button || button.dataset.confirmBypass === '1') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const ok = await window.MusicShareDialog.confirm(button.dataset.confirm || msT('dialog.continue', 'Fortfahren?'), {
      title: button.dataset.confirmTitle || msT('dialog.confirm_title', 'Bitte bestätigen'),
      confirmText: button.dataset.confirmButton || msT('text.bestatigen', 'Bestätigen'),
      variant: button.dataset.confirmVariant || 'danger'
    });
    if (!ok) return;
    button.dataset.confirmBypass = '1';
    if (button.form) { button.form.dataset.confirmBypass = '1'; HTMLFormElement.prototype.submit.call(button.form); }
    else { button.dataset.confirmBypass = '1'; button.click(); }
  }, true);
})();
