<?php

namespace App\Filament\Resources\AdminControl\PluginManagement\Services;

use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
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
            $ignoredPaths = $this->getIgnoredPaths( $root, $temporaryDirectory );
            $zip->addEmptyDir( $pluginId );
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
                    function ( \SplFileInfo $file ) use ( $root, $ignoredPaths ): bool {
                        if ( $file->isLink() || $file->getFilename() === '.git' ) { return false; }
                        $relativePath = substr( $file->getPathname(), strlen( $root ) + 1 );
                        if ( $relativePath === '.gitignore' && $file->isFile() ) { return true; }
                        return !isset( $ignoredPaths[$relativePath] );
                    },
                ),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ( $iterator as $file ) {
                if ( !$file->isFile() || $file->isLink() ) { continue; }
                $relativePath = substr( $file->getPathname(), strlen( $root ) + 1 );
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

    /**
     * 使用 Git 原生规则获取排除路径。
     * 独立临时仓库只加载插件根目录的 .gitignore，不读取子目录或原仓库的规则及索引。
     * @param string $pluginRoot 插件根目录
     * @param string $temporaryDirectory 打包临时目录
     * @return array<string, true> 排除文件映射
     */
    private function getIgnoredPaths( string $pluginRoot, string $temporaryDirectory ): array {
        $ignorePath = "{$pluginRoot}/.gitignore";
        if ( !file_exists( $ignorePath ) && !is_link( $ignorePath ) ) { return []; }
        if ( is_link( $ignorePath ) || !is_file( $ignorePath ) || !is_readable( $ignorePath ) ) {
            throw new RuntimeException( '插件 .gitignore 必须是可读的普通文件。' );
        }
        if ( filesize( $ignorePath ) > 1048576 ) { throw new RuntimeException( '插件 .gitignore 文件不能超过 1 MB。' ); }
        $rules = file_get_contents( $ignorePath );
        if ( $rules === false ) { throw new RuntimeException( '插件 .gitignore 文件读取失败。' ); }
        $repositoryPath = "{$temporaryDirectory}/ignore-".bin2hex( random_bytes( 10 ) );
        // 清除继承的 Git 环境变量，避免影响临时仓库、索引及规则匹配。
        $environment = [];
        foreach ( array_keys( array_merge( getenv(), $_ENV, $_SERVER ) ) as $name ) {
            if ( str_starts_with( (string) $name, 'GIT_' ) ) { $environment[$name] = false; }
        }
        $environment['GIT_CONFIG_NOSYSTEM'] = '1';
        $environment['GIT_CONFIG_GLOBAL'] = '/dev/null';
        try {
            $initialize = new Process( ['git', 'init', '--template=', '--quiet', $repositoryPath], $temporaryDirectory, $environment );
            $initialize->mustRun();
            if ( file_put_contents( "{$repositoryPath}/.gitignore", $rules ) === false ) {
                throw new RuntimeException( '插件排除规则临时文件写入失败。' );
            }
            // 在空工作区匹配真实文件路径，避免子目录规则、嵌套仓库和跟踪状态干扰。
            $iterator = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator( $pluginRoot, FilesystemIterator::SKIP_DOTS ),
                static fn ( \SplFileInfo $file ): bool => !$file->isLink() && $file->getFilename() !== '.git',
            ) );
            $input = ( function () use ( $iterator, $pluginRoot ): \Generator {
                foreach ( $iterator as $file ) {
                    if ( $file->isFile() ) { yield substr( $file->getPathname(), strlen( $pluginRoot ) + 1 )."\0"; }
                }
            } )();
            $process = new Process( [
                'git', '-c', 'core.ignoreCase=false',
                'check-ignore', '--no-index', '--stdin', '-z',
            ], $repositoryPath, $environment, $input );
            $process->run();
            // check-ignore 返回 1 表示没有匹配项，并非执行失败。
            if ( !in_array( $process->getExitCode(), [0, 1], true ) ) {
                throw new RuntimeException( 'Git 排除规则匹配失败。' );
            }
            $paths = explode( "\0", $process->getOutput() );
            return array_fill_keys( array_filter( $paths, static fn ( string $path ): bool => $path !== '' ), true );
        }catch ( Throwable $throwable ) {
            throw new RuntimeException( '插件 .gitignore 解析失败，请确认 Git 已安装且 PHP 可以执行 Git 命令。', 0, $throwable );
        }finally {
            if ( is_dir( $repositoryPath ) && !( new Filesystem() )->deleteDirectory( $repositoryPath ) ) {
                report( new RuntimeException( '插件排除规则临时目录清理失败。' ) );
            }
        }
    }
}
