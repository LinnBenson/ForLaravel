<?php

namespace App\Filament\Resources\SystemSettings\SystemConfig;

use App\Filament\Concerns\HasNavigationLevel;
use App\Models\SystemConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * SystemConfigPage
 * 系统配置管理页面。
 * @package App\Filament\Resources\SystemSettings\SystemConfig
 */
class SystemConfigPage extends Page {
    use HasNavigationLevel, WithFileUploads;

    protected static string $navigationPermission = 'system_config';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'system-settings/config';

    protected static ?int $navigationSort = 10;

    protected string $view = 'Filament::SystemSettings.SystemConfig.system-config';

    /** @var array<int, bool|string|null> */
    public array $values = [];

    /** @var array<int, object|null> */
    public array $uploads = [];

    /** @var array<int, bool> */
    public array $removeFiles = [];

    /** @var array<int, int|string> */
    public array $indexes = [];

    /** @var array{category: string, type: string, name: string, key: string, value: bool|string|null, description: string, index: int} */
    public array $newConfig = [
        'category' => 'app',
        'type' => 'text',
        'name' => '',
        'key' => '',
        'value' => '',
        'description' => '',
        'index' => 255,
    ];

    public $newUpload = null;

    public bool $showCreateForm = false;

    public string $activeCategory = 'app';

    /**
     * 获取头部操作。
     * 在页面标题右侧提供新增配置键开关。
     * @return array<int, Action> 头部操作按钮
     */
    protected function getHeaderActions(): array {
        return [
            Action::make( 'toggleCreateForm' )
                ->label( fn (): string => $this->showCreateForm ? '取消新增' : '添加配置键' )
                ->icon( fn (): Heroicon => $this->showCreateForm ? Heroicon::OutlinedXMark : Heroicon::OutlinedPlus )
                ->action( function (): void {
                    $this->showCreateForm = ! $this->showCreateForm;
                } ),
        ];
    }

    /**
     * 初始化系统配置页面。
     * 加载全部配置值用于直接编辑。
     * @return void
     */
    public function mount(): void {
        $this->loadValues();
    }

    /**
     * 切换配置类别。
     * 更新当前编辑区，并让新增表单默认使用所选类别。
     * @param string $category 配置类别
     * @return void
     */
    public function selectCategory( string $category ): void {
        if ( ! array_key_exists( $category, SystemConfig::CATEGORIES ) ) { return; }
        $this->activeCategory = $category;
        $this->newConfig['category'] = $category;
        $this->resetValidation();
    }

    /**
     * 获取分组配置。
     * 按类别返回所有系统配置记录。
     * @return array<string, Collection<int, SystemConfig>> 分组配置记录
     */
    public function getGroupedConfigs(): array {
        $grouped = [];
        foreach ( array_keys( SystemConfig::CATEGORIES ) as $category ) {
            $grouped[$category] = collect();
        }
        $configs = SystemConfig::query()
            ->orderByRaw( "FIELD(category, 'app', 'system', 'other')" )
            ->orderBy( 'index' )
            ->orderBy( 'key' )
            ->get();
        foreach ( $configs->groupBy( 'category' ) as $category => $items ) {
            $grouped[$category] = $items;
        }
        return $grouped;
    }

    /**
     * 保存指定类别。
     * 校验并保存类别下全部配置，文件操作失败时回滚数据库和新文件。
     * @param string $category 配置类别
     * @return void
     */
    public function saveCategory( string $category ): void {
        if ( ! array_key_exists( $category, SystemConfig::CATEGORIES ) ) {
            $this->notifyFailure( '配置类别无效。' );
            return;
        }
        $configs = SystemConfig::query()->where( 'category', $category )->get();
        $data = [
            'values' => $this->values,
            'uploads' => $this->uploads,
            'indexes' => $this->indexes,
        ];
        Validator::make( $data, $this->getCategoryRules( $configs ), $this->getValidationMessages() )->validate();
        $storedFiles = [];
        $replacedFiles = [];
        try {
            DB::transaction( function () use ( $configs, &$storedFiles, &$replacedFiles ): void {
                foreach ( $configs as $config ) {
                    $value = $this->prepareValue( $config, $storedFiles, $replacedFiles );
                    $config->update( [
                        'value' => $value,
                        'index' => (int) ( $this->indexes[$config->id] ?? 0 ),
                    ] );
                }
            } );
            foreach ( $replacedFiles as $path ) {
                $this->deleteConfigFile( $path );
            }
            foreach ( $configs as $config ) {
                unset( $this->uploads[$config->id] );
                unset( $this->removeFiles[$config->id] );
            }
            $this->loadValues();
            Notification::make()
                ->title( SystemConfig::CATEGORIES[$category] . '已保存' )
                ->success()
                ->send();
        }catch ( Throwable $exception ) {
            foreach ( $storedFiles as $path ) {
                $this->deleteConfigFile( $path );
            }
            report( $exception );
            $this->notifyFailure( '配置保存失败，请稍后重试。' );
        }
    }

