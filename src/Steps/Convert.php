<?php
namespace App\Steps;

/**
 * 步骤 3：提取 EMF 图片并转 PNG
 * 核心逻辑：从 xlsx（zip）中提取 EMF → LibreOffice 转 PDF → ImageMagick 转 PNG
 */
class Convert
{
    private array $config;
    private \App\Logger $logger;

    public function __construct(array $config, \App\Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $xlsxFile 合并后的 xlsx 文件路径
     * @param string $outDir   输出目录
     * @return array 生成的 PNG 文件路径映射 ['image1' => '/path/to/image1.png', ...]
     * @throws \RuntimeException
     */
    public function execute(string $xlsxFile, string $outDir): array
    {
        if (!file_exists($xlsxFile)) {
            throw new \RuntimeException("xlsx 文件不存在: {$xlsxFile}");
        }

        // 1. 提取 EMF 图片
        $emfDir = $outDir . '/emf';
        if (!is_dir($emfDir)) mkdir($emfDir, 0755, true);

        $emfFiles = $this->extractEmfFromZip($xlsxFile, $emfDir);

        if (empty($emfFiles)) {
            throw new \RuntimeException('未找到 EMF 图片');
        }

        $this->logger->info("提取到 " . count($emfFiles) . " 个 EMF 文件");

        // 2. 逐个转换
        $pngFiles = [];
        foreach ($emfFiles as $name => $emfPath) {
            $pngPath = $this->convertSingle($emfPath, $outDir, $name);
            $pngFiles[$name] = $pngPath;
        }

        $this->logger->info("PNG 转换完成，共 " . count($pngFiles) . " 张");

        return $pngFiles;
    }

    /**
     * 从 xlsx（zip 格式）中提取 EMF 图片
     */
    private function extractEmfFromZip(string $xlsxFile, string $outDir): array
    {
        $found   = [];
        $targets = $this->config['images'] ?? null;
        // null = 全转，不再过滤

        // 用 PHP ZipArchive 提取 xl/media/ 下的 emf 文件
        $zip = new \ZipArchive();
        if ($zip->open($xlsxFile) !== true) {
            throw new \RuntimeException("无法打开 xlsx 文件（不是有效的 zip）");
        }

        // 过滤：仅取 drawingX 的 EMF，跳过 vmlDrawingX（后者是重复图）
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!str_contains($entry, 'drawings/_rels/drawing') || str_contains($entry, 'vmlDrawing')) continue;
            $relsXml = $zip->getFromIndex($i);
            preg_match_all('/Target="\.\.\/media\/(image\d+)\.emf"/', $relsXml, $im);
            foreach ($im[1] as $imgName) {
                $validNames[$imgName] = true;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            // 搜所有 .emf 文件，不限目录（PhpSpreadsheet 可能改变路径）
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if ($ext !== 'emf') continue;

            $baseName = pathinfo($entry, PATHINFO_FILENAME);
            if ($targets !== null && !in_array($baseName, $targets)) continue;
            // 跳过 vmlDrawing 的重复图
            if (!isset($validNames[$baseName])) continue;

            $destPath = $outDir . '/' . basename($entry);
            $content = $zip->getFromIndex($i);
            file_put_contents($destPath, $content);
            $found[$baseName] = $destPath;
        }

        $zip->close();
        return $found;
    }

    /**
     * 单个 EMF → PNG 转换
     * EMF → PDF（LibreOffice）→ PNG（ImageMagick）
     */
    private function convertSingle(string $emfPath, string $outDir, string $name): string
    {
        $lo  = $this->config['libreoffice'] ?? '/usr/bin/libreoffice';
        $img = $this->config['imagemagick'] ?? '/usr/bin/convert';

        // EMF → PDF
        $pdfPath = $outDir . '/' . $name . '.pdf';
        $cmd = sprintf(
            'HOME=/tmp %s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellcmd($lo),
            escapeshellarg($outDir),
            escapeshellarg($emfPath)
        );
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new \RuntimeException("LibreOffice EMF→PDF 失败: " . implode("\n", $output));
        }

        // 检查产物
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF 未生成: {$pdfPath}");
        }

        // PDF → PNG（高清，2400px 宽）
        $pngPath = $outDir . '/' . $name . '.png';
        $cmd2 = sprintf(
            '%s -density 600 %s -resize 2400x %s 2>&1',
            escapeshellcmd($img),
            escapeshellarg($pdfPath),
            escapeshellarg($pngPath)
        );
        exec($cmd2, $output2, $code2);
        if ($code2 !== 0) {
            throw new \RuntimeException("ImageMagick PDF→PNG 失败: " . implode("\n", $output2));
        }

        // 裁剪白边（Pillow + numpy，同 Mac 版算法）
        $trimScript = ROOT . '/scripts/trim_white.py';
        $cmd3 = sprintf(
            'python3 %s %s 2>&1',
            escapeshellarg($trimScript),
            escapeshellarg($pngPath)
        );
        exec($cmd3, $output3, $code3);

        if (!file_exists($pngPath)) {
            throw new \RuntimeException("PNG 未生成: {$pngPath}");
        }

        return $pngPath;
    }
}
