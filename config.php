<?php
/**
 * 时服推送系统 v3 — 全局配置
 * 静态默认值。动态修改通过 Web 面板写入 data/settings.json 覆盖。
 *
 * === 密码生成 ===
 * php scripts/hash_password.php <你的密码>
 */

return [
    // ========== 系统 ==========
    'debug'         => false,
    'timezone'      => 'Asia/Shanghai',
    'base_url'      => 'http://your-domain.com',

    // ========== 用户认证（为多用户预留） ==========
    'users' => [
        'admin' => [
            'password' => '$2y$10$Z5.gBzKbeOSfkCLK05XOZ.q/DkmXAoZuRPLGc6Hm.NW4cLUzkfwV2',
        ],
    ],

    // ========== pzoom 公共凭据 ==========
    'pzoom' => [
        'username'  => '',
        'password'  => '',
        'login_url' => 'https://login.pzoom.com/',
        'overview'  => 'https://app.pzoom.com/pinzhi/mrs/overview/vivo',
    ],

    // ========== 飞书 ==========
    'feishu' => [
        'app_id'     => 'your_feishu_app_id',
        'app_secret' => 'your_feishu_app_secret',
    ],

    // ========== 全局告警 webhook ==========
    'alert_webhook' => '',

    // ========== 24 时间点配置 ==========
    // enabled: 开关  data_prefix: 源文件名前缀
    // copy_range: [起始列, 结束列]  template: 模板文件名
    // exports: { 'sheet': { webhook: '...', cell_ranges: ['A1:N23', ...] }, ... }
    'hours' => [
        0  => ['enabled' => false,  'data_prefix' => '2340_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        1  => ['enabled' => false, 'data_prefix' => '0040_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        2  => ['enabled' => false, 'data_prefix' => '0140_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        3  => ['enabled' => false, 'data_prefix' => '0240_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        4  => ['enabled' => false, 'data_prefix' => '0340_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        5  => ['enabled' => false, 'data_prefix' => '0440_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        6  => ['enabled' => false, 'data_prefix' => '0540_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        7  => ['enabled' => false, 'data_prefix' => '0640_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        8  => ['enabled' => false, 'data_prefix' => '0740_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        9  => ['enabled' => false, 'data_prefix' => '0840_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        10 => ['enabled' => false,  'data_prefix' => '0940_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        11 => ['enabled' => false, 'data_prefix' => '1040_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        12 => ['enabled' => false, 'data_prefix' => '1140_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        13 => ['enabled' => false, 'data_prefix' => '1240_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        14 => ['enabled' => false,  'data_prefix' => '1340_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        15 => ['enabled' => false, 'data_prefix' => '1440_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        16 => ['enabled' => false, 'data_prefix' => '1540_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        17 => ['enabled' => false, 'data_prefix' => '1640_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        18 => ['enabled' => false,  'data_prefix' => '1740_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        19 => ['enabled' => false, 'data_prefix' => '1840_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        20 => ['enabled' => false, 'data_prefix' => '1940_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        21 => ['enabled' => false, 'data_prefix' => '2040_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        22 => ['enabled' => false,  'data_prefix' => '2140_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
        23 => ['enabled' => false, 'data_prefix' => '2240_时报', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []],
    ],

    // ========== 目录 ==========
    'data_dir'     => ROOT . '/data',
    'output_dir'   => ROOT . '/tmp',
    'log_dir'      => ROOT . '/logs',
    'template_dir' => ROOT . '/templates',

    // ========== 定时任务（Web 面板管理，此处默认值） ==========
    'schedule' => [
        ['time' => '10:00', 'hour' => 10],
        ['time' => '14:00', 'hour' => 14],
        ['time' => '18:00', 'hour' => 18],
        ['time' => '22:00', 'hour' => 22],
    ],

    // ========== 模板 ==========
    'template_max_size' => 10 * 1024 * 1024,   // 10MB

    // ========== LibreOffice / ImageMagick ==========
    'libreoffice' => '/usr/bin/libreoffice',
    'imagemagick' => '/usr/bin/convert',
];
