<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * QWeather REST 路由注册。
 *
 * @package Brave_Love
 */

function brave_get_home_weather_payload() {
    if (!function_exists('brave_is_weather_enabled') || !brave_is_weather_enabled()) {
        return brave_qweather_build_home_weather_payload(false);
    }

    $config = brave_get_qweather_config();
    if (empty($config['configured'])) {
        return brave_qweather_build_home_weather_payload(false);
    }

    $cached = brave_qweather_get_home_snapshot_record();
    if (!empty($cached['data'])) {
        return $cached['data'];
    }

    $payload = brave_qweather_build_home_weather_payload(false);
    if (!brave_qweather_home_payload_has_errors($payload)) {
        brave_qweather_store_home_snapshot($payload);
        return $payload;
    }

    $backup = brave_qweather_get_home_snapshot_record(true);

    if (brave_qweather_home_payload_has_successful_cities($payload)) {
        if (!empty($backup['data'])) {
            $payload = brave_qweather_fill_home_payload_city_errors(
                $payload,
                $backup['data'],
                __('天气接口短时波动，部分城市已回退到稍早缓存。', 'brave-love')
            );
        }

        brave_qweather_store_home_snapshot($payload, false);
        return $payload;
    }

    if (!empty($backup['data'])) {
        return brave_qweather_mark_home_snapshot_stale(
            $backup['data'],
            __('天气接口短时波动，已回退到稍早缓存。', 'brave-love')
        );
    }

    return $payload;
}

function brave_get_weather_city_by_index($index) {
    $cities = function_exists('brave_get_weather_cities') ? array_values(brave_get_weather_cities()) : array();

    if (!isset($cities[$index]) || !is_array($cities[$index])) {
        return new WP_Error(
            'brave_weather_city_not_found',
            __('未找到对应的天气城市配置。', 'brave-love'),
            array('status' => 404)
        );
    }

    return $cities[$index];
}

function brave_get_weather_city_detail_payload($index) {
    if (!function_exists('brave_is_weather_enabled') || !brave_is_weather_enabled()) {
        return new WP_Error(
            'brave_weather_disabled',
            __('天气功能当前未启用。', 'brave-love'),
            array('status' => 503)
        );
    }

    $config = brave_get_qweather_config();
    if (empty($config['configured'])) {
        return new WP_Error(
            'brave_qweather_not_configured',
            __('QWeather 尚未完成配置。', 'brave-love'),
            array('status' => 503)
        );
    }

    $city = brave_get_weather_city_by_index($index);
    if (is_wp_error($city)) {
        return $city;
    }

    return brave_qweather_normalize_city_weather($city, $index, true);
}

/**
 * 天气 REST 使用的客户端 IP。
 *
 * 只取 REMOTE_ADDR，避免 X-Forwarded-For 被伪造后绕过限流。
 *
 * @return string
 */
function brave_weather_rest_client_ip() {
    $ip = '';

    if (!empty($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
        $ip = trim((string) wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * 天气 REST 的公开读接口限流。
 *
 * @return true|WP_Error
 */
function brave_weather_rest_permission() {
    $limit = 30;
    $window = MINUTE_IN_SECONDS;
    $key = 'brave_wx_rl_' . md5(brave_weather_rest_client_ip());
    $count = (int) get_transient($key);

    if ($count >= $limit) {
        return new WP_Error(
            'brave_weather_rate_limited',
            __('天气请求过于频繁，请稍后再试。', 'brave-love'),
            array('status' => 429)
        );
    }

    set_transient($key, $count + 1, $window);

    return true;
}

function brave_register_weather_rest_routes() {
    register_rest_route('brave-love/v1', '/weather', array(
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => 'brave_weather_rest_permission',
        'callback' => function () {
            return rest_ensure_response(brave_get_home_weather_payload());
        },
    ));

    register_rest_route('brave-love/v1', '/weather/(?P<index>\d+)', array(
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => 'brave_weather_rest_permission',
        'callback' => function ($request) {
            $index = absint($request['index']);
            return rest_ensure_response(brave_get_weather_city_detail_payload($index));
        },
    ));
}
add_action('rest_api_init', 'brave_register_weather_rest_routes');
