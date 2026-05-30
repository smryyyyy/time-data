<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>时报推送系统</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <nav class="topbar">
        <div class="topbar-inner">
            <a href="/" class="logo">时报推送系统</a>
            <div class="nav-links">
                <a href="/" class="<?= $view === 'dashboard' ? 'active' : '' ?>">仪表盘</a>
                <a href="/logs" class="<?= $view === 'logs' ? 'active' : '' ?>">日志</a>
                <a href="/settings" class="<?= $view === 'settings' ? 'active' : '' ?>">设置</a>
                <a href="/logout" style="color:#ef4444">退出</a>
            </div>
        </div>
    </nav>
    <main class="container">
        <?= $content ?>
    </main>
</body>
</html>
