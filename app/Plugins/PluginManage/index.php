<?php

use App\Filament\Concerns\AdminLevel;
use App\Plugins\PluginManage\Controllers\AdminController;
use App\Plugins\PluginManage\Controllers\MarketController;
use App\Plugins\PluginManage\Support\PackagesTable;
use App\Providers\PluginProvider;
use App\Plugins\PluginManage\Models\Packages;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Plugin Manage
 * 私有部署的插件市场系统
 */
return new class extends PluginProvider {
    /**
     * 插件信息
     */
    public function __construct() {
        $this->name = '插件市场';
        $this->description = '私有部署的插件市场系统';
        $this->version = '1.0.2';
        $this->author = 'System';
    }

    /**
     * 注册插件 Hook。
     * @return void
     */
    public function boot(): void {
        $this->hook( 'APP_SERVICE_PROVIDER_BOOT', 'register' );
        $this->hook( 'ADMIN_PANEL_PROVIDER_PANEL', 'registerAdminPage' );
        $this->hook( 'REBUILD_PLUGIN_DATA', function() {
            Packages::down();
            Packages::up();
            return true;
        });
    }

    /**
     * 注册插件管理路由与数据库连接。
     * @return void
     */
    public function register(): void {
        /**
         * 注册命名空间
         */
        $namespace = 'PluginManage';
        app( 'view' )->addNamespace( $namespace, "{$this->path}Views" );
        /**
         * 注册路由
         */
        $routerName = "plugin.plugin-manage";
        // 普通路由
        Route::prefix( $this->config( 'entrance' ) )
        ->name( "{$routerName}." )
        ->group(function(): void {
            Route::post( "/upload", [MarketController::class, 'upload'] )->name( 'upload' );
            Route::get( "/download/{name}", [MarketController::class, 'download'] )->name( 'download' );
            Route::get( "/list", [MarketController::class, 'list'] )->name( 'list' );
        });
        // 管理路由
        Route::middleware( AdminLevel::class )
        ->prefix( '/'.config( 'filament.path' ).'/plugin/plugin-manage' )
        ->name( "{$routerName}." )
        ->group(function(): void {
            Route::post( '/rebuild-tables', [AdminController::class, 'rebuild'] )->name( 'rebuild-tables' );
        });
        /**
         * 注册数据库连接
         */
        $this->sqlite( 'plugin-manage', 'database.sqlite' );
    }

    /**
     * 注册后台插件包管理页面。
     * @param Panel $panel Filament 面板
     * @return void
     */
    public function registerAdminPage( Panel $panel ): void {
        config()->set( 'filament.navigation_levels.log_information', config( 'filament.navigation_levels.log_information', 99900 ) );
        $panel->pages( [
            PackagesTable::class,
        ] );
    }

    /**
     * 安装插件。
     * 创建插件包数据表。
     * @return bool 安装成功返回 true
     */
    public function install(): bool {
        $this->register();
        Packages::up();
        return true;
    }

    /**
     * 卸载插件。
     * 删除插件包数据表。
     * @return bool 卸载成功返回 true
     */
    public function uninstall(): bool {
        $this->register();
        Packages::down();
        return true;
    }
};
