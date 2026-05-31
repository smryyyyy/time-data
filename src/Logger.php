<?php
namespace App;

/**
 * 结构化日志
 * 每天一个文件：logs/2026-05-23.log
 * ERROR/WARN 自动推送到告警 Webhook
 */
class Logger
{
    private string $dir;
    private ?string $alertWebhook = null;
    private ?Services\Feishu $feishu = null;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    /**
     * 设置告警推送（在 App 初始化后调用）
     */
    public function setAlert(?string $webhook, ?Services\Feishu $feishu = null): void
    {
        $this->alertWebhook = $webhook;
        $this->feishu = $feishu;
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
        @chmod($this->dir . "/{$date}.log", 0666);

        // ERROR / WARN 自动推送告警
        if ($this->alertWebhook && ($level === 'ERROR' || $level === 'WARN')) {
            $this->sendAlert($level, $msg, $date, $time);
        }
    }

    private function sendAlert(string $level, string $msg, string $date, string $time): void
    {
        $ch = curl_init($this->alertWebhook);
        $text = "[{$level}] {$date} {$time}\n{$msg}";
        $payload = json_encode([
            'msg_type' => 'text',
            'content'  => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
        ], JSON_UNESCAPED_UNICODE);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
        ]);
        curl_exec($ch);
        curl_close($ch);
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
