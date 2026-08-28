<?php

namespace App\Filament\Resources\AdminControl\PluginManagement\Services;

use App\Providers\PluginProvider;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * PluginInstaller
 * 插件压缩包下载、校验、安装与回滚服务。
 * @package App\Filament\Resources\AdminControl\PluginManagement\Services
 */
class PluginInstaller {
    private const MAX_ARCHIVE_SIZE = 104857600;
    private const MAX_EXTRACTED_SIZE = 209715200;
    private const MAX_ARCHIVE_FILES = 5000;
    private const MAX_REDIRECTS = 5;

    /**
     * 从上传文件安装插件。
     * @param UploadedFile $upload 上传的 ZIP 压缩包
     * @return array{id: string, name: string, version: string, updated: bool} 安装结果
     */
    public function installFromUpload( UploadedFile $upload ): array {
        return $this->withWorkspace( function( string $workspace ) use ( $upload ): array {
            if ( !$upload->isValid() ) { throw new RuntimeException( '插件压缩包上传失败。' ); }
            if ( $upload->getSize() > self::MAX_ARCHIVE_SIZE ) { throw new RuntimeException( '插件压缩包不能超过 100 MB。' ); }
            $archivePath = "{$workspace}/plugin.zip";
            if ( !copy( $upload->getRealPath(), $archivePath ) ) { throw new RuntimeException( '插件压缩包写入缓存失败。' ); }
            return $this->installArchive( $archivePath, $workspace );
        } );
    }

    /**
     * 从上传文件更新指定插件。
     * 为后续扩展手动更新入口保留与远程更新相同的安全流程。
     * @param string $pluginId 已安装插件标识
     * @param UploadedFile $upload 上传的 ZIP 压缩包
     * @return array{id: string, name: string, version: string, updated: bool} 更新结果
     */
    public function updateFromUpload( string $pluginId, UploadedFile $upload ): array {
        return $this->withWorkspace( function( string $workspace ) use ( $pluginId, $upload ): array {
            if ( !$upload->isValid() ) { throw new RuntimeException( '插件压缩包上传失败。' ); }
            if ( $upload->getSize() > self::MAX_ARCHIVE_SIZE ) { throw new RuntimeException( '插件压缩包不能超过 100 MB。' ); }
            $archivePath = "{$workspace}/plugin.zip";
            if ( !copy( $upload->getRealPath(), $archivePath ) ) { throw new RuntimeException( '插件压缩包写入缓存失败。' ); }
            return $this->installArchive( $archivePath, $workspace, $pluginId );
        } );
    }

    /**
     * 从远程链接安装插件。
     * @param string $url 插件 ZIP 链接
     * @return array{id: string, name: string, version: string, updated: bool} 安装结果
     */
    public function installFromUrl( string $url ): array {
        return $this->withWorkspace( function( string $workspace ) use ( $url ): array {
            $archivePath = "{$workspace}/plugin.zip";
            $this->download( $url, $archivePath );
            return $this->installArchive( $archivePath, $workspace );
        } );
    }

    /**
     * 从插件声明的来源链接更新插件。
     * @param string $pluginId 已安装插件标识
     * @param string $url 插件 ZIP 链接
     * @return array{id: string, name: string, version: string, updated: bool} 更新结果
     */
    public function updateFromUrl( string $pluginId, string $url ): array {
        return $this->withWorkspace( function( string $workspace ) use ( $pluginId, $url ): array {
            $archivePath = "{$workspace}/plugin.zip";
            $this->download( $url, $archivePath );
            return $this->installArchive( $archivePath, $workspace, $pluginId );
        } );
    }

