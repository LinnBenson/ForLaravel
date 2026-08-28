<?php

return array (
  'enabled' => 
  array (
    0 => 'ToView',
    1 => 'LaravelTool',
    2 => 'PushNotifier',
    3 => 'NodeSubscription',
    4 => 'TikHubMedia',
    5 => 'MassEmailing',
    6 => 'PluginManage',
  ),
  'hooks' => 
  array (
    'APP_SERVICE_PROVIDER_REGISTER' => '应用服务注册时调用权限',
    'APP_SERVICE_PROVIDER_BOOT' => '应用服务启动时调用权限',
    'ADMIN_PANEL_PROVIDER_PANEL' => '后台面板配置时调用权限',
    'SET_REQUEST_MIDDLEWARE_HANDLE' => '请求中间件处理时调用权限',
  ),
);
