<?php $view = 'login'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 — 时报推送</title>
    <style>
    :root {
        --bg: #f5f0eb;
        --bg-card: #ffffff;
        --text: #3d3d3d;
        --text-muted: #b8a99a;
        --accent: #5a7a6b;
        --accent-hover: #4d6b5d;
        --border: #e0d8cf;
        --radius: 6px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: -apple-system, 'Helvetica Neue', 'PingFang SC', sans-serif;
        font-weight: 300;
        background: var(--bg);
        display: flex; align-items: center; justify-content: center;
        min-height: 100vh;
    }
    .login-box {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 40px 32px;
        width: 360px;
    }
    .login-box h1 {
        text-align: center; margin-bottom: 24px;
        font-size: 18px; font-weight: 300; letter-spacing: 0.06em;
        color: var(--text);
    }
    .login-box input {
        width: 100%; padding: 10px 12px;
        border: 1px solid var(--border); border-radius: var(--radius);
        font-size: 13px; font-weight: 300; color: var(--text);
        margin-bottom: 16px; outline: none;
        transition: border-color 0.5s ease-in-out;
    }
    .login-box input:focus { border-color: var(--accent); }
    .login-box input::placeholder { color: var(--text-muted); }
    .login-box button {
        width: 100%; padding: 10px;
        background: var(--accent); color: #fff;
        border: none; border-radius: var(--radius);
        font-size: 13px; font-weight: 300; cursor: pointer;
        transition: background 0.5s ease-in-out;
    }
    .login-box button:hover { background: var(--accent-hover); }
    .error { color: #c0504d; font-size: 12px; margin-bottom: 12px; text-align: center; font-weight: 300; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>时报推送系统</h1>
    <?php if (!empty($error)): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <form method="POST" action="/login">
        <input type="text" name="username" placeholder="用户名" autofocus required>
        <input type="password" name="password" placeholder="密码" required>
        <button type="submit">登录</button>
    </form>
</div>
</body>
</html>
