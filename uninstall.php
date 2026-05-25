<?php
/**
 * 插件卸载清理文件
 * 
 * 当插件被删除时，清理所有相关的数据库数据
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 所有插件设置选项
$options = array(
    'ip_location_api_base',        // API基础地址
    'ip_location_api_key',         // API密钥
    'ip_location_enable',          // 是否启用归属地显示
    'ip_location_show_full',       // 是否显示完整地址
    'ip_location_show_ip',         // 是否显示IP
    'ip_location_mask_ip',         // 是否IP打码
    'ip_location_enable_cdn',      // 是否启用CDN模式
    'ip_location_cdn_headers',     // CDN请求头优先级
    'ip_location_cache_time',      // 缓存时间
    'ip_location_enable_log'       // 是否启用日志
);

// 删除所有插件设置
foreach ($options as $option) {
    delete_option($option);
}

// 删除评论元数据
global $wpdb;

// 删除位置信息和状态信息
$wpdb->query("
    DELETE FROM {$wpdb->commentmeta} 
    WHERE meta_key IN ('Eray-IP-Location', 'Eray-IP-Location-Status')
");

// 清理所有缓存（包括成功和失败的缓存）
$wpdb->query("
    DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_ip_cache_%' 
       OR option_name LIKE '_transient_timeout_ip_cache_%'
       OR option_name LIKE '_transient_ip_failed_%'
       OR option_name LIKE '_transient_timeout_ip_failed_%'
");

// 记录卸载日志（如果日志功能之前被启用）
if (defined('WP_DEBUG') && WP_DEBUG === true) {
    error_log('[IP Location Plugin] Plugin uninstalled and all data cleaned.');
}
