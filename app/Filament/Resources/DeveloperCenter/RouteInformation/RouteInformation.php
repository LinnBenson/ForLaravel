<?php

namespace App\Filament\Resources\DeveloperCenter\RouteInformation;

use App\Filament\Concerns\HasNavigationLevel;
use BackedEnum;
use Closure;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use ReflectionFunction;
use Throwable;
use UnitEnum;

/**
 * RouteInformation
 * 开发者中心路由列表页面。
 * @package App\Filament\Resources\DeveloperCenter\RouteInformation
 */
class RouteInformation extends Page {
    use HasNavigationLevel;

    protected static string $navigationPermission = 'route_information';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $slug = 'developer-center/routes';

    protected static ?int $navigationSort = 11;

    protected string $view = 'Filament::DeveloperCenter.RouteInformation.route-information';

    public string $search = '';

    public string $method = 'all';

    /**
     * 获取路由页面数据。
     * 读取当前应用已注册的路由，并按关键词和请求方法过滤。
     * @return array{routes: array<int, array<string, mixed>>, methods: array<int, string>, total: int, named: int, filtered: int} 路由页面数据
     */
    public function getRouteData(): array {
        $routes = array_map(
            fn ( Route $route ): array => $this->normalizeRoute( $route ),
            RouteFacade::getRoutes()->getRoutes()
        );
        $methods = [];
        foreach ( $routes as $route ) {
            foreach ( $route['methods'] as $method ) { $methods[$method] = true; }
        }
        $methods = array_keys( $methods );
        sort( $methods );
        $total = count( $routes );
        $named = count( array_filter( $routes, fn ( array $route ): bool => $route['name'] !== '-' ) );
        $search = mb_strtolower( trim( $this->search ) );
        $routes = array_values( array_filter( $routes, function ( array $route ) use ( $search ): bool {
            if ( $this->method !== 'all' && ! in_array( $this->method, $route['methods'], true ) ) { return false; }
            if ( $search === '' ) { return true; }
            return str_contains( mb_strtolower( $route['searchable'] ), $search );
        } ) );
        return [
            'routes' => $routes,
            'methods' => $methods,
            'total' => $total,
            'named' => $named,
            'filtered' => count( $routes ),
        ];
    }

    /**
     * 标准化路由信息。
     * 将 Laravel 路由对象转换为页面可直接展示的数组。
     * @param Route $route 路由对象
     * @return array<string, mixed> 标准化路由信息
     */
    private function normalizeRoute( Route $route ): array {
        $methods = $route->methods();
        try {
            $middleware = $route->gatherMiddleware();
        }catch ( Throwable ) {
            $middleware = $route->middleware();
        }
        $source = $this->resolveRouteSource( $route );
        $data = [
            'domain' => $route->getDomain() ?: '-',
            'methods' => $methods,
            'uri' => $route->uri(),
            'name' => $route->getName() ?: '-',
            'action' => $route->getActionName(),
            'action_short' => $this->formatActionName( $route->getActionName() ),
            'middleware' => array_values( array_map( 'strval', $middleware ) ),
            'source_file' => $source['file'],
            'source_type' => $source['type'],
        ];
        $data['searchable'] = implode( ' ', [
            $data['domain'],
            implode( ' ', $data['methods'] ),
            $data['uri'],
            $data['name'],
            $data['action'],
            implode( ' ', $data['middleware'] ),
            $data['source_file'],
            $data['source_type'],
        ] );
        return $data;
    }

    /**
     * 解析路由来源文件。
     * 闭包路由定位注册文件，类处理器定位控制器或 Filament 页面文件。
     * @param Route $route 路由对象
     * @return array{file: string, type: string} 路由来源信息
     */
    private function resolveRouteSource( Route $route ): array {
        $uses = $route->getAction( 'uses' );
        $file = null;
        $type = '动态注册';
        try {
            if ( $uses instanceof Closure ) {
                $file = ( new ReflectionFunction( $uses ) )->getFileName() ?: null;
                $type = '闭包路由';
            }else {
                $action = $route->getActionName();
                $class = explode( '@', $action, 2 )[0];
                if ( class_exists( $class ) ) {
                    $reflection = new ReflectionClass( $class );
                    $file = $reflection->getFileName() ?: null;
                    if ( is_subclass_of( $class, Page::class ) || str_starts_with( $class, 'App\\Filament\\' ) ) {
                        $type = 'Filament 页面';
                    }elseif ( str_contains( $class, 'Controller' ) ) {
                        $type = '控制器路由';
                    }
                }
            }
        }catch ( Throwable ) {
            $file = null;
        }
        $relativeFile = $this->formatSourcePath( $file );
        return [
            'file' => $relativeFile,
            'type' => $type,
        ];
    }

    /**
     * 格式化来源文件路径。
     * 项目目录内的文件使用相对路径，便于阅读且避免暴露服务器绝对路径。
     * @param string|null $file 来源文件绝对路径
     * @return string 格式化后的路径
     */
    private function formatSourcePath( ?string $file ): string {
        if ( $file === null || $file === '' ) { return '-'; }
        $file = str_replace( '\\', '/', $file );
        $basePath = str_replace( '\\', '/', base_path() );
        if ( str_starts_with( $file, "{$basePath}/" ) ) {
            return substr( $file, strlen( $basePath ) + 1 );
        }
        return basename( $file );
    }

    /**
     * 格式化处理器名称。
     * 仅保留类名和方法名，完整命名空间由页面点击后展示。
     * @param string $action 完整处理器名称
     * @return string 简短处理器名称
     */
    private function formatActionName( string $action ): string {
        if ( $action === 'Closure' ) { return $action; }
        [$class, $method] = array_pad( explode( '@', $action, 2 ), 2, null );
        $className = str_contains( $class, '\\' )
            ? substr( $class, strrpos( $class, '\\' ) + 1 )
            : $class;
        return $method === null ? $className : "{$className}@{$method}";
    }

    /**
     * 页面信息
     */
    public function getBreadcrumbs(): array { return [__( 'filament.groups.developer' ), __( 'filament.navigation.routes' )]; }
    public static function getNavigationLabel(): string { return __( 'filament.navigation.routes' ); }
    public function getTitle(): string { return __( 'filament.titles.routes' ); }
    public static function getNavigationGroup(): string|UnitEnum|null { return __( 'filament.groups.developer' ); }
}
