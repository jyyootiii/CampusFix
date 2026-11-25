<?php
/**
 * CampusFix — Reusable Footer Component
 */
if (!function_exists('getBasePath')) {
    require_once __DIR__ . '/auth.php';
}
$base = getBasePath();
?>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <p><strong>CampusFix</strong> &copy; 2026 &mdash; Smart Campus Complaint &amp; Maintenance Management System</p>
        <p style="margin-top: 4px;">
            <a href="<?php echo $base; ?>home.html">Home</a> | 
            <a href="<?php echo $base; ?>about.html">About</a> | 
            <a href="<?php echo $base; ?>knowledge.html">Knowledge Centre</a> | 
            <a href="<?php echo $base; ?>contact.html">Contact</a>
        </p>
    </footer>

    <script src="<?php echo $base; ?>assets/js/script.js"></script>
</body>
</html>
