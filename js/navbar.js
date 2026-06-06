(function() {
    const collapseId = 'mainNav';
    const overlayId = 'navbarOverlay';

    const collapseEl = document.getElementById(collapseId);
    const overlayEl = document.getElementById(overlayId);
    const body = document.body;

    if (!collapseEl || !overlayEl) return;

    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, {toggle: false});

    const openMenu = () => {
        bsCollapse.show();
    };

    const closeMenu = () => {
        bsCollapse.hide();
    };

    const setOverlay = (show) => {
        overlayEl.classList.toggle('show', show);
        overlayEl.setAttribute('aria-hidden', show ? 'false' : 'true');
        body.classList.toggle('offcanvas-open', show);
    };

    collapseEl.addEventListener('shown.bs.collapse', () => setOverlay(true));
    collapseEl.addEventListener('hidden.bs.collapse', () => setOverlay(false));

    overlayEl.addEventListener('click', closeMenu);

    // Swipe gestures for mobile
    let touchStartX = null;
    let touchStartY = null;

    const handleTouchStart = (event) => {
        if (!event.touches || event.touches.length !== 1) return;
        const touch = event.touches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
    };

    const handleTouchEnd = (event) => {
        if (touchStartX === null || touchStartY === null) return;
        const touch = event.changedTouches ? event.changedTouches[0] : event;
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;

        // Ignore mostly vertical swipes
        if (Math.abs(deltaY) > Math.abs(deltaX)) {
            touchStartX = null;
            touchStartY = null;
            return;
        }

        const threshold = 60;
        const edgeZone = 30;

        const isOpen = collapseEl.classList.contains('show');

        // Open when swipe right from left edge
        if (!isOpen && touchStartX <= edgeZone && deltaX > threshold) {
            openMenu();
        }

        // Close when swipe left inside open menu
        if (isOpen && touchStartX > 0 && deltaX < -threshold) {
            closeMenu();
        }

        touchStartX = null;
        touchStartY = null;
    };

    document.addEventListener('touchstart', handleTouchStart, {passive: true});
    document.addEventListener('touchend', handleTouchEnd, {passive: true});

    // Ensure body doesn't stay locked if menu starts hidden
    document.addEventListener('DOMContentLoaded', () => {
        if (!collapseEl.classList.contains('show')) {
            setOverlay(false);
        }
    });
})();
