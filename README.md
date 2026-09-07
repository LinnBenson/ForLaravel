# 开始使用
1. 克隆项目到本地
   - `git clone https://github.com/LinnBenson/LaravelDevelopment.git`
2. 安装依赖
   - `composer install`
3. 复制 .env.example 为 .env 并修改配置
   - `cp .env.example .env`
4. 生成应用密钥
   - `php artisan key:generate`
5. 清除可能存在的缓存
   - `php artisan optimize:clear`
6. 运行数据库迁移和数据填充
   - `php artisan migrate --seed`
7. 创建公开存储目录链接
   - `php artisan storage:link`
   - 用于访问管理员头像、用户头像及其它存储在 `public` 磁盘中的文件
8. 启动基础队列
   - `php artisan queue:work --tries=1 --timeout=65`
   - 用于处理异步任务，如发送通知、邮件等

# 环境配置
1. 所需 PHP 函数
   - `shell_exec,proc_open,exec,symlink,pcntl_alarm,pcntl_fork,pcntl_signal,pcntl_signal_dispatch,pcntl_wait`
   - `shell_exec` 用于后台服务项管理和 LaravelTool 插件执行受控的 Artisan 命令
   - `proc_open` 和 `exec` 用于 Symfony Process、Artisan 命令及相关进程调用
   - `symlink` 用于 `php artisan storage:link` 创建公开存储目录链接
   - `pcntl_alarm` 和 `pcntl_signal` 用于 Laravel 队列的任务超时与优雅停止
   - `pcntl_fork`、`pcntl_signal_dispatch` 和 `pcntl_wait` 用于 Workerman 多进程服务

