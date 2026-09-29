<?php
/**
 * Brave Love Theme Functions
 *
 * @package Brave_Love
 */

// 定义常量
define('BRAVE_VERSION', '1.2.3');
define('BRAVE_BOOTSTRAP_VERSION', '5.3.2');
define('BRAVE_PHOTOSWIPE_VERSION', '5.4.2');
define('BRAVE_REWRITE_VERSION', 'travel-plan-v1');
define('BRAVE_WEATHER_REFRESH_MS', 5 * MINUTE_IN_SECONDS * 1000);
define('BRAVE_DIR', get_template_directory());
define('BRAVE_URI', get_template_directory_uri());

/**
 * 主题初始化
 */
function brave_setup() {
    // 加载语言包
    load_theme_textdomain('brave-love', BRAVE_DIR . '/languages');

    // 添加主题支持
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ));
    add_theme_support('responsive-embeds');
    add_theme_support('customize-selective-refresh-widgets');

    // 设置特色图片尺寸
    set_post_thumbnail_size(400, 400, true);
    add_image_size('timeline-thumb', 300, 300, true);

    // 注册导航菜单
    register_nav_menus(array(
        'primary' => __('主导航', 'brave-love'),
    ));
}
add_action('after_setup_theme', 'brave_setup');

/**
 * 标记主题 rewrite 规则需要在下一次请求时刷新。
 */
function brave_schedule_rewrite_refresh() {
    delete_option('brave_rewrite_version');
}
add_action('after_switch_theme', 'brave_schedule_rewrite_refresh');

/**
 * 在主题路由版本变化后仅刷新一次 rewrite 规则。
 */
function brave_maybe_flush_rewrite_rules() {
    static $did_flush = false;

    if ($did_flush || wp_installing()) {
        return;
    }

    $stored_version = (string) get_option('brave_rewrite_version', '');
    if (BRAVE_REWRITE_VERSION === $stored_version) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('brave_rewrite_version', BRAVE_REWRITE_VERSION, false);
    $did_flush = true;
}
add_action('init', 'brave_maybe_flush_rewrite_rules', 99);

/**
 * 加载样式和脚本
 */
function brave_scripts() {
    // 本地化前端依赖，避免页面运行时依赖外部 CDN。
    wp_enqueue_style('bootstrap', BRAVE_URI . '/assets/vendor/bootstrap/bootstrap.min.css', array(), BRAVE_BOOTSTRAP_VERSION);

    // 主题字体
    wp_enqueue_style('brave-fonts', BRAVE_URI . '/assets/css/fonts.css', array(), BRAVE_VERSION);
    
    // 主题样式
    wp_enqueue_style('brave-style', BRAVE_URI . '/assets/css/theme-core.css', array('bootstrap', 'brave-fonts'), BRAVE_VERSION);
    
    // 额外样式
    wp_enqueue_style('brave-extra', BRAVE_URI . '/assets/css/brave.css', array('brave-style'), BRAVE_VERSION);
    
    wp_enqueue_script('bootstrap', BRAVE_URI . '/assets/vendor/bootstrap/bootstrap.bundle.min.js', array(), BRAVE_BOOTSTRAP_VERSION, true);
    
    // 主题脚本
    wp_enqueue_script('brave-script', BRAVE_URI . '/assets/js/brave.js', array('jquery'), BRAVE_VERSION, true);
    
    // 传递数据到 JS
    $theme_options = array(
        'love_start_datetime' => brave_get_love_start_datetime(),
        'next_anniversary_datetime' => get_theme_mod('brave_next_anniversary_datetime', ''),
        'weather_api_url' => rest_url('brave-love/v1/weather'),
        'weather_detail_api_base' => trailingslashit(rest_url('brave-love/v1/weather')),
        'weather_refresh_ms' => BRAVE_WEATHER_REFRESH_MS,
    );
    wp_localize_script('brave-script', 'braveData', $theme_options);

    // 恋爱清单存档页面样式
    if (is_post_type_archive('love_list') || is_tax('list_category')) {
        wp_enqueue_style('brave-love-list', BRAVE_URI . '/assets/css/love-list.css', array('brave-extra'), BRAVE_VERSION);
    }
    
    // 随笔说说页面样式
    if (is_page_template('page-templates/page-notes.php')) {
        wp_enqueue_style('brave-notes', BRAVE_URI . '/assets/css/notes.css', array('brave-extra'), BRAVE_VERSION);
    }

    // 关于我们页面样式
    if (is_page_template('page-templates/page-about.php')) {
        wp_enqueue_style('brave-about', BRAVE_URI . '/assets/css/about.css', array('brave-extra'), BRAVE_VERSION);
    }

    // 旅行计划页面样式
    if (is_page_template('page-templates/page-travel-plans.php') || is_singular('travel_plan')) {
        wp_enqueue_style('brave-travel', BRAVE_URI . '/assets/css/travel.css', array('brave-extra'), BRAVE_VERSION);
    }

    // 甜蜜相册页面样式和脚本
    if (is_page_template('page-templates/page-memories.php')) {
        wp_enqueue_style('photoswipe', BRAVE_URI . '/assets/vendor/photoswipe/photoswipe.css', array(), BRAVE_PHOTOSWIPE_VERSION);
        wp_enqueue_style('brave-memory', BRAVE_URI . '/assets/css/memory.css', array('brave-extra', 'photoswipe'), BRAVE_VERSION);
        wp_enqueue_script('photoswipe', BRAVE_URI . '/assets/vendor/photoswipe/photoswipe.umd.min.js', array(), BRAVE_PHOTOSWIPE_VERSION, true);
        wp_enqueue_script('photoswipe-lightbox', BRAVE_URI . '/assets/vendor/photoswipe/photoswipe-lightbox.umd.min.js', array('photoswipe'), BRAVE_PHOTOSWIPE_VERSION, true);
        wp_enqueue_script('brave-memory', BRAVE_URI . '/assets/js/memory.js', array('photoswipe', 'photoswipe-lightbox'), BRAVE_VERSION, true);
    }
}
add_action('wp_enqueue_scripts', 'brave_scripts');

