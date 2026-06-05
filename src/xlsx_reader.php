<?php

/**
 * 从 xlsx 直接读取导出 sheet 的文案文字（ZipArchive + XML解析，省内存）
 * @param string $xlsxFile xlsx 文件路径
 * @param array $hourCfg 时间点配置，用于获取每个 sheet 的 text_row_start/text_row_end
 */
function readExportSheetText(string $xlsxFile, array $hourCfg = []): array
{
    $texts = [];
    $exports = $hourCfg['exports'] ?? [];
    $zip = new ZipArchive();
    if ($zip->open($xlsxFile) !== true) return $texts;

    // 1. 读 sharedStrings.xml
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    $sharedStrings = [];
    if ($ssXml) {
        preg_match_all('/<si[^>]*>.*?<\/si>/s', $ssXml, $siMatches);
        foreach ($siMatches[0] as $si) {
            if (preg_match('/<t[^>]*>(.*?)<\/t>/s', $si, $tm)) {
                $sharedStrings[] = $tm[1];
            } else {
                $sharedStrings[] = '';
            }
        }
    }

    // 2. read workbook.xml → sheet names + rId
    $wbXml = $zip->getFromName('xl/workbook.xml');
    preg_match_all('/<sheet[^>]*name="([^"]+)"[^>]*r:id="([^"]+)"/', $wbXml, $sheetMatches);
    $sheetMap = []; // rId => name
    for ($i = 0; $i < count($sheetMatches[0]); $i++) {
        $sheetMap[$sheetMatches[2][$i]] = $sheetMatches[1][$i];
    }

    // 3. read workbook.xml.rels → rId => target
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    preg_match_all('/<Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"/', $relsXml, $relMatches);
    $targetMap = []; // rId => target path
    for ($i = 0; $i < count($relMatches[0]); $i++) {
        $targetMap[$relMatches[1][$i]] = $relMatches[2][$i];
    }

    // 4. 遍历每个 sheet，只处理导出 sheet
    foreach ($sheetMap as $rId => $sheetName) {
        if (in_array($sheetName, ['数据', '今日', '昨日'])) continue;
        if (str_starts_with($sheetName, 'microsoft.com:')) continue;

        $target = $targetMap[$rId] ?? '';
        if (!$target) continue;

        $sheetXml = $zip->getFromName('xl/' . $target);
        if (!$sheetXml) continue;

        // 使用配置的行数范围，默认 50-60
        $sheetCfg = $exports[$sheetName] ?? [];
        $rowStart = is_array($sheetCfg) ? ($sheetCfg['text_row_start'] ?? 50) : 50;
        $rowEnd   = is_array($sheetCfg) ? ($sheetCfg['text_row_end'] ?? 60) : 60;

        $lines = [];
        for ($row = $rowStart; $row <= $rowEnd; $row++) {
            $cellRef = 'A' . $row;
            $pattern = '/<c[^>]*r="' . $cellRef . '"[^>]*>(.*?)<\/c>/s';
            if (!preg_match($pattern, $sheetXml, $cm)) continue;

            $cellContent = $cm[1];

            // 先取 <v> 缓存值
            $v = null;
            if (preg_match('/<v>(.*?)<\/v>/s', $cellContent, $vm)) {
                $v = $vm[1];
            }

            // 判断 cell 类型
            if (preg_match('/<f[^>]*>/', $cellContent)) {
                // 公式 → 用缓存值
                if ($v !== null) {
                    $val = $v;
                } else {
                    continue;
                }
            } elseif (preg_match('/<c[^>]*t="s"/', $cm[0])) {
                // 共享字符串
                $val = $sharedStrings[(int)$v] ?? '';
            } elseif (preg_match('/<c[^>]*t="inlineStr"/', $cm[0])) {
                // 内联字符串
                if (preg_match('/<t[^>]*>(.*?)<\/t>/s', $cellContent, $tm)) {
                    $val = $tm[1];
                } else {
                    $val = '';
                }
            } else {
                // 普通值
                $val = $v;
            }

            if ($val !== '' && $val !== null) {
                $lines[] = $val;
            }
        }
        $texts[$sheetName] = implode("\n", $lines);
    }

    $zip->close();
    return $texts;
}
