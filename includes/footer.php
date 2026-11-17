    </main>
    <footer class="site-footer">
        <div class="container footer-content">
            <p class="footer-brand"><?= e(SITE_NAME) ?></p>
            <ul class="trust-list" aria-label="Informations de confiance">
                <li>Paiement à la livraison</li>
                <li>Livraison à Abidjan et hors Abidjan</li>
            </ul>
        </div>
    </footer>
    <script src="<?= e(site_url('assets/js/app.js?v=' . filemtime(__DIR__ . '/../assets/js/app.js'))) ?>" defer></script>
</body>
</html>
