<?php

/**
 * 从 xlsx 直接读取导出 sheet 的 A50:A60 文字（ZipArchive + XML解析，省内存）
 */
function readExportSheetText(string $xlsxFile): array
{
    $texts = [];
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

        // 找 sheet 的列定义（可能影响 cell 引用）
        // 直接用正则匹配 A50-A60
        $lines = [];
        for ($row = 50; $row <= 60; $row++) {
            $col = 'A';
            $cellRef = $col . $row;
            // 匹配单元格: <c r="A50" ...>  (可能有 s="N" t="str" 等属性)
            $pattern = '/<c[^>]*r="' . $cellRef . '"[^>]*>(.*?)<\/c>/s';
            if (preg_match($pattern, $sheetXml, $cm)) {
                $cellContent = $cm[1];
                // 检查是否公式 + 缓存值
                if (preg_match('/<v>(.*?)<\/v>/s', $cellContent, $vm)) {
                    $v = $vm[1];
                // 检查cell类型：先判公式
                if (preg_match('/<f[^>]*>/', $cellContent)) {
                    // formula - use cached value <v>
                    if (preg_match('/<v>(.*?)<\/v>/s', $cellContent, $vm)) {
                        $cachedVal = $vm[1];
                        // cached value of string formula is plain text in <v>
                        $val = $cachedVal;
                    } else {
                        continue; // no cached value
                    }
                } elseif (preg_match('/<c[^>]*t="s"/', $cm[0])) {
                    // shared string index
                    $idx = (int)$v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif (preg_match('/<c[^>]*t="inlineStr"/', $cm[0])) {
                    // inline string
                    if (preg_match('/<t[^>]*>(.*?)<\/t>/s', $cellContent, $tm)) {
                        $val = $tm[1];
                    } else {
                        $val = '';
                    }
                } else {
                    // plain value
                    $val = $v;
                }

                    if ($val !== '' && $val !== null) {
                        $lines[] = $val;
                    }
                }
            }
        }
        $texts[$sheetName] = implode("\n", $lines);
    }

    $zip->close();
    return $texts;
}
