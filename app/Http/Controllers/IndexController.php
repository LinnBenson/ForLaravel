<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class IndexController extends Controller {
    /**
     * 首页数据接口
     * 返回首页所需的应用信息。
     * @return \Illuminate\Http\JsonResponse JSON 响应
     */
    public function index( Request $request ): \Illuminate\Http\JsonResponse {
        // 获取语言包
        $lang = null;
        $langs = $request->query( 'langs' );
        if ( is_string( $langs ) && $langs !== '' ) {
            $requestedLangs = array_unique( array_filter( explode( '|', $langs ) ) );
            foreach ( $requestedLangs as $langName ) {
                $translations = Lang::get( $langName, [], app()->getLocale() );
                if ( is_array( $translations ) ) { $lang[$langName] = $translations; }
            }
        }
        // 检查用户
        $user = null;
        // 返回应用信息
        return echoJson( 0, [
            'app' => [
                'title' => setting( 'app.title' ),
                'debug' => setting( 'app.debug' ),
                'host' => setting( 'app.host' ),
                'icon' => setting( 'app.icon' ),
                'copyright' => setting( 'app.copyright' ),
            ],
            'user' => $user,
            'lang' => $lang,
        ]);
    }
    /**
     * 首页
     * 显示网站首页。
     * @return \Illuminate\View\View
     */
    public function view(): \Illuminate\View\View {
        return view( 'Welcome' );
    }
    /**
     * 调试
     * 用于调试的测试方法。
     * @return any
     */
    public function debug() {
        return null;
    }
}
