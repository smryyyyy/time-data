<?php $view = 'login'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>登录 - 时报推送</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh}
.login-box{background:#fff;padding:40px 32px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.1);width:360px}
.login-box h1{text-align:center;margin-bottom:24px;font-size:20px;color:#1a1a2e}
.login-box input{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;margin-bottom:16px}
.login-box button{width:100%;padding:10px;background:#4f46e5;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer}
.login-box button:hover{background:#4338ca}
.error{color:#ef4444;font-size:13px;margin-bottom:12px;text-align:center}
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