/**
 * 判断是否为恋爱清单前台主查询。
 */
function brave_is_love_list_archive_query($query) {
    return $query instanceof WP_Query
        && !is_admin()
        && $query->is_main_query()
        && ($query->is_post_type_archive('love_list') || $query->is_tax('list_category'));
}

/**
 * 将恋爱清单排序规则应用到查询 SQL。
 */
function brave_apply_love_list_sort_clauses($clauses) {
    global $wpdb;

    if (false === strpos($clauses['join'], 'brave_done_meta')) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS brave_done_meta
            ON ({$wpdb->posts}.ID = brave_done_meta.post_id AND brave_done_meta.meta_key = '_is_done')";
    }

    if (false === strpos($clauses['join'], 'brave_done_date_meta')) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS brave_done_date_meta
            ON ({$wpdb->posts}.ID = brave_done_date_meta.post_id AND brave_done_date_meta.meta_key = '_done_date')";
    }

    $clauses['orderby'] = "CASE
            WHEN brave_done_meta.meta_value = '1' THEN 1
            ELSE 0
        END ASC,
        CASE
            WHEN brave_done_meta.meta_value = '1' AND brave_done_date_meta.meta_value <> '' THEN 0
            ELSE 1
        END ASC,
        CASE
            WHEN brave_done_meta.meta_value = '1' THEN brave_done_date_meta.meta_value
            ELSE NULL
        END DESC,
        {$wpdb->posts}.post_date DESC";

    return $clauses;
}

/**
 * 为恋爱清单前台列表添加状态筛选与排序。
 */
function brave_filter_love_list_archive_query($query) {
    if (!brave_is_love_list_archive_query($query)) {
        return;
    }

    // 恋爱清单前台始终展示全部条目，不再分页。
    $query->set('posts_per_page', -1);
    $query->set('nopaging', true);
    $query->set('ignore_sticky_posts', true);
    $query->set('brave_love_list_sort', true);

    $status = isset($_GET['filter_status']) ? sanitize_key(wp_unslash($_GET['filter_status'])) : '';

    if (!in_array($status, array('done', 'pending'), true)) {
        return;
    }

    $meta_query = $query->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : array();

    if ('done' === $status) {
        $meta_query[] = array(
            'key' => '_is_done',
            'value' => '1',
            'compare' => '=',
        );
    } else {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_is_done',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_is_done',
                'value' => '1',
                'compare' => '!=',
            ),
        );
    }

    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'brave_filter_love_list_archive_query');

/**
 * 恋爱清单默认按完成状态和完成日期排序：
 * 未完成置顶，已完成按完成日期倒序，最早完成的排在最后。
 */
function brave_sort_love_list_archive_clauses($clauses, $query) {
    if (!brave_is_love_list_archive_query($query) || !$query->get('brave_love_list_sort')) {
        return $clauses;
    }

    return brave_apply_love_list_sort_clauses($clauses);
}
add_filter('posts_clauses', 'brave_sort_love_list_archive_clauses', 10, 2);

/**
 * 判断是否为后台恋爱清单管理列表主查询。
 */
