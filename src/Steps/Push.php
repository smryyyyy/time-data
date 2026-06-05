<?php
namespace App\Steps;

class Push
{
    private array $config;
    private \App\Logger $logger;

    public function __construct(array $config, \App\Logger $logger, $unused = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(string $mergedFile, array $pngFiles, int $hour): void
    {
        $hourCfg = $this->config['hours'][$hour] ?? null;
        if (!$hourCfg) throw new \RuntimeException("时间点 {$hour} 未配置");
        $exports = $hourCfg['exports'] ?? [];
        if (empty($exports)) throw new \RuntimeException("时间点 {$hour} 没有配置导出 webhook");
        $feishuCfg = $this->config['feishu'];

        // 1. 上传图片到飞书 → image_key（直接 curl，绕过 Feishu 类）
        $this->logger->info("上传图片到飞书...");
        $imageKeys = [];
        $uploadFailures = 0;
        foreach ($pngFiles as $name => $path) {
            try {
                $imageKeys[$name] = $this->curlUploadImage($path, $feishuCfg);
                $this->logger->info("  {$name} → image_key OK");
            } catch (\Throwable $e) {
                $uploadFailures++;
                $this->logger->error("上传失败 {$name}: " . $e->getMessage());
            }
        }

        // 如果有上传失败，抛异常触发告警（但已成功的图片继续推送）
        if ($uploadFailures > 0) {
            $msg = "上传失败 {$uploadFailures}/" . count($pngFiles) . " 张图片";
            $this->logger->error($msg);
            throw new \RuntimeException($msg);
        }

        // 2. 读文字
        require_once ROOT . '/src/xlsx_reader.php';
        $texts = readExportSheetText($mergedFile, $hourCfg);

        // 3. 推送
        foreach ($exports as $sheetName => $cfg) {
            $webhook = is_string($cfg) ? $cfg : ($cfg['webhook'] ?? '');
            if (empty($webhook)) continue;
            $this->logger->info("推送: {$sheetName}");

            $ranges = is_array($cfg) ? ($cfg['cell_ranges'] ?? []) : [];
            foreach ($ranges as $idx => $range) {
                $imgName = $sheetName . '_' . $idx . '.png';
                if (isset($imageKeys[$imgName])) {
                    try {
                        $this->curlSendMessage($webhook, json_encode(['msg_type' => 'image', 'content' => json_encode(['image_key' => $imageKeys[$imgName]])]));
                        $this->logger->info("  📷 {$imgName} ({$range})");
                    } catch (\Throwable $e) {
                        $this->logger->error("  图片失败 {$imgName}: " . $e->getMessage());
                    }
                }
            }

            if (!empty($texts[$sheetName])) {
                usleep(500000);
                try {
                    $this->curlSendMessage($webhook, json_encode(['msg_type' => 'text', 'content' => json_encode(['text' => $texts[$sheetName]], JSON_UNESCAPED_UNICODE)], JSON_UNESCAPED_UNICODE));
                    $this->logger->info("  📝 文字");
                } catch (\Throwable $e) {
                    $this->logger->error("  文字失败: " . $e->getMessage());
                }
            }
        }
    }

    private function curlSendMessage(string $webhook, string $jsonPayload): array
    {
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $jsonPayload,
        ]);
        $rb = curl_exec($ch);
        $hc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($hc !== 200) {
            throw new \RuntimeException("推送失败: HTTP={$hc} {$rb}");
        }
        file_put_contents('/tmp/push_debug.log', date('H:i:s')." URL=".$webhook."\nPAYLOAD=".$jsonPayload."\nHTTP={$hc} RESP={$rb}\n\n", FILE_APPEND);
        return json_decode($rb, true) ?: [];
    }

    private function getToken(array $feishuCfg): string
    {
        $ch = curl_init('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal');
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['app_id' => $feishuCfg['app_id'], 'app_secret' => $feishuCfg['app_secret']]),
        ]);
        $rb = curl_exec($ch);
        curl_close($ch);
        $r = json_decode($rb, true);
        if (empty($r['tenant_access_token'])) throw new \RuntimeException('获取 token 失败');
        return $r['tenant_access_token'];
    }

    private function curlUploadImage(string $filePath, array $feishuCfg): string
    {
        $token = $this->getToken($feishuCfg);
        $boundary = '----B' . bin2hex(random_bytes(8));
        $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"image_type\"\r\n\r\nmessage\r\n";
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"image\"; filename=\"img.png\"\r\nContent-Type: image/png\r\n\r\n";
        $body .= file_get_contents($filePath);
        $body .= "\r\n--" . $boundary . "--\r\n";

        $ch = curl_init('https://open.feishu.cn/open-apis/im/v1/images');
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $rb = curl_exec($ch);
        $hc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $r = json_decode($rb, true);
        if ($hc !== 200 || ($r['code'] ?? -1) !== 0) {
            throw new \RuntimeException("上传图片失败: " . json_encode($r));
        }
        return $r['data']['image_key'];
    }
}
