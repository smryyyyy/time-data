<?php
namespace App\Controllers;

class AuthController
{
    public function login(): void
    {
        render('login', [], false);
    }

    public function doLogin(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $config   = $GLOBALS['hermes_config'];
        $users    = $config['users'] ?? [];

        if (isset($users[$username])) {
            if (password_verify($password, $users[$username]['password'])) {
                session_start();
                $_SESSION['hermes_logged_in'] = true;
                $_SESSION['hermes_user']      = $username;
                header('Location: /');
                exit;
            }
        }

        render('login', ['error' => '用户名或密码错误'], false);
    }

    public function logout(): void
    {
        session_start();
        unset($_SESSION['hermes_logged_in'], $_SESSION['hermes_user']);
        session_destroy();
        header('Location: /login');
        exit;
    }
}
