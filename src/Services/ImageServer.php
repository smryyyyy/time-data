<?php
namespace App\Services;

/**
 * 图床服务 — 对接 shiba.img.guaibao.top
 */
class ImageServer
{
    private string $apiUrl;
    private string $user;
    private string $pass;
    private string $urlPrefix;

    public function __construct(array $cfg)
    {
        $this->apiUrl    = $cfg['api_url']    ?? 'http://shiba.img.guaibao.top/';
        $this->user      = $cfg['username']   ?? 'admin';
        $this->pass      = $cfg['password']   ?? '';
        $this->urlPrefix = $cfg['url_prefix'] ?? 'http://shiba.img.guaibao.top/uploads/';
    }

    /**
     * 上传图片，返回 ['url' => '...']
     */
    public function upload(array $file): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERPWD        => $this->user . ':' . $this->pass,
            CURLOPT_POSTFIELDS     => [
                'file' => new \CURLFile($file['tmp_name'], mime_content_type($file['tmp_name']), $file['name']),
            ],
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("图床上传失败: HTTP {$httpCode}");
        }

        $resp = json_decode($body, true);
        if (empty($resp['success'])) {
            throw new \RuntimeException('图床上传失败: ' . ($resp['error'] ?? $body));
        }

        return [
            'url'  => $resp['url'] ?? ($this->urlPrefix . ($resp['filename'] ?? basename($file['name']))),
            'name' => $resp['filename'] ?? basename($file['name']),
        ];
    }

    public function list(): array { return []; }
    public function delete(string $name): void {}
    public function cleanup(): void {}
}
