<?php
// footer.php - shared footer and closing tags
?>
</main>
<footer class="site-footer">
    <div class="container" style="padding:16px 0;color:var(--muted);font-size:0.9rem;">
        © <?php echo date('Y'); ?> <?php echo htmlspecialchars((string)get_setting('site_name', 'ShareHub')); ?>
    </div>
</footer>
</body>
</html>
