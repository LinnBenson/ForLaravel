<?php
/**
 * Filament 配置文件
 * @package config
 */
return [
    // 后台面板路径
    'path' => env( 'APP_ADMIN_PREFIX', 'admin' ),
    // 后台资源路径
    'assets_path' => 'filament',
    // 代理管理等级，小于等于此等级即为代理
    'agent' => 1000,
    // 后台菜单等级权限，访问需要大于此级别
    'navigation_levels' => [
        'dashboard' => 0,
        'admin_users' => 0,
        'plugin_management' => 99900,
        'users' => 0,
        'notification_information' => 0,
        'system_config' => 90000,
        'service_management' => 99900,
        'log_information' => 99900,
        'route_information' => 99900,
        'filament_icons' => 99900,
        'readme' => 99900,
    ],
    // 后台菜单分组
    'navigation_groups' => [
        'filament.groups.admin',
        'filament.groups.user',
        'filament.groups.other',
        'filament.groups.system',
        'filament.groups.developer',
    ],
];
