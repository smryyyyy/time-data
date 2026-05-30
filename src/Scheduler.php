<?php
namespace App;

/**
 * 定时调度器
 * 策略：cron 每分钟请求 /cron/tick，这里判断是否到了任务时间
 */
class Scheduler
{
    private string $dataFile;
    private array $defaults;
    private Logger $logger;

    public function __construct(string $dataDir, array $defaults, Logger $logger)
    {
        $this->dataFile = $dataDir . '/schedule.json';
        $this->defaults = $defaults;
        $this->logger = $logger;
        // 首次初始化
        if (!file_exists($this->dataFile)) {
            $this->save($defaults);
        }
    }

    /**
     * 获取所有定时任务
     */
    public function getAll(): array
    {
        if (!file_exists($this->dataFile)) return $this->defaults;

        $data = json_decode(file_get_contents($this->dataFile), true);
        return is_array($data) ? $data : $this->defaults;
    }

    /**
     * 保存定时任务
     */
    public function save(array $tasks): void
    {
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($this->dataFile, json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 检查当前时间是否有待执行的任务
     * 返回 ['tasks' => [...], 'log' => [...]]
     */
    public function tick(): array
    {
        $now    = date('H:i');
        $today  = date('Y-m-d');
        $tasks  = $this->getAll();
        $toRun  = [];
        $log    = [];

        $log[] = "[{$now}] 开始载入任务";

        foreach ($tasks as &$task) {
            $taskName = ($task['hour'] ?? $task['time']) . '点';
            if ($task['time'] !== $now) {
                $log[] = "[{$now}] 【{$taskName}】...条件检查 false";
                continue;
            }
            // 防重复：记录上次执行日期
            if (($task['last_run'] ?? '') === $today) {
                $log[] = "[{$now}] 【{$taskName}】...条件检查 false (今日已执行)";
                continue;
            }

            $log[] = "[{$now}] 【{$taskName}】...条件检查 true";
            $task['last_run'] = $today;
            $toRun[] = $task;
        }

        if (!empty($toRun)) {
            $this->save($tasks);
        }

        $log[] = "[{$now}] 全部任务处理完成";

        return ['tasks' => $toRun, 'log' => $log];
    }
}
