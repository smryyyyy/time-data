<?php
namespace App;

class Middleware
{
    /**
     * 检查登录态，未登录跳 login 页
     */
    public static function auth(): void
    {
        session_start();
        if (empty($_SESSION['hermes_logged_in'])) {
            // POST/DELETE/PUT 等 API 请求返回 JSON，不跳转
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '未登录，请刷新页面重新登录']);
                exit;
            }
            http_response_code(302);
            header('Location: /login');
            exit;
        }
    }
}