function brave_is_love_list_admin_query($query) {
    global $pagenow;

    return $query instanceof WP_Query
        && is_admin()
        && $query->is_main_query()
        && 'edit.php' === $pagenow
        && 'love_list' === $query->get('post_type');
}

/**
 * 后台恋爱清单管理列表默认沿用前台排序规则；
 * 如用户手动点击排序列，则尊重后台显式排序。
 */
function brave_sort_love_list_admin_query($query) {
    if (!brave_is_love_list_admin_query($query)) {
        return;
    }

    $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : '';

    if ('' !== $orderby) {
        return;
    }

    $query->set('brave_love_list_sort', true);
}
add_action('pre_get_posts', 'brave_sort_love_list_admin_query');

/**
 * 为后台恋爱清单管理列表应用默认排序。
 */
function brave_sort_love_list_admin_clauses($clauses, $query) {
    if (!brave_is_love_list_admin_query($query) || !$query->get('brave_love_list_sort')) {
        return $clauses;
    }

    return brave_apply_love_list_sort_clauses($clauses);
}
add_filter('posts_clauses', 'brave_sort_love_list_admin_clauses', 10, 2);

/**
 * 获取恋爱清单归档页的规范 URL。
 */
function brave_get_love_list_archive_canonical_url() {
    if (!(is_post_type_archive('love_list') || is_tax('list_category'))) {
        return '';
    }

    $base_url = is_tax('list_category')
        ? get_term_link(get_queried_object())
        : get_post_type_archive_link('love_list');

    if (is_wp_error($base_url) || empty($base_url)) {
        return '';
    }

    $status = isset($_GET['filter_status']) ? sanitize_key(wp_unslash($_GET['filter_status'])) : '';

    if (!in_array($status, array('done', 'pending'), true)) {
        $status = '';
    }

    return $status ? add_query_arg('filter_status', $status, $base_url) : $base_url;
}

/**
 * 恋爱清单已取消分页，统一清理旧分页和无效参数。
 */
function brave_redirect_love_list_pagination() {
    if (is_admin()) {
        return;
    }

    if (!(is_post_type_archive('love_list') || is_tax('list_category'))) {
        return;
    }

    if (is_customize_preview() || is_preview() || is_feed()) {
        return;
    }

    $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

    if (!in_array($request_method, array('GET', 'HEAD'), true)) {
        return;
    }

    $has_legacy_pagination = (int) get_query_var('paged') > 1;
    $status = isset($_GET['filter_status']) ? sanitize_key(wp_unslash($_GET['filter_status'])) : '';
    $has_invalid_status = isset($_GET['filter_status']) && !in_array($status, array('done', 'pending'), true);
    $allowed_query_args = array('filter_status');
    $unexpected_query_args = array_diff(array_keys($_GET), $allowed_query_args);

    if (!$has_legacy_pagination && !$has_invalid_status && empty($unexpected_query_args)) {
        return;
    }

    $target_url = brave_get_love_list_archive_canonical_url();

    if (empty($target_url)) {
        return;
    }

    wp_safe_redirect($target_url, 302);
    exit;
}
add_action('template_redirect', 'brave_redirect_love_list_pagination');

/**
 * 为恋爱清单归档页输出 canonical，统一 SEO 收口。
 */
function brave_output_love_list_canonical() {
    $canonical_url = brave_get_love_list_archive_canonical_url();

    if (empty($canonical_url)) {
        return;
    }

    echo '<link rel="canonical" href="' . esc_url($canonical_url) . '">' . "\n";
}
add_action('wp_head', 'brave_output_love_list_canonical', 1);

/**
 * 加载后台样式和脚本
 */
function brave_admin_scripts($hook) {
    $theme_uri = BRAVE_URI;
    $version = BRAVE_VERSION;
    
    // 在所有后台页面加载样式
    wp_enqueue_style('brave-admin', $theme_uri . '/assets/css/admin.css', array(), $version);

    if ('settings_page_brave-weather' === $hook) {
        wp_enqueue_script('jquery-ui-sortable');
    }

    if (in_array($hook, array('post.php', 'post-new.php'), true)) {
        wp_enqueue_media();
        wp_enqueue_script('brave-admin', $theme_uri . '/assets/js/admin.js', array('jquery'), $version, true);
    }
}
add_action('admin_enqueue_scripts', 'brave_admin_scripts');

/**
 * 注册自定义文章类型
 */
require BRAVE_DIR . '/inc/post-types.php';

/**
 * 自定义字段
 */
require BRAVE_DIR . '/inc/meta-boxes.php';

/**
 * 工具函数
 */
require BRAVE_DIR . '/inc/helpers.php';

/**
 * Customizer 设置
 */
