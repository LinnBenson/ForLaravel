<?php

namespace App\Providers;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class PluginProvider {

    /** @var array<string, PluginProvider> */
    private static array $cache = [];

    /** @var array<string, bool> */
    private static array $loading = [];

    /** @var array<string, callable> */
    private array $hooks = [];

    /** @var array<string, mixed>|null */
    private ?array $configs = null;

    private bool $enabled = false;

    public ?string $id = null;

    public ?string $path = null;

    private array $types = [ 'rely', 'plugin' ];
    public string $type = 'rely';

    public ?string $name = null;

    public ?string $version = null;

    public ?string $author = null;

    public ?string $description = null;

    public ?string $source = null;

    /** @var array<string, string> */
    public array $relyComposer = [];

    /** @var array<string, string> */
    public array $relyPlugin = [];

    /**
     * 加载插件。
     * 验证插件目录、依赖和循环引用后启用插件。
     * @param string $id 插件标识
     * @return PluginProvider|null 插件实例
     */
    public static function load( string $id ): ?PluginProvider {
        // 安全检查
        if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $id ) !== 1 ) { return null; }
        // 检查缓存
        if ( isset( self::$cache[$id] ) ) { return self::$cache[$id]; }
        // 检查插件是否处在加载中，防止循环依赖
        if ( isset( self::$loading[$id] ) ) {
            throw new LogicException(
                "Circular plugin dependency detected while loading {$id}."
            );
        }
        $pluginPath = self::resolvePluginPath( $id );
        if ( $pluginPath === null ) { return null; }
        self::$loading[$id] = true;
        try {
            $plugin = require "{$pluginPath}/index.php";
            if ( !$plugin instanceof self ) {
                throw new LogicException(
                    "Plugin {$id} must return a PluginProvider instance."
                );
            }
            if (
                !self::validateComposerDependencies( $id, $plugin ) ||
                !self::validatePluginDependencies( $id, $plugin )
            ) { return null; }
            $plugin->enable( $id, $pluginPath );
            self::$cache[$id] = $plugin;
            return $plugin;
        }finally {
            unset( self::$loading[$id] );
        }
    }

    /**
     * 移除插件加载缓存。
     * 安装或更新回滚时用于避免继续使用已移除的插件实例。
     * @param string $id 插件标识
     * @return void
     */
    public static function forget( string $id ): void {
        unset( self::$cache[$id], self::$loading[$id] );
    }

    /**
     * 启用插件。
     * 每个插件实例只能启用一次。
     * @param string $id 插件标识
     * @param string $path 插件路径
     * @return void
     */
    final public function enable( string $id, string $path ): void {
        if ( $this->enabled ) {
            throw new LogicException(
                "Plugin {$this->id} is already enabled; enable() cannot be called again."
            );
        }
        $this->enabled = true;
        $this->id = $id;
        $this->path = "{$path}/";

        try {
            $this->boot();
        }catch ( Throwable $throwable ) {
            $this->enabled = false;
            $this->id = null;
            $this->path = null;
            throw $throwable;
        }
    }

    /**
     * 运行插件钩子。
     * @param string $name 钩子名称
     * @param mixed ...$args 钩子回调参数
     * @return mixed 钩子回调返回值
     */
    public static function runHook( string $name, ...$args ): mixed {
        if ( $name === 'REBUILD_PLUGIN_DATA' ) { throw new LogicException( "Hook {$name} cannot be called." ); }
        if ( !array_key_exists( $name, config( 'plugin.hooks', [] ) ) ) {
            throw new LogicException( "Hook {$name} is not registered." );
        }
        // 检查插件钩子
        $plugins = config( 'plugin.enabled', [] );
        $plugins = is_array( $plugins ) ? $plugins : [];
        foreach ( $plugins as $pluginId ) {
            if ( !is_string( $pluginId ) ) { continue; }
            $plugin = plugin( $pluginId );
            if ( $plugin === null ) { continue; }
            if ( array_key_exists( $name, $plugin->hooks ) ) {
                call_user_func_array( $plugin->hooks[$name], $args );
            }
        }
        return true;
    }

    /**
     * 判断插件是否已经启用。
     * @return bool 是否启用
     */
    final public function isEnabled(): bool { return $this->enabled; }

    /**
     * 插件启动入口。
     * 子插件可以覆盖此方法注册配置、事件及其他功能。
     * @return void
     */
    protected function boot(): void {}

    /**
     * 插件安装入口。
     * 子插件可以覆盖此方法执行安装逻辑，如创建数据库表、写入配置等。
     * @return bool 安装成功返回 true，失败返回 false
     */
    public function install(): bool { return true; }

    /**
     * 插件卸载入口。
     * 子插件可以覆盖此方法执行卸载逻辑，如删除数据库表、清理配置等。
     * @return bool 卸载成功返回 true，失败返回 false
     */
    public function uninstall(): bool { return true; }

    /**
     * 注册插件钩子。
     * @param string $name 钩子名称
     * @param callable|string $callback 钩子回调函数或方法名
     * @return bool 注册成功返回 true，失败返回 false
     */
    public function hook( string $name, callable|string $callback ): bool {
        if ( !array_key_exists( $name, config( 'plugin.hooks', [] ) ) || array_key_exists( $name, $this->hooks ) ) {
            return false;
        }
        // 注册钩子回调函数或方法
        if ( is_callable( $callback ) ) {
            $this->hooks[$name] = $callback;
        }else if ( is_string( $callback ) && is_public( $this, $callback ) ) {
            $this->hooks[$name] = function( ...$args ) use ( $callback ) {
                return $this->{$callback}( ...$args );
            };
        }
        return isset( $this->hooks[$name] );
    }

    /**
     * 获取插件钩子。
     * @return array<string, callable> 插件钩子
     */
    public function getHook(): array { return $this->hooks; }

    /**
     * 注册 SQLite 数据库连接。
     * @param string $name 连接名称
     * @param string $file 数据库文件名
     * @param array<string, mixed> $options 连接选项
     * @return bool 注册成功返回 true，失败返回 false
     */
    public function sqlite( string $name, string $file, array $options = [] ): bool {
        $databasePath = "{$this->path}Database/{$file}";
        if ( !file_exists( $databasePath ) && file_put_contents( $databasePath, '' ) === false ) {
            throw new RuntimeException( 'Unable to create the SQLite database file.' );
            return false;
        }
        $prefix = substr( preg_replace( '/[^A-Za-z]/', '', (string) $this->id ) ?? '', 0, 15 );
        if ( $prefix === '' ) { $prefix = 'sqlite'; }
        config()->set( "database.connections.{$name}", array_merge([
            'driver' => 'sqlite',
            'url' => null,
            'database' => $databasePath,
            'prefix' => "{$prefix}_",
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
        ], $options ));
        return true;
    }

    /**
     * 获取插件配置。
     * @param string $name 配置名称
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function config( string $name, mixed $default = null ): mixed {
        if ( $this->configs === null ) {
            $loadConfig = function( string $file ): array {
                if ( !is_file( $file ) || !is_readable( $file ) ) { return []; }
                $config = include $file;
                return is_array( $config ) ? $config : [];
            };
            $this->configs = array_replace_recursive(
                $loadConfig( "{$this->path}config.php" ),
                $loadConfig( config_path( "plugin/{$this->id}.php" ) ),
            );
        }
        if ( $name === '' ) { return $this->configs; }
        return data_get( $this->configs, $name, $default );
    }

    /**
     * 设置插件类型。
     * @param int $type 插件类型索引
     * @return bool 设置成功返回 true，失败返回 false
     */
    public function setType( int $type ): bool {
        if ( !array_key_exists( $type, $this->types ) ) { return false; }
        $this->type = $this->types[$type];
        return true;
    }

    /**
     * 解析插件目录。
     * 确保插件真实路径位于 app/Plugins 目录内。
     * @param string $id 插件标识
     * @return string|null 插件目录
     */
    private static function resolvePluginPath( string $id ): ?string {
        $pluginRoot = realpath( app_path( 'Plugins' ) );
        if ( $pluginRoot === false ) { return null; }
        $pluginPath = realpath( "{$pluginRoot}/{$id}" );
        if (
            $pluginPath === false ||
            !is_dir( $pluginPath ) ||
            !str_starts_with(
                "{$pluginPath}/",
                rtrim( $pluginRoot, DIRECTORY_SEPARATOR ).DIRECTORY_SEPARATOR,
            )
        ) { return null; }
        $pluginIndex = "{$pluginPath}/index.php";
        if ( !is_file( $pluginIndex ) || !is_readable( $pluginIndex ) ) {
            return null;
        }
        return $pluginPath;
    }

    /**
     * 验证 Composer 依赖。
     * @param string $id 插件标识
     * @param PluginProvider $plugin 插件实例
     * @return bool 依赖是否满足
     */
    private static function validateComposerDependencies( string $id, PluginProvider $plugin ): bool {
        $versionParser = new VersionParser();
        foreach ( $plugin->relyComposer as $package => $constraint ) {
            if ( !InstalledVersions::isInstalled( $package ) ) {
                Log::warning( 'The plugin Composer dependency is missing and plugin loading has been skipped.', [
                    'plugin_id' => $id,
                    'package' => $package,
                    'required_version' => $constraint,
                ] );
                return false;
            }
            try {
                if ( InstalledVersions::satisfies( $versionParser, $package, $constraint ) ) { continue; }
                Log::warning( 'The dependency version of the plug-in Composer is not satisfied and the plug-in loading has been skipped.', [
                    'plugin_id' => $id,
                    'package' => $package,
                    'required_version' => $constraint,
                    'installed_version' => InstalledVersions::getPrettyVersion( $package ) ?? 'unknown',
                ] );
            }catch ( Throwable $throwable ) {
                Log::warning( 'The plugin Composer dependency declaration is invalid and plugin loading has been skipped.', [
                    'plugin_id' => $id,
                    'package' => $package,
                    'required_version' => $constraint,
                    'error' => $throwable->getMessage(),
                ] );
            }
            return false;
        }
        return true;
    }

    /**
     * 验证插件依赖。
     * @param string $id 插件标识
     * @param PluginProvider $plugin 插件实例
     * @return bool 依赖是否满足
     */
    private static function validatePluginDependencies( string $id, PluginProvider $plugin ): bool {
        foreach ( $plugin->relyPlugin as $pluginId => $constraint ) {
            $relyPlugin = self::load( $pluginId );
            if ( $relyPlugin === null ) {
                Log::warning( 'The plug-in dependency is missing or cannot be loaded, and the plug-in loading has been skipped.', [
                    'plugin_id' => $id,
                    'dependency_plugin_id' => $pluginId,
                    'required_version' => $constraint,
                ] );
                return false;
            }
            $installedVersion = $relyPlugin->version;
            try {
                if ( is_string( $installedVersion ) && Semver::satisfies( $installedVersion, $constraint ) ) { continue; }
                Log::warning( 'The plug-in dependency version is not satisfied and plug-in loading has been skipped.', [
                    'plugin_id' => $id,
                    'dependency_plugin_id' => $pluginId,
                    'required_version' => $constraint,
                    'installed_version' => $installedVersion ?? 'unknown',
                ] );
            }catch ( Throwable $throwable ) {
                Log::warning( 'The plug-in dependency declaration is invalid and plug-in loading has been skipped.', [
                    'plugin_id' => $id,
                    'dependency_plugin_id' => $pluginId,
                    'required_version' => $constraint,
                    'installed_version' => $installedVersion ?? 'unknown',
                    'error' => $throwable->getMessage(),
                ] );
            }
            return false;
        }
        return true;
    }
}
