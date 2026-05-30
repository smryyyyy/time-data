<?php
namespace App\Controllers;

/**
 * 定时任务入口 — cron 每分钟调用 /cron/tick
 */
class CronController
{
    public function tick(): void
    {
        $config    = $GLOBALS['hermes_config'];
        $logger    = $GLOBALS['hermes_logger'];
        $scheduler = new \App\Scheduler($config['data_dir'], $config['schedule'] ?? []);

        // 每次触发时清理图床 7 天前图片
        try {
            $imgServer = new \App\Services\ImageServer($config['image_server']);
            $imgServer->cleanup();
        } catch (\Throwable $e) {
            $logger->warn('图床清理失败: ' . $e->getMessage());
        }

        $tasks = $scheduler->tick();

        if (empty($tasks)) {
            echo "OK — no tasks\n";
            return;
        }

        $runner = new \App\TaskRunner($config, $logger);
        $date   = date('Y-m-d');

        foreach ($tasks as $task) {
            $hour = (int)($task['hour'] ?? $task['time'] ?? 0);
            if (!isset($config['hours'][$hour]) || empty($config['hours'][$hour]['enabled'])) {
                continue;
            }

            $logger->info("定时触发 [{$hour}点]");
            $result = $runner->run($date, $hour);
            echo "[{$hour}点] " . ($result['success'] ? 'OK' : 'FAIL: ' . $result['message']) . "\n";
        }
    }
}
