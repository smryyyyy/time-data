<?php
namespace App\Controllers;

class DashboardController
{
    public function index(): void
    {
        $config = $GLOBALS['hermes_config'];
        $hours  = $config['hours'];
        $today  = date('Y-m-d');
        $logText = '';
        $logFile = ($config['log_dir'] ?? ROOT . '/logs') . '/' . $today . '.log';
        if (file_exists($logFile)) {
            $logText = file_get_contents($logFile);
        }
        $statuses = [];
        foreach ($hours as $h => $cfg) {
            if (empty($cfg['enabled'])) continue;
            $statuses[$h] = 'waiting';
            $runLog = ($config['output_dir'] ?? ROOT . '/tmp') . "/{$today}/{$h}/.status";
            if (file_exists($runLog)) {
                $statuses[$h] = trim(file_get_contents($runLog));
            }
        }

        // 历史数据存储容量
        $storage = [];
        $dirs = [
            '数据源' => $config['data_dir'],
            '临时文件' => $config['output_dir'] ?? ROOT . '/tmp',
            '日志'  => $config['log_dir'],
        ];
        foreach ($dirs as $label => $dir) {
            if (!is_dir($dir)) continue;
            $size = 0;
            $files = 0;
            foreach (glob($dir . '/20*', GLOB_ONLYDIR) as $sub) {
                $size += dirSize($sub);
                $files++;
            }
            // 日志目录是 .log 文件，不是子目录
            if ($label === '日志') {
                $size = 0;
                $files = 0;
                foreach (glob($dir . '/20*.log') as $f) {
                    $size += filesize($f);
                    $files++;
                }
            }
            $storage[$label] = ['size' => $size, 'files' => $files];
        }
        $storage['总计'] = [
            'size' => array_sum(array_column($storage, 'size')),
            'files' => array_sum(array_column($storage, 'files')),
        ];

        render('dashboard', compact('hours', 'today', 'logText', 'statuses', 'storage'));
    }