    /**
     * 创建配置键。
     * 校验并新增系统配置，上传失败时回滚已写入文件。
     * @return void
     */
    public function createConfig(): void {
        $rules = [
            'newConfig.category' => ['required', Rule::in( array_keys( SystemConfig::CATEGORIES ) )],
            'newConfig.type' => ['required', Rule::in( array_keys( SystemConfig::TYPES ) )],
            'newConfig.name' => ['required', 'string', 'max:191'],
            'newConfig.key' => [
                'required',
                'string',
                'max:191',
                'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/',
                Rule::unique( 'system_config', 'key' ),
            ],
            'newConfig.index' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'newConfig.value' => $this->getValueRules( $this->newConfig['type'] ),
            'newConfig.description' => ['nullable', 'string', 'max:65535'],
        ];
        if ( $this->newConfig['type'] === 'image' ) {
            $rules['newUpload'] = $this->getUploadRules( 'image' );
        }elseif ( $this->newConfig['type'] === 'file' ) {
            $rules['newUpload'] = $this->getUploadRules( 'file' );
        }
        Validator::make(
            ['newConfig' => $this->newConfig, 'newUpload' => $this->newUpload],
            $rules,
            $this->getValidationMessages(),
        )->validate();
        $storedPath = null;
        try {
            DB::transaction( function () use ( &$storedPath ): void {
                $value = $this->normalizeValue( $this->newConfig['type'], $this->newConfig['value'] );
                if ( in_array( $this->newConfig['type'], ['image', 'file'], true ) && $this->newUpload !== null ) {
                    $storedPath = $this->storeUploadedFile( $this->newUpload, $this->newConfig['type'] );
                    $value = $storedPath;
                }
                SystemConfig::query()->create( [
                    'category' => $this->newConfig['category'],
                    'type' => $this->newConfig['type'],
                    'name' => trim( $this->newConfig['name'] ),
                    'key' => $this->newConfig['key'],
                    'value' => $value,
                    'description' => $this->normalizeDescription( $this->newConfig['description'] ),
                    'index' => (int) $this->newConfig['index'],
                ] );
            } );
            $this->activeCategory = $this->newConfig['category'];
            $this->resetCreateForm();
            $this->loadValues();
            Notification::make()
                ->title( '配置键已创建' )
                ->success()
                ->send();
        }catch ( Throwable $exception ) {
            if ( $storedPath !== null ) { $this->deleteConfigFile( $storedPath ); }
            report( $exception );
            $this->notifyFailure( '配置键创建失败，请稍后重试。' );
        }
    }

    /**
     * 更新新增配置类型。
     * 切换类型时清空旧值和上传文件。
     * @return void
     */
    public function updatedNewConfig( bool|string|null $value, string $key ): void {
        if ( $key !== 'type' ) { return; }
        $this->newConfig['value'] = $value === 'boolean' ? false : '';
        $this->newUpload = null;
        $this->resetValidation();
    }

    /**
     * 更新已有配置上传文件。
     * 选择新文件后自动取消删除标记。
     * @param mixed $value 上传文件
     * @param string $key 配置 ID
     * @return void
     */
    public function updatedUploads( mixed $value, string $key ): void {
        if ( $value === null || ! ctype_digit( $key ) ) { return; }
        $this->removeFiles[(int) $key] = false;
    }

    /**
     * 切换文件删除状态。
     * 标记删除时清除待上传的新文件，保存类别后才执行实际删除。
     * @param int $configId 配置 ID
     * @return void
     */
    public function toggleFileRemoval( int $configId ): void {
        $config = SystemConfig::query()->find( $configId );
        if ( $config === null || ! in_array( $config->type, ['image', 'file'], true ) ) { return; }
        $this->removeFiles[$configId] = ! ( $this->removeFiles[$configId] ?? false );
        if ( $this->removeFiles[$configId] ) { unset( $this->uploads[$configId] ); }
        $this->resetValidation( "uploads.{$configId}" );
    }

    /**
     * 清除新增配置上传。
     * 移除尚未保存的临时上传文件。
     * @return void
     */
    public function clearNewUpload(): void {
        $this->newUpload = null;
        $this->resetValidation( 'newUpload' );
    }

