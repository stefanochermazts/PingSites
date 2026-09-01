<script>
    (function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const intervalMs = 120000;

        setInterval(function () {
            if (document.hidden) {
                return;
            }

            const active = document.activeElement;
            if (active && active !== document.body && active.closest('a, button, input, select, textarea')) {
                return;
            }

            window.location.reload();
        }, intervalMs);
    })();
</script>
