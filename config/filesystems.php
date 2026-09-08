<?php
return [
    /**
     * 默认文件系统磁盘
     * 这里可以指定框架默认使用的文件系统磁盘。应用可以使用 "local" 磁盘以及多种基于云的磁盘来存储文件。
     */
    'default' => env( 'FILESYSTEM_DISK', 'local' ),
    /**
     * 文件系统磁盘
     * 下面可以按需配置任意数量的文件系统磁盘，也可以为同一个驱动配置多个磁盘。这里提供了大多数支持的存储驱动示例供参考。
     * 支持的驱动: "local", "ftp", "sftp", "s3"
     */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path( 'app/private' ),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path( 'app/public' ),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
        's3' => [
            'driver' => 's3',
            'key' => env( 'AWS_ACCESS_KEY_ID' ),
            'secret' => env( 'AWS_SECRET_ACCESS_KEY' ),
            'region' => env( 'AWS_DEFAULT_REGION' ),
            'bucket' => env( 'AWS_BUCKET' ),
            'url' => env( 'AWS_URL' ),
            'endpoint' => env( 'AWS_ENDPOINT' ),
            'use_path_style_endpoint' => env( 'AWS_USE_PATH_STYLE_ENDPOINT', false ),
            'throw' => false,
            'report' => false,
        ],
    ],
    /**
     * 符号链接
     * 这里可以配置执行 `storage:link` Artisan 命令时要创建的符号链接。数组键应为链接位置，数组值应为目标位置。
     */
    'links' => [
        public_path( 'storage' ) => storage_path( 'app/public' ),
    ],
];