require BRAVE_DIR . '/inc/customizer.php';

/**
 * QWeather 天气服务
 */
require BRAVE_DIR . '/inc/weather-service.php';

/**
 * 天气管理
 */
require BRAVE_DIR . '/inc/weather-admin.php';

/**
 * 相册数据管理
 */
require BRAVE_DIR . '/inc/gallery-admin.php';

/**
 * 点滴摘要迁移
 */
require BRAVE_DIR . '/inc/moment-excerpt-migration.php';

/**
 * 禁用 WordPress 默认功能
 */
function brave_disable_defaults() {
    // 禁用 Emoji
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    
    // 移除 wp-embed
    wp_deregister_script('wp-embed');
}
add_action('init', 'brave_disable_defaults');

/**
 * 自定义上传图片质量
 */
function brave_jpeg_quality($quality) {
    return 85;
}
add_filter('jpeg_quality', 'brave_jpeg_quality');

/**
 * 移除导航菜单的 li 多余 class/id
 */
function brave_nav_menu_css_class($classes) {
    $keep = array('current-menu-item', 'menu-item-has-children', 'dropdown');
    return array_intersect($classes, $keep);
}
add_filter('nav_menu_css_class', 'brave_nav_menu_css_class', 10, 1);

/**
 * 获取纪念日数据
 */
function brave_get_anniversaries() {
    $anniversaries = get_option('brave_anniversaries', array());
    $result = array();
    
    if (!empty($anniversaries) && is_array($anniversaries)) {
        foreach ($anniversaries as $item) {
            $name = sanitize_text_field($item['name'] ?? '');
            $date_value = brave_sanitize_iso_date($item['date'] ?? '');

            if ('' === $name || '' === $date_value) {
                continue;
            }

            $date = strtotime($date_value);
            $now = strtotime(current_time('Y-m-d'));
            $diff = $date - $now;
            $days = abs(floor($diff / 86400));
            $is_countdown = $diff > 0;
            
            $result[] = array(
                'name' => $name,
                'date' => $date_value,
                'days' => $days,
                'is_countdown' => $is_countdown,
            );
        }
    }
    
    // 按倒计时天数排序（即将到的在前）
    usort($result, function($a, $b) {
        if ($a['is_countdown'] && !$b['is_countdown']) return -1;
        if (!$a['is_countdown'] && $b['is_countdown']) return 1;
        return $a['days'] - $b['days'];
    });
    
    return $result;
}

/**
 * 获取头像 URL。
 *
 * 优先使用站点内头像，缺失时回退到主题内联 SVG 占位头像，
 * 避免页面头像依赖外部头像服务。
 */
function brave_get_avatar_url($email, $name = '', $size = 100, $background = 'ff5162') {
    $avatar_url = brave_get_safe_wp_avatar_url($email, $size);

    if ($avatar_url) {
        return $avatar_url;
    }

    if (empty($name) && is_string($email)) {
        $email_parts = explode('@', $email);
        $name = $email_parts[0];
    }

    return brave_get_placeholder_avatar_url($name, $size, $background);
}

/**
 * 页面模板跳转
 */
function brave_page_template_redirect() {
    if (is_post_type_archive('moment')) {
        wp_safe_redirect(brave_get_page_link('moments'));
        exit;
    }
}
add_action('template_redirect', 'brave_page_template_redirect');

/**
 * 添加 body class
 */
function brave_body_classes($classes) {
    if (is_page_template('page-templates/page-home.php')) {
        $classes[] = 'page-home';
    }
    return $classes;
}
add_filter('body_class', 'brave_body_classes');

/**
 * 处理前台发布说说
 */
