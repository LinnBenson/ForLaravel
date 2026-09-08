<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_config', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('系统配置ID');
            $table->string('category', 32)->index()->comment('类别');
            $table->string('type', 32)->comment('类型');
            $table->string('name', 191)->nullable()->comment('名称');
            $table->string('key', 191)->unique()->comment('键名');
            $table->longText('value')->nullable()->comment('值');
            $table->text('description')->nullable()->comment('描述');
            $table->unsignedInteger('index')->default(0)->index()->comment('排序');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        $now = now();
        $insert = [];
        $insertConfig = [
            [
                'category' => 'app',
                'type' => 'text',
                'name' => '应用名称',
                'key' => 'app.title',
                'value' => config( 'app.name' ),
                'description' => '用于应用名称显示'
            ],
            [
                'category' => 'app',
                'type' => 'text',
                'name' => '应用域名',
                'key' => 'app.host',
                'value' => explode( '//', config( 'app.url' ) )[1] ?? '',
                'description' => '应用接口默认 HOST'
            ],
            [
                'category' => 'app',
                'type' => 'image',
                'name' => '应用标志',
                'key' => 'app.icon',
                'value' => config( 'app.url' ).'/favicon.ico',
                'description' => '用于应用标志显示'
            ],
            [
                'category' => 'app',
                'type' => 'boolean',
                'name' => '调试模式',
                'key' => 'app.debug',
                'value' => config( 'app.debug' ) ? '1' : '0',
                'description' => '用于修改应用的开发调试状态'
            ],
            [
                'category' => 'app',
                'type' => 'textarea',
                'name' => '版权声明',
                'key' => 'app.copyright',
                'value' => '© '.date( 'Y' ).' <a href="'.config( 'app.url' ).'" target="_blank">'.config( 'app.name' ).'</a> All Rights Reserved.',
                'description' => '用于应用页脚版权声明'
            ],
            [
                'category' => 'app',
                'type' => 'json',
                'name' => '应用主题',
                'key' => 'app.theme',
                'value' => json_encode([
                    'Default' => [
                        'icon' => '/assets/icons/favicon.png',
                        'logo' => '/assets/icons/logo_dark.png',
                        'img' => '/assets/images/Default.jpg',
                        'style' => '',
                        '--r0' => '237, 236, 231',
                        '--r1' => '70, 70, 70',
                        '--r2' => '65, 115, 179',
                        '--r2c' => 'var( --r0 )',
                        '--r3' => '114, 141, 167',
                        '--r3c' => 'var( --r0 )',
                        '--r4' => '141, 178, 43',
                        '--r4c' => 'var( --r0 )',
                        '--r5' => '223, 92, 79',
                        '--r5c' => 'var( --r0 )',
                        '--r6' => '249, 247, 244',
                        '--radius' => '4px',
                    ],
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR ),
                'description' => '用于修改应用显示的主题效果'
            ],
            [
                'category' => 'system',
                'type' => 'text',
                'name' => '邮箱 SMTP 服务器',
                'key' => 'system.mail.host',
                'value' => config( 'mail.mailers.smtp.host' ) ?? '',
                'description' => '用于配置邮箱 SMTP 服务器'
            ],
            [
                'category' => 'system',
                'type' => 'number',
                'name' => '邮箱 SMTP 端口',
                'key' => 'system.mail.port',
                'value' => config( 'mail.mailers.smtp.port' ) ?? '',
                'description' => '用于配置邮箱 SMTP 端口'
            ],
            [
                'category' => 'system',
                'type' => 'text',
                'name' => '邮箱 SMTP 协议',
                'key' => 'system.mail.scheme',
                'value' => config( 'mail.mailers.smtp.scheme' ) ?? '',
                'description' => '用于配置邮箱 SMTP 服务器协议'
            ],
            [
                'category' => 'system',
                'type' => 'text',
                'name' => '邮箱 SMTP 用户名',
                'key' => 'system.mail.username',
                'value' => config( 'mail.mailers.smtp.username' ) ?? '',
                'description' => '用于配置邮箱 SMTP 用户名'
            ],
            [
                'category' => 'system',
                'type' => 'text',
                'name' => '邮箱 SMTP 密码',
                'key' => 'system.mail.password',
                'value' => config( 'mail.mailers.smtp.password' ) ?? '',
                'description' => '用于配置邮箱 SMTP 密码'
            ]
        ];
        foreach ( $insertConfig as $index => $config ) {
            $insert[] = array_merge( $config, [
                'index' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ] );
        }
        DB::table('system_config')->insert( $insert );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_config');
    }
};
