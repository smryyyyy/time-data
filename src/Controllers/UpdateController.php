<?php
namespace App\Controllers;

/**
 * 在线更新 — 从 GitHub 拉取最新代码覆盖容器内文件
 * 支持通过 update_proxy 配置走代理下载（解决国内 GitHub 直连 SSL 中断问题）
 */
class UpdateController
{
    public function index(): void
    {
        $config = $GLOBALS['hermes_config'];
        $logger = $GLOBALS['hermes_logger'];
        $logger->info("开始在线更新...");

        // 读取代理配置（settings.json 或 config.php 中的 update_proxy）
        $proxyUrl = $config['update_proxy'] ?? '';

        // 1. 下载最新源码压缩包
        $tmpFile = sys_get_temp_dir() . '/time-data-update.tar.gz';
        $url = 'https://github.com/smryyyyy/time-data/archive/refs/heads/main.tar.gz';
        
        $ch = curl_init($url);
        $fp = fopen($tmpFile, 'w');
        $curlOpts = [
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        if ($proxyUrl) {
            $curlOpts[CURLOPT_PROXY] = $proxyUrl;
            $logger->info("使用代理: {$proxyUrl}");
        }
        curl_setopt_array($ch, $curlOpts);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200 || $err) {
            unlink($tmpFile);
            $logger->error("更新下载失败: HTTP={$httpCode} {$err}");
            jsonError("下载失败: HTTP={$httpCode} " . ($err ?: ''), 500);
        }

        // 2. 解压到 /var/www/html，覆盖旧文件
        $cmd = sprintf('tar -xzf %s --strip-components=1 --overwrite -C /var/www/html 2>&1', escapeshellarg($tmpFile));
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);

        if ($exitCode !== 0) {
            $logger->error("更新解压失败: " . implode("\n", $output));
            jsonError("解压失败", 500);
        }

        // 3. 重新安装 Composer 依赖（如果有变动）
        exec('cd /var/www/html && composer install --no-dev --optimize-autoloader 2>&1', $co, $ce);

        // 4. 修复权限
        exec('chown -R www-data:www-data /var/www/html/data /var/www/html/logs /var/www/html/tmp /var/www/html/templates /var/www/html/uploads 2>&1');

        // 5. 重启 Apache 清 OPcache
        exec('apache2ctl restart 2>&1', $ao, $ae);

        $logger->info("在线更新完成");

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => '更新完成，代码已替换为 GitHub 最新版',
            'composer' => $ce === 0 ? 'ok' : implode("\n", $co),
            'apache' => 'restarted',
        ], JSON_UNESCAPED_UNICODE);
    }
}