# 伪静态部署
```
location ^~ /internal- {
    try_files $uri $uri/ /index.php?$query_string;
}
location ^~ /livewire- {
    try_files $uri $uri/ /index.php?$query_string;
}
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 预设命令集
- 启动服务
  - `php artisan server [服务标识] start [-d]`
  - 以守护进程模式启动 `config/workerman.php` 中指定的服务
- 重启服务
  - `php artisan server [服务标识] restart [-d]`
  - 以守护进程模式重启 `config/workerman.php` 中指定的服务
- 停止服务
  - `php artisan server [服务标识] stop`
  - 停止 `config/workerman.php` 中指定的服务
- 查看服务状态
  - `php artisan server [服务标识] status`
  - 输出 `config/workerman.php` 中指定服务的 Workerman 进程状态
- 调试服务
  - `php artisan server [服务标识] debug`
  - 以循环重启的方式调试 `config/workerman.php` 中指定的服务，使用 `stop` 停止调试任务

## 自定义参数
- RID 请求唯一标识符
  - 通过 `SetRequestMiddleware` 中间件设置，优先使用请求头中的 `RID` 参数，其次使用 Cookie 中的 `rid` 参数，若都不存在则自动生成一个 UUID 作为 RID
  - 可通过 `$request->attributes->get('rid')` 获取当前请求的 RID
- Locale 应用语言
  - 通过 `SetRequestMiddleware` 中间件设置，依次读取请求头中的 `Locale` 参数、Cookie 中的 `locale` 参数和 `Accept-Language` 请求头
  - 仅接受 `config/app.php` 中 `locales` 已配置的语言，均未匹配时使用应用默认语言

## 后台菜单等级权限
- 菜单等级统一配置在 `config/filament.php` 的 `navigation_levels` 中
- 配置值为显示菜单和访问页面所需的最低管理员等级，设置为 `0` 时不额外限制等级
- 后台页面或资源使用 `HasNavigationLevel`，并通过 `$navigationPermission` 指定对应的配置键
- 权限判断同时控制菜单显示和直接页面访问，未达到要求等级时页面返回 403

## 公共函数 [app/Helpers/Common.php]
- 判断字符串是否为 JSON
  - `is_json( [string]待判断的字符串 )`
  - return [bool]是否为合法 JSON 对象或 JSON 数组字符串
- 判断对象方法是否公开
  - `is_public( [object]待判断的对象, [string]待判断的方法名称 )`
  - return [bool]是否为对象上存在的公开方法
- 判断对象方法是否公开
  - `isPublic( [object]待判断的对象, [string]待判断的方法名称 )`
  - return [bool]是否为对象上存在的公开方法
- 判断字符串是否为 UUID
  - `is_uuid( [string]待判断的字符串 )`
  - return [bool]是否为标准 UUID 字符串
- 生成 UUID
  - `uuid()`
  - return [string]标准 UUID 字符串
- 生成随机字符串
  - `randomString( [int]字符串长度, [0|1|2]字符串类型 = 2 )`
  - 字符串类型: 0 仅数字，1 仅大小写字母，2 大小写字母加数字
  - return [string]随机字符串
- 格式化时间戳为日期字符串
  - `toDate( [int|null]时间戳 = null )`
  - 时间戳为 null 时使用当前时间
  - return [string]`Y-m-d H:i:s` 格式的日期时间字符串
- 将任意值转换为字符串
  - `toString( [mixed]待转换的值 )`
  - 字符串原样返回；纯字符串数组使用换行符连接，其它数组使用 `var_export()` 转换
  - 布尔值、null 和对象转换为对应的类型标记，数值直接转换为字符串，其它类型使用 `var_export()` 转换
  - return [string]转换后的字符串

## 系统函数 [app/Helpers/System.php]
- 读取系统配置
  - `setting( [string|null]配置键名 = null, [mixed]默认值 = null )`
  - 键名为 null 时返回全部配置；配置不存在时返回默认值
  - return [mixed]按配置类型转换后的配置值、全部配置或默认值
- 输出标准 JSON 响应
  - `echoJson( [bool|int]状态, [mixed]响应数据, [int|null]HTTP 状态码 = null, [array]响应头 = [] )`
  - 布尔状态或整数状态会转换为 `success`、`info`、`error`、`warning` 或 `unknown`
  - 响应数据可传入 `[翻译键, 替换参数数组]`，函数会通过 Laravel 多语言机制转换为对应文案
  - Web 环境返回包含 `status`、`code`、`time` 和 `data` 的 JSON 响应对象
  - CLI、Artisan 和 Workerman 环境返回不包含 HTTP 响应头的纯 JSON 字符串
  - return [\Illuminate\Http\JsonResponse|string]JSON 响应对象或 JSON 字符串
- 获取插件实例
  - `plugin( [string]插件标识 )`
  - 通过 `PluginProvider::load()` 加载插件，同一进程内重复调用时返回缓存实例
  - 插件不存在、标识无效或依赖不满足时返回 null；循环依赖或插件入口结构无效时抛出 `LogicException`
  - return [object|null]插件实例或 null
- 执行 Shell 命令
  - `runShell( [string]Shell 命令 )`
  - 执行 Shell 命令并合并返回标准输出和错误输出
  - return [string|null]命令输出，无法获取输出时返回 null
- 获取队列工作进程状态
  - `getQueueWorkerStatus( [string|null]队列工作进程名称 = null )`
  - 默认读取公共队列 Worker 的心跳状态；传入名称时读取对应命名 Worker 的预留心跳状态
  - return [bool]队列工作进程是否正在运行

## 插件提供器 [app/Providers/PluginProvider.php]
- 插件标识: String|null `$plugin->id`
- 插件目录: String|null `$plugin->path`
- 插件类型: String `$plugin->type`
- 插件名称: String|null `$plugin->name`
- 插件版本: String|null `$plugin->version`
- 插件作者: String|null `$plugin->author`
- 插件来源: String|null `$plugin->source`
- 插件描述: String|null `$plugin->description`
- Composer 依赖: Array `$plugin->relyComposer`
  - 数组键为 Composer 包名，数组值为 Composer 版本约束
- 插件依赖: Array `$plugin->relyPlugin`
  - 数组键为插件标识，数组值为插件的版本约束
- 加载插件
  - `PluginProvider::load( [string]插件标识 )`
  - 校验插件标识、目录、Composer 依赖、插件依赖及循环依赖，通过后启用并缓存插件实例
  - 插件不存在、标识无效或依赖不满足时返回 null；循环依赖或插件入口未返回 `PluginProvider` 实例时抛出 `LogicException`
  - return [PluginProvider|null]插件实例
- 移除插件加载缓存
  - `PluginProvider::forget( [string]插件标识 )`
  - 安装、更新或回滚后移除当前进程内的插件实例缓存
  - return [void]
- 启用插件
  - `$plugin->enable( [string]插件标识, [string]插件目录 )`
  - 设置插件标识和目录并执行插件启动入口，每个插件实例只能调用一次
  - return [void]
- 运行插件钩子
  - `PluginProvider::runHook( [string]钩子名称, [mixed]...钩子回调参数 )`
  - 按 `config/plugin.php` 中的 `enabled` 顺序调用所有插件注册的同名钩子；钩子未在系统配置中注册时抛出 `LogicException`
  - return [mixed]当前实现执行完成后返回 true
- 插件启动入口
  - `protected function boot(): void`
  - 插件启用时由 `enable()` 自动调用，插件类可以覆盖此受保护方法以注册配置、事件和钩子
  - return [void]
- 插件安装入口
  - `$plugin->install()`
  - 安装插件时调用，插件类可以覆盖此方法执行数据库或配置初始化
  - return [bool]安装成功返回 true，失败返回 false
- 插件卸载入口
  - `$plugin->uninstall()`
  - 卸载插件时调用，插件类可以覆盖此方法清理数据库或配置
  - return [bool]卸载成功返回 true，失败返回 false
- 注册插件钩子
  - `$plugin->hook( [string]钩子名称, [callable|string]钩子回调函数或方法名 )`
  - 钩子名称必须在系统钩子配置中存在，且不能与当前插件已注册钩子重复；传入方法名时，该方法必须是插件实例的公开方法
  - return [bool]注册成功返回 true，失败返回 false
- 获取插件钩子
  - `$plugin->getHook()`
  - return [array]当前插件已注册的钩子回调
- 注册 SQLite 数据库连接
  - `$plugin->sqlite( [string]连接名称, [string]数据库文件名, [array<string, mixed>]连接选项 = [] )`
  - return [bool]注册成功返回 true，失败返回 false
- 获取插件配置
  - `$plugin->config( [string]配置名称, [mixed]默认值 = null )`
  - 合并插件目录 `config.php` 与 `config/plugin/{插件标识}.php`，用户配置递归覆盖插件默认配置，并支持 `database.host` 点号读取
  - 配置名称为空字符串时返回全部插件配置
  - return [mixed]配置值或全部配置，配置不存在时返回默认值
- 设置插件类型
  - `$plugin->setType( [int]插件类型索引 )`
  - 插件类型索引: 0 依赖插件 `rely`，1 功能插件 `plugin`
  - return [bool]设置成功返回 true，索引无效时返回 false
- 判断插件是否已启用
  - `$plugin->isEnabled()`
  - return [bool]插件是否已经启用
- 插件权限
  - APP_SERVICE_PROVIDER_REGISTER()
    - 应用服务注册时调用权限
    - return [void]
  - APP_SERVICE_PROVIDER_BOOT()
    - 应用服务启动时调用权限
    - return [void]
  - ADMIN_PANEL_PROVIDER_PANEL( \Filament\Panel $panel )
    - 后台面板配置时调用权限
    - return [void]
  - SET_REQUEST_MIDDLEWARE_HANDLE( \Illuminate\Http\Request $request )
    - 请求中间件处理时调用权限
    - return [void]
  - REBUILD_PLUGIN_DATA
    - 重建插件数据时调用权限
    - return [bool]重建结果

## 用户模型 [app/Models/User.php]
- 字段备注: Array `User::FIELD_COMMENTS`
- 用户等级: Array `User::LEVELS`
- 获取全部字段备注
  - `User::fields()`
  - return [array]字段名与中文备注
- 获取指定字段备注
  - `User::field( [string]字段名 )`
  - return [string]字段备注，不存在时返回空字符串
- 获取上级代理管理员
  - `$user->agentAdmin()`
  - return [BelongsTo]通过 `agent` 字段关联的管理员
- 获取用户等级
  - `User::getLevel( [int|string|null]用户等级 = null )`
  - 不传等级时返回全部等级配置；传入等级时返回最接近且不小于该数值的等级名称
  - 无效、负数或超过最高等级时返回 `Unknown`
  - return [array|string]等级配置列表或等级名称
- 组合电话号码存储格式
  - `User::formatPhoneForStorage( [string|null]国际区号, [string|null]本地号码 )`
  - return [string|null]不带加号的 `xx xxxxxxxxxxx` 格式
- 拆分电话号码
  - `User::splitPhone( [string|null]已存储的电话号码 )`
  - return [array]国际区号和本地号码
- 格式化电话号码显示
  - `User::formatPhoneForDisplay( [string|null]已存储的电话号码 )`
  - return [string|null]带加号的 `+xx xxxxxxxxxxx` 格式

## 管理员模型 [app/Models/AdminUser.php]
- 字段备注: Array `AdminUser::FIELD_COMMENTS`
- 获取全部字段备注
  - `AdminUser::fields()`
  - return [array]字段名与中文备注
- 获取指定字段备注
  - `AdminUser::field( [string]字段名 )`
  - return [string]字段备注，不存在时返回空字符串
- 获取 Filament 头像地址
  - `$adminUser->getFilamentAvatarUrl()`
  - return [string|null]头像存在时返回 `public` 磁盘访问地址，否则返回 null
- 判断是否可以访问后台面板
  - `$adminUser->canAccessPanel( [Panel]后台面板 )`
  - return [bool]管理员处于启用状态时返回 true

## Filament 数据库通知 [app/Filament/Config/SendNotification.php]
- 在 HTTP 控制器、路由或服务中向指定用户发送数据库通知
  ```php
  use App\Filament\Config\SendNotification;
  use Filament\Actions\Action;
  use Illuminate\Support\Facades\DB;

  DB::transaction( function () use ( $user ): void {
      SendNotification::make() // 必填；创建通知，ID 自动生成
          ->title( '系统通知' ) // 通知标题，建议填写；string|null，通知标题
          ->body( '你的申请已经通过。' ) // 通知内容，可选；string|null，通知正文
          ->status( 'success' ) // 通知状态，可选；success|info|warning|danger，默认 null
          ->color( 'success' ) // 通知颜色，可选；primary|success|info|warning|danger|gray，默认 null
          ->icon( 'heroicon-o-check-circle' ) // 图标，可选；Heroicon 名称，默认根据 status 选择
          ->iconColor( 'success' ) // 图标颜色，可选；primary|success|info|warning|danger|gray，默认跟随 status
          ->seconds( 10 ) // 超时关闭时间，可选；float 秒数，默认 6 秒；也可使用 duration( 毫秒 ) 或 persistent() 禁用
          ->actions( [ // 可选；Action[]，默认 []
              Action::make( 'viewDetails' ) // 必填；按钮唯一标识，仅使用字母、数字、短横线和下划线
                  ->label( '查看详情' ) // 按钮显示文字，建议填写；string|null
                  ->icon( 'heroicon-o-eye' ) // 按钮图标，可选；Heroicon 名称，默认 null
                  ->color( 'primary' ) // 按钮颜色，可选；primary|success|info|warning|danger|gray，默认 null
                  ->url( '/admin/example', true ) // 按钮跳转链接，可选；string|null，站内路径或完整 URL，第二个值可选；新窗口打开链接，未调用时为 false
                  ->markAsRead() // 可选；点击后标记已读，未调用时为 false
                  ->close(), // 可选；点击后关闭通知，未调用时为 false
          ] )
          ->sendToDatabase( $user ); // 接收者必填；第二参数可选，是否广播刷新事件，默认 false
  } );
  ```

## 系统配置模型 [app/Models/SystemConfig.php]
- 配置类别: Array `SystemConfig::CATEGORIES`
- 配置类型: Array `SystemConfig::TYPES`
- 获取配置类别名称
  - `$config->category_label`
  - 由 `getCategoryLabelAttribute()` 访问器提供
  - return [string]类别对应的中文名称，未知类别返回原值
- 获取配置类型名称
  - `$config->type_label`
  - 由 `getTypeLabelAttribute()` 访问器提供
  - return [string]类型对应的中文名称，未知类型返回原值

## Workerman 服务 [app/Workerman/Server.php]
- 服务项名称: String `Server::$name`
- 服务配置: Array `Server::$config`
- 事件处理对象: Object|null `Server::$event`
- Workerman 对象: \Workerman\Worker|null `Server::$worker`
- Register 对象: \GatewayWorker\Register|null `Server::$register`
- Gateway 对象: \GatewayWorker\Gateway|null `Server::$gateway`
- BusinessWorker 对象: \GatewayWorker\BusinessWorker|null `Server::$business`
- 定时器列表: Array `Server::$timers`
- 构建并运行服务
  - `Server::build( [string]服务标识, [array]服务配置 )`
  - 根据配置中的 `type` 构建普通 Workerman 或 GatewayWorker 服务，并进入常驻运行状态
  - return [void]
- 获取服务状态
  - `Server::status( [string]服务标识 )`
  - 根据服务心跳判断服务是否正在运行
  - return [bool]服务是否正在运行
- 设置定时器
  - `Server::setTimer( [string]定时器名称, [mixed]...Workerman 定时器参数 )`
  - 可变参数会依次传递给 `Workerman\Timer::add()`，常用参数为时间间隔、回调函数、回调参数和是否持久执行
  - 同一进程中定时器名称不可重复；名称已存在时不会重复创建
  - return [bool|int]成功返回定时器 ID，名称已存在时返回 false
- 删除定时器
  - `Server::delTimer( [string]定时器名称 )`
  - 根据名称删除由 `Server::setTimer()` 创建的定时器
  - return [bool]定时器存在并完成删除时返回 true，否则返回 false

## 代理权限说明
- 代理身份判定
  - 代理等级阈值统一配置在 `config/filament.php` 的 `agent` 中，当前值为 `1000`
  - 后台管理员 `level <= agent` 时按代理处理，`level > agent` 时按高级管理员处理
  - 调整阈值会同步影响管理员创建、用户归属、用户编辑和通知查询等权限，请勿只修改页面显示逻辑
- 用户归属关系
  - 前台用户通过 `users.agent` 保存所属代理管理员 ID，`0` 表示未分配代理
  - 可通过 `$user->agentAdmin()` 获取用户所属的代理管理员
  - 高级管理员可以查看全部用户，并可将用户分配给等级不超过代理阈值的管理员
  - 代理只能查看、搜索和筛选 `agent` 等于自身管理员 ID 的用户，直接访问其他代理用户同样会被拒绝
- 用户创建与修改
  - 代理创建用户时，系统会强制将用户归属设置为当前代理，并将用户等级设置为 `10`（System User）
  - 代理编辑用户时不能修改用户归属和用户等级，提交时仍会保留数据库中的原值
  - 代理只能编辑或删除自己名下且等级低于 `User::LEVEL_USER`（当前为 `50`）的用户
  - 高级管理员可以创建、编辑和删除权限范围内的用户，也可以选择符合代理等级要求的归属管理员
- 管理员管理
  - 代理不能创建新的后台管理员
  - 管理员列表仅包含当前管理员自己以及等级低于当前管理员的记录
  - 管理员只能查看或编辑自己及等级更低的管理员；删除时不能删除自己，只能删除等级更低的管理员
  - 编辑代理自己的管理员资料时，用户名、状态和等级等受限字段不会开放给代理修改
- 通知数据权限
  - 代理只能查看自己名下用户的数据库通知
  - 管理员通知仅允许查看发送给自己或等级低于自己的管理员的记录
  - 高级管理员可以查看全部前台用户通知，但仍遵循管理员通知的等级范围
- 权限边界
  - 代理数据范围同时应用于列表查询、详情访问、编辑、删除和行操作，不能通过手动拼接后台 URL 绕过
  - 菜单是否显示还受 `config/filament.php` 中 `navigation_levels` 配置控制；代理数据权限与菜单等级权限相互独立，并且需要同时满足
