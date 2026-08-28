<?php

namespace App\Filament\Resources\AdminControl\PluginManagement;

use App\Filament\Concerns\HasNavigationLevel;
use App\Filament\Resources\AdminControl\PluginManagement\Services\PluginInstaller;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use FilesystemIterator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * PluginManagement
 * 系统插件管理页面。
 * @package App\Filament\Resources\AdminControl\PluginManagement
 */
class PluginManagement extends Page {
    use HasNavigationLevel;

    protected static string $navigationPermission = 'plugin_management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $slug = 'plugin-management';

    protected static ?int $navigationSort = 20;

    protected string $view = 'Filament::AdminControl.PluginManagement.plugin-management';

    /**
     * 获取头部操作。
     * 提供压缩包上传或远程链接安装入口。
     * @return array<int, Action> 头部操作按钮
     */
    protected function getHeaderActions(): array {
        return [
            Action::make( 'installPlugin' )
                ->label( '安装插件' )
                ->icon( Heroicon::OutlinedArrowDownTray )
                ->modalIcon( Heroicon::OutlinedArrowDownTray )
                ->modalHeading( '安装插件' )
                ->modalDescription( '上传插件压缩包，或从来源链接获取插件。' )
                ->schema( [
                    ToggleButtons::make( 'method' )
                        ->label( '安装方式' )
                        ->options( [
                            'upload' => '上传 ZIP 压缩包',
                            'url' => '来源链接',
                        ] )
                        ->icons( [
                            'upload' => Heroicon::OutlinedArchiveBoxArrowDown,
                            'url' => Heroicon::OutlinedLink,
                        ] )
                        ->default( 'upload' )
                        ->inline()
                        ->live()
                        ->required(),
                    FileUpload::make( 'package' )
                        ->label( '插件 ZIP 压缩包' )
                        ->acceptedFileTypes( ['application/zip', 'application/x-zip-compressed'] )
                        ->maxSize( 102400 )
                        ->storeFiles( false )
                        ->required( fn ( Get $get ): bool => $get( 'method' ) === 'upload' )
                        ->visible( fn ( Get $get ): bool => $get( 'method' ) === 'upload' ),
                    TextInput::make( 'source_url' )
                        ->label( '插件来源链接' )
                        ->placeholder( 'https://github.com/owner/plugin/archive/refs/heads/main.zip' )
                        ->prefixIcon( Heroicon::OutlinedGlobeAlt )
                        ->url()
                        ->required( fn ( Get $get ): bool => $get( 'method' ) === 'url' )
                        ->visible( fn ( Get $get ): bool => $get( 'method' ) === 'url' ),
                ] )
                ->modalWidth( '2xl' )
                ->modalSubmitActionLabel( '安装插件' )
                ->modalCancelActionLabel( '取消' )
                ->action( fn ( array $data ): mixed => $this->installPlugin( $data ) ),
        ];
    }

    /**
     * 安装插件。
     * 根据弹窗选择从上传文件或远程链接安装插件。
     * @param array<string, mixed> $data 安装表单数据
     * @return mixed
     */
    private function installPlugin( array $data ): mixed {
        try {
            $installer = app( PluginInstaller::class );
            if ( ( $data['method'] ?? null ) === 'upload' ) {
                $upload = $data['package'] ?? null;
                if ( !$upload instanceof TemporaryUploadedFile ) { throw new RuntimeException( '请选择有效的 ZIP 压缩包。' ); }
                $result = $installer->installFromUpload( $upload );
            }else {
                $result = $installer->installFromUrl( (string) ( $data['source_url'] ?? '' ) );
            }
            $operation = ( $result['updated'] ?? false ) ? '更新' : '安装';
            Notification::make()
                ->title( "{$result['name']} {$operation}成功" )
                ->body( "插件标识：{$result['id']}，版本：{$result['version']}" )
                ->success()
                ->send();
            return $this->redirect( static::getUrl() );
        }catch ( Throwable $throwable ) {
            Notification::make()->title( '插件安装失败' )->body( $throwable->getMessage() )->danger()->send();
            return null;
        }
    }

