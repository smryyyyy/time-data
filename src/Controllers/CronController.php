<?php
namespace App\Controllers;

/**
 * 定时任务入口 — cron 每分钟调用 /cron/tick
 * 容器内置了每分钟循环，无需外部 crontab
 */
class CronController
{
    public function tick(): void
    {
        $config    = $GLOBALS['hermes_config'];
        $logger    = $GLOBALS['hermes_logger'];

        // 每天 03:00 清理 7 天前的历史数据
        if (date('H:i') === '03:00') {
            $this->cleanup($config, $logger);
        }

        $scheduler = new \App\Scheduler($config['data_dir'], $config['schedule'] ?? [], $logger);
        $now       = date('Y-m-d H:i:s');

        $result = $scheduler->tick();
        $tasks  = $result['tasks'];
        $log    = $result['log'];

        // 日志文件 + 标准输出
        foreach ($log as $line) {
            $logger->info(preg_replace('/\[\d{2}:\d{2}\] /', '', $line));
            echo $line . "\n";
        }
        flush();

        if (empty($tasks)) {
            return;
        }

        $runner = new \App\TaskRunner($config, $logger);
        $date   = date('Y-m-d');

        foreach ($tasks as $task) {
            $hour = (int)($task['hour'] ?? explode(':', $task['time'] ?? '0')[0]);
            if (!isset($config['hours'][$hour]) || empty($config['hours'][$hour]['enabled'])) {
                $logger->info("[{$hour}点] 跳过（时间点未启用）");
                continue;
            }

            $logger->info("定时触发 [{$hour}点]");
            echo "[{$now}] 【{$hour}点】执行开始...\n";

            $runResult = $runner->run($date, $hour);
            $msg = $runResult['success'] ? "完成 ✓" : "失败: " . $runResult['message'];
            $logger->info("[{$hour}点] {$msg}");
            echo "[{$now}] 【{$hour}点】{$msg}\n";
            flush();
        }
    }

    /**
     * 清理 7 天前的历史数据（源文件、临时文件、日志）
     */
    private function cleanup(array $config, \App\Logger $logger): void
    {
        $keepDays = 4;
        $cutoff = strtotime("-{$keepDays} days");
        if (!$cutoff) return;
        $cutoffDate = date('Y-m-d', $cutoff);
        $totalSize = 0;
        $totalFiles = 0;

        // 清理 data/{date}/ (下载的源文件)
        $dataDir = $config['data_dir'];
        foreach (glob($dataDir . '/20*', GLOB_ONLYDIR) as $dir) {
            $dirDate = basename($dir);
            if ($dirDate >= $cutoffDate) continue;
            $size = dirSize($dir);
            removeDir($dir);
            $totalSize += $size;
            $totalFiles++;
            $logger->info("清理数据目录: {$dirDate} (" . formatSize($size) . ")");
        }

        // 清理 tmp/{date}/ (合并文件+截图)
        $tmpDir = $config['output_dir'];
        foreach (glob($tmpDir . '/20*', GLOB_ONLYDIR) as $dir) {
            $dirDate = basename($dir);
            if ($dirDate >= $cutoffDate) continue;
            $size = dirSize($dir);
            removeDir($dir);
            $totalSize += $size;
            $totalFiles++;
            $logger->info("清理临时目录: {$dirDate} (" . formatSize($size) . ")");
        }

        // 清理 logs/{date}.log
        $logDir = $config['log_dir'];
        foreach (glob($logDir . '/20*.log') as $file) {
            $fileDate = basename($file, '.log');
            if ($fileDate >= $cutoffDate) continue;
            $size = filesize($file);
            unlink($file);
            $totalSize += $size;
            $totalFiles++;
            $logger->info("清理日志: " . basename($file) . " (" . formatSize($size) . ")");
        }

        if ($totalFiles > 0) {
            $logger->info("清理完成: 共 {$totalFiles} 个文件/目录, 释放 " . formatSize($totalSize));
        }
    }
}
