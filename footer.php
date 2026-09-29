<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
</main><!-- #main -->

<!-- 页脚 -->
<?php $footer_nav_items = function_exists('brave_get_footer_nav_items') ? brave_get_footer_nav_items() : array(); ?>
<footer class="footer">
    <?php if (!empty($footer_nav_items)) : ?>
    <nav class="footer-nav" aria-label="<?php esc_attr_e('页脚导航', 'brave-love'); ?>">
        <ul class="footer-nav-list">
            <?php foreach ($footer_nav_items as $item) : ?>
            <li class="footer-nav-item">
                <a href="<?php echo esc_url($item['url']); ?>" class="footer-nav-link"><?php echo esc_html($item['label']); ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php if (brave_theme_mod_enabled('brave_pv_enabled', true)) : ?>
    <?php $pv = brave_get_pv_stats(); ?>
    <div class="footer-text footer-stats">
        <span class="pv-stats-inline">
            <span class="pv-label"><?php echo esc_html(brave_get_pv_display_text('today_prefix')); ?></span>
            <span class="pv-number"><?php echo number_format($pv['today_count']); ?></span>
            <span class="pv-label"><?php echo esc_html(brave_get_pv_display_text('today_suffix')); ?></span>
            <span class="pv-separator">·</span>
            <span class="pv-label"><?php echo esc_html(brave_get_pv_display_text('total_prefix')); ?></span>
            <span class="pv-number"><?php echo number_format($pv['total_count']); ?></span>
            <span class="pv-label"><?php echo esc_html(brave_get_pv_display_text('total_suffix')); ?></span>
        </span>
    </div>
    <?php endif; ?>
    <div class="footer-text footer-signoff">
        <span class="footer-signoff-copyright">© <?php echo esc_html(wp_date('Y')); ?> <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a></span>
        <span class="footer-slogan"><?php _e('用 ❤️ 记录我们的故事', 'brave-love'); ?></span>
    </div>
</footer>

<!-- 返回顶部 -->
<button class="back-to-top" id="backToTop" aria-label="<?php _e('返回顶部', 'brave-love'); ?>">
    ↑
</button>

<?php wp_footer(); ?>
</body>
</html>
