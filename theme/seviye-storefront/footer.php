<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
</main>
<footer class="scp-site-footer">
    <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
