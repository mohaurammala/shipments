/*
 * Small enhancements on top of pages that work without JavaScript:
 *   - add and remove parcel rows on the shipment form;
 *   - ask for confirmation before a form marked data-confirm is sent;
 *   - lock a form's submit buttons once it is sent, with a spinner, so a double click cannot
 *     send it twice (the server still guards against duplicates on its own).
 * No inline scripts: the content security policy only allows files from this site.
 */
(() => {
  'use strict';

  const list = document.querySelector('[data-parcel-list]');
  const template = document.getElementById('parcel-template');
  const addButton = document.querySelector('[data-add-parcel]');

  if (list && template && addButton) {
    const max = Number(list.dataset.max || 20);
    const rows = () => Array.from(list.querySelectorAll('[data-parcel]'));

    // A new row gets the next unused key, so existing rows (and their error messages,
    // which are keyed by row) never change number when one is added or removed.
    const nextKey = () => rows().reduce((highest, row) => Math.max(highest, Number(row.dataset.parcel)), -1) + 1;

    const refresh = () => {
      const current = rows();
      current.forEach((row, index) => {
        row.querySelector('[data-parcel-number]').textContent = String(index + 1);
        row.querySelector('[data-remove-parcel]').hidden = current.length === 1;
      });
      addButton.disabled = current.length >= max;
    };

    addButton.hidden = false;

    addButton.addEventListener('click', () => {
      // The template is server-rendered and escaped; only a number is put into it here.
      list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextKey())));
      refresh();
      list.lastElementChild?.querySelector('input')?.focus();
    });

    list.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-remove-parcel]');
      if (!remove) {
        return;
      }
      remove.closest('[data-parcel]').remove();
      refresh();
      addButton.focus();
    });

    refresh();
  }

  const unlock = (form) => {
    delete form.dataset.submitting;
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = false;
      button.querySelector('[data-spinner]')?.remove();
    });
  };

  // Registered before the lock below, so declining stops the submission before anything locks.
  // Without JavaScript the form is sent without asking; the server still checks everything.
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.submitting !== 'true' && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    });
  });

  document.querySelectorAll('form[data-lock-on-submit]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = 'true';
      // Disabled after the browser has collected the form, so the submission still goes ahead.
      setTimeout(() => {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
          button.disabled = true;
          button.insertAdjacentHTML(
            'afterbegin',
            '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true" data-spinner></span>',
          );
        });
      }, 0);
    });
  });

  // Coming back with the browser's Back button can restore a page with its buttons still
  // locked; unlock them so the form can be used again.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      document.querySelectorAll('form[data-lock-on-submit]').forEach(unlock);
    }
  });
})();
