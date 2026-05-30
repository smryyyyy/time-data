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

    public function __construct(string $dataDir, array $defaults)
    {
        $this->dataFile = $dataDir . '/schedule.json';
        $this->defaults = $defaults;
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
     * 返回要执行的任务数组 [{time, template}]
     */
    public function tick(): array
    {
        $now    = date('H:i');
        $today  = date('Y-m-d');
        $tasks  = $this->getAll();
        $toRun  = [];

        foreach ($tasks as &$task) {
            if ($task['time'] !== $now) continue;
            // 防重复：记录上次执行日期
            if (($task['last_run'] ?? '') === $today) continue;

            $task['last_run'] = $today;
            $toRun[] = $task;
        }

        if (!empty($toRun)) {
            $this->save($tasks);
        }

        return $toRun;
    }
}