    public function run(): void
    {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-cache');
        set_time_limit(0);  // 防止长时间执行超时

        $config = $GLOBALS['hermes_config'];
        $date   = $_POST['date'] ?? date('Y-m-d');
        $hour   = (int)($_POST['hour'] ?? 10);
        if (!isset($config['hours'][$hour])) { echo "错误: 无效的时间点\n"; return; }

        $hourCfg  = $config['hours'][$hour];
        $tmplFile = $config['template_dir'] . '/' . $hourCfg['template'];
        $workDir  = $config['output_dir'] . '/' . $date . '/' . $hour;
        if (!is_dir($workDir)) mkdir($workDir, 0755, true);
        $mergedFile = $workDir . '/' . basename($hourCfg['template']);
        $log = $GLOBALS['hermes_logger'];

        echo "[{$hour}点] 日期: {$date}\n━━━━━━━━━━━━━━━━━━━━━━━\n";

        // 1. 下载（今日+昨日）
        $prefix = $hourCfg['data_prefix'];
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $todayFile = sourceFilePath($config['data_dir'], $date, $prefix);
        $yestFile = sourceFilePath($config['data_dir'], $yesterday, $prefix);
        
        echo "⏳ 步骤 1/4 下载数据...\n";
        
        if (!file_exists($todayFile)) {
            $downloadCmd = sprintf('python3 %s %s %s %s %s %s %s 2>&1',
                escapeshellarg(ROOT . '/scripts/download_pzoom.py'),
                escapeshellarg($config['pzoom']['username']),
                escapeshellarg($config['pzoom']['password']),
                escapeshellarg($config['pzoom']['login_url']),
                escapeshellarg($config['pzoom']['overview']),
                escapeshellarg($date),
                escapeshellarg($prefix));
            putenv('PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright');
            $proc = popen($downloadCmd, 'r');
            if (!$proc) { echo "❌ 步骤 1/4 下载启动失败\n"; return; }
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line !== false) {
                    echo $line;
                    $trimmed = trim($line);
                    if (str_contains($trimmed, '错误') || str_contains($trimmed, '失败')) {
                        $log->error($trimmed);
                    } else {
                        $log->info($trimmed);
                    }
                    flush();
                }
            }
            $exitCode = pclose($proc);
            if ($exitCode !== 0) { echo "❌ 步骤 1/4 下载失败 (exit={$exitCode})\n"; $log->error("下载失败 (exit={$exitCode})"); return; }
            // Rename
            $downloaded = $config['data_dir'] . "/{$date}.xlsx";
            $dir = dirname($todayFile);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (file_exists($downloaded)) {
                rename($downloaded, $todayFile);
                $log->info("今日下载完成 → {$todayFile}");
            }
        } else {
            echo "  今日数据已存在，跳过\n";
        }
        
        if (!file_exists($yestFile)) {
            echo "  下载昨日数据...\n";
            $yestCmd = sprintf('python3 %s %s %s %s %s %s %s 2>&1',
                escapeshellarg(ROOT . '/scripts/download_pzoom.py'),
                escapeshellarg($config['pzoom']['username']),
                escapeshellarg($config['pzoom']['password']),
                escapeshellarg($config['pzoom']['login_url']),
                escapeshellarg($config['pzoom']['overview']),
                escapeshellarg($yesterday),
                escapeshellarg($prefix));
            $proc = popen($yestCmd, 'r');
            if (!$proc) { echo "  ❌ 昨日下载启动失败\n"; return; }
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line !== false) { echo $line; $log->info(trim($line)); flush(); }
            }
            $exitCode = pclose($proc);
            if ($exitCode !== 0) { echo "  ❌ 昨日下载失败 (exit={$exitCode})\n"; return; }
            $dlYest = $config['data_dir'] . "/{$yesterday}.xlsx";
            if (file_exists($dlYest)) {
                $ydir = dirname($yestFile);
                if (!is_dir($ydir)) mkdir($ydir, 0755, true);
                rename($dlYest, $yestFile);
                $log->info("昨日下载完成 → {$yestFile}");
            }
        } else {
            echo "  昨日数据已存在，跳过\n";
        }
        echo "✅ 步骤 1/4 下载完成\n"; flush();

        // 2. 合并（ZipArchive直写+LO重算）
        copy($tmplFile, $mergedFile);
        echo "⏳ 步骤 2/4 合并数据...\n"; flush();
        try { (new \App\Steps\Merge($config, $log))->execute($date, $hour, $mergedFile); echo "✅ 步骤 2/4 合并完成\n"; }
        catch (\Throwable $e) { echo "❌ 步骤 2/4 合并失败: {$e->getMessage()}\n"; return; }
        flush();

        // 3. 截图（从合并后文件按cell_ranges导出PNG）
        $pngFiles = [];
        echo "⏳ 步骤 3/4 截图导出...\n"; flush();
        $exports = $hourCfg['exports'] ?? [];
        $log->info("截图: exports count=" . count($exports));
        if (!empty($exports)) {
            $pyScript = ROOT . '/scripts/screenshot_range.py';
            foreach ($exports as $sheetName => $cfg) {
                if (is_string($cfg)) continue;
                $ranges = $cfg['cell_ranges'] ?? [];
                $log->info("截图: sheet={$sheetName} ranges=" . count($ranges));
                if (empty($ranges)) continue;
                foreach ($ranges as $idx => $range) {
                    $srcSheet = $sheetName;
                    $srcRange = $range;
                    if (str_contains($range, '!')) {
                        [$srcSheet, $srcRange] = explode('!', $range, 2);
                    }
                    $imgName = $sheetName . '_' . $idx . '.png';
                    $imgPath = $workDir . '/' . $imgName;
                    $cmd = sprintf('python3 %s %s %s %s %s 2>&1',
                        escapeshellarg($pyScript), escapeshellarg($mergedFile),
                        escapeshellarg($srcSheet), escapeshellarg($srcRange), escapeshellarg($imgPath));
                    $log->info("截图cmd: {$cmd}");
                    exec($cmd, $output, $code);
                    $log->info("截图result: code={$code} out=" . implode('|', $output));
                    if ($code === 0 && file_exists($imgPath)) {
                        $pngFiles[$imgName] = $imgPath;
                    }
                }
            }
        }
        echo "✅ 步骤 3/4 截图完成 (" . count($pngFiles) . " 张)\n";
        flush();

        // 4. 推送
        echo "⏳ 步骤 4/4 推送飞书...\n"; flush();
        try {
            $feishu = new \App\Services\Feishu();
            (new \App\Steps\Push($config, $log, $feishu))->execute($mergedFile, $pngFiles, $hour);
            echo "✅ 步骤 4/4 推送完成\n";
        } catch (\Throwable $e) { echo "❌ 步骤 4/4 推送失败: {$e->getMessage()}\n"; return; }

        echo "━━━━━━━━━━━━━━━━━━━━━━━\n🎉 [{$hour}点] {$date} 全部完成\n";
    }

    /**
     * 手动清理 4 天前的历史数据
     */
    public function cleanup(): void
    {
        $config = $GLOBALS['hermes_config'];
        $logger = $GLOBALS['hermes_logger'];
        $cutoff = strtotime('-4 days');
        $cutoffDate = date('Y-m-d', $cutoff);
        $totalSize = 0;
        $totalFiles = 0;
        $details = [];

        foreach ([$config['data_dir'], $config['output_dir']] as $dir) {
            foreach (glob($dir . '/20*', GLOB_ONLYDIR) as $sub) {
                $dirDate = basename($sub);
                if ($dirDate >= $cutoffDate) continue;
                $size = dirSize($sub);
                removeDir($sub);
                $totalSize += $size;
                $totalFiles++;
                $details[] = basename($dir) . '/' . $dirDate . ' (' . formatSize($size) . ')';
            }
        }
        foreach (glob($config['log_dir'] . '/20*.log') as $file) {
            $fileDate = basename($file, '.log');
            if ($fileDate >= $cutoffDate) continue;
            $size = filesize($file);
            unlink($file);
            $totalSize += $size;
            $totalFiles++;
            $details[] = 'logs/' . basename($file) . ' (' . formatSize($size) . ')';
        }

        $logger->info("手动清理: {$totalFiles}个, 释放 " . formatSize($totalSize));

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "清理完成: {$totalFiles} 个文件/目录, 释放 " . formatSize($totalSize),
            'details' => $details,
            'total_freed' => $totalSize,
        ], JSON_UNESCAPED_UNICODE);
    }
}
