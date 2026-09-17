(function () {
    const outputWidth = 400;
    const outputHeight = 500;
    const cropAspect = outputWidth / outputHeight;

    function clamp(value, minimum, maximum) {
        return Math.min(Math.max(value, minimum), maximum);
    }

    function dataTransferFor(file) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        return transfer;
    }

    function canvasBlob(canvas) {
        return new Promise((resolve, reject) => {
            canvas.toBlob(blob => {
                if (blob) {
                    resolve(blob);
                    return;
                }

                canvas.toBlob(fallback => fallback ? resolve(fallback) : reject(new Error('Unable to prepare the selected photo.')), 'image/png');
            }, 'image/webp', 0.9);
        });
    }

    function filenameFromUrl(url, mimeType) {
        try {
            const pathname = new URL(url, window.location.href).pathname;
            const filename = pathname.split('/').pop() || '';
            if (filename.includes('.')) {
                return filename;
            }
        } catch (error) {
            // A generated filename below is safe when the URL cannot be parsed.
        }

        return 'student-photo.' + (mimeType === 'image/png' ? 'png' : 'webp');
    }

    function createCropFrame() {
        const frame = document.createElement('div');
        frame.className = 'photo-crop-frame';
        frame.setAttribute('aria-hidden', 'true');

        ['top', 'right', 'bottom', 'left'].forEach(edge => {
            const edgeElement = document.createElement('span');
            edgeElement.className = 'photo-crop-edge photo-crop-edge-' + edge;
            edgeElement.dataset.photoFrameDrag = 'true';
            frame.append(edgeElement);
        });

        [
            ['nw', 'Resize crop from top left'],
            ['ne', 'Resize crop from top right'],
            ['se', 'Resize crop from bottom right'],
            ['sw', 'Resize crop from bottom left'],
        ].forEach(([corner, label]) => {
            const handle = document.createElement('button');
            handle.type = 'button';
            handle.className = 'photo-crop-handle photo-crop-handle-' + corner;
            handle.dataset.photoResize = corner;
            handle.tabIndex = -1;
            handle.setAttribute('aria-label', label);
            frame.append(handle);
        });

        return frame;
    }

    function initializeEditor(editor) {
        const input = document.getElementById(editor.dataset.inputId || '');
        const form = editor.closest('form');
        const stage = editor.querySelector('[data-photo-stage]');
        const image = editor.querySelector('[data-photo-source]');
        const slider = editor.querySelector('[data-photo-zoom]');
        const zoomValue = editor.querySelector('[data-photo-zoom-value]');
        const outputPreview = editor.querySelector('[data-photo-output-preview]');
        const outputPreviewWrap = editor.querySelector('[data-photo-output-preview-wrap]');
        const resetButton = editor.querySelector('[data-photo-reset]');
        const existingButton = editor.parentElement?.querySelector('[data-photo-edit-existing]');
        const zoomInButton = editor.querySelector('[data-photo-zoom-in]');
        const zoomOutButton = editor.querySelector('[data-photo-zoom-out]');
        const rotateLeftButton = editor.querySelector('[data-photo-rotate-left]');
        const rotateRightButton = editor.querySelector('[data-photo-rotate-right]');
        const flipButton = editor.querySelector('[data-photo-flip]');
        const modeInputs = Array.from(editor.querySelectorAll('[data-photo-mode]'));
        const existingPhotoUrl = editor.dataset.photoExistingUrl || '';

        if (!input || !form || !stage || !image || !slider) {
            return;
        }

        const cropFrame = createCropFrame();
        stage.append(cropFrame);

        const state = {
            loaded: false,
            processed: false,
            sourceKind: '',
            shouldSave: false,
            mode: 'crop',
            zoom: 1,
            baseScale: 1,
            scale: 1,
            x: 0,
            y: 0,
            width: 0,
            height: 0,
            crop: { x: 0, y: 0, width: 0, height: 0 },
            drag: null,
            url: null,
            originalFile: null,
            transformed: false,
            previewFrame: 0,
        };

        function dimensions() {
            const bounds = stage.getBoundingClientRect();
            return { width: bounds.width, height: bounds.height };
        }

        function minimumCropWidth(stageSize) {
            return Math.min(stageSize.width, Math.max(48, Math.min(100, stageSize.width * 0.45)));
        }

        function resetCrop(stageSize = dimensions()) {
            const padding = Math.min(18, stageSize.width * 0.06, stageSize.height * 0.06);
            const width = Math.min(
                stageSize.width - (padding * 2),
                (stageSize.height - (padding * 2)) * cropAspect
            );
            const height = width / cropAspect;

            state.crop.width = Math.max(0, width);
            state.crop.height = Math.max(0, height);
            state.crop.x = Math.max(0, (stageSize.width - width) / 2);
            state.crop.y = Math.max(0, (stageSize.height - height) / 2);
        }

        function constrainCrop(stageSize = dimensions()) {
            if (state.crop.width <= 0 || state.crop.height <= 0) {
                resetCrop(stageSize);
                return;
            }

            const maximumWidth = Math.min(stageSize.width, stageSize.height * cropAspect);
            const width = clamp(state.crop.width, minimumCropWidth(stageSize), maximumWidth);
            state.crop.width = width;
            state.crop.height = width / cropAspect;
            state.crop.x = clamp(state.crop.x, 0, Math.max(0, stageSize.width - state.crop.width));
            state.crop.y = clamp(state.crop.y, 0, Math.max(0, stageSize.height - state.crop.height));
        }

        function zoomBounds() {
            return {
                minimum: state.mode === 'crop' ? 1 : 0.25,
                maximum: 3,
            };
        }

        function updateSlider() {
            const bounds = zoomBounds();
            slider.min = String(bounds.minimum);
            slider.max = String(bounds.maximum);
            slider.step = '0.01';
            slider.value = String(state.zoom);
            if (zoomValue) {
                zoomValue.textContent = Math.round(state.zoom * 100) + '%';
            }
        }

        function updateImageSize() {
            if (!state.loaded) {
                return;
            }

            let scale = state.baseScale * state.zoom;
            if (state.mode === 'crop') {
                const minimumScale = Math.max(
                    state.crop.width / image.naturalWidth,
                    state.crop.height / image.naturalHeight
                );
                scale = Math.max(scale, minimumScale);
                state.zoom = Math.max(1, scale / state.baseScale);
            }

            state.scale = scale;
            state.width = image.naturalWidth * scale;
            state.height = image.naturalHeight * scale;
        }

        function constrainImagePosition() {
            if (state.mode === 'crop') {
                state.x = clamp(state.x, state.crop.x + state.crop.width - state.width, state.crop.x);
                state.y = clamp(state.y, state.crop.y + state.crop.height - state.height, state.crop.y);
                return;
            }

            const visibleWidth = Math.min(24, state.width / 3);
            const visibleHeight = Math.min(24, state.height / 3);
            state.x = clamp(
                state.x,
                state.crop.x - state.width + visibleWidth,
                state.crop.x + state.crop.width - visibleWidth
            );
            state.y = clamp(
                state.y,
                state.crop.y - state.height + visibleHeight,
                state.crop.y + state.crop.height - visibleHeight
            );
        }

        function centreImage() {
            updateImageSize();
            state.x = state.crop.x + ((state.crop.width - state.width) / 2);
            state.y = state.crop.y + ((state.crop.height - state.height) / 2);
            constrainImagePosition();
        }

        function renderCropFrame() {
            cropFrame.style.left = state.crop.x + 'px';
            cropFrame.style.top = state.crop.y + 'px';
            cropFrame.style.width = state.crop.width + 'px';
            cropFrame.style.height = state.crop.height + 'px';
        }

        function queueOutputPreview() {
            if (!outputPreview || !outputPreviewWrap || state.previewFrame) {
                return;
            }

            state.previewFrame = window.requestAnimationFrame(() => {
                state.previewFrame = 0;
                try {
                    outputPreview.src = exportCanvas().toDataURL('image/webp', 0.82);
                    outputPreviewWrap.hidden = false;
                } catch (error) {
                    outputPreviewWrap.hidden = true;
                }
            });
        }

        function render() {
            if (!state.loaded) {
                return;
            }

            constrainCrop();
            updateImageSize();
            constrainImagePosition();
            updateSlider();
            image.style.width = state.width + 'px';
            image.style.height = state.height + 'px';
            image.style.left = state.x + 'px';
            image.style.top = state.y + 'px';
            renderCropFrame();
            stage.classList.toggle('is-fit', state.mode === 'fit');
            stage.classList.add('has-photo');
            queueOutputPreview();
        }

        function markChanged() {
            state.shouldSave = true;
            state.processed = false;
        }

        function reset(markAsChanged = false) {
            if (!state.loaded) {
                return;
            }

            state.zoom = 1;
            resetCrop();
            const scaleArea = state.crop;
            state.baseScale = state.mode === 'crop'
                ? Math.max(scaleArea.width / image.naturalWidth, scaleArea.height / image.naturalHeight)
                : Math.min(scaleArea.width / image.naturalWidth, scaleArea.height / image.naturalHeight);
            centreImage();
            render();

            if (markAsChanged) {
                markChanged();
            }
        }

        function setMode(mode) {
            state.mode = mode === 'fit' ? 'fit' : 'crop';
            reset(true);
        }

        function exportCanvas() {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = outputWidth;
            canvas.height = outputHeight;
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, outputWidth, outputHeight);

            const scale = outputWidth / state.crop.width;
            context.drawImage(
                image,
                (state.x - state.crop.x) * scale,
                (state.y - state.crop.y) * scale,
                state.width * scale,
                state.height * scale
            );
            return canvas;
        }

        function loadFile(file, sourceKind = 'new', shouldSave = sourceKind === 'new', preserveOriginal = false) {
            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            if (state.url) {
                URL.revokeObjectURL(state.url);
            }

            state.url = URL.createObjectURL(file);
            state.loaded = false;
            state.processed = false;
            state.sourceKind = sourceKind;
            state.shouldSave = shouldSave;
            state.crop = { x: 0, y: 0, width: 0, height: 0 };
            if (state.previewFrame) {
                window.cancelAnimationFrame(state.previewFrame);
                state.previewFrame = 0;
            }
            if (outputPreview && outputPreviewWrap) {
                outputPreview.removeAttribute('src');
                outputPreviewWrap.hidden = true;
            }
            if (!preserveOriginal) {
                state.originalFile = file;
                state.transformed = false;
            } else {
                state.transformed = true;
            }
            editor.hidden = false;
            image.onload = () => {
                state.loaded = true;
                window.requestAnimationFrame(() => reset(false));
            };
            image.onerror = () => {
                state.loaded = false;
                stage.classList.remove('has-photo');
                editor.hidden = true;
                window.alert('The selected file could not be opened as an image.');
            };
            image.src = state.url;
        }

        async function loadExistingPhoto() {
            if (existingPhotoUrl === '') {
                return;
            }

            const originalLabel = existingButton ? existingButton.innerHTML : '';
            if (existingButton) {
                existingButton.disabled = true;
                existingButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Loading…';
            }

            try {
                const response = await window.fetch(existingPhotoUrl, { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('The saved photo could not be loaded.');
                }

                const blob = await response.blob();
                if (!blob.type.startsWith('image/')) {
                    throw new Error('The saved file is not a supported image.');
                }

                input.value = '';
                loadFile(new File([blob], filenameFromUrl(existingPhotoUrl, blob.type), { type: blob.type }), 'existing', false);
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'The saved photo could not be loaded.');
            } finally {
                if (existingButton) {
                    existingButton.disabled = false;
                    existingButton.innerHTML = originalLabel;
                }
            }
        }

        function setZoom(nextZoom, focalPoint = null) {
            if (!state.loaded) {
                return;
            }

            const bounds = zoomBounds();
            const previousWidth = state.width;
            const previousHeight = state.height;
            const previousX = state.x;
            const previousY = state.y;
            state.zoom = clamp(nextZoom, bounds.minimum, bounds.maximum);
            updateImageSize();

            if (focalPoint && previousWidth > 0 && previousHeight > 0) {
                state.x = focalPoint.x - ((focalPoint.x - previousX) * (state.width / previousWidth));
                state.y = focalPoint.y - ((focalPoint.y - previousY) * (state.height / previousHeight));
            }

            markChanged();
            render();
        }

        async function transformImage(operation) {
            if (!state.loaded) {
                return;
            }

            const sourceWidth = image.naturalWidth;
            const sourceHeight = image.naturalHeight;
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            if (operation === 'rotate-left' || operation === 'rotate-right') {
                canvas.width = sourceHeight;
                canvas.height = sourceWidth;
                if (operation === 'rotate-right') {
                    context.translate(canvas.width, 0);
                    context.rotate(Math.PI / 2);
                } else {
                    context.translate(0, canvas.height);
                    context.rotate(-Math.PI / 2);
                }
            } else {
                canvas.width = sourceWidth;
                canvas.height = sourceHeight;
                context.translate(canvas.width, 0);
                context.scale(-1, 1);
            }

            context.drawImage(image, 0, 0);
            const buttons = [rotateLeftButton, rotateRightButton, flipButton].filter(Boolean);
            buttons.forEach(button => { button.disabled = true; });

            try {
                const blob = await canvasBlob(canvas);
                loadFile(new File([blob], 'student-photo.webp', { type: blob.type }), state.sourceKind || 'new', true, true);
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'Unable to transform the selected photo.');
            } finally {
                buttons.forEach(button => { button.disabled = false; });
            }
        }

        function startDragging(event, kind, handle = '') {
            if (!state.loaded || (event.pointerType === 'mouse' && event.button !== 0)) {
                return;
            }

            event.preventDefault();
            state.drag = {
                kind,
                handle,
                pointerId: event.pointerId,
                x: event.clientX,
                y: event.clientY,
                imageX: state.x,
                imageY: state.y,
                crop: { ...state.crop },
            };
            stage.classList.add('is-dragging');
            stage.classList.toggle('is-resizing', kind === 'resize');
            stage.setPointerCapture(event.pointerId);
        }

        function moveCropFrame(dx, dy) {
            const stageSize = dimensions();
            state.crop.x = clamp(state.drag.crop.x + dx, 0, stageSize.width - state.crop.width);
            state.crop.y = clamp(state.drag.crop.y + dy, 0, stageSize.height - state.crop.height);
        }

        function resizeCrop(handle, dx, dy) {
            const stageSize = dimensions();
            const start = state.drag.crop;
            const horizontalChange = handle.includes('e') ? dx : -dx;
            const verticalChange = (handle.includes('s') ? dy : -dy) * cropAspect;
            const widthChange = Math.abs(horizontalChange) >= Math.abs(verticalChange)
                ? horizontalChange
                : verticalChange;
            const minimum = minimumCropWidth(stageSize);
            let maximum;

            if (handle === 'nw') {
                maximum = Math.min(start.x + start.width, (start.y + start.height) * cropAspect);
            } else if (handle === 'ne') {
                maximum = Math.min(stageSize.width - start.x, (start.y + start.height) * cropAspect);
            } else if (handle === 'se') {
                maximum = Math.min(stageSize.width - start.x, (stageSize.height - start.y) * cropAspect);
            } else {
                maximum = Math.min(start.x + start.width, (stageSize.height - start.y) * cropAspect);
            }

            const width = clamp(start.width + widthChange, minimum, maximum);
            const height = width / cropAspect;

            if (handle === 'nw') {
                state.crop.x = start.x + start.width - width;
                state.crop.y = start.y + start.height - height;
            } else if (handle === 'ne') {
                state.crop.x = start.x;
                state.crop.y = start.y + start.height - height;
            } else if (handle === 'se') {
                state.crop.x = start.x;
                state.crop.y = start.y;
            } else {
                state.crop.x = start.x + start.width - width;
                state.crop.y = start.y;
            }

            state.crop.width = width;
            state.crop.height = height;
        }

        input.addEventListener('change', () => loadFile(input.files && input.files[0]));
        existingButton?.addEventListener('click', loadExistingPhoto);

        modeInputs.forEach(modeInput => {
            modeInput.addEventListener('change', () => {
                if (modeInput.checked) {
                    setMode(modeInput.value);
                }
            });
        });

        slider.addEventListener('input', () => setZoom(Number(slider.value)));
        zoomInButton?.addEventListener('click', () => setZoom(state.zoom + 0.1));
        zoomOutButton?.addEventListener('click', () => setZoom(state.zoom - 0.1));
        resetButton?.addEventListener('click', () => {
            state.mode = 'crop';
            modeInputs.forEach(modeInput => {
                modeInput.checked = modeInput.value === 'crop';
            });
            if (state.transformed && state.originalFile) {
                loadFile(state.originalFile, state.sourceKind || 'new', state.sourceKind === 'new');
                return;
            }

            reset(false);
            state.shouldSave = state.sourceKind === 'new';
            state.processed = false;
        });
        rotateLeftButton?.addEventListener('click', () => transformImage('rotate-left'));
        rotateRightButton?.addEventListener('click', () => transformImage('rotate-right'));
        flipButton?.addEventListener('click', () => transformImage('flip'));

        cropFrame.querySelectorAll('[data-photo-frame-drag]').forEach(edge => {
            edge.addEventListener('pointerdown', event => startDragging(event, 'frame'));
        });
        cropFrame.querySelectorAll('[data-photo-resize]').forEach(handle => {
            handle.addEventListener('pointerdown', event => startDragging(event, 'resize', handle.dataset.photoResize || ''));
        });

        stage.addEventListener('pointerdown', event => {
            if (event.target.closest('[data-photo-frame-drag], [data-photo-resize]')) {
                return;
            }
            startDragging(event, 'image');
        });

        stage.addEventListener('pointermove', event => {
            if (!state.drag || state.drag.pointerId !== event.pointerId) {
                return;
            }

            const dx = event.clientX - state.drag.x;
            const dy = event.clientY - state.drag.y;
            if (state.drag.kind === 'image') {
                state.x = state.drag.imageX + dx;
                state.y = state.drag.imageY + dy;
            } else if (state.drag.kind === 'frame') {
                moveCropFrame(dx, dy);
            } else {
                resizeCrop(state.drag.handle, dx, dy);
            }

            if (dx !== 0 || dy !== 0) {
                markChanged();
            }
            render();
        });

        stage.addEventListener('wheel', event => {
            if (!state.loaded) {
                return;
            }

            event.preventDefault();
            const bounds = stage.getBoundingClientRect();
            setZoom(
                state.zoom * (event.deltaY < 0 ? 1.1 : 0.9),
                { x: event.clientX - bounds.left, y: event.clientY - bounds.top }
            );
        }, { passive: false });

        function stopDragging(event) {
            if (state.drag && (!event || state.drag.pointerId === event.pointerId)) {
                state.drag = null;
                stage.classList.remove('is-dragging', 'is-resizing');
            }
        }

        stage.addEventListener('pointerup', stopDragging);
        stage.addEventListener('pointercancel', stopDragging);
        stage.addEventListener('lostpointercapture', stopDragging);

        form.addEventListener('submit', async event => {
            const hasInputFile = Boolean(input.files && input.files.length > 0);
            if (!state.loaded || state.processed || (!hasInputFile && !state.shouldSave)) {
                return;
            }

            event.preventDefault();
            const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
            const originalLabel = submitter ? submitter.innerHTML : '';
            if (submitter) {
                submitter.disabled = true;
                submitter.textContent = 'Preparing photo...';
            }

            try {
                const blob = await canvasBlob(exportCanvas());
                const extension = blob.type === 'image/webp' ? 'webp' : 'png';
                const filename = 'student-photo.' + extension;
                input.files = dataTransferFor(new File([blob], filename, { type: blob.type })).files;
                state.processed = true;
                form.requestSubmit(submitter || undefined);
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'Unable to prepare the selected photo.');
            } finally {
                if (submitter) {
                    submitter.disabled = false;
                    submitter.innerHTML = originalLabel;
                }
            }
        });

        window.addEventListener('resize', () => {
            if (state.loaded) {
                render();
            }
        });
    }

    document.querySelectorAll('[data-photo-editor]').forEach(initializeEditor);
}());
