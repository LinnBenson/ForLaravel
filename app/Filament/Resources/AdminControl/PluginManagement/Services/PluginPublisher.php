<?php

namespace App\Filament\Resources\AdminControl\PluginManagement\Services;

use FilesystemIterator;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * PluginPublisher
 * 已安装插件打包上传服务。
 * @package App\Filament\Resources\AdminControl\PluginManagement\Services
 */
class PluginPublisher {
    private const MAX_ARCHIVE_SIZE = 20971520;

    /**
     * 判断插件上传环境配置是否完整。
     * @return bool 是否允许上传插件
     */
    public static function isConfigured(): bool {
        return trim( (string) env( 'PLUGIN_UPLOAD_URL', '' ) ) !== '' &&
            trim( (string) env( 'PLUGIN_UPLOAD_TOKEN', '' ) ) !== '';
    }

    /**
     * 打包并上传插件。
     * 只读取本地插件，上传结束后仅删除生成的临时压缩包。
     * @param string $pluginId 插件标识
     * @return array<string, mixed> 远端响应数据
     */
    public function publish( string $pluginId ): array {
        if ( !self::isConfigured() ) { throw new RuntimeException( '插件上传地址或 Token 未配置。' ); }
        $plugin = $this->resolvePlugin( $pluginId );
        $archivePath = null;
        try {
            $archivePath = $this->createArchive( $pluginId, (string) $plugin->path );
            if ( filesize( $archivePath ) > self::MAX_ARCHIVE_SIZE ) { throw new RuntimeException( '插件压缩包超过 20 MB。' ); }
            $stream = fopen( $archivePath, 'rb' );
            if ( $stream === false ) { throw new RuntimeException( '插件压缩包无法读取。' ); }
            try {
                $url = rtrim( trim( (string) env( 'PLUGIN_UPLOAD_URL', '' ) ), '/' ).'/upload';
                $response = Http::acceptJson()
                    ->withToken( trim( (string) env( 'PLUGIN_UPLOAD_TOKEN', '' ) ) )
                    ->timeout( 120 )
                    ->attach( 'upload', $stream, "{$pluginId}.zip" )
                    ->post( $url, [
                        'rid' => $pluginId,
                        'name' => (string) ( $plugin->name ?? $pluginId ),
                        'description' => (string) ( $plugin->description ?? '' ),
                        'version' => (string) $plugin->version,
                        'author' => (string) ( $plugin->author ?? '' ),
                    ] );
            }finally {
                fclose( $stream );
            }
            $result = $response->json();
            if ( !$response->successful() || !is_array( $result ) || ( $result['status'] ?? null ) !== 'success' ) {
                $responseData = is_array( $result ) ? ( $result['data'] ?? null ) : null;
                $message = is_array( $responseData ) ? ( $responseData['message'] ?? null ) : $responseData;
                throw new RuntimeException( is_string( $message ) ? $message : "远端上传失败（HTTP {$response->status()}）。" );
            }
            return $result;
        }finally {
            if ( is_string( $archivePath ) && is_file( $archivePath ) && !unlink( $archivePath ) ) {
                report( new RuntimeException( "Unable to remove the publish archive: {$archivePath}" ) );
            }
        }
    }

    /**
     * 解析并校验本地插件。
     * @param string $pluginId 插件标识
     * @return object 插件实例
     */
    private function resolvePlugin( string $pluginId ): object {
        if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $pluginId ) !== 1 ) { throw new RuntimeException( '插件标识无效。' ); }
        $plugin = plugin( $pluginId );
        $pluginRoot = realpath( app_path( 'Plugins' ) );
        $pluginPath = $plugin ? realpath( (string) $plugin->path ) : false;
        if (
            !$plugin || $pluginRoot === false || $pluginPath === false ||
            is_link( app_path( "Plugins/{$pluginId}" ) ) ||
            !str_starts_with( "{$pluginPath}/", rtrim( $pluginRoot, DIRECTORY_SEPARATOR ).DIRECTORY_SEPARATOR )
        ) {
            throw new RuntimeException( '插件不存在或目录无效。' );
        }
        return $plugin;
    }

    /**
     * 创建保留插件根目录的 ZIP 压缩包。
     * @param string $pluginId 插件标识
     * @param string $pluginPath 插件目录
     * @return string ZIP 文件路径
     */
    private function createArchive( string $pluginId, string $pluginPath ): string {
        $temporaryDirectory = storage_path( 'framework/plugins/publish' );
        if ( !is_dir( $temporaryDirectory ) && !mkdir( $temporaryDirectory, 0755, true ) && !is_dir( $temporaryDirectory ) ) {
            throw new RuntimeException( '插件打包临时目录创建失败。' );
        }
        $archivePath = "{$temporaryDirectory}/{$pluginId}-".bin2hex( random_bytes( 10 ) ).'.zip';
        $zip = new ZipArchive();
        if ( $zip->open( $archivePath, ZipArchive::CREATE | ZipArchive::EXCL ) !== true ) {
            throw new RuntimeException( '插件压缩包创建失败。' );
        }
        try {
            $root = rtrim( realpath( $pluginPath ) ?: '', DIRECTORY_SEPARATOR );
            if ( $root === '' ) { throw new RuntimeException( '插件目录不存在。' ); }
            $zip->addEmptyDir( $pluginId );
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ( $iterator as $file ) {
                if ( !$file->isFile() || $file->isLink() ) { continue; }
                $relativePath = substr( $file->getPathname(), strlen( $root ) + 1 );
                if ( preg_match( '#(^|/)\.git(/|$)#', $relativePath ) === 1 ) { continue; }
                if ( !$zip->addFile( $file->getPathname(), "{$pluginId}/{$relativePath}" ) ) {
                    throw new RuntimeException( "插件文件加入压缩包失败：{$relativePath}" );
                }
            }
        }catch ( Throwable $throwable ) {
            $zip->close();
            if ( is_file( $archivePath ) ) { unlink( $archivePath ); }
            throw $throwable;
        }
        if ( !$zip->close() || !is_file( $archivePath ) ) { throw new RuntimeException( '插件压缩包写入失败。' ); }
        return $archivePath;
    }
}