    /**
     * 获取已安装插件列表。
     * 扫描 app/Plugins 一级目录并读取可正常加载的插件信息。
     * @return array<int, array<string, mixed>> 插件列表
     */
    public function getPlugins(): array {
        $pluginRoot = app_path( 'Plugins' );
        if ( !is_dir( $pluginRoot ) || !is_readable( $pluginRoot ) ) { return []; }
        $enabledPlugins = config( 'plugin.enabled', [] );
        $enabledPlugins = is_array( $enabledPlugins ) ? $enabledPlugins : [];
        $plugins = [];
        foreach ( new FilesystemIterator( $pluginRoot, FilesystemIterator::SKIP_DOTS ) as $directory ) {
            if ( !$directory->isDir() || $directory->isLink() ) { continue; }
            $pluginId = $directory->getFilename();
            if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $pluginId ) !== 1 ) { continue; }
            try {
                $plugin = plugin( $pluginId );
            }catch ( Throwable $throwable ) {
                $plugins[] = [
                    'id' => $pluginId,
                    'type' => 'failed',
                    'name' => $pluginId,
                    'version' => '--',
                    'author' => '--',
                    'description' => $throwable->getMessage() ?: '插件加载时发生未知错误。',
                ];
                continue;
            }
            if ( $plugin === null ) {
                $plugins[] = [
                    'id' => $pluginId,
                    'type' => 'failed',
                    'name' => $pluginId,
                    'version' => '--',
                    'author' => '--',
                    'description' => '插件文件存在异常。',
                ];
                continue;
            }
            if ( !in_array( $plugin->type, ['plugin', 'rely'], true ) ) {
                $plugins[] = [
                    'id' => $pluginId,
                    'type' => 'failed',
                    'name' => $plugin->name ?? $pluginId,
                    'version' => $plugin->version ?? '--',
                    'author' => $plugin->author ?? '--',
                    'description' => "插件类型 {$plugin->type} 无效。",
                ];
                continue;
            }
            $plugins[] = [
                'id' => $pluginId,
                'type' => $plugin->type,
                'name' => $plugin->name ?? $pluginId,
                'version' => $plugin->version ?? '--',
                'author' => $plugin->author ?? '--',
                'description' => $plugin->description ?? '暂无插件描述',
                'has_hooks' => $plugin->getHook() !== [],
                'has_config' => $plugin->config( '' ) !== [],
                'has_admin' => is_file( "{$plugin->path}admin.php" ) && is_readable( "{$plugin->path}admin.php" ),
                'has_source' => is_string( $plugin->source ) && trim( $plugin->source ) !== '',
                'hooks_trusted' => in_array( $pluginId, $enabledPlugins, true ),
            ];
        }
        usort( $plugins, fn ( array $left, array $right ): int => strnatcasecmp( $left['name'], $right['name'] ) );
        return $plugins;
    }

    /**
     * 删除插件。
     * 执行卸载入口后隔离插件目录，并在删除失败时恢复系统配置和文件。
     * @param string $id 插件标识
     * @return void
     */
    public function deletePlugin( string $id ): void {
        $pluginPath = null;
        $quarantinePath = null;
        $pluginConfigFile = config_path( 'plugin.php' );
        $pluginConfig = [];
        $pluginConfigChanged = false;
        $userConfigFile = config_path( "plugin/{$id}.php" );
        $userConfig = [];
        $userConfigDeleted = false;
        try {
            $pluginPath = $this->resolvePluginDirectory( $id );
            $plugin = $this->resolvePlugin( $id );
            if ( is_public( $plugin, 'uninstall' ) && $plugin->uninstall() !== true ) {
                throw new RuntimeException( '插件卸载入口执行失败。' );
            }
            $quarantinePath = dirname( $pluginPath ).'/.deleting-'.$id.'-'.uuid();
            if ( !rename( $pluginPath, $quarantinePath ) ) {
                throw new RuntimeException( '插件目录隔离失败。' );
            }
            $pluginConfig = $this->readConfigFile( $pluginConfigFile );
            $enabled = $pluginConfig['enabled'] ?? [];
            $enabled = is_array( $enabled ) ? $enabled : [];
            if ( in_array( $id, $enabled, true ) ) {
                $updatedPluginConfig = $pluginConfig;
                $updatedPluginConfig['enabled'] = array_values( array_filter(
                    $enabled,
                    fn ( mixed $pluginId ): bool => $pluginId !== $id,
                ) );
                $this->writeConfigFile( $pluginConfigFile, $updatedPluginConfig );
                $pluginConfigChanged = true;
                config()->set( 'plugin.enabled', $updatedPluginConfig['enabled'] );
            }
            if ( is_file( $userConfigFile ) || is_link( $userConfigFile ) ) {
                $userConfig = $this->readConfigFile( $userConfigFile );
                if ( !unlink( $userConfigFile ) ) {
                    throw new RuntimeException( '插件用户配置删除失败。' );
                }
                $userConfigDeleted = true;
            }
            if ( !File::deleteDirectory( $quarantinePath ) || is_dir( $quarantinePath ) ) {
                throw new RuntimeException( '插件目录删除失败。' );
            }
            Notification::make()->title( "{$id} 插件已删除" )->success()->send();
        }catch ( Throwable $throwable ) {
            $rollbackErrors = [];
            try {
                if ( is_string( $quarantinePath ) && is_dir( $quarantinePath ) && is_string( $pluginPath ) && !file_exists( $pluginPath ) ) {
                    if ( !rename( $quarantinePath, $pluginPath ) ) {
                        throw new RuntimeException( '插件目录恢复失败。' );
                    }
                }
            }catch ( Throwable $rollbackThrowable ) {
                $rollbackErrors[] = $rollbackThrowable->getMessage();
            }
            try {
                if ( $userConfigDeleted ) { $this->writeConfigFile( $userConfigFile, $userConfig ); }
            }catch ( Throwable $rollbackThrowable ) {
                $rollbackErrors[] = '插件用户配置恢复失败：'.$rollbackThrowable->getMessage();
            }
            try {
                if ( $pluginConfigChanged ) {
                    $this->writeConfigFile( $pluginConfigFile, $pluginConfig );
                    config()->set( 'plugin.enabled', $pluginConfig['enabled'] ?? [] );
                }
            }catch ( Throwable $rollbackThrowable ) {
                $rollbackErrors[] = '插件启用配置恢复失败：'.$rollbackThrowable->getMessage();
            }
            $message = $throwable->getMessage();
            if ( $rollbackErrors !== [] ) { $message .= ' '.implode( ' ', $rollbackErrors ); }
            Notification::make()->title( '插件删除失败' )->body( $message )->danger()->send();
        }
    }

    /**
     * 删除插件操作。
     * 确认后执行不可撤销的插件删除流程。
     * @return Action 插件删除操作
     */
    public function deletePluginAction(): Action {
        return Action::make( 'deletePlugin' )
            ->requiresConfirmation()
            ->modalIcon( Heroicon::OutlinedTrash )
            ->modalHeading( fn ( array $arguments ): string => "删除插件 {$arguments['pluginId']}？" )
            ->modalDescription( '将依次取消 Hook 信任、删除用户配置和整个插件目录。此操作无法撤销。' )
            ->modalSubmitActionLabel( '确认删除' )
            ->modalCancelActionLabel( '取消' )
            ->color( 'danger' )
            ->action( fn ( array $arguments ): mixed => $this->deletePlugin( (string) ( $arguments['pluginId'] ?? '' ) ) );
    }

    /**
     * 查看插件详细信息操作。
     * 展示功能插件或依赖插件的元数据、依赖和 Hook 申请。
     * @return Action 插件详情操作
     */
    public function viewPluginDetailsAction(): Action {
        return Action::make( 'viewPluginDetails' )
            ->modalIcon( Heroicon::OutlinedInformationCircle )
            ->modalHeading( function( array $arguments ): string {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                return $plugin->name ?? (string) $plugin->id;
            } )
            ->modalDescription( '插件详细信息与扩展申请。' )
            ->modalContent( function( array $arguments ) {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                $hookConfigs = config( 'plugin.hooks', [] );
                $hookConfigs = is_array( $hookConfigs ) ? $hookConfigs : [];
                $hooks = [];
                foreach ( array_keys( $plugin->getHook() ) as $hook ) {
                    $hooks[$hook] = is_string( $hookConfigs[$hook] ?? null ) ? $hookConfigs[$hook] : '暂无 Hook 说明';
                }
                $enabledPlugins = config( 'plugin.enabled', [] );
                $enabledPlugins = is_array( $enabledPlugins ) ? $enabledPlugins : [];
                return view( 'Filament::AdminControl.PluginManagement.plugin-details', [
                    'plugin' => [
                        'id' => (string) $plugin->id,
                        'type' => $plugin->type,
                        'name' => $plugin->name ?? (string) $plugin->id,
                        'version' => $plugin->version ?? '--',
                        'author' => $plugin->author ?? '--',
                        'description' => $plugin->description ?? '暂无插件描述',
                        'composer_dependencies' => $plugin->relyComposer,
                        'plugin_dependencies' => $plugin->relyPlugin,
                        'hooks' => $hooks,
                        'has_config' => $plugin->config( '' ) !== [],
                        'has_hooks' => $hooks !== [],
                        'hooks_trusted' => in_array( (string) $plugin->id, $enabledPlugins, true ),
                        'has_admin' => is_file( "{$plugin->path}admin.php" ) && is_readable( "{$plugin->path}admin.php" ),
                        'has_source' => is_string( $plugin->source ) && trim( $plugin->source ) !== '',
                        'readme' => $this->getPluginReadmeHtml( (string) $plugin->id ),
                    ],
                ] );
            } )
            ->modalWidth( '3xl' )
            ->extraModalWindowAttributes( ['class' => 'plugin-details-modal'] )
            ->modalSubmitAction( false )
            ->modalCancelActionLabel( '关闭' );
    }

    /**
     * 从插件详情切换到其他插件操作。
     * 先关闭当前详情弹窗，避免 Filament 在同一 Action 弹窗内重复挂载操作。
     * @param string $action 目标操作名称
     * @param array<string, mixed> $arguments 操作参数
     * @return void
     */
    public function switchPluginDetailsAction( string $action, array $arguments ): void {
        if ( !in_array( $action, ['trustHooks', 'cancelHooks', 'editConfig', 'managePlugin', 'updatePlugin'], true ) ) {
            throw new RuntimeException( '插件操作无效。' );
        }
        $pluginId = (string) ( $arguments['pluginId'] ?? '' );
        $this->resolvePlugin( $pluginId );
        $this->unmountAction( cancelParentActions: false );
        $this->mountAction( $action, ['pluginId' => $pluginId] );
    }

    /**
     * 管理插件操作。
     * 渲染插件根目录的 admin.php Blade 管理页面。
     * @return Action 插件管理操作
     */
    public function managePluginAction(): Action {
        return Action::make( 'managePlugin' )
            ->modalIcon( Heroicon::OutlinedWrenchScrewdriver )
            ->modalHeading( function( array $arguments ): string {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                return "管理 {$plugin->name}";
            } )
            ->modalDescription( '由插件提供的管理页面。' )
            ->modalContent( function( array $arguments ) {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                return view( 'Filament::AdminControl.PluginManagement.plugin-admin', [
                    'content' => $this->renderPluginAdmin( $plugin ),
                ] );
            } )
            ->modalWidth( '3xl' )
            ->extraModalWindowAttributes( ['class' => 'plugin-admin-modal'] )
            ->modalSubmitAction( false )
            ->modalCancelActionLabel( '关闭' );
    }

    /**
     * 更新插件操作。
     * 从插件声明的来源链接下载并安装更高版本。
     * @return Action 插件更新操作
     */
    public function updatePluginAction(): Action {
        return Action::make( 'updatePlugin' )
            ->requiresConfirmation()
            ->modalIcon( Heroicon::OutlinedArrowPath )
            ->modalHeading( function( array $arguments ): string {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                return "更新 {$plugin->name}？";
            } )
            ->modalDescription( '将从插件声明的来源链接下载并安装更高版本。' )
            ->modalContent( function( array $arguments ) {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                return view( 'Filament::AdminControl.PluginManagement.update-plugin-source', [
                    'source' => (string) $plugin->source,
                ] );
            } )
            ->modalSubmitActionLabel( '确认更新' )
            ->modalCancelActionLabel( '取消' )
            ->color( 'primary' )
            ->action( fn ( array $arguments ): mixed => $this->updatePlugin( (string) ( $arguments['pluginId'] ?? '' ) ) );
    }

    /**
     * 信任插件 Hook 操作。
     * 显示插件申请的 Hook，确认后将插件加入已启用清单。
     * @return Action Hook 信任操作
     */
    public function trustHooksAction(): Action {
        return Action::make( 'trustHooks' )
            ->modalIcon( Heroicon::OutlinedShieldCheck )
            ->modalHeading( fn ( array $arguments ): string => "信任 {$arguments['pluginId']} 申请的 Hook？" )
            ->modalDescription( '确认后，系统会允许该插件响应下列 Hook。' )
            ->modalContent( function( array $arguments ) {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                $hookConfigs = config( 'plugin.hooks', [] );
                $hookConfigs = is_array( $hookConfigs ) ? $hookConfigs : [];
                $hooks = [];
                foreach ( array_keys( $plugin->getHook() ) as $hook ) {
                    $hooks[$hook] = is_string( $hookConfigs[$hook] ?? null ) ? $hookConfigs[$hook] : '暂无 Hook 说明';
                }
                return view( 'Filament::AdminControl.PluginManagement.trust-hooks', ['hooks' => $hooks] );
            } )
            ->modalSubmitActionLabel( '确认信任' )
            ->modalCancelActionLabel( '取消' )
            ->color( 'warning' )
            ->action( fn ( array $arguments ): mixed => $this->trustPluginHooks( (string) ( $arguments['pluginId'] ?? '' ) ) );
    }

    /**
     * 取消插件 Hook 信任操作。
     * 确认后将插件从已启用清单移除。
     * @return Action Hook 取消信任操作
     */
    public function cancelHooksAction(): Action {
        return Action::make( 'cancelHooks' )
            ->requiresConfirmation()
            ->modalIcon( Heroicon::OutlinedShieldExclamation )
            ->modalHeading( fn ( array $arguments ): string => "取消 {$arguments['pluginId']} 的 Hook 信任？" )
            ->modalDescription( '取消后，系统将不再调用该插件申请的 Hook。' )
            ->modalSubmitActionLabel( '确认取消' )
            ->modalCancelActionLabel( '返回' )
            ->color( 'danger' )
            ->action( fn ( array $arguments ): mixed => $this->cancelPluginHooks( (string) ( $arguments['pluginId'] ?? '' ) ) );
    }

    /**
     * 修改插件配置操作。
     * 以 JSON 格式编辑插件配置并保存用户配置文件。
     * @return Action 插件配置操作
     */
    public function editConfigAction(): Action {
        return Action::make( 'editConfig' )
            ->modalIcon( Heroicon::OutlinedCog6Tooth )
            ->modalHeading( fn ( array $arguments ): string => "修改 {$arguments['pluginId']} 配置" )
            ->modalDescription( '请使用合法的 JSON 格式，用户配置会覆盖插件默认配置。' )
            ->fillForm( function( array $arguments ): array {
                $plugin = $this->resolvePlugin( (string) ( $arguments['pluginId'] ?? '' ) );
                $config = json_encode(
                    $plugin->config( '' ),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );
                return ['config' => $config === false ? '{}' : $config];
            } )
            ->schema( [
                Textarea::make( 'config' )
                    ->label( 'JSON 配置' )
                    ->required()
                    ->rule( 'json' )
                    ->rows( 18 )
                    ->extraInputAttributes( [
                        'spellcheck' => 'false',
                        'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;',
                    ] ),
            ] )
            ->modalWidth( '3xl' )
            ->modalSubmitActionLabel( '保存配置' )
            ->modalCancelActionLabel( '取消' )
            ->action( fn ( array $data, array $arguments ): mixed => $this->savePluginConfig(
                (string) ( $arguments['pluginId'] ?? '' ),
                (string) ( $data['config'] ?? '' ),
            ) );
    }

    /**
     * 信任插件 Hook。
     * @param string $id 插件标识
     * @return void
     */
    private function trustPluginHooks( string $id ): void {
        try {
            $plugin = $this->resolvePlugin( $id );
            if ( $plugin->getHook() === [] ) { throw new RuntimeException( '该插件没有申请 Hook。' ); }
            $pluginConfig = $this->readConfigFile( config_path( 'plugin.php' ) );
            $enabled = $pluginConfig['enabled'] ?? [];
            $enabled = is_array( $enabled ) ? $enabled : [];
            if ( !in_array( $id, $enabled, true ) ) { $enabled[] = $id; }
            $pluginConfig['enabled'] = array_values( $enabled );
            $this->writeConfigFile( config_path( 'plugin.php' ), $pluginConfig );
            config()->set( 'plugin.enabled', $pluginConfig['enabled'] );
            Notification::make()->title( "已信任 {$id} 的 Hook" )->success()->send();
        }catch ( Throwable $throwable ) {
            Notification::make()->title( 'Hook 信任失败' )->body( $throwable->getMessage() )->danger()->send();
        }
    }

    /**
     * 取消插件 Hook 信任。
     * @param string $id 插件标识
     * @return void
     */
    private function cancelPluginHooks( string $id ): void {
        try {
            $plugin = $this->resolvePlugin( $id );
            if ( $plugin->getHook() === [] ) { throw new RuntimeException( '该插件没有申请 Hook。' ); }
            $pluginConfig = $this->readConfigFile( config_path( 'plugin.php' ) );
            $enabled = $pluginConfig['enabled'] ?? [];
            $enabled = is_array( $enabled ) ? $enabled : [];
            $pluginConfig['enabled'] = array_values( array_filter(
                $enabled,
                fn ( mixed $pluginId ): bool => $pluginId !== $id,
            ) );
            $this->writeConfigFile( config_path( 'plugin.php' ), $pluginConfig );
            config()->set( 'plugin.enabled', $pluginConfig['enabled'] );
            Notification::make()->title( "已取消 {$id} 的 Hook 信任" )->success()->send();
        }catch ( Throwable $throwable ) {
            Notification::make()->title( 'Hook 信任取消失败' )->body( $throwable->getMessage() )->danger()->send();
        }
    }

    /**
     * 保存插件配置。
     * @param string $id 插件标识
     * @param string $json JSON 配置
     * @return void
     */
    private function savePluginConfig( string $id, string $json ): void {
        try {
            $plugin = $this->resolvePlugin( $id );
            if ( $plugin->config( '' ) === [] ) { throw new RuntimeException( '该插件没有可修改的配置。' ); }
            $config = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
            if ( !is_array( $config ) ) { throw new RuntimeException( '插件配置必须是 JSON 对象或数组。' ); }
            $this->writeConfigFile( config_path( "plugin/{$id}.php" ), $config );
            Notification::make()->title( "{$id} 配置已保存" )->success()->send();
        }catch ( Throwable $throwable ) {
            Notification::make()->title( '插件配置保存失败' )->body( $throwable->getMessage() )->danger()->send();
        }
    }

    /**
     * 从插件来源链接更新插件。
     * @param string $id 插件标识
     * @return mixed
     */
    private function updatePlugin( string $id ): mixed {
        try {
            $plugin = $this->resolvePlugin( $id );
            $source = is_string( $plugin->source ) ? trim( $plugin->source ) : '';
            if ( $source === '' ) { throw new RuntimeException( '该插件未声明来源链接。' ); }
            $result = app( PluginInstaller::class )->updateFromUrl( $id, $source );
            Notification::make()
                ->title( "{$result['name']} 更新成功" )
                ->body( "已更新至 {$result['version']}" )
                ->success()
                ->send();
            return $this->redirect( static::getUrl() );
        }catch ( Throwable $throwable ) {
            Notification::make()->title( '插件更新失败' )->body( $throwable->getMessage() )->danger()->send();
            return null;
        }
    }

    /**
     * 解析已安装插件。
     * @param string $id 插件标识
     * @return object 插件实例
     */
    private function resolvePlugin( string $id ): object {
        if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $id ) !== 1 ) {
            throw new RuntimeException( '插件标识无效。' );
        }
        $plugin = plugin( $id );
        if ( $plugin === null || !in_array( $plugin->type, ['plugin', 'rely'], true ) ) {
            throw new RuntimeException( '插件不存在或无法加载。' );
        }
        return $plugin;
    }

    /**
     * 解析并校验插件目录。
     * @param string $id 插件标识
     * @return string 插件真实目录
     */
    private function resolvePluginDirectory( string $id ): string {
        if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $id ) !== 1 ) {
            throw new RuntimeException( '插件标识无效。' );
        }
        $pluginRoot = realpath( app_path( 'Plugins' ) );
        $pluginPath = realpath( app_path( "Plugins/{$id}" ) );
        if (
            $pluginRoot === false ||
            $pluginPath === false ||
            is_link( app_path( "Plugins/{$id}" ) ) ||
            !is_dir( $pluginPath ) ||
            !str_starts_with( "{$pluginPath}/", rtrim( $pluginRoot, DIRECTORY_SEPARATOR ).DIRECTORY_SEPARATOR )
        ) {
            throw new RuntimeException( '插件目录不存在或路径不合法。' );
        }
        return $pluginPath;
    }

    /**
     * 获取插件 README 内容。
     * 读取插件根目录 README.md 并转换为安全 HTML。
     * @param string $id 插件标识
     * @return HtmlString|null README 内容或 null
     */
    private function getPluginReadmeHtml( string $id ): ?HtmlString {
        $readmePath = $this->resolvePluginDirectory( $id ).'/README.md';
        if ( !File::isFile( $readmePath ) || !File::isReadable( $readmePath ) ) { return null; }
        try {
            $html = Str::markdown( File::get( $readmePath ), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 100,
            ] );
            return new HtmlString( $html );
        }catch ( Throwable ) {
            return null;
        }
    }

    /**
     * 渲染插件管理页面。
     * 只允许读取已校验插件根目录中的 admin.php。
     * @param object $plugin 插件实例
     * @return HtmlString 插件管理页面
     */
    private function renderPluginAdmin( object $plugin ): HtmlString {
        $pluginPath = $this->resolvePluginDirectory( (string) $plugin->id );
        $adminPath = "{$pluginPath}/admin.php";
        if ( !File::isFile( $adminPath ) || !File::isReadable( $adminPath ) ) {
            throw new RuntimeException( '插件管理页面不存在或不可读。' );
        }
        $html = BladeCompiler::render( File::get( $adminPath ), ['plugin' => $plugin], true );
        return new HtmlString( $html );
    }

    /**
     * 原子写入 PHP 配置文件。
     * @param string $path 配置文件路径
     * @param array<string, mixed> $config 配置内容
     * @return void
     */
    private function writeConfigFile( string $path, array $config ): void {
        $directory = dirname( $path );
        if ( !is_dir( $directory ) && !mkdir( $directory, 0755, true ) && !is_dir( $directory ) ) {
            throw new RuntimeException( '配置目录创建失败。' );
        }
        $temporaryFile = tempnam( $directory, '.plugin-' );
        if ( $temporaryFile === false ) { throw new RuntimeException( '临时配置文件创建失败。' ); }
        $content = "<?php\n\nreturn ".var_export( $config, true ).";\n";
        try {
            if ( file_put_contents( $temporaryFile, $content, LOCK_EX ) === false ) {
                throw new RuntimeException( '配置文件写入失败。' );
            }
            if ( !rename( $temporaryFile, $path ) ) {
                throw new RuntimeException( '配置文件替换失败。' );
            }
        }finally {
            if ( is_file( $temporaryFile ) ) { unlink( $temporaryFile ); }
        }
    }

    /**
     * 读取指定 PHP 配置文件。
     * 直接读取源文件，避免写回 Laravel 合并后的配置数据。
     * @param string $path 配置文件路径
     * @return array<string, mixed> 配置内容
     */
    private function readConfigFile( string $path ): array {
        if ( !is_file( $path ) || !is_readable( $path ) ) { return []; }
        $config = include $path;
        return is_array( $config ) ? $config : [];
    }

    /**
     * 页面信息
     */
    public function getBreadcrumbs(): array { return [__( 'filament.groups.admin' ), __( 'filament.navigation.plugins' )]; }
    public static function getNavigationLabel(): string { return __( 'filament.navigation.plugins' ); }
    public function getTitle(): string { return __( 'filament.titles.plugins' ); }
    public static function getNavigationGroup(): string|UnitEnum|null { return __( 'filament.groups.admin' ); }
}
