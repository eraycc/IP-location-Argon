<?php
/*Plugin Name: 归属地＆IP显示插件
Description: 显示评论IP归属地或IP地址，基于腾讯位置定位API查询IP归属地，Argon 定制版 By Eray。
Author: Eray
Version: 1.4.0
Requires PHP: 5.6
Author URI: https://blog.369989.xyz/
Plugin URI: https://blog.369989.xyz/ip-location-plugin
License: MIT
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
                        <p class="description">请前往<a href="https://lbs.qq.com/" target="_blank">腾讯位置服务</a>申请密钥（归属地查询必需）</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_enable">启用IP归属地显示</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_enable" name="ip_location_enable" 
                               value="1" <?php checked(1, get_option('ip_location_enable', 1), true); ?>>
                        <p class="description">仅在开启时才调用API查询并显示IP归属地（不影响IP地址显示）</p>
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
                        <p class="description">显示IP地址（如有归属地则显示在其后，否则单独显示）</p>
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
                    <th><label for="ip_location_enable_cdn">启用CDN模式</label></th>
                    <td>
                        <input type="checkbox" id="ip_location_enable_cdn" name="ip_location_enable_cdn" 
                               value="1" <?php checked(1, get_option('ip_location_enable_cdn', 0), true); ?>>
                        <p class="description">如果网站使用了CDN，请开启此选项以获取真实访客IP</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_location_cdn_headers">CDN请求头优先级</label></th>
                    <td>
                        <input type="text" id="ip_location_cdn_headers" name="ip_location_cdn_headers" 
                               value="<?php echo esc_attr(get_option('ip_location_cdn_headers', 'HTTP_X_FORWARDED_FOR,HTTP_X_REAL_IP,HTTP_CF_CONNECTING_IP,HTTP_X_FORWARDED,HTTP_CLIENT_IP,HTTP_X_CLUSTER_CLIENT_IP,HTTP_FORWARDED_FOR,HTTP_FORWARDED')); ?>" 
                               class="large-text">
                        <p class="description">使用英文逗号分隔，按优先级从高到低排列。常见CDN请求头：</p>
                        <ul style="margin-top: 5px; font-size: 12px; color: #666;">
                            <li>• Cloudflare: HTTP_CF_CONNECTING_IP</li>
                            <li>• 阿里云/腾讯云CDN: HTTP_X_REAL_IP, HTTP_X_FORWARDED_FOR</li>
                            <li>• Nginx代理: HTTP_X_REAL_IP</li>
                            <li>• 通用代理: HTTP_X_FORWARDED_FOR, HTTP_CLIENT_IP</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <th><label>当前检测到的IP</label></th>
                    <td>
                        <?php
                        $current_ip = ip_location_get_real_ip();
                        $current_method = ip_location_get_ip_source();
                        ?>
                        <p><strong>IP地址：</strong><?php echo esc_html($current_ip); ?></p>
                        <p><strong>获取方式：</strong><?php echo esc_html($current_method); ?></p>
                        <p class="description">这是系统当前检测到的您的IP地址及获取方式</p>
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
    register_setting('ip_location_settings', 'ip_location_enable_cdn', 'intval');
    register_setting('ip_location_settings', 'ip_location_cdn_headers', function($input) {
        // 清理输入，移除空格
        $input = preg_replace('/\s+/', '', $input);
        return sanitize_text_field($input);
    });
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

// 获取真实IP地址
function ip_location_get_real_ip() {
    // 检查是否启用CDN模式
    $enable_cdn = get_option('ip_location_enable_cdn', 0);
    
    if (!$enable_cdn) {
        // 未启用CDN模式，直接返回REMOTE_ADDR
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    // 获取配置的请求头列表
    $headers_string = get_option('ip_location_cdn_headers', 
        'HTTP_X_FORWARDED_FOR,HTTP_X_REAL_IP,HTTP_CF_CONNECTING_IP,HTTP_X_FORWARDED,HTTP_CLIENT_IP,HTTP_X_CLUSTER_CLIENT_IP,HTTP_FORWARDED_FOR,HTTP_FORWARDED');
    
    // 将字符串转换为数组
    $headers = array_map('trim', explode(',', $headers_string));
    
    // 按优先级尝试获取IP
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // 处理多个IP的情况（如X-Forwarded-For可能包含多个IP）
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                // 获取第一个有效的IP（通常是真实客户端IP）
                foreach ($ips as $single_ip) {
                    $single_ip = trim($single_ip);
                    // 优先返回公网IP
                    if (filter_var($single_ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $single_ip;
                    }
                }
                // 如果没有公网IP，返回第一个有效IP
                $ip = trim($ips[0]);
            }
            
            // 验证IP地址格式
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    // 所有CDN头都获取失败，返回REMOTE_ADDR
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

// 获取IP来源（用于调试）
function ip_location_get_ip_source() {
    $enable_cdn = get_option('ip_location_enable_cdn', 0);
    
    if (!$enable_cdn) {
        return 'REMOTE_ADDR (CDN模式未启用)';
    }
    
    $headers_string = get_option('ip_location_cdn_headers', 
        'HTTP_X_FORWARDED_FOR,HTTP_X_REAL_IP,HTTP_CF_CONNECTING_IP,HTTP_X_FORWARDED,HTTP_CLIENT_IP,HTTP_X_CLUSTER_CLIENT_IP,HTTP_FORWARDED_FOR,HTTP_FORWARDED');
    
    $headers = array_map('trim', explode(',', $headers_string));
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return str_replace('HTTP_', '', $header) . ' (CDN)';
            }
        }
    }
    
    return 'REMOTE_ADDR (默认)';
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
        // 检查是否是失败的IP
        $failed_cache_key = 'ip_failed_' . md5($ip);
        $is_failed = get_transient($failed_cache_key);
        if ($is_failed !== false) {
            return $is_failed;
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
        // 返回一个错误响应格式
        return json_encode(array(
            'status' => 999,
            'message' => '网络请求失败'
        ));
    }
    
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    
    if ($cache_time > 0 && $json) {
        if (isset($json["status"])) {
            if ($json["status"] == 0) {
                // 成功结果缓存
                set_transient($cache_key, $body, $cache_time);
            } else {
                // 所有失败结果都缓存
                set_transient($failed_cache_key, $body, $cache_time);
            }
        }
    }
    
    return $body;
}

// 获取用户位置信息
function get_user_city_e($ip, $comment_ID = null) {
    // 检查是否启用归属地功能
    if (!get_option('ip_location_enable', 1)) {
        return array('status' => 'disabled', 'message' => '归属地功能未启用');
    }
    
    $api_key = get_ip_location_api_key();
    
    // 检查API密钥是否配置
    if (!$api_key) {
        return array('status' => 'no_key', 'message' => '未配置密钥');
    }
    
    // 确保有评论ID
    if ($comment_ID === null) {
        $comment_ID = get_comment_ID();
        if (!$comment_ID) {
            return array('status' => 'error', 'message' => '无效的评论');
        }
    }
    
    $api_base = get_option('ip_location_api_base', 'https://apis.map.qq.com');
    $api_url = "{$api_base}/ws/location/v1/ip?ip={$ip}&key={$api_key}";
    $result = ip_location_curl_get($api_url, $ip);
    
    if (!$result) {
        return array('status' => 'error', 'message' => '网络请求失败');
    }
    
    $json = json_decode($result, true);
    
    if ($json === null) {
        ip_location_log_error("JSON解析失败: " . print_r($result, true));
        return array('status' => 'error', 'message' => '数据解析失败');
    }
    
    // 处理各种状态码
    if ($json["status"] == 0) {
        $location = format_location_only($json["result"]);
        // 保存位置信息
        update_comment_meta($comment_ID, 'Eray-IP-Location', $location);
        update_comment_meta($comment_ID, 'Eray-IP-Location-Status', 'success');
        return array('status' => 'success', 'message' => $location);
    } else {
        // 记录错误并保存错误信息
        $error_msg = isset($json['message']) ? $json['message'] : '未知错误';
        ip_location_log_error("API错误: {$error_msg} (状态码: {$json['status']})");
        
        // 保存错误信息到meta
        update_comment_meta($comment_ID, 'Eray-IP-Location', $error_msg);
        update_comment_meta($comment_ID, 'Eray-IP-Location-Status', 'error_' . $json['status']);
        
        return array('status' => 'error', 'message' => $error_msg);
    }
}

// 格式化位置信息
function format_location_only($result) {
    $show_full = get_option('ip_location_show_full', 1);
    
    $location_parts = array();
    $ad_info = $result["ad_info"];
    
    if ($show_full) {
        // 显示完整地址
        if (!empty($ad_info["nation"])) {
            $location_parts[] = $ad_info["nation"];
        }
        if (!empty($ad_info["province"])) {
            $location_parts[] = $ad_info["province"];
        }
        if (!empty($ad_info["city"]) && $ad_info["province"] !== $ad_info["city"]) {
            $location_parts[] = $ad_info["city"];
        }
    } else {
        // 只显示国家
        if (!empty($ad_info["nation"])) {
            $location_parts[] = $ad_info["nation"];
        }
    }
    
    return implode(' ', $location_parts);
}

// 显示位置信息
function ip_location_info($comment_text) {
    $comment_ID = get_comment_ID();
    $comment = get_comment($comment_ID);
    
    if (!$comment) {
        return $comment_text;
    }
    
    $show_location = get_option('ip_location_enable', 1);
    $show_ip = get_option('ip_location_show_ip', 1);
    
    // 如果两个都不显示，直接返回
    if (!$show_location && !$show_ip) {
        return $comment_text;
    }
    
    // SVG图标base64编码
    $location_icon = 'data:image/svg+xml;charset=utf-8;base64,PHN2ZyB0PSIxNjYxMzAxMTk0MzQ4IiBjbGFzcz0iaWNvbiIgdmlld0JveD0iMCAwIDEwMjQgMTAyNCIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHAtaWQ9IjExMDMiIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiI+PHBhdGggZD0iTTEwMjMuOTg0MDQgNTEyYzAgMjgyLjc0OTU4Mi0yMjkuMjE2NDE4IDUxMi01MTEuOTg0IDUxMkMyMjkuMjM0NDU4IDEwMjQgMC4wMDAwNCA3OTQuNzQ5NTgyIDAuMDAwMDQgNTEyIDAuMDAwMDQgMjI5LjIzNDQxOCAyMjkuMjM0NDU4IDAgNTEyLjAwMDA0IDAgNzk0Ljc2NzYyMiAwIDEwMjMuOTg0MDQgMjI5LjIzNDQxOCAxMDIzLjk4NDA0IDUxMnoiIGZpbGw9IiM1RDlDRUMiIHAtaWQ9IjExMDQiPjwvcGF0aD48cGF0aCBkPSJNOTk4Ljg5MDQzMiAzNTMuMjA0NDgxYy0yNC4wMzE2MjUtNzMuNjg4ODQ5LTY1LjQwNDk3OC0xNDIuMTcxNzc5LTExOS43MTgxMjktMTk4LjA0NjkwNS01NC4yNDkxNTItNTUuNzgxMTI4LTEyMS40MzgxMDMtOTkuMDc4NDUyLTE5NC4yODA5NjQtMTI1LjIxODA0NGwtMjEuMjgxNjY4LTcuNjM5ODgtNi4zNzU5IDIxLjcxNzY2Yy0yLjkzNzk1NCA5LjkwNzg0NS01LjE1NTkxOSAxNy4wNjM3MzMtNi42ODk4OTYgMjEuMzc1NjY2LTE4Ljk5OTcwMyA4LjkwNTg2MS0zOS4zMTEzODYgMTYuOTM3NzM1LTU4Ljk2NzA3OCAyNC43NDk2MTQtNjIuNzE1MDIgMjQuODQxNjEyLTEyNy41NDQwMDcgNTAuNTQ1MjEtMTcyLjI2MzMwOSAxMDkuNDgyMjg5LTE5LjQ1NTY5NiAyNS42MjU2LTIxLjcwNTY2MSA1Mi4zOTExODEtNS44NTk5MDggNjkuODI4OTA5IDguMjk1ODcgOS4xMzk4NTcgMjAuMzc1NjgyIDEzLjc4MTc4NSAzNS45MDU0MzkgMTMuNzgxNzg0IDEwLjEyMzg0MiAwIDIwLjY4NzY3Ny0xLjkwNTk3IDI5Ljk5OTUzMS0zLjU3Nzk0NCA2LjU2MTg5Ny0xLjE4Nzk4MSAxMy4zNDM3OTItMi40MDU5NjIgMTcuMzI3NzI5LTIuNDA1OTYyIDAuMjk1OTk1IDAgMC41NjE5OTEgMC4wMTYgMC44Mjc5ODcgMC4wMzE5OTkgMjIuOTIxNjQyIDEuNjM5OTc0IDY1LjYyNDk3NSA5Ljc0OTg0OCAxMDMuODc0Mzc3IDE5LjcxNzY5MiA0Mi4wMzEzNDMgMTAuOTM3ODI5IDY0LjMxMjk5NSAyMC4xODc2ODUgNzMuODEyODQ3IDI1LjU3OTYwMS02LjM3NTkgNy41OTM4ODEtMjMuNDY3NjMzIDE5LjMyOTY5OC00Ni4zNDUyNzYgMTkuMzI5Njk4LTExLjE4NzgyNSAwLTIyLjA2MTY1NS0yLjg4OTk1NS0zMi4zNzU0OTQtOC41Nzk4NjYtMTUuNzgxNzUzLTguNzAzODY0LTU1LjI4MTEzNi0xNi45Njc3MzUtMTA5LjcxODI4Ni0yNi42ODc1ODNsLTUuMjgxOTE3LTAuOTUzOTg1Yy0yLjI4MTk2NC0wLjQwNTk5NC00Ljg4OTkyNC0wLjYwOTk5LTcuOTUzODc2LTAuNjA5OTkxLTIyLjg3NTY0MyAwLTc2LjgyODggMTEuMDc3ODI3LTExNy4yMTgxNjggNTMuMDMxMTcyLTM1LjMyNzQ0OCAzNi42NzE0MjctNTIuMDQ1MTg3IDg3LjIxODYzNy00OS42ODcyMjQgMTUwLjIxNzY1MiAxLjQ1Mzk3NyAzOC43MzMzOTUgMTQuNTkzNzcyIDcxLjYwODg4MSAzOC4wMTU0MDYgOTUuMDQ0NTE1IDIzLjc2NTYyOSAyMy44MTM2MjggNTYuODc1MTExIDM2LjU5NTQyOCA5NS43MzQ1MDQgMzcuMDYzNDIxIDQuNjIzOTI4IDAuMDMyIDkuMjY1ODU1IDAuMDMyIDEzLjg3NTc4MyAwLjAzMmgzLjA5Mzk1MmMyMi40NTM2NDkgMCA0NS42NTUyODcgMCA2Mi44MTEwMTkgNi40Njc4OTkgMTAuOTg1ODI4IDQuMTIzOTM2IDI0LjUxNzYxNyAxMi4yNDk4MDkgMzEuODI5NTAyIDM4LjYyNTM5NiA3LjU2MTg4MiAyNy40Mzc1NzEgOS43NDk4NDggNTUuNDk5MTMzIDExLjk5OTgxMyA4NS4yMTY2NjkgMi4xMjM5NjcgMjcuOTY3NTYzIDQuMzc1OTMyIDU2LjkwNTExMSAxMS40OTk4MiA4NS42NTQ2NjEgMTQuMTIzNzc5IDU2LjkzOTExIDQzLjIxNzMyNSA3MS4xNTY4ODggNjUuMTIyOTgzIDczLjA2NDg1OSAzLjEyMzk1MSAwLjI0OTk5NiA2LjI0OTkwMiAwLjM3NTk5NCA5LjMxMTg1NCAwLjM3NTk5NCA0OC43NTEyMzggMCA4MS4xMjQ3MzItMzIuODEzNDg3IDEwNy41OTQzMTktNjMuNTk1MDA3IDYuMzExOTAxLTcuMzQ1ODg1IDEzLjIxNzc5My0xNC40Mzk3NzQgMjAuNTMxNjc5LTIxLjkzOTY1NyAxNi4xMjU3NDgtMTYuNTMxNzQyIDMyLjgxMzQ4Ny0zMy42MjM0NzUgNDQuNjg5MzAyLTU1LjM0MzEzNSAxMy43MTc3ODYtMjQuOTk5NjA5IDE4LjYyMzcwOS01MS4yNDkxOTkgMjMuNDM3NjM0LTc2LjU5MjgwMyA0LjQ5OTkzLTI0LjA2MTYyNCA4LjgxMTg2Mi00Ni43ODMyNjkgMjAuMjQ5NjgzLTY2LjY1Njk1OSAxLjU2MTk3Ni0yLjY4Nzk1OCAzLjM3NTk0Ny01Ljc0OTkxIDUuMzc1OTE2LTkuMjE3ODU2IDU4LjA5NTA5Mi05OS42MTA0NDQgNzIuMDYyODc0LTEzNi40ODM4NjcgNTkuNzgzMDY2LTE1Ny44NzM1MzMtNS41MzE5MTQtOS41OTM4NS0xNS42NTU3NTUtMTQuNzgxNzY5LTI3LjQ2OTU3MS0xMy44NzU3ODMtNS42MjM5MTIgMC40Mzc5OTMtMTEuMTg3ODI1IDAuNjU1OTktMTYuNDk5NzQyIDAuNjU1OTktMzQuOTk5NDUzIDAtNjMuOTM3MDAxLTkuNjI1ODUtNzkuNDA0NzU5LTI2LjQwNzU4OC00LjM3NTkzMi00Ljc0OTkyNi02LjQ2Nzg5OS04Ljg0Mzg2Mi03LjQwNTg4NC0xMS41NjM4MTkgMC41MzE5OTItMC4wMzIgMS4xMjM5ODItMC4wNDU5OTkgMS44MTE5NzEtMC4wNDU5OTkgMTAuMjQ5ODQgMCAyNS45OTk1OTQgNC4yMDM5MzQgNDEuMjE3MzU2IDguMjgxODcgMTguNDA1NzEyIDQuOTM3OTIzIDM3LjQ2NzQxNSAxMC4wMzE4NDMgNTQuMjE3MTUzIDEwLjAzMTg0MyAyNy4yODM1NzQgMCA0NS4wMzMyOTYtMTQuMTcxNzc5IDQ4LjkzOTIzNS0zOC45ODUzOSAzLjg3NTkzOS03LjAzMTg5IDIyLjA2MTY1NS0yNC4xNzE2MjIgMzQuMzc1NDYzLTI1Ljc4MTU5OGwyNS40OTk2MDItMy4zMjc5NDgtNy45Njc4NzYtMjQuNDMxNjE4ek0xNjQuMjk3NDczIDYxMS43NTA0NDFjLTE2LjAzMzc0OS0yMy4zMTM2MzYtMzQuMzkxNDYzLTQ1Ljg3NTI4My01MC44NzUyMDUtNjguODI4OTI0LTE1LjM1OTc2LTIxLjQwNTY2Ni03NS4zMTA4MjMtMTE4Ljc1MDE0NS0xMDMuMDk0Mzg5LTEzMy43MTc5MTFBNTE0LjYyMzk1OSA1MTQuNjIzOTU5IDAgMCAwIDAuMDAwMDQgNTEyYzAgMTI0LjYyNDA1MyA0NC41MzMzMDQgMjM4LjgxMjI2OSAxMTguNTMyMTQ4IDMyNy42MjI4ODEgMC4wNjE5OTkgMC4wNjE5OTkgMC4xNzE5OTcgMC4wOTM5OTkgMC4zMjc5OTUgMC4wOTM5OTggNC40ODM5MyAwIDQ2LjE4OTI3OC0zMC45OTk1MTYgNDkuOTY5MjE5LTM0LjIxNzQ2NSAxNi40MjE3NDMtMTMuOTM3NzgyIDMwLjE3MTUyOS0zMC45Njc1MTYgMzYuMzI3NDMyLTUxLjkzNzE4OCAxNC4zNTk3NzYtNDguODExMjM3LTEzLjg1OTc4My0xMDIuNTYyMzk3LTQwLjg1OTM2MS0xNDEuODExNzg1eiIgZmlsbD0iI0EwRDQ2OCIgcC1pZD0iMTEwNSI+PC9wYXRoPjwvc3ZnPg==';
    
    $display_parts = array();
    $has_error = false;
    
    // 获取归属地信息
    if ($show_location) {
        $location = get_comment_meta($comment_ID, 'Eray-IP-Location', true);
        $location_status = get_comment_meta($comment_ID, 'Eray-IP-Location-Status', true);
        
        // 如果没有位置信息，尝试获取
        if (!$location) {
            $api_key = get_ip_location_api_key();
            if ($api_key && $comment->comment_author_IP) {
                $location_result = get_user_city_e($comment->comment_author_IP, $comment_ID);
                if ($location_result['status'] === 'success') {
                    $location = $location_result['message'];
                } else {
                    // 显示错误信息
                    $location = $location_result['message'];
                    $has_error = true;
                }
            } elseif (!$api_key) {
                $location = '未配置密钥';
                $has_error = true;
            }
        } else {
            // 检查是否是错误信息
            if (strpos($location_status, 'error_') === 0) {
                $has_error = true;
            }
        }
        
        // 添加位置信息到显示数组
        if ($location) {
            if ($has_error) {
                $display_parts[] = '<span class="ip-location-error">[' . esc_html($location) . ']</span>';
            } else {
                $display_parts[] = esc_html($location);
            }
        }
    }
    
    // 获取并显示IP地址
    if ($show_ip && $comment->comment_author_IP) {
        $masked_ip = mask_ip_address($comment->comment_author_IP);
        $display_parts[] = esc_html($masked_ip);
    }
    
    // 如果有内容要显示
    if (!empty($display_parts)) {
        $display_text = implode(' ', $display_parts);
        $comment_text .= '<div class="comment-useragent"><img src="' . $location_icon . '" width="16" height="16" alt="位置图标" />&nbsp;' . $display_text . '</div>';
    }
    
    return $comment_text;
}

// 后台管理列显示
add_action('manage_comments_custom_column', 'output_ip_location_comments_columns', 10, 2);
function output_ip_location_comments_columns($column, $comment_ID) {
    if ($column == 'ip_location') {
        $comment = get_comment($comment_ID);
        $show_location = get_option('ip_location_enable', 1);
        $show_ip = get_option('ip_location_show_ip', 1);
        $has_api_key = get_ip_location_api_key() !== false;
        $ip = $comment ? $comment->comment_author_IP : '';
        
        $display_parts = array();
        $has_error = false;
        
        // 获取归属地
        if ($show_location) {
            if ($has_api_key) {
                $location = get_comment_meta($comment_ID, 'Eray-IP-Location', true);
                $location_status = get_comment_meta($comment_ID, 'Eray-IP-Location-Status', true);
                
                if ($location) {
                    // 检查是否是错误信息
                    if (strpos($location_status, 'error_') === 0) {
                        $display_parts[] = '[' . $location . ']';
                        $has_error = true;
                    } else {
                        $display_parts[] = $location;
                    }
                }
            } else {
                $display_parts[] = '[未配置密钥]';
                $has_error = true;
            }
        }
        
        // 显示IP
        if ($show_ip && $ip) {
            $masked_ip = mask_ip_address($ip);
            $display_parts[] = $masked_ip;
        }
        
        echo '<div class="ip-location-display' . ($has_error ? ' ip-location-error' : '') . '" data-comment-id="' . esc_attr($comment_ID) . '" data-comment-ip="' . esc_attr($ip) . '">';
        
        if (!empty($display_parts)) {
            echo esc_html(implode(' ', $display_parts));
        } elseif (!$show_location && !$show_ip) {
            echo '功能未启用';
        } else {
            echo '--';
        }
        
        echo '</div>';
        
        // 只有在启用归属地功能且有API密钥时才显示更新按钮
        if ($ip && $show_location && $has_api_key) {
            echo '<div class="update-ip-btn" data-comment-id="' . esc_attr($comment_ID) . '">更新归属地</div>';
            echo '<span class="spinner" style="visibility:hidden;"></span>';
        }
    }
}

// 修改后台列标题
add_filter('manage_edit-comments_columns', 'ip_location_comments_columns');
function ip_location_comments_columns($columns) {
    $show_location = get_option('ip_location_enable', 1);
    $show_ip = get_option('ip_location_show_ip', 1);
    
    if ($show_location || $show_ip) {
        $title = array();
        if ($show_location) $title[] = '归属地';
        if ($show_ip) $title[] = 'IP';
        $columns['ip_location'] = implode('/', $title);
    }
    
    return $columns;
}

// 后台管理CSS
function ip_location_admin_css() {
    echo "<style>
    .column-ip_location { width: 200px; }
    .comment-useragent { margin-top: 5px; font-size: 0.9em; color: #666; }
    .comment-useragent img { vertical-align: middle; margin-right: 3px; }
    .ip-location-notice { color: #d63638 !important; }
    .ip-location-error { color: #d63638; }
    .update-ip-btn { display: inline-block; background: #f0f0f1; border: 1px solid #8c8f94; padding: 0 8px; border-radius: 3px; cursor: pointer; margin-top: 5px; font-size: 12px; }
    .update-ip-btn:hover { background: #dcdcde; }
    .spinner { display: inline-block; float: none; margin: -2px 0 0 5px; }
    .ip-location-display { word-break: break-all; }
    </style>";
}
add_action('admin_head', 'ip_location_admin_css');

// 在评论提交时获取位置
add_action('comment_post', 'ip_location_save_on_submit', 10, 3);
function ip_location_save_on_submit($comment_ID, $comment_approved, $commentdata) {
    if (get_option('ip_location_enable', 1) && $comment_approved) {
        // 使用改进的IP获取函数
        $ip = ip_location_get_real_ip();
        
        if ($ip) {
            // 如果WordPress保存的IP与我们获取的不同，更新它
            $comment = get_comment($comment_ID);
            if ($comment && $comment->comment_author_IP !== $ip) {
                // 更新评论的IP地址为真实IP
                wp_update_comment(array(
                    'comment_ID' => $comment_ID,
                    'comment_author_IP' => $ip
                ));
            }
            
            get_user_city_e($ip, $comment_ID);
        }
    }
}

// 钩子：在WordPress获取评论者IP时使用我们的函数
add_filter('pre_comment_user_ip', 'ip_location_filter_comment_ip');
function ip_location_filter_comment_ip($ip) {
    $real_ip = ip_location_get_real_ip();
    return $real_ip ? $real_ip : $ip;
}

// AJAX更新处理
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
        wp_send_json_error('归属地功能未启用');
    }
    
    $api_key = get_ip_location_api_key();
    if (!$api_key) {
        wp_send_json_error('未配置API密钥');
    }
    
    // 清除该IP的失败缓存，允许重新查询
    $failed_cache_key = 'ip_failed_' . md5($ip);
    delete_transient($failed_cache_key);
    
    // 清除之前的meta数据
    delete_comment_meta($comment_id, 'Eray-IP-Location');
    delete_comment_meta($comment_id, 'Eray-IP-Location-Status');
    
    $location_result = get_user_city_e($ip, $comment_id);
    
    // 构建显示内容
    $display_parts = array();
    
    if ($location_result['status'] === 'success') {
        $display_parts[] = $location_result['message'];
    } else {
        // 显示错误信息
        $display_parts[] = '[' . $location_result['message'] . ']';
    }
    
    if (get_option('ip_location_show_ip', 1)) {
        $masked_ip = mask_ip_address($ip);
        $display_parts[] = $masked_ip;
    }
    
    if (!empty($display_parts)) {
        wp_send_json_success(implode(' ', $display_parts));
    } else {
        wp_send_json_error('获取失败');
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