    /**
     * 清除已有配置的新上传。
     * 移除尚未保存的替换文件并恢复当前文件预览。
     * @param int $configId 配置 ID
     * @return void
     */
    public function clearConfigUpload( int $configId ): void {
        unset( $this->uploads[$configId] );
        $this->resetValidation( "uploads.{$configId}" );
    }

    /**
     * 获取临时图片预览链接。
     * 返回已有配置刚选择图片的 Livewire 临时地址。
     * @param int $configId 配置 ID
     * @return string|null 临时预览链接
     */
    public function getUploadPreviewUrl( int $configId ): ?string {
        return $this->getTemporaryUploadUrl( $this->uploads[$configId] ?? null );
    }

    /**
     * 获取新增图片预览链接。
     * 返回新增配置刚选择图片的 Livewire 临时地址。
     * @return string|null 临时预览链接
     */
    public function getNewUploadPreviewUrl(): ?string {
        return $this->getTemporaryUploadUrl( $this->newUpload );
    }

    /**
     * 获取待上传文件名。
     * 返回 Livewire 临时上传文件的原始文件名。
     * @param mixed $upload 临时上传文件
     * @return string|null 文件名
     */
    public function getUploadFileName( mixed $upload ): ?string {
        if ( ! is_object( $upload ) || ! method_exists( $upload, 'getClientOriginalName' ) ) { return null; }
        $name = trim( (string) $upload->getClientOriginalName() );
        return $name === '' ? null : $name;
    }

    /**
     * 获取公开配置文件地址。
     * 只返回系统配置目录下存在文件的公开地址。
     * @param string|null $path 文件路径
     * @return string|null 文件地址
     */
    public function getConfigFileUrl( ?string $path ): ?string {
        $appHost = parse_url( (string) config( 'app.url' ), PHP_URL_HOST );
        $pathHost = parse_url( $path, PHP_URL_HOST );
        if ( ! empty( $pathHost ) && is_string( $appHost ) && strcasecmp( $pathHost, $appHost ) === 0 ) {
            return $path;
        }
        $path = $this->getConfigFilePath( $path );
        if ( $path === null ) { return null; }
        $disk = (string) config( 'system_uploads.system_config.disk', 'public' );
        if ( ! Storage::disk( $disk )->exists( $path ) ) { return null; }
        return Storage::disk( $disk )->url( $path );
    }

    /**
     * 获取页面面包屑。
     * 返回系统设置页面层级。
     * @return array<string> 面包屑列表
     */
    public function getBreadcrumbs(): array {
        return [__( 'filament.groups.system' ), __( 'filament.navigation.system_config' )];
    }

    /**
     * 加载配置值。
     * 将数据库值转换为适合对应控件的状态。
     * @return void
     */
    private function loadValues(): void {
        $this->values = [];
        $this->indexes = [];
        foreach ( SystemConfig::query()->get() as $config ) {
            $value = $config->value;
            if ( $config->type === 'boolean' ) {
                $value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            }elseif ( $config->type === 'json' && filled( $value ) ) {
                $decoded = json_decode( $value, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $value = json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                }
            }
            $this->values[$config->id] = $value;
            $this->indexes[$config->id] = $config->index;
        }
    }

    /**
     * 获取类别校验规则。
     * 根据每条配置类型生成对应的值和上传文件校验规则。
     * @param Collection<int, SystemConfig> $configs 配置记录
     * @return array<string, array<int, mixed>> 校验规则
     */
    private function getCategoryRules( Collection $configs ): array {
        $rules = [];
        foreach ( $configs as $config ) {
            $rules["values.{$config->id}"] = $this->getValueRules( $config->type );
            $rules["indexes.{$config->id}"] = ['required', 'integer', 'min:0', 'max:4294967295'];
            if ( $config->type === 'image' ) {
                $rules["uploads.{$config->id}"] = $this->getUploadRules( 'image' );
            }elseif ( $config->type === 'file' ) {
                $rules["uploads.{$config->id}"] = $this->getUploadRules( 'file' );
            }
        }
        return $rules;
    }

