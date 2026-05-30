<?php
namespace App;

/**
 * 动态设置存储 — JSON 文件读写
 * 替代 SettingsController 中的正则替换 config.php 方案
 */
class SettingsStore
{
    private string $file;

    public function __construct(string $dataDir)
    {
        $this->file = $dataDir . '/settings.json';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }

    /**
     * 读取全部设置
     */
    public function load(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->file), true);
        return is_array($data) ? $data : [];
    }

    /**
     * 保存全部设置（覆盖）
     */
    public function save(array $data): void
    {
        file_put_contents(
            $this->file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * 合并更新：只更新传入的 key，保留其他
     */
    public function merge(array $updates): void
    {
        $current = $this->load();
        $this->save(array_replace_recursive($current, $updates));
    }
}
