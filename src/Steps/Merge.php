<?php
namespace App\Steps;

/**
 * 步骤 3（原步骤2）：ZipArchive 直写 今日/昨日，保护公式和图表
 */
class Merge
{
    private array $config;
    private \App\Logger $logger;

    public function __construct(array $config, \App\Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(string $date, int $hour, string $mergedFile): void
    {
        $hourCfg = $this->config['hours'][$hour] ?? null;
        if (!$hourCfg) throw new \RuntimeException("时间点 {$hour} 未配置");

        $prefix    = $hourCfg['data_prefix'];
        $range     = $hourCfg['copy_range'];
        $dataDir   = $this->config['data_dir'];
        $yesterday = yesterday($date);

        $todayFile = sourceFilePath($dataDir, $date, $prefix);
        $yestFile  = sourceFilePath($dataDir, $yesterday, $prefix);

        if (!file_exists($todayFile)) throw new \RuntimeException("今日源文件不存在: {$todayFile}");
        if (!file_exists($yestFile)) throw new \RuntimeException("昨日源文件不存在: {$yestFile}");
        if (!file_exists($mergedFile)) throw new \RuntimeException("模板文件不存在: {$mergedFile}");

        $cols = expandColumnRange($range);

        // 读取源数据
        $this->logger->info("读取源数据...");
        $todayData = $this->loadSourceData($todayFile, $cols);
        $yestData  = $this->loadSourceData($yestFile, $cols);

        // 直写 今日/昨日
        $this->logger->info("写入今日 sheet ({$date})...");
        $this->writeSheetData($mergedFile, '今日', $todayData, $cols);

        $this->logger->info("写入昨日 sheet ({$yesterday})...");
        $this->writeSheetData($mergedFile, '昨日', $yestData, $cols);

        $this->logger->info("合并完成: {$mergedFile} (" . formatSize(filesize($mergedFile)) . ")");

        // 步骤 4：LibreOffice UNO 重算所有公式缓存值 + 渲染图表为 PNG
        $this->logger->info("重算公式并渲染图表 (LibreOffice UNO)...");
        $tmpRecalc = $mergedFile . '.recalc.tmp';
        $pyScript = __DIR__ . '/../../scripts/recalc_xlsx.py';
        $renderDir = dirname($mergedFile);  // render PNGs alongside merged file
        $cmd = sprintf('python3 %s %s %s %s 2>&1',
            escapeshellarg($pyScript),
            escapeshellarg($mergedFile),
            escapeshellarg($tmpRecalc),
            escapeshellarg($renderDir)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->logger->error("UNO 重算失败: " . implode("\n", $output));
            throw new \RuntimeException("公式重算失败 (exit={$exitCode})");
        }
        // 用重算后的文件替换原文件
        if (!rename($tmpRecalc, $mergedFile)) {
            throw new \RuntimeException("替换重算文件失败");
        }
        $this->logger->info("公式重算完成: {$mergedFile} (" . formatSize(filesize($mergedFile)) . ")");
    }

    private function loadSourceData(string $xlsxFile, array $cols): array
    {
        $colStr = implode(',', $cols);
        $pyScript = ROOT . '/scripts/read_source_data.py';
        $cmd = sprintf('python3 %s %s "%s"',
            escapeshellarg($pyScript),
            escapeshellarg($xlsxFile),
            $colStr);

        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!$proc) {
            throw new \RuntimeException("无法启动 Python 进程");
        }
        fclose($pipes[0]); // close stdin

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $code = proc_close($proc);

        if ($code !== 0) {
            throw new \RuntimeException("读取源数据失败: " . $stderr);
        }

        // Remove warning line (starts with "UserWarning" or empty) from stdout
        $lines = explode("\n", $stdout);
        $json = '';
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l === '' || str_starts_with($l, 'UserWarning') || str_starts_with($l, '  warn')) continue;
            $json .= $l;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function writeSheetData(string $xlsxFile, string $sheetName, array $data, array $cols): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsxFile) !== true) throw new \RuntimeException("无法打开 xlsx");

        // 找 sheet 的 xml 文件
        $wbXml = $zip->getFromName('xl/workbook.xml');
        preg_match('/<sheet[^>]*name="' . preg_quote($sheetName, '/') . '"[^>]*r:id="([^"]+)"/', $wbXml, $sm);
        if (empty($sm[1])) throw new \RuntimeException("Sheet '{$sheetName}' not found");
        unset($wbXml);

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        preg_match('/<Relationship[^>]*Id="' . preg_quote($sm[1], '/') . '"[^>]*Target="([^"]+)"/', $relsXml, $rm);
        if (empty($rm[1])) throw new \RuntimeException("Sheet rel not found");
        unset($relsXml);

        $sheetPath = 'xl/' . $rm[1];

        // 生成新行（用数组避免字符串拼接碎片）
        $rows = [];
        for ($i = 0; $i < count($data); $i++) {
            $rowNum = $i + 1;
            $cells = [];
            foreach ($cols as $col) {
                $val = $data[$i][$col] ?? '';
                if ($val === null) $val = '';
                $cellRef = $col . $rowNum;
                if (is_numeric($val)) {
                    $cells[] = '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
                } else {
                    $cells[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$val, ENT_XML1) . '</t></is></c>';
                }
            }
            $rows[] = '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
            unset($cells);
        }
        $newRows = '<sheetData>' . implode('', $rows) . '</sheetData>';
        unset($rows);

        $sheetXml = $zip->getFromName($sheetPath);
        if (!$sheetXml) throw new \RuntimeException("Sheet XML not found");
        $sheetXml = preg_replace('/<sheetData>.*?<\/sheetData>/s', $newRows, $sheetXml);
        $zip->addFromString($sheetPath, $sheetXml);
        unset($sheetXml, $newRows);
        $zip->close();
    }
}