    /**
     * 在独立缓存目录内执行安装任务。
     * @param callable(string): array $callback 安装任务
     * @return array{id: string, name: string, version: string, updated: bool} 安装结果
     */
    private function withWorkspace( callable $callback ): array {
        $cacheRoot = storage_path( 'framework/plugins' );
        if ( !File::isDirectory( $cacheRoot ) && !File::makeDirectory( $cacheRoot, 0755, true ) ) {
            throw new RuntimeException( '插件缓存目录创建失败。' );
        }
        $cacheRoot = realpath( $cacheRoot );
        if ( $cacheRoot === false || !is_writable( $cacheRoot ) ) { throw new RuntimeException( '插件缓存目录不可写。' ); }
        $workspace = $cacheRoot.DIRECTORY_SEPARATOR.'install-'.uuid();
        if ( !File::makeDirectory( $workspace, 0755 ) ) { throw new RuntimeException( '插件安装缓存创建失败。' ); }
        try {
            return $callback( $workspace );
        }finally {
            if ( File::isDirectory( $workspace ) && !is_file( "{$workspace}/.preserve" ) ) {
                File::deleteDirectory( $workspace );
            }
        }
    }

    /**
     * 校验并安装 ZIP 压缩包。
     * @param string $archivePath 压缩包路径
     * @param string $workspace 安装缓存目录
     * @param string|null $updateId 待更新的插件标识
     * @return array{id: string, name: string, version: string, updated: bool} 安装结果
     */
    private function installArchive( string $archivePath, string $workspace, ?string $updateId = null ): array {
        if ( !is_file( $archivePath ) || filesize( $archivePath ) === 0 ) { throw new RuntimeException( '插件压缩包为空。' ); }
        if ( filesize( $archivePath ) > self::MAX_ARCHIVE_SIZE ) { throw new RuntimeException( '插件压缩包不能超过 100 MB。' ); }
        $extractPath = "{$workspace}/extracted";
        File::makeDirectory( $extractPath, 0755 );
        $this->extractArchive( $archivePath, $extractPath );
        $this->removeArchiveMetadata( $extractPath );
        $pluginRoot = $this->locatePluginRoot( $extractPath );
        $pluginId = $this->normalizePluginId( basename( $pluginRoot ) );
        if ( $pluginId === null ) { throw new RuntimeException( '无法从压缩包确定合法的插件标识。' ); }
        if ( $updateId !== null && $pluginId !== $updateId ) {
            throw new RuntimeException( "更新包插件标识 {$pluginId} 与已安装插件 {$updateId} 不一致。" );
        }
        $candidate = require "{$pluginRoot}/index.php";
        if ( !$candidate instanceof PluginProvider ) { throw new RuntimeException( '插件 index.php 必须返回 PluginProvider 实例。' ); }
        $version = $this->validateVersion( $candidate->version );
        $destination = app_path( "Plugins/{$pluginId}" );
        $backupPath = null;
        if ( $updateId === null && !file_exists( $destination ) ) {
            // 插件尚未安装，继续执行首次安装。
        }else {
            $this->assertCanUpdate( $pluginId, $version, $destination );
            $backupPath = "{$workspace}/previous-{$pluginId}";
            PluginProvider::forget( $pluginId );
            if ( !File::moveDirectory( $destination, $backupPath ) ) { throw new RuntimeException( '原插件目录备份失败。' ); }
        }
        if ( !File::moveDirectory( $pluginRoot, $destination ) ) {
            $message = '插件移入正式目录失败。';
            if ( $backupPath !== null ) { $message .= $this->restoreBackup( $backupPath, $destination ); }
            throw new RuntimeException( $message );
        }
        return $this->finishInstallation( $pluginId, $destination, $backupPath );
    }

