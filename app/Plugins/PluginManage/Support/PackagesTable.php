<?php

namespace App\Plugins\PluginManage\Support;

use App\Filament\Concerns\HasNavigationLevel;
use App\Plugins\PluginManage\Models\Packages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * PackagesTable
 * 插件包管理页面。
 * @package App\Plugins\PluginManage\Support
 */
class PackagesTable extends Page implements HasTable {
    use HasNavigationLevel {
        canAccess as protected canAccessByNavigationLevel;
    }
    use InteractsWithTable;

    protected static string $navigationPermission = 'plugin_manage_packages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $slug = 'plugin-packages';

    protected static ?int $navigationSort = 1;

    protected string $view = 'PluginManage::packages-table';

    /**
     * 判断是否允许访问管理页面。
     * @return bool 是否允许访问
     */
    public static function canAccess(): bool {
        return Packages::schema()->hasTable( 'packages' ) && static::canAccessByNavigationLevel();
    }

    /**
     * 配置插件包列表。
     * @param Table $table 表格实例
     * @return Table 表格实例
     */
    public function table( Table $table ): Table {
        return $table
            ->query( Packages::query() )
            ->defaultSort( 'id', 'desc' )
            ->columns( [
                TextColumn::make( 'id' )->label( 'ID' )->searchable()->sortable(),
                TextColumn::make( 'rid' )
                    ->label( '唯一名称' )
                    ->searchable()
                    ->copyable()
                    ->copyMessage( '插件唯一名称已复制' ),
                TextColumn::make( 'name' )->label( '插件名称' )->searchable(),
                TextColumn::make( 'description' )
                    ->label( '插件描述' )
                    ->searchable()
                    ->limit( 60 )
                    ->tooltip( fn ( Packages $record ): ?string => $record->description )
                    ->placeholder( '暂无描述' )
                    ->wrap(),
                TextColumn::make( 'version' )->label( '版本号' )->searchable()->badge(),
                TextColumn::make( 'author' )->label( '作者' )->searchable()->sortable(),
                TextColumn::make( 'count' )->label( '下载次数' )->numeric()->sortable(),
                TextColumn::make( 'updated_at' )->label( '更新时间' )->dateTime( 'Y.m.d H:i:s' )->sortable(),
                TextColumn::make( 'created_at' )->label( '上传时间' )->dateTime( 'Y.m.d H:i:s' )->sortable(),
            ] )
            ->recordActions( [
                ActionGroup::make( [
                    Action::make( 'backupPackage' )
                        ->label( '备份' )
                        ->icon( Heroicon::OutlinedArchiveBoxArrowDown )
                        ->color( 'gray' )
                        ->action( fn ( Packages $record ): null => $this->backupPackage( $record ) ),
                    Action::make( 'deletePackage' )
                        ->label( '删除' )
                        ->icon( Heroicon::OutlinedTrash )
                        ->color( 'danger' )
                        ->requiresConfirmation()
                        ->modalHeading( fn ( Packages $record ): string => "删除插件包 {$record->name}？" )
                        ->modalDescription( '将同时删除插件包记录及对应 ZIP 文件，此操作无法撤销。' )
                        ->modalSubmitActionLabel( '确认删除' )
                        ->modalCancelActionLabel( '取消' )
                        ->action( fn ( Packages $record ): null => $this->deletePackage( $record ) ),
                ] ),
            ] )
            ->recordActionsColumnLabel( '操作' )
            ->defaultPaginationPageOption( 25 )
            ->paginationPageOptions( [25, 50, 100] )
            ->emptyStateIcon( Heroicon::OutlinedArchiveBox )
            ->emptyStateHeading( '暂无插件包' )
            ->emptyStateDescription( '插件包上传后将在此处显示。' );
    }

