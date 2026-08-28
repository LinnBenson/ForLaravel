<?php

use App\Plugins\LaravelTool\Controllers\AdminController;
use App\Filament\Concerns\AdminLevel;
use App\Providers\PluginProvider;
use Illuminate\Support\Facades\Route;

/**
 * Readme Plugin
 */
return new class extends PluginProvider {
    /**
     * 构建程序信息
     */
    public function __construct() {
        $this->name = 'Laravel Tool';
        $this->description = 'Laravel Framework Development Tools';
        $this->version = '1.0.5';
        $this->author = 'System';
        $this->setType( 1 );
        $this->source = 'https://github.com/LinnBenson/ForLaravel/releases/download/plugins-latest/LaravelTool.zip';
    }
    /**
     * 插件启动时执行的操作
     * @return void
     */
    public function boot(): void {
        $this->hook( 'APP_SERVICE_PROVIDER_BOOT', 'register' );
    }
    /**
     * 注册基本信息
     * @return void
     */
    public function register(): void {
        Route::post( '/'.config( 'app.admin_path' ).'/plugins/laravel-tool/run-command', [AdminController::class, 'runCommand'] )
            ->middleware( AdminLevel::class )
            ->name( 'plugins.laravel-tool.run-command' );
    }
};
