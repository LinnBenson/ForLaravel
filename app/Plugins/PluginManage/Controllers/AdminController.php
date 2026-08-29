<?php

namespace App\Plugins\PluginManage\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\PluginManage\Models\Packages;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * AdminController
 * 插件管理数据表控制器。
 * @package App\Plugins\PluginManage\Controllers
 */
class AdminController extends Controller {
    /**
     * 重建插件包数据表。
     * 删除已有数据后重新创建当前版本的数据表结构。
     * @return JsonResponse JSON 响应
     */
    public function rebuild(): JsonResponse {
        try {
            DB::connection( Packages::CONNECTION )->transaction( function (): void {
                Packages::down();
                Packages::up();
            } );
            return echoJson( true, ['message' => '插件包数据表重装成功。'] );
        }catch ( Throwable $throwable ) {
            report( $throwable );
            return echoJson( false, ['message' => '插件包数据表重装失败，请查看系统日志。'], 500 );
        }
    }
}
