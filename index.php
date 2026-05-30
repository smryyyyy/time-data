<?php
/**
 * 时服推送系统 v3
 */
define('ROOT', __DIR__);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = ROOT . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

if (!file_exists(ROOT . '/vendor/autoload.php')) {
    http_response_code(500);
    die("依赖未安装。请运行: cd " . ROOT . " && bash install.sh");
}
require ROOT . '/vendor/autoload.php';
require_once ROOT . '/src/helpers.php';

// 加载配置
$config = require ROOT . '/config.php';

// 动态设置覆盖
$store = new App\SettingsStore($config['data_dir']);
$overrides = $store->load();
if (!empty($overrides)) {
    $config = array_replace_recursive($config, $overrides);
}

// 启动
$app = new App\App($config);
$app->run();
