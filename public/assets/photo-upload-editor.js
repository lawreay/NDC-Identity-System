(function () {
    const outputWidth = 400;
    const outputHeight = 500;

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
            }, 'image/webp', 0.88);
        });
    }

    function initializeEditor(editor) {
        const input = document.getElementById(editor.dataset.inputId || '');
        const form = editor.closest('form');
        const stage = editor.querySelector('[data-photo-stage]');
        const image = editor.querySelector('[data-photo-source]');
        const slider = editor.querySelector('[data-photo-zoom]');
        const resetButton = editor.querySelector('[data-photo-reset]');
        const modeInputs = Array.from(editor.querySelectorAll('[data-photo-mode]'));

        if (!input || !form || !stage || !image || !slider) {
            return;
        }

        const state = {
            loaded: false,
            processed: false,
            mode: 'crop',
            zoom: 1,
            scale: 1,
            x: 0,
            y: 0,
            width: 0,
            height: 0,
            drag: null,
            url: null,
        };

        function dimensions() {
            const bounds = stage.getBoundingClientRect();
            return { width: bounds.width, height: bounds.height };
        }

        function updateSlider() {
            slider.min = state.mode === 'crop' ? '1' : '0.25';
            slider.max = state.mode === 'crop' ? '3' : '1';
            slider.step = '0.01';
            slider.value = String(state.zoom);
        }

        function fitPosition() {
            const stageSize = dimensions();
            const baseScale = state.mode === 'crop'
                ? Math.max(stageSize.width / image.naturalWidth, stageSize.height / image.naturalHeight)
                : Math.min(stageSize.width / image.naturalWidth, stageSize.height / image.naturalHeight);
            state.scale = baseScale * state.zoom;
            state.width = image.naturalWidth * state.scale;
            state.height = image.naturalHeight * state.scale;

            if (state.mode === 'fit') {
                state.x = (stageSize.width - state.width) / 2;
                state.y = (stageSize.height - state.height) / 2;
                return;
            }

            state.x = Math.min(0, Math.max(stageSize.width - state.width, state.x));
            state.y = Math.min(0, Math.max(stageSize.height - state.height, state.y));
        }

        function render() {
            if (!state.loaded) {
                return;
            }

            fitPosition();
            image.style.width = state.width + 'px';
            image.style.height = state.height + 'px';
            image.style.left = state.x + 'px';
            image.style.top = state.y + 'px';
            stage.classList.toggle('is-fit', state.mode === 'fit');
        }

        function reset() {
            if (!state.loaded) {
                return;
            }

            state.zoom = 1;
            const stageSize = dimensions();
            const baseScale = state.mode === 'crop'
                ? Math.max(stageSize.width / image.naturalWidth, stageSize.height / image.naturalHeight)
                : Math.min(stageSize.width / image.naturalWidth, stageSize.height / image.naturalHeight);
            state.x = (stageSize.width - image.naturalWidth * baseScale) / 2;
            state.y = (stageSize.height - image.naturalHeight * baseScale) / 2;
            state.processed = false;
            updateSlider();
            render();
        }

        function setMode(mode) {
            state.mode = mode === 'fit' ? 'fit' : 'crop';
            state.processed = false;
            reset();
        }

        function exportCanvas() {
            const stageSize = dimensions();
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = outputWidth;
            canvas.height = outputHeight;
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, outputWidth, outputHeight);
            const scale = outputWidth / stageSize.width;
            context.drawImage(image, state.x * scale, state.y * scale, state.width * scale, state.height * scale);
            return canvas;
        }

        function loadFile(file) {
            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            if (state.url) {
                URL.revokeObjectURL(state.url);
            }

            state.url = URL.createObjectURL(file);
            state.loaded = false;
            state.processed = false;
            editor.hidden = false;
            image.onload = () => {
                state.loaded = true;
                window.requestAnimationFrame(reset);
            };
            image.onerror = () => {
                state.loaded = false;
                editor.hidden = true;
                window.alert('The selected file could not be opened as an image.');
            };
            image.src = state.url;
        }

        input.addEventListener('change', () => loadFile(input.files && input.files[0]));

        modeInputs.forEach(modeInput => {
            modeInput.addEventListener('change', () => {
                if (modeInput.checked) {
                    setMode(modeInput.value);
                }
            });
        });

        slider.addEventListener('input', () => {
            state.zoom = Number(slider.value);
            state.processed = false;
            render();
        });

        resetButton?.addEventListener('click', reset);

        stage.addEventListener('pointerdown', event => {
            if (!state.loaded || state.mode !== 'crop') {
                return;
            }

            state.drag = { x: event.clientX, y: event.clientY, startX: state.x, startY: state.y };
            stage.classList.add('is-dragging');
            stage.setPointerCapture(event.pointerId);
        });

        stage.addEventListener('pointermove', event => {
            if (!state.drag) {
                return;
            }

            state.x = state.drag.startX + (event.clientX - state.drag.x);
            state.y = state.drag.startY + (event.clientY - state.drag.y);
            state.processed = false;
            render();
        });

        function stopDragging() {
            state.drag = null;
            stage.classList.remove('is-dragging');
        }

        stage.addEventListener('pointerup', stopDragging);
        stage.addEventListener('pointercancel', stopDragging);

        form.addEventListener('submit', async event => {
            if (!state.loaded || state.processed || !input.files || input.files.length === 0) {
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
