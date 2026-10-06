(function () {
    const base = `${window.location.origin}/vendor/tesseract`;
    let workerPromise;

    const worker = () => {
        if (!workerPromise) {
            workerPromise = window.Tesseract.createWorker('eng', 1, {
                workerPath: `${base}/worker.min.js`,
                corePath: `${base}/core`,
                langPath: `${base}/lang`,
            });
        }
        return workerPromise;
    };

    const compact = text => String(text || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    const findGstin = text => compact(text).match(/[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]/)?.[0] || '';
    const findPan = text => compact(text).match(/[A-Z]{5}[0-9]{4}[A-Z]/)?.[0] || '';

    const updateInput = (root, name, value) => {
        const input = root.querySelector(`[name="${name}"]`);
        if (!input || !value) return false;
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
        setter?.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    };

    const statusFor = input => {
        let status = input.parentElement.querySelector('[data-ocr-status]');
        if (!status) {
            status = document.createElement('p');
            status.dataset.ocrStatus = 'true';
            status.className = 'mt-2 text-xs text-slate-500';
            input.insertAdjacentElement('afterend', status);
        }
        return status;
    };

    const previewFile = (input, file) => {
        const type = input.name === 'supplier_gst_document' ? 'gst' : 'pan';
        const root = input.closest('form') || document;
        const preview = root.querySelector(`[data-document-preview="${type}"]`);
        if (!preview) return;
        if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
        const url = URL.createObjectURL(file);
        preview.dataset.objectUrl = url;
        preview.replaceChildren();
        if (file.type.startsWith('image/')) {
            const image = document.createElement('img');
            image.src = url;
            image.alt = `${type.toUpperCase()} document preview`;
            preview.append(image);
        } else if (file.type === 'application/pdf') {
            const object = document.createElement('object');
            object.data = url;
            object.type = 'application/pdf';
            object.setAttribute('aria-label', `${type.toUpperCase()} PDF preview`);
            preview.append(object);
        } else {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noreferrer';
            link.textContent = `Open ${file.name}`;
            preview.append(link);
        }
    };

    const bind = input => {
        if (input.dataset.ocrReady) return;
        input.dataset.ocrReady = 'true';
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;
            previewFile(input, file);
            const status = statusFor(input);
            if (!file.type.startsWith('image/')) {
                status.textContent = 'Automatic extraction works with JPG, PNG and WebP images.';
                status.className = 'mt-2 text-xs text-amber-700';
                return;
            }

            status.textContent = 'Reading document…';
            status.className = 'mt-2 text-xs text-cyan-700';
            input.disabled = true;
            try {
                const engine = await worker();
                const result = await engine.recognize(file);
                const root = input.closest('form') || document;
                const isGst = input.name === 'supplier_gst_document';
                const gstin = isGst ? findGstin(result.data.text) : '';
                const pan = findPan(result.data.text) || (gstin ? gstin.slice(2, 12) : '');
                const found = isGst
                    ? updateInput(root, 'gst_number', gstin)
                    : updateInput(root, 'pan_number', pan);
                if (isGst && pan) updateInput(root, 'pan_number', pan);
                status.textContent = found ? 'Number extracted. Please verify it before saving.' : 'No valid number found. Please enter it manually.';
                status.className = `mt-2 text-xs ${found ? 'text-emerald-700' : 'text-amber-700'}`;
            } catch (error) {
                console.error('Document OCR failed', error);
                status.textContent = 'Could not read this image. Please enter the number manually.';
                status.className = 'mt-2 text-xs text-red-700';
            } finally {
                input.disabled = false;
            }
        });
    };

    const mount = root => {
        root.querySelectorAll?.('input[name="supplier_gst_document"], input[name="supplier_pan_document"]').forEach(bind);
    };

    document.addEventListener('DOMContentLoaded', () => mount(document));
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === 1) mount(node);
    }))).observe(document.documentElement, { childList: true, subtree: true });
})();