    /**
     * 完成插件加载和安装入口调用。
     * @param string $pluginId 插件标识
     * @param string $destination 正式插件目录
     * @param string|null $backupPath 更新前的插件备份目录
     * @return array{id: string, name: string, version: string, updated: bool} 安装结果
     */
    private function finishInstallation( string $pluginId, string $destination, ?string $backupPath = null ): array {
        $plugin = null;
        try {
            if ( $backupPath !== null ) {
                $this->preserveDatabaseDirectory( $backupPath, $destination );
            }
            PluginProvider::forget( $pluginId );
            $plugin = PluginProvider::load( $pluginId );
            if ( $plugin === null ) { throw new RuntimeException( '插件移入后无法加载。' ); }
            // 插件安装通常包含建表等 DDL，MySQL 会隐式提交事务，不能由外层事务包裹。
            if ( $plugin->install() !== true ) { throw new RuntimeException( '插件 install() 未返回 true。' ); }
            return [
                'id' => $pluginId,
                'name' => $plugin->name ?? $pluginId,
                'version' => (string) $plugin->version,
                'updated' => $backupPath !== null,
            ];
        }catch ( Throwable $throwable ) {
            $rollbackErrors = [];
            PluginProvider::forget( $pluginId );
            if ( File::isDirectory( $destination ) && !File::deleteDirectory( $destination ) ) {
                $rollbackErrors[] = '插件目录回滚删除失败。';
            }
            if ( $backupPath !== null && File::isDirectory( $backupPath ) ) {
                $restoreMessage = $this->restoreBackup( $backupPath, $destination );
                if ( $restoreMessage !== '' ) { $rollbackErrors[] = trim( $restoreMessage ); }
            }
            $message = $throwable->getMessage();
            if ( $rollbackErrors !== [] ) { $message .= ' 回滚异常：'.implode( ' ', $rollbackErrors ); }
            throw new RuntimeException( $message, 0, $throwable );
        }
    }

    /**
     * 保留更新前插件 Database 目录中的文件。
     * 旧目录内容覆盖更新包中的同名文件，更新包新增的其他文件仍会保留。
     * @param string $backupPath 更新前的插件备份目录
     * @param string $destination 新版本插件目录
     * @return void
     */
    private function preserveDatabaseDirectory( string $backupPath, string $destination ): void {
        $source = "{$backupPath}/Database";
        if ( !File::isDirectory( $source ) ) { return; }
        $target = "{$destination}/Database";
        if ( File::exists( $target ) && !File::isDirectory( $target ) ) {
            throw new RuntimeException( '更新包中的 Database 路径不是目录，无法保留原插件数据。' );
        }
        if ( !File::copyDirectory( $source, $target ) ) {
            throw new RuntimeException( '原插件 Database 目录保留失败。' );
        }
    }

    /**
     * 恢复更新前的插件目录。
     * 恢复失败时保留整个安装缓存，避免旧版本被清理。
     * @param string $backupPath 备份目录
     * @param string $destination 正式插件目录
     * @return string 恢复结果附加消息
     */
    private function restoreBackup( string $backupPath, string $destination ): string {
        PluginProvider::forget( basename( $destination ) );
        if ( !file_exists( $destination ) && File::moveDirectory( $backupPath, $destination ) ) { return ''; }
        $workspace = dirname( $backupPath );
        File::put( "{$workspace}/.preserve", 'Plugin update rollback requires manual recovery.' );
        return " 原插件自动恢复失败，备份已保留在 {$backupPath}。";
    }

