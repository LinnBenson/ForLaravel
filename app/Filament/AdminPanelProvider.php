<?php

namespace App\Filament;

use App\Filament\Concerns\LogoutDisabledAdmin;
use App\Filament\Resources\AdminControl\PluginManagement\PluginManagement;
use App\Filament\Resources\Dashboard\Login\Login;
use App\Filament\Resources\Dashboard\Home\HomeDashboard;
use App\Filament\Resources\DeveloperCenter\LogInformation\LogInformation;
use App\Filament\Resources\DeveloperCenter\Readme\Readme;
use App\Filament\Resources\DeveloperCenter\FilamentIcons\FilamentIcons;
use App\Filament\Resources\DeveloperCenter\RouteInformation\RouteInformation;
use App\Filament\Resources\SystemSettings\SystemConfig\SystemConfigPage;
use App\Filament\Resources\SystemSettings\ServiceManagement\ServiceManagement;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Providers\PluginProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

/**
 * AdminPanelProvider
 * Filament 后台面板服务提供器。
 * @package App\Filament
 */
class AdminPanelProvider extends PanelProvider {
    /**
     * 配置后台面板。
     * 配置管理员后台面板路径、登录守卫、资源发现和中间件。
     * @param Panel $panel Filament 面板
     * @return Panel Filament 面板
     */
    public function panel( Panel $panel ): Panel {
        $panel
            ->default()
            ->spa()
            ->id( 'admin' )
            ->brandName( 'Admin Dashboard' )
            ->path( config( 'filament.path', 'admin' ) )
            ->authGuard( 'admin' )
            ->login( Login::class )
            ->databaseNotifications()
            ->databaseNotificationsPolling( '0s' )
            ->assets( [
                Js::make( 'masonry', asset( 'filament/js/masonry.pkgd.min.js' ) ),
            ] )
            ->colors( [
                'primary' => Color::Blue,
            ] )
            ->discoverResources( in: app_path( 'Filament/Resources' ), for: 'App\Filament\Resources' )
            ->pages( [
                HomeDashboard::class,
                PluginManagement::class,
                FilamentIcons::class,
                LogInformation::class,
                RouteInformation::class,
                Readme::class,
                SystemConfigPage::class,
                ServiceManagement::class,
            ] )
            ->navigationGroups( array_map(
                static fn ( string $group ): string => __( $group ),
                config( 'filament.navigation_groups', [] )
            ) )
            ->middleware( [
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ] )
            ->authMiddleware( [
                LogoutDisabledAdmin::class,
            ] )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="'.asset( config( 'filament.assets_path' ).'/css/filament/filament/global.css' ).'">'
                )
            );
        PluginProvider::runHook( 'ADMIN_PANEL_PROVIDER_PANEL', $panel );
        return $panel;
    }
}
