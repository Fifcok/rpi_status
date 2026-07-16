<?php
/**
 * Wspólna stopka HTML. Oczekuje opcjonalnej zmiennej $pageScripts (array ścieżek do JS).
 */

declare(strict_types=1);

$pageScripts = $pageScripts ?? [];
?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= h(BASE_PATH) ?>/assets/js/app.js"></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= h(BASE_PATH . $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
