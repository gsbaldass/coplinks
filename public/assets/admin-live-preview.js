(() => {
  'use strict';

  const forms = document.querySelectorAll('form[data-live-preview-form][data-live-preview-endpoint]');
  const iframe = document.querySelector('iframe[data-live-preview-iframe]');
  const status = document.querySelector('[data-live-preview-status]');

  if (!forms.length || !iframe || !status) return;

  const debounceMs = 300;
  const maxImageBytes = 2 * 1024 * 1024;
  const uploads = [
    { field: 'avatar_file', previewField: 'avatar_preview_data' },
    { field: 'image_file', previewField: 'image_preview_data' }
  ];
  const allowedImageTypes = new Set(['image/png', 'image/jpeg', 'image/webp']);
  let debounceTimer = null;
  let activeController = null;
  let requestNonce = 0;

  const setStatus = (message) => {
    status.textContent = message;
  };

  const fileAsDataUrl = (file) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.addEventListener('load', () => {
      if (typeof reader.result === 'string') resolve(reader.result);
      else reject(new Error('Não foi possível ler a imagem.'));
    }, { once: true });
    reader.addEventListener('error', () => reject(new Error('Não foi possível ler a imagem.')), { once: true });
    reader.readAsDataURL(file);
  });

  const buildFormData = async (form) => {
    const data = new FormData(form);

    for (const upload of uploads) {
      const input = form.elements.namedItem(upload.field);
      const file = input && input.files ? input.files[0] : null;
      data.delete(upload.field);
      data.delete(upload.previewField);

      if (!file) continue;
      if (!allowedImageTypes.has(file.type) || file.size > maxImageBytes) {
        throw new Error('Use PNG, JPEG ou WebP de até 2 MiB para a prévia.');
      }
      data.append(upload.previewField, await fileAsDataUrl(file));
    }

    data.set('action', 'live-preview');
    return data;
  };

  const updatePreview = async (form, nonce) => {
    try {
      const data = await buildFormData(form);
      if (nonce !== requestNonce) return;

      const controller = new AbortController();
      activeController = controller;
      const response = await fetch(form.dataset.livePreviewEndpoint, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { Accept: 'text/html' },
        signal: controller.signal
      });

      if (nonce !== requestNonce) return;
      if (!response.ok) throw new Error(`Resposta HTTP ${response.status}`);

      const html = await response.text();
      if (nonce !== requestNonce) return;
      iframe.srcdoc = html;
      setStatus('Prévia atualizada');
    } catch (error) {
      if (nonce !== requestNonce || (error && error.name === 'AbortError')) return;
      setStatus('Não foi possível atualizar');
    } finally {
      if (nonce === requestNonce) activeController = null;
    }
  };

  const scheduleUpdate = (form) => {
    window.clearTimeout(debounceTimer);
    requestNonce += 1;
    const nonce = requestNonce;
    if (activeController) {
      activeController.abort();
      activeController = null;
    }
    setStatus('Atualizando');
    debounceTimer = window.setTimeout(() => updatePreview(form, nonce), debounceMs);
  };

  forms.forEach((form) => {
    form.addEventListener('input', (event) => {
      if (event.target && event.target.form === form) scheduleUpdate(form);
    });
    form.addEventListener('change', (event) => {
      if (event.target && event.target.form === form) scheduleUpdate(form);
    });
    if (form.hasAttribute('data-live-preview-submit')) {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        scheduleUpdate(form);
      });
    }
  });
})();
