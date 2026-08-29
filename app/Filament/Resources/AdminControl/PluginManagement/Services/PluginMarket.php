<?php

namespace App\Filament\Resources\AdminControl\PluginManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * PluginMarket
 * 远程插件市场客户端。
 * @package App\Filament\Resources\AdminControl\PluginManagement\Services
 */
class PluginMarket {
    /**
     * 判断插件市场环境配置是否完整。
     * @return bool 是否启用插件市场
     */
    public static function isConfigured(): bool {
        return trim( (string) env( 'PLUGIN_DOWNLOAD_URL', '' ) ) !== '' &&
            trim( (string) env( 'PLUGIN_DOWNLOAD_TOKEN', '' ) ) !== '';
    }

    /**
     * 获取远程插件列表。
     * @param int $page 当前页码
     * @param string $keyword 搜索关键词
     * @return array{total: int, page: int, quantity: int, data: array<int, array<string, mixed>>} 插件分页数据
     */
    public function getList( int $page = 1, string $keyword = '' ): array {
        if ( !self::isConfigured() ) { throw new RuntimeException( '插件市场地址或 Token 未配置。' ); }
        $url = rtrim( trim( (string) env( 'PLUGIN_DOWNLOAD_URL', '' ) ), '/' ).'/list';
        $response = Http::acceptJson()
            ->withToken( trim( (string) env( 'PLUGIN_DOWNLOAD_TOKEN', '' ) ) )
            ->timeout( 30 )
            ->get( $url, [
                'page' => max( $page, 1 ),
                'keyword' => trim( $keyword ),
            ] );
        $result = $response->json();
        if ( !$response->successful() || !is_array( $result ) || ( $result['status'] ?? null ) !== 'success' ) {
            throw new RuntimeException( "插件市场请求失败（HTTP {$response->status()}）。" );
        }
        $data = $result['data'] ?? null;
        if ( !is_array( $data ) || !is_array( $data['data'] ?? null ) ) {
            throw new RuntimeException( '插件市场返回的数据格式无效。' );
        }
        return [
            'total' => max( (int) ( $data['total'] ?? 0 ), 0 ),
            'page' => max( (int) ( $data['page'] ?? 1 ), 1 ),
            'quantity' => max( (int) ( $data['quantity'] ?? 1 ), 1 ),
            'data' => array_values( array_filter( $data['data'], 'is_array' ) ),
        ];
    }

    /**
     * 下载并安装市场插件。
     * @param string $pluginId 插件标识
     * @return array{id: string, name: string, version: string, updated: bool} 安装结果
     */
    public function install( string $pluginId ): array {
        $this->validatePluginId( $pluginId );
        if ( is_dir( app_path( "Plugins/{$pluginId}" ) ) ) { throw new RuntimeException( '该插件已经安装。' ); }
        return $this->withDownloadedPackage(
            $pluginId,
            fn ( UploadedFile $upload ): array => app( PluginInstaller::class )->installFromUpload( $upload ),
        );
    }

    /**
     * 从插件市场更新已安装插件。
     * @param string $pluginId 插件标识
     * @return array{id: string, name: string, version: string, updated: bool} 更新结果
     */
    public function update( string $pluginId ): array {
        $this->validatePluginId( $pluginId );
        if ( !is_dir( app_path( "Plugins/{$pluginId}" ) ) ) { throw new RuntimeException( '待更新插件尚未安装。' ); }
        return $this->withDownloadedPackage(
            $pluginId,
            fn ( UploadedFile $upload ): array => app( PluginInstaller::class )->updateFromUpload( $pluginId, $upload ),
        );
    }

    /**
     * 校验市场插件标识和下载配置。
     * @param string $pluginId 插件标识
     * @return void
     */
    private function validatePluginId( string $pluginId ): void {
        if ( !self::isConfigured() ) { throw new RuntimeException( 'PLUGIN_DOWNLOAD_URL 或 PLUGIN_DOWNLOAD_TOKEN 未配置。' ); }
        if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $pluginId ) !== 1 ) { throw new RuntimeException( '插件标识无效。' ); }
    }

    /**
     * 下载市场插件并执行安装回调。
     * @param string $pluginId 插件标识
     * @param callable(UploadedFile): array $callback 安装或更新回调
     * @return array{id: string, name: string, version: string, updated: bool} 操作结果
     */
    private function withDownloadedPackage( string $pluginId, callable $callback ): array {
        $temporaryDirectory = storage_path( 'framework/plugins/market' );
        if ( !is_dir( $temporaryDirectory ) && !mkdir( $temporaryDirectory, 0755, true ) && !is_dir( $temporaryDirectory ) ) {
            throw new RuntimeException( '插件市场临时目录创建失败。' );
        }
        $archivePath = "{$temporaryDirectory}/{$pluginId}-".bin2hex( random_bytes( 10 ) ).'.zip';
        try {
            $url = rtrim( trim( (string) env( 'PLUGIN_DOWNLOAD_URL', '' ) ), '/' ).'/download/'.rawurlencode( $pluginId );
            $response = Http::acceptJson()
                ->withToken( trim( (string) env( 'PLUGIN_DOWNLOAD_TOKEN', '' ) ) )
                ->timeout( 120 )
                ->withOptions( ['sink' => $archivePath] )
                ->get( $url );
            if ( !$response->successful() ) { throw new RuntimeException( "插件下载失败（HTTP {$response->status()}）。" ); }
            if ( !is_file( $archivePath ) || filesize( $archivePath ) === 0 ) { throw new RuntimeException( '下载的插件压缩包为空。' ); }
            if ( filesize( $archivePath ) > 20 * 1024 * 1024 ) { throw new RuntimeException( '插件压缩包超过 20 MB。' ); }
            $upload = new UploadedFile( $archivePath, "{$pluginId}.zip", 'application/zip', null, true );
            return $callback( $upload );
        }catch ( Throwable $throwable ) {
            throw new RuntimeException( $throwable->getMessage(), 0, $throwable );
        }finally {
            if ( is_file( $archivePath ) && !unlink( $archivePath ) ) {
                report( new RuntimeException( "Unable to remove the market archive: {$archivePath}" ) );
            }
        }
    }
}
