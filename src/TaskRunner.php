<?php
namespace App;

/**
 * 任务编排器 — 按时间点执行 4 步，任一步失败即告警
 */
class TaskRunner
{
    private array $config;
    private Logger $logger;
    private Services\Feishu $feishu;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->feishu = new Services\Feishu();
    }

    /**
     * 执行单个时间点的完整流程
     * @param string $date  Y-m-d
     * @param int    $hour  0-23
     * @return array {success: bool, message: string}
     */
    public function run(string $date, int $hour): array
    {
        set_time_limit(0);
        $hourCfg = $this->config['hours'][$hour] ?? null;
        if (!$hourCfg || empty($hourCfg['enabled'])) {
            return ['success' => false, 'message' => "时间点 {$hour} 未启用"];
        }

        $log      = $this->logger;
        $config   = $this->config;
        $prefix   = $hourCfg['data_prefix'];
        $template = $hourCfg['template'];

        if (empty($template)) {
            $err = "时间点 {$hour} 未配置模板";
            $log->error($err);
            $this->sendAlert($date, $hour, '初始化', $err);
            return ['success' => false, 'message' => $err];
        }

        $tmplFile = $config['template_dir'] . '/' . $template;
        if (!file_exists($tmplFile)) {
            $err = "模板文件不存在: {$tmplFile}";
            $log->error($err);
            $this->sendAlert($date, $hour, '初始化', $err);
            return ['success' => false, 'message' => $err];
        }

        // 工作目录
        $workDir = $config['output_dir'] . '/' . $date . '/' . $hour;
        if (!is_dir($workDir)) mkdir($workDir, 0755, true);

        // ── 步骤 1：下载 ──
        $log->step(1, "[{$hour}点] 开始下载数据");
        try {
            $step1 = new Steps\Download($config, $log);
            $step1->execute($date, $hour);
            $log->step(1, "[{$hour}点] 下载完成");
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            $log->step(1, "[{$hour}点] 下载失败: {$err}");
            $this->sendAlert($date, $hour, '下载数据', $err);
            return ['success' => false, 'message' => "步骤1失败: {$err}"];
        }

        // ── 步骤 2：合并（ZipArchive直写+LO重算，先行，方便截图取最新数据）──
        $mergedFile = $workDir . '/' . basename($template);
        copy($tmplFile, $mergedFile);
        $log->step(2, "[{$hour}点] 开始合并数据");
        try {
            $step2 = new Steps\Merge($config, $log);
            $step2->execute($date, $hour, $mergedFile);
            $log->step(2, "[{$hour}点] 合并完成 → {$mergedFile}");
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            $log->step(2, "[{$hour}点] 合并失败: {$err}");
            $this->sendAlert($date, $hour, '合并数据', $err);
            return ['success' => false, 'message' => "步骤2失败: {$err}"];
        }

        // ── 步骤 3：截图（从合并后文件按cell_ranges导出PNG）──
        $pngFiles = [];
        $log->step(3, "[{$hour}点] 开始截图导出");
        $exports = $hourCfg['exports'] ?? [];
        if (!empty($exports)) {
            $pyScript = ROOT . '/scripts/screenshot_range.py';
            foreach ($exports as $sheetName => $cfg) {
                // Support both old (string webhook) and new (array with cell_ranges) formats
                if (is_string($cfg)) {
                    // Old format: just webhook, no cell_ranges → skip screenshot
                    continue;
                }
                $ranges = $cfg['cell_ranges'] ?? [];
                if (empty($ranges)) continue;
                
                foreach ($ranges as $idx => $range) {
                    // Parse 'sheet!range' or just 'range' (defaults to current sheet)
                    $srcSheet = $sheetName;
                    $srcRange = $range;
                    if (str_contains($range, '!')) {
                        [$srcSheet, $srcRange] = explode('!', $range, 2);
                    }
                    $imgName = $sheetName . '_' . $idx . '.png';
                    $imgPath = $workDir . '/' . $imgName;
                    $cmd = sprintf('python3 %s %s %s %s %s 2>&1',
                        escapeshellarg($pyScript),
                        escapeshellarg($mergedFile),
                        escapeshellarg($srcSheet),
                        escapeshellarg($srcRange),
                        escapeshellarg($imgPath)
                    );
                    exec($cmd, $output, $code);
                    if ($code === 0 && file_exists($imgPath)) {
                        $pngFiles[$imgName] = $imgPath;
                        $log->info("  📸 {$sheetName}[{$idx}] {$range} → {$imgName}");
                    } else {
                        $log->error("  ✗ {$sheetName}[{$idx}] 截图失败: " . implode("\n", $output));
                    }
                }
            }
        }
        $log->step(3, "[{$hour}点] 截图完成，共 " . count($pngFiles) . " 张");

        // ── 步骤 4：推送 ──
        $log->step(4, "[{$hour}点] 开始推送飞书");
        try {
            $step4 = new Steps\Push($config, $log, $this->feishu);
            $step4->execute($mergedFile, $pngFiles, $hour);
            $log->step(4, "[{$hour}点] 推送完成 ✓");
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            $log->step(4, "[{$hour}点] 推送失败: {$err}");
            $this->sendAlert($date, $hour, '推送飞书', $err);
            return ['success' => false, 'message' => "步骤4失败: {$err}"];
        }

        $log->info("════ [{$hour}点] {$date} 全部完成 ════");
        return ['success' => true, 'message' => '全部完成'];
    }

    /**
     * 发送告警到全局 alert_webhook
     */
    private function sendAlert(string $date, int $hour, string $step, string $error): void
    {
        $webhook = $this->config['alert_webhook'] ?? '';
        if (empty($webhook)) {
            $this->logger->warn("告警 webhook 未配置，跳过告警推送");
            return;
        }

        try {
            $time = date('H:i:s');
            $this->feishu->sendAlert($webhook, [
                'title'    => "时服推送失败 [{$hour}点]",
                'date'     => $date,
                'hour'     => (string)$hour,
                'time'     => $time,
                'step'     => $step,
                'error'    => $error,
                'link'     => ($this->config['base_url'] ?? '') . '/logs',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("发送告警失败: " . $e->getMessage());
        }
    }
}