function brave_handle_frontend_note_publish() {
    // 检查是否有提交
    if (!isset($_POST['publish_note']) || !isset($_POST['publish_note_nonce'])) {
        return;
    }

    $publish_note_nonce = sanitize_text_field(wp_unslash($_POST['publish_note_nonce']));

    // 验证 nonce
    if (!wp_verify_nonce($publish_note_nonce, 'publish_note_action')) {
        wp_die('安全验证失败');
    }

    // 检查用户是否登录
    if (!is_user_logged_in()) {
        wp_die('请先登录');
    }

    $note_post_type = get_post_type_object('note');
    $publish_cap = ($note_post_type && !empty($note_post_type->cap->publish_posts)) ? $note_post_type->cap->publish_posts : 'publish_posts';
    if (!current_user_can($publish_cap)) {
        wp_die('权限不足');
    }
    
    // 获取内容
    $content = isset($_POST['note_content']) ? sanitize_textarea_field(wp_unslash($_POST['note_content'])) : '';

    if (empty($content)) {
        wp_die('请输入说说内容');
    }

    // 获取心情和思念度
    $mood = isset($_POST['note_mood']) ? sanitize_text_field(wp_unslash($_POST['note_mood'])) : '😊';
    $miss_level = isset($_POST['note_miss_level']) ? max(1, min(5, intval(wp_unslash($_POST['note_miss_level'])))) : 3;

    // 创建文章
    $post_data = array(
        'post_title'   => wp_trim_words($content, 20),
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'note',
        'post_author'  => get_current_user_id(),
    );
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        wp_die('发布失败，请重试');
    }
    
    // 保存元数据
    update_post_meta($post_id, '_note_mood', $mood);
    update_post_meta($post_id, '_note_miss_level', $miss_level);
    
    // 重定向回当前页面
    $redirect_url = wp_get_referer();
    if (!$redirect_url) {
        $redirect_url = brave_get_page_link('notes');
    }

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('template_redirect', 'brave_handle_frontend_note_publish');

/**
 * 祝福留言必须审核
 * 所有新评论都需要管理员审核
 */
add_filter('pre_comment_approved', 'brave_comment_moderation', 99, 2);
function brave_comment_moderation($approved, $commentdata) {
    $user_id = 0;

    if (isset($commentdata['user_id'])) {
        $user_id = absint($commentdata['user_id']);
    } elseif (isset($commentdata['user_ID'])) {
        $user_id = absint($commentdata['user_ID']);
    }

    // 能审核评论的登录用户（含管理员）自己的留言直接通过，避免祝福墙自己留言还要再审一遍。
    if ($user_id && user_can($user_id, 'moderate_comments')) {
        return 1;
    }

    return 0;
}

/**
 * 当前请求是否应计入 PV。
 *
 * @return bool
 */
function brave_should_track_pv() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    if ((function_exists('wp_is_json_request') && wp_is_json_request()) || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }

    if (is_customize_preview() || is_preview() || is_feed() || is_robots() || is_trackback()) {
        return false;
    }

    return true;
}

/**
 * PV 访问统计
 */
function brave_update_pv_stats() {
    // 检查是否启用
    if (!brave_theme_mod_enabled('brave_pv_enabled', true) || !brave_should_track_pv()) {
        return;
    }

    $today = wp_date('Y-m-d');
    
    // 获取统计数据
    $stats = get_option('brave_pv_stats', array(
        'today_date' => $today,
        'today_count' => 0,
        'total_count' => 0,
    ));
    
    // 检查是否跨天，重置当日计数
    if ($stats['today_date'] !== $today) {
        $stats['today_date'] = $today;
        $stats['today_count'] = 0;
    }

    // 检查手动覆盖，并确保本次请求不会再额外自增对应计数。
    $manual_today = get_theme_mod('brave_pv_today_manual');
    $manual_total = get_theme_mod('brave_pv_total_manual');
    $skip_today_increment = false;
    $skip_total_increment = false;

    if ($manual_today !== '' && $manual_today !== false && $manual_today !== null) {
        $stats['today_count'] = intval($manual_today);
        $skip_today_increment = true;
        remove_theme_mod('brave_pv_today_manual');
    }

    if ($manual_total !== '' && $manual_total !== false && $manual_total !== null) {
        $stats['total_count'] = intval($manual_total);
        $skip_total_increment = true;
        remove_theme_mod('brave_pv_total_manual');
    }

    if (!$skip_today_increment) {
        $stats['today_count']++;
    }

    if (!$skip_total_increment) {
        $stats['total_count']++;
    }
    
    // 保存
    update_option('brave_pv_stats', $stats);
}
add_action('wp', 'brave_update_pv_stats');

/**
 * 获取 PV 统计数据
 */
function brave_get_pv_stats() {
    $stats = get_option('brave_pv_stats', array(
        'today_date' => wp_date('Y-m-d'),
        'today_count' => 0,
        'total_count' => 0,
    ));
    
    // 如果日期不对，今日计数为0
    if ($stats['today_date'] !== wp_date('Y-m-d')) {
        $stats['today_count'] = 0;
    }
    
    return $stats;
}

/**
 * 获取 PV 显示文字
 */
function brave_get_pv_display_text($type = 'today_prefix') {
    $defaults = array(
        'today_prefix' => __('你是今日第', 'brave-love'),
        'today_suffix' => __('位访客', 'brave-love'),
        'total_prefix' => __('累计第', 'brave-love'),
        'total_suffix' => __('位访客', 'brave-love'),
    );
    
    return get_theme_mod("brave_pv_{$type}", $defaults[$type] ?? '');
}
