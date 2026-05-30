<?php
namespace App\Services;

class Feishu
{
    private array $tokenCache = [];
    private float $tokenExpire = 0;

    /**
     * 获取飞书 tenant_access_token
     */
    public function getToken(array $feishuCfg): string
    {
        if (time() < $this->tokenExpire) {
            return $this->tokenCache;
        }

        $ch = curl_init('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS     => json_encode([
                'app_id'     => $feishuCfg['app_id'],
                'app_secret' => $feishuCfg['app_secret'],
            ]),
        ]);
        $resp = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($resp['tenant_access_token'])) {
            throw new \RuntimeException('飞书 token 获取失败: ' . ($resp['msg'] ?? 'unknown'));
        }
        $this->tokenExpire = time() + $resp['expire'] - 60;
        $this->tokenCache = $resp['tenant_access_token'];
        return $this->tokenCache;
    }

    /**
     * 上传图片到飞书，返回 image_key
     */
    public function uploadImage(string $filePath, array $feishuCfg): string
    {
        $token = $this->getToken($feishuCfg);

        $boundary = '----Boundary' . bin2hex(random_bytes(8));
        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"image_type\"\r\n\r\n";
        $body .= "message\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"image\"; filename=\"img.png\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($filePath);
        $body .= "\r\n--{$boundary}--\r\n";

        $ch = curl_init('https://open.feishu.cn/open-apis/im/v1/images');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ],
            CURLOPT_POSTFIELDS     => $body,
        ]);
        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resp = json_decode($respBody, true);
        if ($httpCode !== 200 || ($resp['code'] ?? -1) !== 0) {
            throw new \RuntimeException('飞书图片上传失败: ' . json_encode($resp));
        }
        return $resp['data']['image_key'];
    }

    /**
     * 发送图片消息
     */
    public function sendImage(string $webhook, string $imageKey): array
    {
        return $this->sendWebhook($webhook, [
            'msg_type' => 'image',
            'content'  => json_encode(['image_key' => $imageKey], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * 发送告警卡片消息
     */
    public function sendAlert(string $webhook, array $data): array
    {
        $card = [
            'header' => [
                'title'    => ['tag' => 'plain_text', 'content' => $data['title'] ?? '通知'],
                'template' => 'red',
            ],
            'elements' => [
                ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' =>
                    "**时间点：** {$data['hour']}点\n" .
                    "**日期：** {$data['date']}\n" .
                    "**时间：** {$data['time']}\n" .
                    "**步骤：** {$data['step']}\n" .
                    "**错误：** {$data['error']}\n"]],
            ],
        ];
        return $this->sendWebhook($webhook, ['msg_type' => 'interactive', 'card' => $card]);
    }

    /**
     * 发送纯文本消息
     */
    public function sendText(string $webhook, string $text): array
    {
        return $this->sendWebhook($webhook, [
            'msg_type' => 'text',
            'content'  => ['text' => $text],
        ]);
    }

    // ===== 底层 =====

    private function sendWebhook(string $webhook, array $body): array
    {
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("飞书 Webhook 请求失败: HTTP {$httpCode} " . $respBody);
        }
        return json_decode($respBody, true) ?: [];
    }

    private function postJson(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($data),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body, true) ?: [];
    }
}
