<script>
    (function () {
        const intervalMs = 120000;

        setInterval(function () {
            if (!document.hidden) {
                window.location.reload();
            }
        }, intervalMs);
    })();
</script>
