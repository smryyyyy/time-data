<?php
namespace App\Controllers;

class ImageController
{
    public function page(): void
    {
        $server = new \App\Services\ImageServer($GLOBALS['hermes_config']['image_server']);
        $server->cleanup();  // 每次查看自动清理 7 天前图片
        $images = $server->list();
        render('images', compact('images'));
    }

    public function delete(string $name): void
    {
        try {
            $server = new \App\Services\ImageServer($GLOBALS['hermes_config']['image_server']);
            $server->delete($name);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
