
(function (global) {
    const OVERLAY_SELECTOR = '.gda-spinner-overlay';

    const ensureContainerPosition = function (container) {
        const computedStyle = global.getComputedStyle(container);

        if (computedStyle.position === 'static') {
            container.dataset.gdaSpinnerPreviousPosition = 'static';
            container.style.position = 'relative';
        }
    };

    const restoreContainerPosition = function (container) {
        if (container.dataset.gdaSpinnerPreviousPosition === 'static') {
            container.style.position = '';
            delete container.dataset.gdaSpinnerPreviousPosition;
        }
    };

    const buildOverlay = function (text) {
        const overlay = global.document.createElement('div');
        overlay.className = 'gda-spinner-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');

        overlay.innerHTML = [
            '<div class="gda-spinner-shell">',
            '  <div class="gda-spinner-bubbles" aria-hidden="true">',
            '    <span></span><span></span><span></span>',
            '  </div>',
            '  <div class="gda-spinner-icon" aria-hidden="true">&#129343;</div>',
            '  <div class="gda-spinner-text"></div>',
            '</div>'
        ].join('');

        overlay.querySelector('.gda-spinner-text').textContent = text || 'Chargement en cours...';

        return overlay;
    };

    const show = function (container, options = {}) {
        if (!container) {
            return;
        }

        const text = options.text || 'Chargement en cours...';
        let overlay = container.querySelector(OVERLAY_SELECTOR);

        ensureContainerPosition(container);
        container.classList.add('is-loading');
        container.setAttribute('aria-busy', 'true');

        if (!overlay) {
            overlay = buildOverlay(text);
            container.appendChild(overlay);
        } else {
            const textNode = overlay.querySelector('.gda-spinner-text');
            if (textNode) {
                textNode.textContent = text;
            }
        }
    };

    const hide = function (container) {
        if (!container) {
            return;
        }

        const overlay = container.querySelector(OVERLAY_SELECTOR);
        if (overlay) {
            overlay.remove();
        }

        container.classList.remove('is-loading');
        container.removeAttribute('aria-busy');
        restoreContainerPosition(container);
    };

    global.GdaSpinner = {
        show,
        hide
    };

    // Backward compatibility for legacy code.
    global.showspinner = show;
    global.hidespinner = hide;
})(window);