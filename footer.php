<!-- Footer -->
<footer class="bg-light mt-5 py-4">
    <div class="container text-center">
        <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> Student Payment Management System. Built by Just Dream Ltd </p>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Font Awesome -->

<!-- Global Loading Spinner Markup -->
<div id="globalSpinner" class="global-spinner" aria-hidden="true" style="display:none;">
    <div class="spinner-inner">
        <?php if (file_exists(__DIR__ . '/img/loading.gif')): ?>
            <img src="img/loading.gif" alt="Loading..." />
        <?php else: ?>
            <svg width="60" height="60" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img">
                <circle cx="25" cy="25" r="20" stroke="#007bff" stroke-width="4" fill="none" stroke-linecap="round">
                    <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite" />
                </circle>
            </svg>
        <?php endif; ?>
    </div>
    <button id="globalSpinnerRefresh" type="button" aria-label="Refresh page" title="Refresh page" 
        style="position:absolute;left:50%;transform:translateX(-50%);bottom:12px;background:rgba(255,255,255,0.9);border:0;padding:8px 10px;border-radius:6px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        <i class="fas fa-sync-alt" style="font-size:16px;color:#007bff;"></i>
    </button>
</div>

<!-- Loading script -->
<script src="js/loading.js"></script>
<!-- Navbar side-drawer (mobile) -->
<script src="js/navbar.js"></script>
<script>
// Refresh button inside global spinner
(function(){
    var btn = document.getElementById('globalSpinnerRefresh');
    if (!btn) return;
    btn.addEventListener('click', function(e){
        e.stopPropagation();
        try { window.location.reload(); } catch(err) { window.location.href = window.location.href; }
    });
})();
</script>