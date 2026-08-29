<?php

namespace App\Plugins\PluginManage\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\PluginManage\Models\Packages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * MarketController
 * @package App\Plugins\PluginManage\Controllers
 */
class MarketController extends Controller {

    /**
     * 插件上传接口
     * @param Request $request
     * @return JsonResponse
     */
    public function upload( Request $request ): JsonResponse {
        $token = $request->bearerToken() ?? '';
        if ( empty( $token ) || $token !== plugin( 'PluginManage' )->config( 'upload.token' ) ) {
            return echoJson( 2, ['base.error.403'], 403 );
        }
        // 读取插件信息
        $validator = Validator::make( $request->all(), [
            'rid' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'version' => ['required', 'string', 'max:64'],
            'author' => ['nullable', 'string', 'max:255'],
            'upload' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ])->stopOnFirstFailure();
        if ( $validator->fails() ) { return echoJson( 2, $validator->errors()->first(), 422 ); }
        $package = $validator->validated();
        $upload = $request->file( 'upload' );
        $rid = (string) $package['rid'];
        $existingPackage = Packages::query()->where( 'rid', $rid )->first();
        if ( $existingPackage && !version_compare( (string) $package['version'], $existingPackage->version, '>' ) ) {
            return echoJson( 2, 'The uploaded version must be higher than the current version.', 409 );
        }
        $packageDirectory = dirname( __DIR__ ).'/Database/package';
        $packagePath = "{$packageDirectory}/{$rid}.zip";
        if ( !$existingPackage && is_file( $packagePath ) ) {
            return echoJson( 2, 'The plugin archive already exists.', 409 );
        }
        $temporaryPath = '';
        $backupPath = '';
        $packageReplaced = false;
        try {
            if ( !is_dir( $packageDirectory ) && !mkdir( $packageDirectory, 0775, true ) && !is_dir( $packageDirectory ) ) {
                throw new RuntimeException( 'Unable to create the package directory.' );
            }
            $uniqueSuffix = bin2hex( random_bytes( 12 ) );
            $temporaryPath = "{$packageDirectory}/.{$rid}.{$uniqueSuffix}.upload";
            $backupPath = "{$packageDirectory}/.{$rid}.{$uniqueSuffix}.backup";
            $this->saveUpload( $upload, $temporaryPath );
            if ( is_file( $packagePath ) && !rename( $packagePath, $backupPath ) ) {
                throw new RuntimeException( 'Unable to back up the existing package.' );
            }
            if ( !rename( $temporaryPath, $packagePath ) ) {
                throw new RuntimeException( 'Unable to replace the package file.' );
            }
            $packageReplaced = true;
            DB::connection( Packages::CONNECTION )->transaction( function () use ( $existingPackage, $package ): void {
                $attributes = [
                    'rid' => $package['rid'],
                    'name' => $package['name'],
                    'description' => $package['description'] ?? null,
                    'version' => $package['version'],
                    'author' => $package['author'] ?? '',
                ];
                if ( $existingPackage ) {
                    $existingPackage->fill( $attributes )->saveOrFail();
                    return;
                }
                Packages::query()->create( $attributes + ['count' => 0] );
            } );
            if ( is_file( $backupPath ) && !unlink( $backupPath ) ) {
                report( new RuntimeException( "Unable to remove the package backup: {$backupPath}" ) );
            }
            return echoJson( 0, $existingPackage ? 'Plugin updated successfully.' : 'Plugin uploaded successfully.' );
        }catch ( Throwable $throwable ) {
            if ( $packageReplaced && is_file( $packagePath ) && !unlink( $packagePath ) ) {
                report( new RuntimeException( "Unable to remove the failed package update: {$packagePath}" ) );
            }
            if ( $backupPath !== '' && is_file( $backupPath ) && !rename( $backupPath, $packagePath ) ) {
                report( new RuntimeException( "Unable to restore the package backup: {$backupPath}" ) );
            }
            if ( $temporaryPath !== '' && is_file( $temporaryPath ) && !unlink( $temporaryPath ) ) {
                report( new RuntimeException( "Unable to remove the temporary package: {$temporaryPath}" ) );
            }
            report( $throwable );
            return echoJson( 2, 'Plugin upload failed.', 500 );
        }
    }

