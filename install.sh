#!/bin/bash
# 时报推送系统 v2 — 一键安装脚本
# 用法: bash install.sh

set -e

echo "========================================"
echo "  时报推送系统 v2 安装"
echo "========================================"

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

# ---- 1. 系统依赖 ----
echo ""
echo "[1/5] 检查系统依赖..."

if command -v apt-get &>/dev/null; then
    PKG="apt-get"
elif command -v yum &>/dev/null; then
    PKG="yum"
else
    echo "⚠ 未检测到 apt-get 或 yum，请手动安装依赖: libreoffice imagemagick python3 python3-pip"
fi

# LibreOffice
if ! command -v libreoffice &>/dev/null; then
    echo "安装 LibreOffice..."
    sudo $PKG install -y libreoffice-headless libreoffice-calc 2>/dev/null || \
    sudo $PKG install -y libreoffice 2>/dev/null || \
    echo "⚠ LibreOffice 安装失败，请手动安装"
else
    echo "  ✓ LibreOffice: $(libreoffice --version 2>&1 | head -1)"
fi

# ImageMagick
if ! command -v convert &>/dev/null; then
    echo "安装 ImageMagick..."
    sudo $PKG install -y imagemagick 2>/dev/null || \
    echo "⚠ ImageMagick 安装失败，请手动安装"
else
    echo "  ✓ ImageMagick: $(convert --version 2>&1 | head -1)"
fi

# Python3
if ! command -v python3 &>/dev/null; then
    echo "安装 Python3..."
    sudo $PKG install -y python3 python3-pip 2>/dev/null || \
    echo "⚠ Python3 安装失败"
else
    echo "  ✓ Python3: $(python3 --version)"
fi

# ---- 2. PHP 扩展 ----
echo ""
echo "[2/5] 检查 PHP 扩展..."

for ext in curl zip mbstring xml dom; do
    if php -m | grep -qi "^$ext$"; then
        echo "  ✓ $ext"
    else
        echo "✗ $ext 未安装"
        MISSING=1
    fi
done

if [ -n "$MISSING" ]; then
    echo ""
    echo "⚠ 缺少 PHP 扩展，请安装:"
    if command -v apt-get &>/dev/null; then
        echo "  sudo apt-get install -y php-curl php-zip php-mbstring php-xml"
    elif command -v yum &>/dev/null; then
        echo "  sudo yum install -y php-curl php-zip php-mbstring php-xml"
    fi
fi

# ---- 3. Composer / PhpSpreadsheet ----
echo ""
echo "[3/5] 安装 PHP 依赖 (PhpSpreadsheet)..."

if [ ! -f composer.json ]; then
    cat > composer.json << 'EOF'
{
    "require": {
        "phpoffice/phpspreadsheet": "^2.0"
    }
}
EOF
fi

if command -v composer &>/dev/null; then
    composer install --no-dev --optimize-autoloader 2>&1
else
    # 如果没有 composer，尝试直接用 composer.phar
    if [ ! -f composer.phar ]; then
        echo "下载 Composer..."
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php composer-setup.php
        rm composer-setup.php
    fi
    php composer.phar install --no-dev --optimize-autoloader 2>&1
fi

echo "  ✓ PhpSpreadsheet 已安装"

# ---- 4. Python 依赖 (Playwright) ----
echo ""
echo "[4/5] 安装 Python 依赖 (Playwright)..."

pip3 install playwright 2>&1 || pip install playwright 2>&1
python3 -m playwright install chromium 2>&1 || python -m playwright install chromium 2>&1

echo "  ✓ Playwright + Chromium 已安装"

# ---- 5. 创建目录 & 权限 ----
echo ""
echo "[5/5] 创建目录..."

mkdir -p logs data tmp output uploads templates/backup
chmod 755 logs data tmp output uploads templates templates/backup
chmod 777 logs data tmp output uploads

# 复制 yuanbiao 模板
YUANBIAO="$(dirname "$ROOT")/yuanbiao"
if [ -d "$YUANBIAO" ]; then
    echo "复制模板文件..."
    if [ -f "$YUANBIAO/10点.xlsx" ]; then
        cp "$YUANBIAO/10点.xlsx" templates/ 2>/dev/null || true
    fi
    if [ -f "$YUANBIAO/14点.xlsx" ]; then
        cp "$YUANBIAO/14点.xlsx" templates/ 2>/dev/null || true
    fi
    if [ -f "$YUANBIAO/18点.xlsx" ]; then
        cp "$YUANBIAO/18点.xlsx" templates/ 2>/dev/null || true
    fi
    echo "  ✓ 模板已复制"
else
    echo "  ⚠ 未找到 yuanbiao/ 目录，请手动上传模板文件到 templates/"
fi

echo ""
echo "========================================"
echo "  安装完成！"
echo "========================================"
echo ""
echo "下一步："
echo "1. 编辑 config.php 填写 pzoom 账号、飞书 webhook 等"
echo "2. 配置 Nginx（参考 nginx.conf.example）"
echo "3. 添加 crontab: * * * * * curl -s http://your-domain/cron/tick"
echo "4. 访问 http://your-domain/ 登录（默认密码在 config.php）"
echo ""
