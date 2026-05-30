<?php
namespace App\Steps;

/**
 * 步骤 1：从 pzoom 动态下载数据
 * - 登录 pzoom → 匹配报告列表 → 下载对应日期的时报
 * - 同时确保今日和昨日数据都存在
 */
class Download
{
    private array $config;
    private \App\Logger $logger;

    public function __construct(array $config, \App\Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $date   Y-m-d 格式
     * @param int    $hour   时间点 (0-23)
     * @throws \RuntimeException
     */
    public function execute(string $date, int $hour): void
    {
        $pzoom  = $this->config['pzoom'];
        $prefix = $this->config['hours'][$hour]['data_prefix'] ?? null;
        if (!$prefix) {
            throw new \RuntimeException("时间点 {$hour} 未配置 data_prefix");
        }

        $dataDir = $this->config['data_dir'];
        $todayFile = sourceFilePath($dataDir, $date, $prefix);
        $yesterday = yesterday($date);
        $yestFile  = sourceFilePath($dataDir, $yesterday, $prefix);

        // 检查并下载今日数据
        if (!file_exists($todayFile)) {
            $this->logger->info("下载今日数据: {$date} ({$prefix})");
            $this->downloadFromPzoom($date, $todayFile, $prefix);
        } else {
            $this->logger->info("今日数据已存在，跳过: {$todayFile}");
        }

        // 检查并下载昨日数据
        if (!file_exists($yestFile)) {
            $this->logger->info("下载昨日数据: {$yesterday} ({$prefix})");
            $this->downloadFromPzoom($yesterday, $yestFile, $prefix);
        } else {
            $this->logger->info("昨日数据已存在，跳过: {$yestFile}");
        }
    }

    private function downloadFromPzoom(string $date, string $outputPath, string $prefix): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $pzoom   = $this->config['pzoom'];
        $script  = ROOT . '/scripts/download_pzoom.py';

        if (!file_exists($script)) {
            throw new \RuntimeException("下载脚本不存在: {$script}");
        }

        $cmd = sprintf(
            'python3 %s %s %s %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($pzoom['username']),
            escapeshellarg($pzoom['password']),
            escapeshellarg($pzoom['login_url']),
            escapeshellarg($pzoom['overview']),
            escapeshellarg($date),
            escapeshellarg($prefix)
        );

        $cmdMasked = sprintf(
            'python3 %s %s *** %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($pzoom['username']),
            escapeshellarg($pzoom['login_url']),
            escapeshellarg($pzoom['overview']),
            escapeshellarg($date),
            escapeshellarg($prefix)
        );
        $this->logger->info("执行下载: {$cmdMasked}");

        putenv('PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright');
        exec($cmd, $output, $exitCode);
        $outputStr = implode("\n", $output);
        $this->logger->info("下载输出: {$outputStr}");

        if ($exitCode !== 0) {
            throw new \RuntimeException("下载脚本失败 (exit={$exitCode}): {$outputStr}");
        }

        // 检查产物 — 脚本输出为 data/{date}.xlsx，重命名
        $downloaded = $this->config['data_dir'] . "/{$date}.xlsx";
        if (file_exists($downloaded)) {
            rename($downloaded, $outputPath);
            $this->logger->info("下载完成 → {$outputPath}");
        } elseif (!file_exists($outputPath)) {
            throw new \RuntimeException("下载完成但未找到数据文件: {$outputPath}");
        }
    }
}
