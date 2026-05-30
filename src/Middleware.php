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
            http_response_code(302);
            header('Location: /login');
            exit;
        }
    }
}