    /**
     * 获取上传校验规则。
     * 从配置文件读取图片或普通文件的扩展名与大小限制。
     * @param string $type 上传类型
     * @return array<int, string> 校验规则
     */
    private function getUploadRules( string $type ): array {
        $config = config( 'system_uploads.system_config', [] );
        $extensions = $config["{$type}_extensions"] ?? [];
        $mimeTypes = $config["{$type}_mime_types"] ?? [];
        $maxSize = (int) ( $config["{$type}_max_size"] ?? 10240 );
        $rules = ['nullable', 'file', "max:{$maxSize}"];
        if ( $type === 'image' ) { $rules[] = 'image'; }
        if ( $extensions !== [] ) { $rules[] = 'extensions:' . implode( ',', $extensions ); }
        if ( $mimeTypes !== [] ) { $rules[] = 'mimetypes:' . implode( ',', $mimeTypes ); }
        return $rules;
    }

    /**
     * 获取值校验规则。
     * 根据配置类型返回对应的输入校验规则。
     * @param string $type 配置类型
     * @return array<int, string> 校验规则
     */
    private function getValueRules( string $type ): array {
        return match ( $type ) {
            'boolean' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:65535'],
            'number' => ['nullable', 'integer'],
            'decimal' => ['nullable', 'numeric'],
            'json' => ['nullable', 'json'],
            'image', 'file' => ['nullable', 'string', 'max:65535'],
            default => ['nullable', 'string', 'max:65535'],
        };
    }

    /**
     * 准备配置值。
     * 处理普通值或上传文件，并记录需要清理的文件路径。
     * @param SystemConfig $config 配置记录
     * @param array<int, string> $storedFiles 新文件路径
     * @param array<int, string> $replacedFiles 被替换文件路径
     * @return string|null 配置存储值
     */
    private function prepareValue( SystemConfig $config, array &$storedFiles, array &$replacedFiles ): ?string {
        if ( in_array( $config->type, ['image', 'file'], true ) && isset( $this->uploads[$config->id] ) ) {
            $path = $this->storeUploadedFile( $this->uploads[$config->id], $config->type );
            $storedFiles[] = $path;
            if ( filled( $config->value ) ) { $replacedFiles[] = $config->value; }
            return $path;
        }
        if ( in_array( $config->type, ['image', 'file'], true ) && ( $this->removeFiles[$config->id] ?? false ) ) {
            if ( filled( $config->value ) ) { $replacedFiles[] = $config->value; }
            return null;
        }
        return $this->normalizeValue( $config->type, $this->values[$config->id] ?? null );
    }

    /**
     * 规范化配置值。
     * 将不同控件状态转换为数据库字符串。
     * @param string $type 配置类型
     * @param bool|string|null $value 配置值
     * @return string|null 数据库存储值
     */
    private function normalizeValue( string $type, bool|string|null $value ): ?string {
        if ( $type === 'boolean' ) { return $value ? '1' : '0'; }
        if ( $value === null ) { return null; }
        $value = in_array( $type, ['text', 'textarea'], true ) ? $value : trim( $value );
        return $value === '' ? null : $value;
    }

    /**
     * 规范化配置描述。
     * 保留描述正文并将空字符串转换为 null。
     * @param string|null $description 配置描述
     * @return string|null 数据库存储描述
     */
    private function normalizeDescription( ?string $description ): ?string {
        if ( $description === null ) { return null; }
        return trim( $description ) === '' ? null : $description;
    }

    /**
     * 存储上传文件。
     * 将图片和文件统一保存到 system_file 公开目录。
     * @param object $upload 临时上传文件
     * @param string $type 配置类型
     * @return string 文件公开链接
     */
    private function storeUploadedFile( object $upload, string $type ): string {
        if ( ! method_exists( $upload, 'storePublicly' ) ) { throw new RuntimeException( '上传文件无效。' ); }
        $disk = (string) config( 'system_uploads.system_config.disk', 'public' );
        $directory = trim( (string) config( 'system_uploads.system_config.directory', 'system_file' ), '/' );
        $path = $upload->storePublicly( $directory, $disk );
        if ( ! is_string( $path ) || $path === '' ) { throw new RuntimeException( '文件保存失败。' ); }
        if ( ! Storage::disk( $disk )->exists( $path ) ) { throw new RuntimeException( '上传文件写入失败。' ); }
        return $path;
    }

    /**
     * 获取临时上传链接。
     * 安全读取 Livewire 临时图片预览地址。
     * @param mixed $upload 临时上传文件
     * @return string|null 临时预览链接
     */
    private function getTemporaryUploadUrl( mixed $upload ): ?string {
        if ( ! is_object( $upload ) || ! method_exists( $upload, 'temporaryUrl' ) ) { return null; }
        try {
            return $upload->temporaryUrl();
        }catch ( Throwable ) {
            return null;
        }
    }

