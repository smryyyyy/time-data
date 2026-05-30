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
}
