(function(){
    const spinner = document.getElementById('globalSpinner');
    if (!spinner) return;

    let activeRequests = 0;
    let hideTimeout = null;

    function showSpinner() {
        clearTimeout(hideTimeout);
        activeRequests++;
        spinner.style.display = 'flex';
        spinner.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        // show waiting cursor while spinner is visible
        try { document.documentElement.style.cursor = 'wait'; document.body.style.cursor = 'wait'; } catch(e) {}
        try { localStorage.setItem('globalSpinner_visible_since', Date.now().toString()); } catch(e) {}
    }

    function hideSpinner(force) {
        if (force) activeRequests = 0;
        activeRequests = Math.max(0, activeRequests - 1);
        if (activeRequests === 0) {
            hideTimeout = setTimeout(() => {
                spinner.setAttribute('aria-hidden', 'true');
                spinner.style.display = 'none';
                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
                // restore default cursor
                try { document.documentElement.style.cursor = ''; document.body.style.cursor = ''; } catch(e) {}
                try { localStorage.removeItem('globalSpinner_visible_since'); } catch(e) {}
            }, 200);
        }
    }

    window.GlobalSpinner = { show: showSpinner, hide: hideSpinner };

    // Wrap fetch
    if (window.fetch) {
        const origFetch = window.fetch.bind(window);
        window.fetch = function(...args) {
            showSpinner();
            return origFetch(...args)
                .then(res => { hideSpinner(); return res; })
                .catch(err => { hideSpinner(true); throw err; });
        };
    }

    // Wrap XHR
    (function() {
        const XHR = window.XMLHttpRequest;
        if (!XHR) return;
        const origSend = XHR.prototype.send;
        XHR.prototype.send = function() {
            try { showSpinner(); } catch (e) {}
            this.addEventListener('loadend', function() { try { hideSpinner(); } catch(e){} });
            this.addEventListener('error', function() { try { hideSpinner(true); } catch(e){} });
            this.addEventListener('abort', function() { try { hideSpinner(true); } catch(e){} });
            return origSend.apply(this, arguments);
        };
    })();

    // Intercept forms
    document.addEventListener('submit', function(e) {
        try {
            const form = e.target;
            if (form && form.tagName === 'FORM') {
                const target = form.getAttribute('target');
                if (target && target !== '_self') return;
                showSpinner();
                setTimeout(() => hideSpinner(), 5000);
            }
        } catch (err) {}
    }, true);

    // Intercept link clicks
    document.addEventListener('click', function(e) {
        const el = e.target.closest && e.target.closest('a');
        if (!el) return;
        const href = el.getAttribute('href');
        const target = el.getAttribute('target');
        if (!href) return;
        if (href.startsWith('#') || href.startsWith('javascript:')) return;
        if (target && target !== '_self') return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        showSpinner();
        setTimeout(() => hideSpinner(), 5000);
    }, true);

    window.addEventListener('beforeunload', function() { try { showSpinner(); } catch(e){} });

})();

