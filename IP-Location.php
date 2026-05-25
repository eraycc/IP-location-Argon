<?php
/*Plugin Name: IP属地显示-Argon专版
Description: 显示评论的IP归属地或IP地址，使用腾讯位置定位API查询IP归属地。
Author: Eray
Version: 1.1.1
Requires PHP: 5.6
Author URI: https://blog.369989.xyz/
Plugin URI: https://blog.369989.xyz/ip-location-plugin
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

// 添加插件设置链接
add_filter('plugin_action_links', function($links, $file) {
    if (plugin_basename(__FILE__) !== $file) {
        return $links;
    }
    $settings_link = '<a href="'.admin_url('options-general.php?page=ip-location-settings').'">'.esc_html__('设置').'</a>';
    array_unshift($links, $settings_link);
    return $links;
}, 10, 2);

// 创建设置页面
add_action('admin_menu', 'ip_location_settings_menu');
function ip_location_settings_menu() {
    add_options_page(
        'IP属地设置',
        'IP属地设置',
        'manage_options',
        'ip-location-settings',
        'ip_location_settings_page'
    );
}

function ip_location_settings_page() {
    ?>
    <div class="wrap">
        <h1>IP属地显示设置</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ip_location_settings');
            do_settings_sections('ip_location_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="ip_location_api_base">腾讯地图API基础地址</label></th>
                    <td>
                        <input type="text" id="ip_location_api_base" name="ip_location_api_base" 
                               value="<?php echo esc_attr(get_option('ip_location_api_base', 'https://apis.map.qq.com')); ?>" 
                               class="regular-text">
                        <p class="description">默认为 https://apis.map.qq.com</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_api_key">腾讯地图API密钥</label></th>
                    <td>
                        <input type="text" id="ip_location_api_key" name="ip_location_api_key" 
                               value="<?php echo esc_attr(get_option('ip_location_api_key')); ?>" 
                               class="regular-text">
                        <p class="description">请前往<a href="https://lbs.qq.com/" target="_blank">腾讯位置服务</a>申请密钥（必需）</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_enable">启用IP属地显示</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_enable" name="ip_location_enable" 
                               value="1" <?php checked(1, get_option('ip_location_enable', 1), true); ?>>
                        <p class="description">仅在开启时才调用API查询并显示IP归属地</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_show_full">显示完整IP归属地</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_show_full" name="ip_location_show_full" 
                               value="1" <?php checked(1, get_option('ip_location_show_full', 1), true); ?>>
                        <p class="description">开启后显示完整归属地（国家 省份 城市），关闭则只显示国家</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_show_ip">显示IP地址</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_show_ip" name="ip_location_show_ip" 
                               value="1" <?php checked(1, get_option('ip_location_show_ip', 1), true); ?>>
                        <p class="description">在归属地后追加显示IP地址</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_mask_ip">对IP地址打码</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_mask_ip" name="ip_location_mask_ip" 
                               value="1" <?php checked(1, get_option('ip_location_mask_ip', 1), true); ?>>
                        <p class="description">支持对IPv4和IPv6进行打码处理</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_cache_time">缓存时间（秒）</label></th>
                    <td>
                        <input type="number" id="ip_location_cache_time" name="ip_location_cache_time" 
                               value="<?php echo esc_attr(get_option('ip_location_cache_time', 86400)); ?>" 
                               min="0" class="regular-text">
                        <p class="description">API查询结果缓存时间，默认86400秒（24小时），设为0则不缓存</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_enable_log">启用API错误记录</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_enable_log" name="ip_location_enable_log" 
                               value="1" <?php checked(1, get_option('ip_location_enable_log'), true); ?>>
                        <p class="description">将通过<code>error_log</code>在错误日志中记录API错误信息</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <h2>缓存管理</h2>
        <p>当前缓存的IP查询结果数量：<?php echo ip_location_get_cache_count(); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field('clear_ip_cache', 'ip_cache_nonce'); ?>
            <input type="hidden" name="clear_ip_cache" value="1">
            <?php submit_button('清除所有缓存', 'secondary'); ?>
        </form>
        
        <?php
        // 处理清除缓存
        if (isset($_POST['clear_ip_cache']) && wp_verify_nonce($_POST['ip_cache_nonce'], 'clear_ip_cache')) {
            ip_location_clear_all_cache();
            echo '<div class="notice notice-success"><p>缓存已清除</p></div>';
        }
        ?>
    </div>
    <?php
}

add_action('admin_init', 'ip_location_register_settings');
function ip_location_register_settings() {
    register_setting('ip_location_settings', 'ip_location_api_base', 'sanitize_text_field');
    register_setting('ip_location_settings', 'ip_location_api_key', 'sanitize_text_field');
    register_setting('ip_location_settings', 'ip_location_enable', 'intval');
    register_setting('ip_location_settings', 'ip_location_show_full', 'intval');
    register_setting('ip_location_settings', 'ip_location_show_ip', 'intval');
    register_setting('ip_location_settings', 'ip_location_mask_ip', 'intval');
    register_setting('ip_location_settings', 'ip_location_cache_time', 'intval');
    register_setting('ip_location_settings', 'ip_location_enable_log', 'intval');
}

// 获取缓存数量
function ip_location_get_cache_count() {
    global $wpdb;
    $count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_ip_cache_%' 
         OR option_name LIKE '_transient_ip_failed_%'"
    );
    return $count ?: 0;
}

// 清除所有缓存
function ip_location_clear_all_cache() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_ip_cache_%' 
         OR option_name LIKE '_transient_timeout_ip_cache_%'
         OR option_name LIKE '_transient_ip_failed_%'
         OR option_name LIKE '_transient_timeout_ip_failed_%'"
    );
}

// 获取API密钥
function get_ip_location_api_key() {
    $api_key = trim(get_option('ip_location_api_key'));
    return empty($api_key) ? false : $api_key;
}

// 记录错误日志
function ip_location_log_error($message) {
    if (get_option('ip_location_enable_log')) {
        error_log("[IP Location Plugin] " . $message);
    }
}

// IP地址打码函数
function mask_ip_address($ip) {
    if (!get_option('ip_location_mask_ip', 1)) {
        return $ip;
    }
    
    // IPv4处理
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.*.*.' . $parts[3];
        }
    }
    // IPv6处理
    elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        if (count($parts) >= 3) {
            return $parts[0] . ':' . $parts[1] . ':*:*:' . end($parts);
        }
    }
    
    return $ip;
}

// 带缓存的CURL请求
function ip_location_curl_get($url, $ip) {
    $cache_time = get_option('ip_location_cache_time', 86400);
    
    // 如果缓存时间为0，不使用缓存
    if ($cache_time > 0) {
        // 检查是否是失败的IP（避免重复查询无法定位的IP）
        $failed_cache_key = 'ip_failed_' . md5($ip);
        $is_failed = get_transient($failed_cache_key);
        if ($is_failed !== false) {
            return $is_failed; // 返回缓存的失败结果
        }
        
        // 检查成功缓存
        $cache_key = 'ip_cache_' . md5($url);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
    }
    
    $response = wp_remote_get($url, array('timeout' => 5));
    
    if (is_wp_error($response)) {
        ip_location_log_error("CURL Error: " . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    
    if ($cache_time > 0 && $json) {
        if (isset($json["status"])) {
            if ($json["status"] == 0) {
                // 成功结果缓存
                set_transient($cache_key, $body, $cache_time);
            } elseif ($json["status"] == 382) {
                // IP无法定位的结果也缓存，避免重复查询
                set_transient($failed_cache_key, $body, $cache_time);
            }
        }
    }
    
    return $body;
}

// 获取用户位置信息
function get_user_city_e($ip, $comment_ID = null) {
    // 检查是否启用功能
    if (!get_option('ip_location_enable', 1)) {
        return "功能未启用";
    }
    
    $api_key = get_ip_location_api_key();
    
    // 检查API密钥是否配置
    if (!$api_key) {
        return "未配置密钥";
    }
    
    // 确保有评论ID
    if ($comment_ID === null) {
        $comment_ID = get_comment_ID();
        if (!$comment_ID) return "参数错误";
    }
    
    $api_base = get_option('ip_location_api_base', 'https://apis.map.qq.com');
    $api_url = "{$api_base}/ws/location/v1/ip?ip={$ip}&key={$api_key}";
    $result = ip_location_curl_get($api_url, $ip);
    
    if (!$result) {
        return "网络请求失败";
    }
    
    $json = json_decode($result, true);
    
    if ($json === null) {
        ip_location_log_error("JSON解析失败: " . print_r($result, true));
        return "数据解析失败";
    }
    
    // 处理各种状态码
    if ($json["status"] == 0) {
        $location = format_location_display($json["result"], $ip);
        update_comment_meta($comment_ID, 'Eray-IP-Location', $location);
        return $location;
    } else {
        // 返回具体的错误信息
        $error_msg = isset($json['message']) ? $json['message'] : '未知错误';
        ip_location_log_error("API错误: {$error_msg} (状态码: {$json['status']})");
        
        // 对于某些错误，保存错误信息避免重复查询
        if (in_array($json["status"], [382, 121, 190, 311])) {
            update_comment_meta($comment_ID, 'Eray-IP-Location', $error_msg);
        }
        
        return $error_msg;
    }
}

// 格式化位置显示
function format_location_display($result, $ip) {
    $show_full = get_option('ip_location_show_full', 1);
    $show_ip = get_option('ip_location_show_ip', 1);
    
    $location_parts = array();
    $ad_info = $result["ad_info"];
    
    if ($show_full) {
        // 显示完整地址
        if (!empty($ad_info["nation"])) {
            $location_parts[] = $ad_info["nation"];
        }
        if (!empty($ad_info["province"]) && $ad_info["nation"] == "中国") {
            $location_parts[] = $ad_info["province"];
        }
        if (!empty($ad_info["city"]) && $ad_info["nation"] == "中国") {
            $location_parts[] = $ad_info["city"];
        }
    } else {
        // 只显示国家
        if (!empty($ad_info["nation"])) {
            $location_parts[] = $ad_info["nation"];
        }
    }
    
    $location = implode(' ', $location_parts);
    
    // 添加IP地址
    if ($show_ip && $ip) {
        $masked_ip = mask_ip_address($ip);
        $location .= ' ' . $masked_ip;
    }
    
    return $location;
}

// 显示位置信息
function ip_location_info($comment_text) {
    // 检查是否启用功能
    if (!get_option('ip_location_enable', 1)) {
        return $comment_text;
    }
    
    $comment_ID = get_comment_ID();
    $location = get_comment_meta($comment_ID, 'Eray-IP-Location', true);
    
    // SVG图标base64编码
    $location_icon = 'data:image/svg+xml;charset=utf-8;base64,PHN2ZyB0PSIxNjYxMzAxMTk0MzQ4IiBjbGFzcz0iaWNvbiIgdmlld0JveD0iMCAwIDEwMjQgMTAyNCIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHAtaWQ9IjExMDMiIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiI+PHBhdGggZD0iTTEwMjMuOTg0MDQgNTEyYzAgMjgyLjc0OTU4Mi0yMjkuMjE2NDE4IDUxMi01MTEuOTg0IDUxMkMyMjkuMjM0NDU4IDEwMjQgMC4wMDAwNCA3OTQuNzQ5NTgyIDAuMDAwMDQgNTEyIDAuMDAwMDQgMjI5LjIzNDQxOCAyMjkuMjM0NDU4IDAgNTEyLjAwMDA0IDAgNzk0Ljc2NzYyMiAwIDEwMjMuOTg0MDQgMjI5LjIzNDQxOCAxMDIzLjk4NDA0IDUxMnoiIGZpbGw9IiM1RDlDRUMiIHAtaWQ9IjExMDQiPjwvcGF0aD48cGF0aCBkPSJNOTk4Ljg5MDQzMiAzNTMuMjA0NDgxYy0yNC4wMzE2MjUtNzMuNjg4ODQ5LTY1LjQwNDk3OC0xNDIuMTcxNzc5LTExOS43MTgxMjktMTk4LjA0NjkwNS01NC4yNDkxNTItNTUuNzgxMTI4LTEyMS40MzgxMDMtOTkuMDc4NDUyLTE5NC4yODA5NjQtMTI1LjIxODA0NGwtMjEuMjgxNjY4LTcuNjM5ODgtNi4zNzU5IDIxLjcxNzY2Yy0yLjkzNzk1NCA5LjkwNzg0NS01LjE1NTkxOSAxNy4wNjM3MzMtNi42ODk4OTYgMjEuMzc1NjY2LTE4Ljk5OTcwMyA4LjkwNTg2MS0zOS4zMTEzODYgMTYuOTM3NzM1LTU4Ljk2NzA3OCAyNC43NDk2MTQtNjIuNzE1MDIgMjQuODQxNjEyLTEyNy41NDQwMDcgNTAuNTQ1MjEtMTcyLjI2MzMwOSAxMDkuNDgyMjg5LTE5LjQ1NTY5NiAyNS42MjU2LTIxLjcwNTY2MSA1Mi4zOTExODEtNS44NTk5MDggNjkuODI4OTA5IDguMjk1ODcgOS4xMzk4NTcgMjAuMzc1NjgyIDEzLjc4MTc4NSAzNS45MDU0MzkgMTMuNzgxNzg0IDEwLjEyMzg0MiAwIDIwLjY4NzY3Ny0xLjkwNTk3IDI5Ljk5OTUzMS0zLjU3Nzk0NCA2LjU2MTg5Ny0xLjE4Nzk4MSAxMy4zNDM3OTItMi40MDU5NjIgMTcuMzI3NzI5LTIuNDA1OTYyIDAuMjk1OTk1IDAgMC41NjE5OTEgMC4wMTYgMC44Mjc5ODcgMC4wMzE5OTkgMjIuOTIxNjQyIDEuNjM5OTc0IDY1LjYyNDk3NSA5Ljc0OTg0OCAxMDMuODc0Mzc3IDE5LjcxNzY5MiA0Mi4wMzEzNDMgMTAuOTM3ODI5IDY0LjMxMjk5NSAyMC4xODc2ODUgNzMuODEyODQ3IDI1LjU3OTYwMS02LjM3NTkgNy41OTM4ODEtMjMuNDY3NjMzIDE5LjMyOTY5OC00Ni4zNDUyNzYgMTkuMzI5Njk4LTExLjE4NzgyNSAwLTIyLjA2MTY1NS0yLjg4OTk1NS0zMi4zNzU0OTQtOC41Nzk4NjYtMTUuNzgxNzUzLTguNzAzODY0LTU1LjI4MTEzNi0xNi45Njc3MzUtMTA5LjcxODI4Ni0yNi42ODc1ODNsLTUuMjgxOTE3LTAuOTUzOTg1Yy0yLjI4MTk2NC0wLjQwNTk5NC00Ljg4OTkyNC0wLjYwOTk5LTcuOTUzODc2LTAuNjA5OTkxLTIyLjg3NTY0MyAwLTc2LjgyODggMTEuMDc3ODI3LTExNy4yMTgxNjggNTMuMDMxMTcyLTM1LjMyNzQ0OCAzNi42NzE0MjctNTIuMDQ1MTg3IDg3LjIxODYzNy00OS42ODcyMjQgMTUwLjIxNzY1MiAxLjQ1Mzk3NyAzOC43MzMzOTUgMTQuNTkzNzcyIDcxLjYwODg4MSAzOC4wMTU0MDYgOTUuMDQ0NTE1IDIzLjc2NTYyOSAyMy44MTM2MjggNTYuODc1MTExIDM2LjU5NTQyOCA5NS43MzQ1MDQgMzcuMDYzNDIxIDQuNjIzOTI4IDAuMDMyIDkuMjY1ODU1IDAuMDMyIDEzLjg3NTc4MyAwLjAzMmgzLjA5Mzk1MmMyMi40NTM2NDkgMCA0NS42NTUyODcgMCA2Mi44MTEwMTkgNi40Njc4OTkgMTAuOTg1ODI4IDQuMTIzOTM2IDI0LjUxNzYxNyAxMi4yNDk4MDkgMzEuODI5NTAyIDM4LjYyNTM5NiA3LjU2MTg4MiAyNy40Mzc1NzEgOS43NDk4NDggNTUuNDk5MTMzIDExLjk5OTgxMyA4NS4yMTY2NjkgMi4xMjM5NjcgMjcuOTY3NTYzIDQuMzc1OTMyIDU2LjkwNTExMSAxMS40OTk4MiA4NS42NTQ2NjEgMTQuMTIzNzc5IDU2LjkzOTExIDQzLjIxNzMyNSA3MS4xNTY4ODggNjUuMTIyOTgzIDczLjA2NDg1OSAzLjEyMzk1MSAwLjI0OTk5NiA2LjI0OTkwMiAwLjM3NTk5NCA5LjMxMTg1NCAwLjM3NTk5NCA0OC43NTEyMzggMCA4MS4xMjQ3MzItMzIuODEzNDg3IDEwNy41OTQzMTktNjMuNTk1MDA3IDYuMzExOTAxLTcuMzQ1ODg1IDEzLjIxNzc5My0xNC40Mzk3NzQgMjAuNTMxNjc5LTIxLjkzOTY1NyAxNi4xMjU3NDgtMTYuNTMxNzQyIDMyLjgxMzQ4Ny0zMy42MjM0NzUgNDQuNjg5MzAyLTU1LjM0MzEzNSAxMy43MTc3ODYtMjQuOTk5NjA5IDE4LjYyMzcwOS01MS4yNDkxOTkgMjMuNDM3NjM0LTc2LjU5MjgwMyA0LjQ5OTkzLTI0LjA2MTYyNCA4LjgxMTg2Mi00Ni43ODMyNjkgMjAuMjQ5NjgzLTY2LjY1Njk1OSAxLjU2MTk3Ni0yLjY4Nzk1OCAzLjM3NTk0Ny01Ljc0OTkxIDUuMzc1OTE2LTkuMjE3ODU2IDU4LjA5NTA5Mi05OS42MTA0NDQgNzIuMDYyODc0LTEzNi40ODM4NjcgNTkuNzgzMDY2LTE1Ny44NzM1MzMtNS41MzE5MTQtOS41OTM4NS0xNS42NTU3NTUtMTQuNzgxNzY5LTI3LjQ2OTU3MS0xMy44NzU3ODMtNS42MjM5MTIgMC40Mzc5OTMtMTEuMTg3ODI1IDAuNjU1OTktMTYuNDk5NzQyIDAuNjU1OTktMzQuOTk5NDUzIDAtNjMuOTM3MDAxLTkuNjI1ODUtNzkuNDA0NzU5LTI2LjQwNzU4OC00LjM3NTkzMi00Ljc0OTkyNi02LjQ2Nzg5OS04Ljg0Mzg2Mi03LjQwNTg4NC0xMS41NjM4MTkgMC41MzE5OTItMC4wMzIgMS4xMjM5ODItMC4wNDU5OTkgMS44MTE5NzEtMC4wNDU5OTkgMTAuMjQ5ODQgMCAyNS45OTk1OTQgNC4yMDM5MzQgNDEuMjE3MzU2IDguMjgxODcgMTguNDA1NzEyIDQuOTM3OTIzIDM3LjQ2NzQxNSAxMC4wMzE4NDMgNTQuMjE3MTUzIDEwLjAzMTg0MyAyNy4yODM1NzQgMCA0NS4wMzMyOTYtMTQuMTcxNzc5IDQ4LjkzOTIzNS0zOC45ODUzOSAzLjg3NTkzOS03LjAzMTg5IDIyLjA2MTY1NS0yNC4xNzE2MjIgMzQuMzc1NDYzLTI1Ljc4MTU5OGwyNS40OTk2MDItMy4zMjc5NDgtNy45Njc4NzYtMjQuNDMxNjE4ek0xNjQuMjk3NDczIDYxMS43NTA0NDFjLTE2LjAzMzc0OS0yMy4zMTM2MzYtMzQuMzkxNDYzLTQ1Ljg3NTI4My01MC44NzUyMDUtNjguODI4OTI0LTE1LjM1OTc2LTIxLjQwNTY2Ni03NS4zMTA4MjMtMTE4Ljc1MDE0NS0xMDMuMDk0Mzg5LTEzMy43MTc5MTFBNTE0LjYyMzk1OSA1MTQuNjIzOTU5IDAgMCAwIDAuMDAwMDQgNTEyYzAgMTI0LjYyNDA1MyA0NC41MzMzMDQgMjM4LjgxMjI2OSAxMTguNTMyMTQ4IDMyNy42MjI4ODEgMC4wNjE5OTkgMC4wNjE5OTkgMC4xNzE5OTcgMC4wOTM5OTkgMC4zMjc5OTUgMC4wOTM5OTggNC40ODM5MyAwIDQ2LjE4OTI3OC0zMC45OTk1MTYgNDkuOTY5MjE5LTM0LjIxNzQ2NSAxNi40MjE3NDMtMTMuOTM3NzgyIDMwLjE3MTUyOS0zMC45Njc1MTYgMzYuMzI3NDMyLTUxLjkzNzE4OCAxNC4zNTk3NzYtNDguODExMjM3LTEzLjg1OTc4My0xMDIuNTYyMzk3LTQwLjg1OTM2MS0xNDEuODExNzg1eiIgZmlsbD0iI0EwRDQ2OCIgcC1pZD0iMTEwNSI+PC9wYXRoPjwvc3ZnPg==';

    $api_key = get_ip_location_api_key();
    $has_api_key = $api_key !== false;
    $comment = get_comment($comment_ID);

    // 当位置信息不存在且有API密钥时尝试获取
    if (!$location && $has_api_key && $comment && $comment->comment_author_IP) {
        $location = get_user_city_e($comment->comment_author_IP, $comment_ID);
    }

    // 如果最终有位置信息则显示（排除错误信息）
    $error_messages = ['功能未启用', '未配置密钥', '参数错误', '网络请求失败', '数据解析失败'];
    if ($location && !in_array($location, $error_messages)) {
        $comment_text .= '<div class="comment-useragent"><img src="' . $location_icon . '" width="16" height="16" alt="位置图标" />&nbsp;' . esc_html($location) . '</div>';
    }
    
    return $comment_text;
}

// 后台管理
function ip_location_admin_css() {
    echo "<style>
    .column-ip_location { width: 180px; }
    .comment-useragent { margin-top: 5px; font-size: 0.9em; color: #666; }
    .comment-useragent img { vertical-align: middle; margin-right: 3px; }
    .ip-location-notice { color: #d63638 !important; }
    .update-ip-btn { display: inline-block; background: #f0f0f1; border: 1px solid #8c8f94; padding: 0 8px; border-radius: 3px; cursor: pointer; margin-top: 5px; }
    .update-ip-btn:hover { background: #dcdcde; }
    .spinner { display: inline-block; float: none; margin: -2px 0 0 5px; }
    .ip-location-display { word-break: break-all; }
    </style>";
}
add_action('admin_head', 'ip_location_admin_css');

add_filter('manage_edit-comments_columns', 'ip_location_comments_columns');
function ip_location_comments_columns($columns) {
    if (get_option('ip_location_enable', 1)) {
        $columns['ip_location'] = 'IP属地';
    }
    return $columns;
}

add_action('manage_comments_custom_column', 'output_ip_location_comments_columns', 10, 2);
function output_ip_location_comments_columns($column, $comment_ID) {
    if ($column == 'ip_location') {
        $location = get_comment_meta($comment_ID, 'Eray-IP-Location', true);
        $comment = get_comment($comment_ID);
        $has_api_key = get_ip_location_api_key() !== false;
        $is_enabled = get_option('ip_location_enable', 1);
        $ip = $comment ? $comment->comment_author_IP : '';
        
        echo '<div class="ip-location-display" data-comment-id="' . esc_attr($comment_ID) . '" data-comment-ip="' . esc_attr($ip) . '">';
        
        if (!$is_enabled) {
            echo '功能未启用';
        } elseif (!$has_api_key) {
            echo '未配置密钥';
        } else {
            echo $location ? esc_html($location) : '--';
        }
        
        echo '</div>';
        
        if ($ip && $has_api_key && $is_enabled) {
            echo '<div class="update-ip-btn" data-comment-id="' . esc_attr($comment_ID) . '">更新</div>';
            echo '<span class="spinner" style="visibility:hidden;"></span>';
        }
    }
}

// 在评论提交时获取位置
add_action('comment_post', 'ip_location_save_on_submit', 10, 3);
function ip_location_save_on_submit($comment_ID, $comment_approved, $commentdata) {
    if (get_option('ip_location_enable', 1) && $comment_approved && isset($_SERVER['REMOTE_ADDR'])) {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        get_user_city_e($ip, $comment_ID);
    }
}

// 添加AJAX处理函数
add_action('wp_ajax_update_ip_location', 'update_ip_location_callback');
function update_ip_location_callback() {
    if (!current_user_can('moderate_comments')) {
        wp_send_json_error('权限不足');
    }
    
    $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
    $comment = get_comment($comment_id);
    
    if (!$comment) {
        wp_send_json_error('评论不存在');
    }
    
    $ip = $comment->comment_author_IP;
    if (empty($ip)) {
        wp_send_json_error('IP地址不存在');
    }
    
    if (!get_option('ip_location_enable', 1)) {
        wp_send_json_error('功能未启用');
    }
    
    $api_key = get_ip_location_api_key();
    if (!$api_key) {
        wp_send_json_error('未配置API密钥');
    }
    
    // 清除该IP的失败缓存，允许重新查询
    $failed_cache_key = 'ip_failed_' . md5($ip);
    delete_transient($failed_cache_key);
    
    $location = get_user_city_e($ip, $comment_id);
    
    $error_messages = ['功能未启用', '未配置密钥', '参数错误', '网络请求失败', '数据解析失败'];
    if ($location && !in_array($location, $error_messages)) {
        wp_send_json_success($location);
    } else {
        wp_send_json_error($location ?: '获取失败');
    }
}

// 添加JS脚本
add_action('admin_footer', 'ip_location_admin_script');
function ip_location_admin_script() {
    if (get_current_screen()->id !== 'edit-comments') {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.update-ip-btn').on('click', function() {
            var $btn = $(this);
            var $display = $btn.siblings('.ip-location-display');
            var $spinner = $btn.siblings('.spinner');
            var commentId = $btn.data('comment-id');
            
            $spinner.css('visibility', 'visible');
            $btn.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'update_ip_location',
                comment_id: commentId,
                _ajax_nonce: '<?php echo wp_create_nonce('ip_location_update'); ?>'
            }, function(response) {
                $spinner.css('visibility', 'hidden');
                $btn.prop('disabled', false);
                
                if (response.success) {
                    $display.text(response.data);
                    $btn.text('已更新').fadeOut(1000);
                } else {
                    alert('更新失败: ' + response.data);
                }
            }).fail(function() {
                $spinner.css('visibility', 'hidden');
                $btn.prop('disabled', false);
                alert('请求失败');
            });
        });
    });
    </script>
    <?php
}

// 挂载到主题评论ua
add_filter("argon_comment_ua_icon", "ip_location_info", 10, 1);