    /**
     * 获取插件列表。
     * 按上传时间倒序分页输出插件包信息。
     * @param Request $request 请求对象
     * @return JsonResponse JSON 响应
     */
    public function list( Request $request ): JsonResponse {
        $token = $request->bearerToken() ?? '';
        if ( empty( $token ) || $token !== plugin( 'PluginManage' )->config( 'download.token' ) ) {
            return echoJson( 2, ['base.error.403'], 403 );
        }
        $page = max( $request->integer( 'page', 1 ), 1 );
        $keyword = trim( (string) $request->query( 'keyword', '' ) );
        $packages = Packages::query()
            ->when( $keyword !== '', function ( Builder $query ) use ( $keyword ): void {
                $query->where( function ( Builder $query ) use ( $keyword ): void {
                    $query
                        ->where( 'rid', 'like', "%{$keyword}%" )
                        ->orWhere( 'name', 'like', "%{$keyword}%" )
                        ->orWhere( 'description', 'like', "%{$keyword}%" );
                } );
            } )
            ->orderByDesc( 'updated_at' )
            ->orderByDesc( 'id' )
            ->paginate( 20, ['*'], 'page', $page );
        return echoJson( 0, [
            'total' => $packages->total(),
            'page' => $packages->currentPage(),
            'quantity' => $packages->lastPage(),
            'data' => $packages->items(),
        ] );
    }

    /**
     * 下载插件压缩包。
     * 验证下载权限和插件包文件后累计下载次数。
     * @param Request $request 请求对象
     * @param string $name 插件唯一名称
     * @return JsonResponse|BinaryFileResponse 文件响应或错误响应
     */
    public function download( Request $request, string $name ): JsonResponse|BinaryFileResponse {
        $token = $request->bearerToken() ?? '';
        if ( empty( $token ) || $token !== plugin( 'PluginManage' )->config( 'download.token' ) ) {
            return echoJson( 2, ['base.error.403'], 403 );
        }
        if ( preg_match( '/^[A-Za-z0-9_-]+$/', $name ) !== 1 ) {
            return echoJson( 2, 'Invalid plugin name.', 422 );
        }
        $package = Packages::query()->where( 'rid', $name )->first();
        if ( !$package ) { return echoJson( 2, 'Plugin not found.', 404 ); }
        $packageDirectory = realpath( dirname( __DIR__ ).'/Database/package' );
        $packagePath = realpath( dirname( __DIR__ )."/Database/package/{$name}.zip" );
        if (
            $packageDirectory === false || $packagePath === false ||
            dirname( $packagePath ) !== $packageDirectory || !is_file( $packagePath ) || !is_readable( $packagePath )
        ) {
            return echoJson( 2, 'Plugin archive not found.', 404 );
        }
        try {
            DB::connection( Packages::CONNECTION )->transaction( function () use ( $package ): void {
                $package->increment( 'count' );
            } );
            return response()->download( $packagePath, "{$name}.zip", [
                'Content-Type' => 'application/zip',
            ] );
        }catch ( Throwable $throwable ) {
            report( $throwable );
            return echoJson( 2, 'Plugin download failed.', 500 );
        }
    }

    /**
     * 原子保存上传的插件压缩包。
     * 使用独占模式创建目标文件，避免并发请求覆盖同名插件包。
     * @param UploadedFile|null $upload 上传文件
     * @param string $packagePath 插件包保存路径
     * @return void
     */
    private function saveUpload( ?UploadedFile $upload, string $packagePath ): void {
        if ( !$upload || !$upload->isValid() ) { throw new RuntimeException( 'Invalid uploaded package.' ); }
        $sourcePath = $upload->getRealPath();
        if ( $sourcePath === false ) { throw new RuntimeException( 'Unable to read the uploaded package.' ); }
        $source = @fopen( $sourcePath, 'rb' );
        if ( $source === false ) { throw new RuntimeException( 'Unable to open the uploaded package.' ); }
        $target = @fopen( $packagePath, 'xb' );
        if ( $target === false ) {
            fclose( $source );
            throw new RuntimeException( 'The package file already exists or cannot be created.' );
        }
        try {
            $written = stream_copy_to_stream( $source, $target );
            if ( $written === false || $written !== $upload->getSize() ) {
                throw new RuntimeException( 'The uploaded package was not written completely.' );
            }
            if ( !fflush( $target ) ) { throw new RuntimeException( 'Unable to flush the saved package.' ); }
            clearstatcache( true, $packagePath );
            if ( !is_file( $packagePath ) || filesize( $packagePath ) !== $upload->getSize() ) {
                throw new RuntimeException( 'Unable to confirm the saved package.' );
            }
        }catch ( Throwable $throwable ) {
            if ( is_file( $packagePath ) && !unlink( $packagePath ) ) {
                report( new RuntimeException( "Unable to remove the incomplete package: {$packagePath}" ) );
            }
            throw $throwable;
        }finally {
            fclose( $source );
            fclose( $target );
        }
    }
}
