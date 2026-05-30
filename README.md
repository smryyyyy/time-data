# 时报推送系统

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Apache-2.4-D22128?style=flat-square&logo=apache&logoColor=white" alt="Apache">
  <img src="https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/Python-3.13-3776AB?style=flat-square&logo=python&logoColor=white" alt="Python">
  <img src="https://img.shields.io/badge/Playwright-45BA4B?style=flat-square&logo=playwright&logoColor=white" alt="Playwright">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License">
</p>

<p align="center">
  自动从 pzoom 下载广告投放数据 → 合并 Excel → 截图导出 → 推送飞书群。
</p>

---

## 目录

- [功能特点](#功能特点)
- [快速开始](#快速开始)
- [部署方式](#部署方式)
- [配置说明](#配置说明)
- [定时任务](#定时任务)
- [项目结构](#项目结构)
- [技术栈](#技术栈)
- [许可证](#许可证)

---

## 功能特点

- **自动下载**：Playwright 无头浏览器登录 pzoom，按时间点匹配报告，自动下载源数据
- **Excel 合并**：ZipArchive 直写 今日/昨日 sheet，保护公式和图表不被破坏
- **公式重算**：LibreOffice UNO API 强制重算所有 SUMIFS 公式缓存值
- **截图导出**：openpyxl 读取数据 → HTML 渲染 → Playwright 截图，支持合并单元格、隐藏行、主题色
- **飞书推送**：自动上传图片获取 image_key，按 sheet 分发图片+文字到对应 webhook
- **定时执行**：内置每分钟循环，到整点自动触发流水线（10:00 / 14:00 / 18:00 / 22:00）
- **Web 管理面板**：仪表盘、日志查看、设置页（在线配置模板/Webhook/截图范围）

## 快速开始

### 前置条件

- Docker
- Docker Compose（可选）

### 从源码构建

```bash
git clone https://github.com/smryyyyy/time-data.git
cd time-data

# 编辑配置，填入你的 pzoom 账号和飞书密钥
nano config.php

# 构建镜像（首次约 15 分钟）
docker build -t time-data .

# 启动
docker run -d --name shibao -p 9080:80 \
  -v shibao_data:/var/www/html/data \
  -v shibao_logs:/var/www/html/logs \
  -v shibao_templates:/var/www/html/templates \
  -v shibao_tmp:/var/www/html/tmp \
  -e TZ=Asia/Shanghai \
  --restart unless-stopped \
  time-data
```

### Docker Compose

```yaml
services:
  shibao:
    image: time-data
    container_name: shibao
    ports:
      - "9080:80"
    volumes:
      - shibao_data:/var/www/html/data
      - shibao_logs:/var/www/html/logs
      - shibao_templates:/var/www/html/templates
      - shibao_tmp:/var/www/html/tmp
    environment:
      - TZ=Asia/Shanghai
    restart: unless-stopped

volumes:
  shibao_data:
  shibao_logs:
  shibao_templates:
  shibao_tmp:
```

```bash
docker compose up -d
```

### 无构建部署（直接加载镜像）

```bash
# 本地导出
docker save time-data -o time-data.tar

# 上传到服务器后加载
docker load -i time-data.tar
docker run -d --name shibao -p 9080:80 time-data
```

## 配置说明

### config.php

```php
'pzoom' => [
    'username'  => 'your_account',
    'password'  => 'your_password',
    'login_url' => 'https://login.pzoom.com/',
    'overview'  => 'https://app.pzoom.com/pinzhi/mrs/overview/vivo',
],
'feishu' => [
    'app_id'     => 'cli_xxxx',
    'app_secret' => 'your_app_secret',
],
```

### 时间点配置

通过 Web 面板 `/settings` 配置：

| 配置项 | 说明 |
|--------|------|
| 开启/关闭 | 是否启用该时间点的自动执行 |
| 数据前缀 | 源文件名前缀，如 `0940_时报` |
| 模板 | 上传对应时间点的 Excel 模板 |
| Webhook | 飞书机器人 Webhook URL |
| 截图范围 | 每个导出 sheet 的截图区域（如 `数据!A1:N23`） |

### 定时任务时间

默认在 10:00 / 14:00 / 18:00 / 22:00 整点触发，可在 `config.php` 的 `schedule` 段修改。

## 定时任务

容器内置了每分钟循环，自动调用 `/cron/tick` 检查时间点，无需外部 crontab。

日志每分钟输出：

```
[22:00] 开始载入任务
[22:00] 【10点】...条件检查 false
[22:00] 【14点】...条件检查 false
[22:00] 【18点】...条件检查 false
[22:00] 【22点】...条件检查 true
[22:00] 全部任务处理完成
```

匹配到时间点后自动执行完整流水线：下载 → 合并 → 截图 → 推送。

## 项目结构

```bash
.
├── src/                # PHP 后端
│   ├── Controllers/    # Web 控制器（仪表盘/设置/日志/定时）
│   ├── Steps/          # Pipeline 步骤（下载/合并/截图/推送）
│   ├── Services/       # 飞书 API、图床
│   ├── App.php         # 路由入口
│   ├── Router.php      # 路由分发
│   ├── Scheduler.php   # 定时调度器
│   └── Logger.php      # 日志
├── views/              # PHP 视图模板
├── scripts/            # Python 工具脚本
│   ├── download_pzoom.py    # pzoom 数据下载（Playwright）
│   ├── screenshot_range.py  # Excel 截图导出（openpyxl + Playwright）
│   ├── recalc_xlsx.py       # LO UNO 公式重算
│   └── read_source_data.py  # 源数据读取（openpyxl）
├── assets/             # 静态资源
├── config.php          # 配置文件
├── Dockerfile          # 容器构建
├── start.sh            # 容器启动入口（Apache + 内置定时）
└── docker-compose.yml  # Compose 编排
```

## 技术栈

| 组件 | 用途 |
|------|------|
| PHP 8.2 | Web 后端 |
| Apache 2.4 | Web 服务器 |
| LibreOffice Calc | Excel 公式重算 |
| Playwright + Chromium | 网页截图 + pzoom 下载 |
| Python 3 + openpyxl | Excel 数据读取 |
| PhpSpreadsheet | Excel 处理（仅读取源数据） |

## 许可证

MIT License

Copyright (c) 2026

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
