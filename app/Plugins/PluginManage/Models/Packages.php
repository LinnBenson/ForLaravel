<?php

namespace App\Plugins\PluginManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Packages
 * 插件包数据模型。
 * @package App\Plugins\PluginManage\Models
 */
class Packages extends Model {
    public const CONNECTION = 'plugin-manage';

    protected $connection = self::CONNECTION;

    protected $table = 'packages';

    protected $casts = [
        'count' => 'integer',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'rid',
        'name',
        'description',
        'version',
        'author',
        'count',
    ];

    /**
     * 设置插件唯一名称。
     * 限制唯一名称只能包含数字、字母、横线和下划线。
     * @param string $value 插件唯一名称
     * @return void
     */
    public function setRidAttribute( string $value ): void {
        if ( preg_match( '/^[A-Za-z0-9_-]+$/', $value ) !== 1 ) {
            throw new InvalidArgumentException( '插件唯一名称只能包含数字、字母、横线和下划线。' );
        }
        $this->attributes['rid'] = $value;
    }

    /**
     * 获取插件数据库结构构建器。
     * @return Builder 数据库结构构建器
     */
    public static function schema(): Builder {
        return Schema::connection( self::CONNECTION );
    }

    /**
     * 安装插件包数据表。
     * @return void
     */
    public static function up(): void {
        $schema = self::schema();
        if ( $schema->hasTable( 'packages' ) ) { return; }
        $schema->create( 'packages', function ( Blueprint $table ): void {
            $table->id()->comment( 'ID' );
            $table->string( 'rid' )->unique()->comment( '唯一名称' );
            $table->string( 'name' )->comment( '插件名称' );
            $table->text( 'description' )->nullable()->comment( '插件描述' );
            $table->string( 'version', 64 )->comment( '版本号' );
            $table->string( 'author' )->comment( '作者' );
            $table->unsignedBigInteger( 'count' )->default( 0 )->comment( '下载次数' );
            $table->timestamp( 'created_at' )->nullable()->comment( '上传时间' );
            $table->timestamp( 'updated_at' )->nullable()->comment( '更新时间' );

            $table->index( 'name' );
            $table->index( 'updated_at' );
        } );
    }

    /**
     * 卸载插件包数据表。
     * @return void
     */
    public static function down(): void {
        $schema = self::schema();
        if ( !$schema->hasTable( 'packages' ) ) { return; }
        $schema->drop( 'packages' );
    }
}
