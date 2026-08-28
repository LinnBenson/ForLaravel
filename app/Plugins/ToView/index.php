<?php

use App\Providers\PluginProvider;
use App\Plugins\ToView\Support\BootstrapIcons;
use App\Plugins\ToView\Controllers\AssetController;
use App\Plugins\ToView\Controllers\UploadController;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * To View Plugin
 */
return new class extends PluginProvider {
    /**
     * 构建程序信息
     */
    public function __construct() {
        $this->name = 'To View';
        $this->description = 'System default view rendering component.';
        $this->version = '1.0.8';
        $this->author = 'System';
        $this->source = 'https://github.com/LinnBenson/ForLaravel/releases/download/plugins-latest/ToView.zip';
    }
    /**
     * 插件启动时执行的操作
     * @return void
     */
    public function boot(): void {
        $this->hook( 'APP_SERVICE_PROVIDER_BOOT', 'register' );
        $this->hook( 'ADMIN_PANEL_PROVIDER_PANEL', 'registerAdminPage' );
    }
    /**
     * 注册基本信息
     * @return void
     */
    public function register(): void {
        // 注册视图命名空间
        app( 'view' )->addNamespace( 'View', "{$this->path}Views" );
        Blade::anonymousComponentPath( "{$this->path}Views/Components", 'View' );
        // 注册路由
        Route::middleware( ['web'] )
            ->prefix( 'internal-plugins-to-view' )
            ->name( 'plugins.to-view.' )
            ->group( function(): void {
                Route::get( '/assets/{path}', [AssetController::class, 'show'] )
                    ->where( 'path', '.*' )
                    ->name( 'asset' );
                Route::get( '/verify', [ AssetController::class, 'verify' ] )->name( 'verify' );
                Route::get( '/tmp/{file}', [ UploadController::class, 'tmp' ] )->name( 'upload.tmp' );
            } );
        Route::middleware( ['api'] )
            ->prefix( 'api/plugins-to-view' )
            ->name( 'plugins.to-view.' )
            ->group( function(): void {
                Route::get( '/upload/config', [ UploadController::class, 'uploadConfig' ] )->name( 'upload.config' );
                Route::post( '/upload', [ UploadController::class, 'upload' ] )->name( 'upload' );
            } );
    }
    /**
     * 注入后台管理界面
     * @param $panel
     * @return void
     */
    public function registerAdminPage( $panel ): void {
        config()->set( "filament.navigation_levels.bootstrap_icons", config( 'filament.navigation_levels.filament_icons', 99990 ) );
        $panel->pages( [
            BootstrapIcons::class,
        ] );
    }
};