    /**
     * 安全解压 ZIP 文件。
     * @param string $archivePath 压缩包路径
     * @param string $extractPath 解压目录
     * @return void
     */
    private function extractArchive( string $archivePath, string $extractPath ): void {
        $zip = new ZipArchive();
        if ( $zip->open( $archivePath ) !== true ) { throw new RuntimeException( '无法打开 ZIP 压缩包。' ); }
        try {
            if ( $zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_FILES ) { throw new RuntimeException( '压缩包文件数量超出限制。' ); }
            $totalSize = 0;
            for ( $index = 0; $index < $zip->numFiles; $index++ ) {
                $stat = $zip->statIndex( $index );
                $name = is_array( $stat ) ? (string) ( $stat['name'] ?? '' ) : '';
                if ( !$this->isSafeArchivePath( $name ) ) { throw new RuntimeException( '压缩包包含不安全的文件路径。' ); }
                $totalSize += (int) ( $stat['size'] ?? 0 );
                if ( $totalSize > self::MAX_EXTRACTED_SIZE ) { throw new RuntimeException( '插件解压后不能超过 200 MB。' ); }
                $operations = 0;
                $attributes = 0;
                if ( $zip->getExternalAttributesIndex( $index, $operations, $attributes ) ) {
                    $fileType = ( $attributes >> 16 ) & 0170000;
                    if ( $fileType === 0120000 ) { throw new RuntimeException( '压缩包不允许包含符号链接。' ); }
                }
            }
            if ( !$zip->extractTo( $extractPath ) ) { throw new RuntimeException( '插件压缩包解压失败。' ); }
        }finally {
            $zip->close();
        }
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $extractPath ) );
        foreach ( $iterator as $file ) {
            if ( $file->isLink() ) { throw new RuntimeException( '解压内容不允许包含符号链接。' ); }
        }
    }

    /**
     * 定位压缩包中的插件根目录。
     * @param string $extractPath 解压目录
     * @return string 插件根目录
     */
    private function locatePluginRoot( string $extractPath ): string {
        if ( is_file( "{$extractPath}/index.php" ) ) {
            throw new RuntimeException( '压缩包不能直接存放插件代码，必须包含唯一的插件目录。' );
        }
        $directories = File::directories( $extractPath );
        if ( count( $directories ) !== 1 || !is_file( "{$directories[0]}/index.php" ) ) {
            throw new RuntimeException( '压缩包必须包含唯一的插件目录和 index.php。' );
        }
        return $directories[0];
    }

    /**
     * 清理 macOS 在压缩包中生成的无关元数据。
     * @param string $extractPath 解压目录
     * @return void
     */
    private function removeArchiveMetadata( string $extractPath ): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $extractPath, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ( $iterator as $file ) {
            $name = $file->getFilename();
            if ( $file->isDir() && $name === '__MACOSX' ) {
                File::deleteDirectory( $file->getPathname() );
                continue;
            }
            if ( $file->isFile() && ( $name === '.DS_Store' || str_starts_with( $name, '._' ) ) ) {
                File::delete( $file->getPathname() );
            }
        }
    }

    /**
     * 检查已安装插件是否允许更新到候选版本。
     * @param string $pluginId 插件标识
     * @param string $candidateVersion 候选版本
     * @param string $destination 正式插件目录
     * @return void
     */
    private function assertCanUpdate( string $pluginId, string $candidateVersion, string $destination ): void {
        if ( !File::isDirectory( $destination ) ) { throw new RuntimeException( "{$pluginId} 尚未安装，无法执行更新。" ); }
        $installed = PluginProvider::load( $pluginId );
        if ( $installed === null ) { throw new RuntimeException( "{$pluginId} 无法加载，不能安全更新。" ); }
        $installedVersion = $this->validateVersion( $installed->version );
        if ( Comparator::lessThan( $candidateVersion, $installedVersion ) ) {
            throw new RuntimeException( "禁止将 {$pluginId} 从 {$installedVersion} 降级到 {$candidateVersion}。" );
        }
        if ( !Comparator::greaterThan( $candidateVersion, $installedVersion ) ) {
            throw new RuntimeException( "{$pluginId} 当前已是 {$installedVersion}，未发现更高版本。" );
        }
    }

    /**
     * 验证插件版本号。
     * @param string|null $version 版本号
     * @return string 有效版本号
     */
    private function validateVersion( ?string $version ): string {
        if ( !is_string( $version ) || trim( $version ) === '' ) { throw new RuntimeException( '插件必须声明版本号。' ); }
        try {
            ( new VersionParser() )->normalize( $version );
        }catch ( Throwable $throwable ) {
            throw new RuntimeException( "插件版本号 {$version} 无效。", 0, $throwable );
        }
        return $version;
    }

    /**
     * 下载远程 ZIP 文件并逐跳校验重定向。
     * @param string $url 来源链接
     * @param string $destination 下载文件路径
     * @return string 最终下载链接
     */
    private function download( string $url, string $destination ): string {
        $currentUrl = trim( $url );
        for ( $redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++ ) {
            $this->validateRemoteUrl( $currentUrl );
            $downloaded = 0;
            $response = Http::connectTimeout( 10 )
                ->timeout( 60 )
                ->withHeaders( ['User-Agent' => 'Laravel-Plugin-Installer'] )
                ->withOptions( [
                    'allow_redirects' => false,
                    'sink' => $destination,
                    'progress' => function( int $downloadTotal, int $downloadNow ) use ( &$downloaded ): void {
                        $downloaded = max( $downloadTotal, $downloadNow );
                        if ( $downloaded > self::MAX_ARCHIVE_SIZE ) { throw new RuntimeException( '远程插件压缩包不能超过 100 MB。' ); }
                    },
                ] )
                ->get( $currentUrl );
            if ( $response->redirect() ) {
                $location = $response->header( 'Location' );
                if ( !is_string( $location ) || $location === '' ) { throw new RuntimeException( '插件下载重定向缺少目标地址。' ); }
                $currentUrl = (string) UriResolver::resolve( new Uri( $currentUrl ), new Uri( $location ) );
                continue;
            }
            if ( !$response->successful() ) { throw new RuntimeException( "插件下载失败，HTTP 状态码：{$response->status()}。" ); }
            if ( !is_file( $destination ) || filesize( $destination ) === 0 ) { throw new RuntimeException( '远程插件压缩包为空。' ); }
            return $currentUrl;
        }
        throw new RuntimeException( '插件下载重定向次数过多。' );
    }

    /**
     * 校验远程链接并拒绝内网地址。
     * @param string $url 远程链接
     * @return void
     */
    private function validateRemoteUrl( string $url ): void {
        $parts = parse_url( $url );
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        $host = (string) ( $parts['host'] ?? '' );
        if ( !in_array( $scheme, ['http', 'https'], true ) || $host === '' ) { throw new RuntimeException( '插件来源链接必须是有效的 HTTP 或 HTTPS 地址。' ); }
        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) { throw new RuntimeException( '插件来源链接不允许包含身份凭证。' ); }
        $port = (int) ( $parts['port'] ?? ( $scheme === 'https' ? 443 : 80 ) );
        if ( !in_array( $port, [80, 443], true ) ) { throw new RuntimeException( '插件来源链接仅允许使用 80 或 443 端口。' ); }
        $addresses = filter_var( $host, FILTER_VALIDATE_IP ) ? [$host] : $this->resolveHostAddresses( $host );
        if ( $addresses === [] ) { throw new RuntimeException( '插件来源链接的主机无法解析。' ); }
        foreach ( $addresses as $address ) {
            if ( !filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                throw new RuntimeException( '插件来源链接不允许访问内网或保留地址。' );
            }
        }
    }

    /**
     * 解析主机的 IPv4 和 IPv6 地址。
     * @param string $host 主机名
     * @return array<int, string> IP 地址列表
     */
    private function resolveHostAddresses( string $host ): array {
        $records = dns_get_record( $host, DNS_A | DNS_AAAA );
        if ( !is_array( $records ) ) { return []; }
        $addresses = [];
        foreach ( $records as $record ) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if ( is_string( $address ) ) { $addresses[] = $address; }
        }
        return array_values( array_unique( $addresses ) );
    }

    /**
     * 规范化并验证插件标识。
     * @param string $value 原始标识
     * @return string|null 合法插件标识
     */
    private function normalizePluginId( string $value ): ?string {
        $value = preg_replace( '/-(?:main|master)$/i', '', trim( $value ) ) ?? '';
        return preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $value ) === 1 ? $value : null;
    }

    /**
     * 检查 ZIP 条目路径是否安全。
     * @param string $path ZIP 条目路径
     * @return bool 是否安全
     */
    private function isSafeArchivePath( string $path ): bool {
        if ( $path === '' || str_contains( $path, "\0" ) ) { return false; }
        $path = str_replace( '\\', '/', $path );
        if ( str_starts_with( $path, '/' ) || preg_match( '/^[A-Za-z]:\//', $path ) === 1 ) { return false; }
        foreach ( explode( '/', $path ) as $segment ) {
            if ( $segment === '..' ) { return false; }
        }
        return true;
    }
}
