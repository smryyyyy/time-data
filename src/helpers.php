<?php
/**
 * 全局辅助函数
 * 由 App.php 在 autoload 之后 require_once 加载
 */

/**
 * 渲染视图模板
 */
function render(string $view, array $data = [], bool $useLayout = true): void
{
    $viewFile = ROOT . '/views/' . $view . '.php';
    if (!file_exists($viewFile)) {
        http_response_code(500);
        die("视图不存在: {$viewFile}");
    }
    extract($data);
    if ($useLayout && isset($GLOBALS['hermes_config'])) {
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require ROOT . '/views/layout.php';
    } else {
        require $viewFile;
    }
}

/** 返回 JSON 错误响应 */
function jsonError(string $message, int $code = 500): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 格式化文件大小 */
function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

/** HTML 转义（防 XSS） */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/** 获取启用的时间点列表（按小时排序） */
function enabledHours(array $hours): array
{
    $enabled = [];
    foreach ($hours as $h => $cfg) {
        if (!empty($cfg['enabled'])) {
            $enabled[] = $h;
        }
    }
    sort($enabled);
    return $enabled;
}

/** 获取源数据文件路径 data/{date}/{prefix}({date}).xlsx */
function sourceFilePath(string $dataDir, string $date, string $prefix): string
{
    return $dataDir . '/' . $date . '/' . $prefix . '(' . $date . ').xlsx';
}

/** 获取模板文件路径 */
function templatePath(string $templateDir, string $filename): string
{
    return $templateDir . '/' . $filename;
}

/** 获取当前用户 id（多用户预留） */
function currentUser(): string
{
    return $_SESSION['hermes_user'] ?? 'admin';
}

/** 计算给定日期前一天 */
function yesterday(string $date): string
{
    return date('Y-m-d', strtotime($date . ' -1 day'));
}

/** 解析 copy_range 配置 ['A', 'K'] → 列数组 ['A','B',...'K'] */
function expandColumnRange(array $range): array
{
    $start = $range[0];
    $end   = $range[1];
    $cols  = [];
    $c = $start;
    while (true) {
        $cols[] = $c;
        if ($c === $end) break;
        $c++;
    }
    return $cols;
}

/** 从模板中扫描导出 sheet 名 */
function scanExportSheets(string $xlsxFile): array
{
    $zip = new ZipArchive();
    if ($zip->open($xlsxFile) !== true) return [];

    $wbXml = $zip->getFromName('xl/workbook.xml');
    $zip->close();

    if (!$wbXml) return [];

    preg_match_all('/name="([^"]+)"/', $wbXml, $m);
    $allSheets = $m[1] ?? [];

    $exclude = ['数据', '今日', '昨日'];
    $exports = [];
    foreach ($allSheets as $name) {
        if (in_array($name, $exclude)) continue;
        if (str_starts_with($name, 'microsoft.com:')) continue;
        $exports[] = $name;
    }
    return $exports;
}