    /**
     * 备份插件包 ZIP 文件。
     * 将当前插件包复制到配置目录，并在文件名中附加版本号。
     * @param Packages $package 插件包记录
     * @return null
     */
    private function backupPackage( Packages $package ): null {
        $temporaryPath = null;
        try {
            $sourcePath = dirname( __DIR__ )."/Database/package/{$package->rid}.zip";
            if ( !is_file( $sourcePath ) || !is_readable( $sourcePath ) ) {
                throw new RuntimeException( '插件包 ZIP 文件不存在或不可读。' );
            }
            $configuredPath = trim( (string) plugin( 'PluginManage' )->config( 'backup' ) );
            if ( $configuredPath === '' ) { throw new RuntimeException( '插件备份目录未配置。' ); }
            $backupDirectory = str_starts_with( $configuredPath, DIRECTORY_SEPARATOR )
                ? rtrim( $configuredPath, DIRECTORY_SEPARATOR )
                : base_path( trim( $configuredPath, DIRECTORY_SEPARATOR ) );
            if ( !is_dir( $backupDirectory ) && !mkdir( $backupDirectory, 0755, true ) && !is_dir( $backupDirectory ) ) {
                throw new RuntimeException( '插件备份目录创建失败。' );
            }
            if ( !is_writable( $backupDirectory ) ) { throw new RuntimeException( '插件备份目录不可写。' ); }
            $version = preg_replace( '/[^A-Za-z0-9._-]+/', '_', (string) $package->version );
            if ( !is_string( $version ) || trim( $version, '._-' ) === '' ) { throw new RuntimeException( '插件版本号无法用于备份文件名。' ); }
            $backupPath = "{$backupDirectory}/{$package->rid}_v{$version}.zip";
            $temporaryPath = "{$backupDirectory}/.{$package->rid}.".bin2hex( random_bytes( 12 ) ).'.backup';
            if ( !copy( $sourcePath, $temporaryPath ) ) { throw new RuntimeException( '插件包复制失败。' ); }
            clearstatcache( true, $temporaryPath );
            if ( !is_file( $temporaryPath ) || filesize( $temporaryPath ) !== filesize( $sourcePath ) ) {
                throw new RuntimeException( '插件包备份文件不完整。' );
            }
            if ( !rename( $temporaryPath, $backupPath ) ) { throw new RuntimeException( '插件包备份写入失败。' ); }
            $temporaryPath = null;
            Notification::make()->title( '插件包备份成功' )->body( basename( $backupPath ) )->success()->send();
        }catch ( Throwable $throwable ) {
            report( $throwable );
            Notification::make()->title( '插件包备份失败' )->body( $throwable->getMessage() )->danger()->send();
        }finally {
            if ( is_string( $temporaryPath ) && is_file( $temporaryPath ) && !unlink( $temporaryPath ) ) {
                report( new RuntimeException( "Unable to remove the package backup temporary file: {$temporaryPath}" ) );
            }
        }
        return null;
    }

    /**
     * 删除插件包及关联 ZIP 文件。
     * 先隔离 ZIP 文件，数据库删除失败时恢复原文件。
     * @param Packages $package 插件包记录
     * @return null
     */
    private function deletePackage( Packages $package ): null {
        $packageDirectory = dirname( __DIR__ ).'/Database/package';
        $packagePath = "{$packageDirectory}/{$package->rid}.zip";
        $quarantinePath = '';
        $databaseDeleted = false;
        try {
            if ( is_file( $packagePath ) || is_link( $packagePath ) ) {
                $quarantinePath = "{$packageDirectory}/.{$package->rid}.".bin2hex( random_bytes( 12 ) ).'.deleting';
                if ( !rename( $packagePath, $quarantinePath ) ) {
                    throw new RuntimeException( '关联 ZIP 文件隔离失败。' );
                }
            }
            DB::connection( Packages::CONNECTION )->transaction( function () use ( $package ): void {
                $package->deleteOrFail();
            } );
            $databaseDeleted = true;
            if ( $quarantinePath !== '' && ( is_file( $quarantinePath ) || is_link( $quarantinePath ) ) && !unlink( $quarantinePath ) ) {
                throw new RuntimeException( '关联 ZIP 文件删除失败。' );
            }
            Notification::make()->title( '插件包删除成功' )->success()->send();
        }catch ( Throwable $throwable ) {
            if (
                !$databaseDeleted && $quarantinePath !== '' &&
                ( is_file( $quarantinePath ) || is_link( $quarantinePath ) ) &&
                !rename( $quarantinePath, $packagePath )
            ) {
                report( new RuntimeException( "Unable to restore the package archive: {$quarantinePath}" ) );
            }
            report( $throwable );
            Notification::make()->title( '插件包删除失败' )->body( $throwable->getMessage() )->danger()->send();
        }
        return null;
    }

    /**
     * 页面信息。
     */
    public function getBreadcrumbs(): array { return [__( 'filament.groups.developer' ), '插件包管理']; }
    public static function getNavigationLabel(): string { return '插件包管理'; }
    public function getTitle(): string { return '插件包列表'; }
    public static function getNavigationGroup(): string|UnitEnum|null { return __( 'filament.groups.developer' ); }
}
