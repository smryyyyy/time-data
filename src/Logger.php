<?php
namespace App;

/**
 * 结构化日志
 * 每天一个文件：logs/2026-05-23.log
 */
class Logger
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    public function info(string $msg): void
    {
        $this->write('INFO', $msg);
    }

    public function warn(string $msg): void
    {
        $this->write('WARN', $msg);
    }

    public function error(string $msg): void
    {
        $this->write('ERROR', $msg);
    }

    public function step(int $step, string $msg): void
    {
        $this->write("STEP{$step}", $msg);
    }

    private function write(string $level, string $msg): void
    {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $line = "[{$date} {$time}] [{$level}] {$msg}" . PHP_EOL;
        file_put_contents($this->dir . "/{$date}.log", $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 读取指定日期的日志
     */
    public function get(string $date): string
    {
        $path = $this->dir . "/{$date}.log";
        if (!file_exists($path)) return '';
        return file_get_contents($path);
    }

    /**
     * 列出所有日志日期
     */
    public function listDates(): array
    {
        $files = glob($this->dir . '/*.log');
        $dates = [];
        foreach ($files as $f) {
            $dates[] = basename($f, '.log');
        }
        rsort($dates);
        return $dates;
    }
}