    /**
     * 获取配置文件路径。
     * 将 system_file 公开链接或相对路径解析为 public 磁盘中的安全路径。
     * @param string|null $value 配置存储值
     * @return string|null public 磁盘相对路径
     */
    private function getConfigFilePath( ?string $value ): ?string {
        if ( blank( $value ) ) { return null; }
        $value = trim( $value );
        if ( $this->isAllowedConfigFilePath( $value ) ) { return $value; }
        $valuePath = (string) parse_url( $value, PHP_URL_PATH );
        $disk = (string) config( 'system_uploads.system_config.disk', 'public' );
        $basePath = rtrim( (string) parse_url( Storage::disk( $disk )->url( '' ), PHP_URL_PATH ), '/' );
        if ( $basePath === '' || ! str_starts_with( $valuePath, "{$basePath}/" ) ) { return null; }
        $relativePath = ltrim( substr( $valuePath, strlen( $basePath ) ), '/' );
        return $this->isAllowedConfigFilePath( $relativePath ) ? $relativePath : null;
    }

    /**
     * 判断配置文件路径是否允许。
     * 只允许 system_file 目录中的文件。
     * @param string $path public 磁盘相对路径
     * @return bool 是否为允许的配置文件路径
     */
    private function isAllowedConfigFilePath( string $path ): bool {
        $directory = trim( (string) config( 'system_uploads.system_config.directory', 'system_file' ), '/' );
        return $directory !== '' && str_starts_with( $path, "{$directory}/" );
    }

    /**
     * 删除配置文件。
     * 只允许删除 system_file 目录内的公开文件。
     * @param string $path 文件路径
     * @return void
     */
    private function deleteConfigFile( string $path ): void {
        $path = $this->getConfigFilePath( $path );
        if ( $path === null ) { return; }
        $disk = (string) config( 'system_uploads.system_config.disk', 'public' );
        Storage::disk( $disk )->delete( $path );
    }

    /**
     * 重置新增表单。
     * 恢复新增配置表单默认状态并关闭表单。
     * @return void
     */
    private function resetCreateForm(): void {
        $this->newConfig = [
            'category' => $this->activeCategory,
            'type' => 'text',
            'name' => '',
            'key' => '',
            'value' => '',
            'description' => '',
            'index' => 255,
        ];
        $this->newUpload = null;
        $this->showCreateForm = false;
        $this->resetValidation();
    }

    /**
     * 获取校验提示。
     * 返回系统配置表单中文校验提示。
     * @return array<string, string> 校验提示
     */
    private function getValidationMessages(): array {
        return [
            'newConfig.category.required' => '请选择配置类别。',
            'newConfig.type.required' => '请选择配置类型。',
            'newConfig.name.required' => '请输入配置名称。',
            'newConfig.key.required' => '请输入键名。',
            'newConfig.key.regex' => '键名必须以字母开头，只能包含字母、数字、点、横线和下划线。',
            'newConfig.key.unique' => '该键名已存在。',
            'newConfig.index.required' => '请输入排序值。',
            'newConfig.index.integer' => '排序值必须为整数。',
            'newConfig.index.min' => '排序值不能小于 0。',
            'newConfig.value.url' => '请输入有效链接。',
            'newConfig.value.integer' => '请输入整数。',
            'newConfig.value.numeric' => '请输入有效数字。',
            'newConfig.value.json' => '请输入有效 JSON。',
            'newUpload.image' => '请选择有效的图片文件。',
            'newUpload.extensions' => '所选文件扩展名不受支持。',
            'newUpload.mimetypes' => '所选文件格式不受支持。',
            'newUpload.max' => '所选文件大小超过限制。',
            'values.*.url' => '请输入有效链接。',
            'values.*.integer' => '请输入整数。',
            'values.*.numeric' => '请输入有效数字。',
            'values.*.json' => '请输入有效 JSON。',
            'uploads.*.image' => '请选择有效的图片文件。',
            'uploads.*.extensions' => '所选文件扩展名不受支持。',
            'uploads.*.mimetypes' => '所选文件格式不受支持。',
            'uploads.*.max' => '所选文件大小超过限制。',
        ];
    }

    /**
     * 发送失败通知。
     * 显示系统配置操作失败信息。
     * @param string $message 失败信息
     * @return void
     */
    private function notifyFailure( string $message ): void {
        Notification::make()
            ->title( $message )
            ->danger()
            ->send();
    }


    /**
     * 页面信息
     */
    public static function getNavigationLabel(): string { return __( 'filament.navigation.system_config' ); }
    public function getTitle(): string { return __( 'filament.titles.system_config' ); }
    public static function getNavigationGroup(): string|UnitEnum|null { return __( 'filament.groups.system' ); }
}
