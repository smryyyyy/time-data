# 时报推送系统 v2

基于 PHP Web 面板的自动化数据报表推送系统，从 pzoom 下载数据、合并到 Excel 模板、提取图片、推送到飞书群聊。

---

## 系统流程

```
模板文件 (.xlsx)
     │
     ├── 步骤1. 从 pzoom 下载最新时报数据 (Python + Playwright)
     │          expect_download() 精确等待下载完成
     │
     ├── 步骤2. 合并数据到模板 (PHP + PhpSpreadsheet)
     │          ├── 校验今日 A 列日期 = 今天
     │          └── 校验昨日 A 列日期 = 昨天
     │
     ├── 步骤3. 提取图片并转换 (ZipArchive → LibreOffice → ImageMagick)
     │
     └── 步骤4. 推送飞书 (PHP curl)
                ├── 豆包爱学 (图)
                ├── 小说 (图 + 文，文字从 Excel 动态读取)
                ├── 音乐 (图 + 文，文字从 Excel 动态读取)
                └── 按模板选择不同 webhook 机器人
```

## 目录结构

```
server-v2/
├── index.php                   # 入口文件，URL 路由 + 动态设置加载
├── config.php                  # 静态默认配置
├── composer.json               # PHP 依赖声明
├── install.sh                  # 一键安装脚本
├── nginx.conf.example          # Nginx 配置模板
├── .htaccess                   # Apache 重写规则 + 目录安全
├── README.md                   # 本文件
│
├── views/                      # 视图模板
│   ├── layout.php              # 公共导航布局
│   ├── dashboard.php           # 仪表盘（状态查看 + 手动触发）
│   ├── logs.php                # 日志查看
│   ├── templates.php           # 模板管理（上传/替换/备份）
│   ├── images.php              # 图床管理
│   ├── settings.php            # 设置（定时任务/账号/webhook）
│   └── login.php               # 登录页
│
├── assets/
│   └── style.css               # 统一样式
│
├── scripts/
│   ├── download_pzoom.py       # pzoom 浏览器自动化下载
│   └── hash_password.php       # bcrypt 密码哈希生成工具
│
├── templates/                  # Excel 模板存储目录
│   └── backup/                 # 旧模板备份
│
├── uploads/                    # 图床上传目录
│
├── data/                       # 运行时数据
│   ├── {date}.xlsx             # 下载的源数据
│   ├── settings.json           # Web 面板保存的动态设置
│   └── schedule.json           # 定时任务配置
│
└── src/
    ├── helpers.php             # 全局函数（render/h/formatSize/...)
    ├── App.php                 # 应用引导 + 路由注册
    ├── Router.php              # URL 路由分发（支持路径参数）
    ├── Middleware.php          # 登录鉴权
    ├── Logger.php              # 结构化日志（按天分文件）
    ├── Scheduler.php           # 定时任务调度（防重复）
    ├── TaskRunner.php          # 4 步任务编排 + 报错推送
    ├── SettingsStore.php       # 动态设置 JSON 持久化
    │
    ├── Controllers/
    │   ├── DashboardController.php
    │   ├── LogController.php
    │   ├── SettingsController.php
    │   ├── TemplateController.php
    │   ├── ImageController.php
    │   ├── AuthController.php
    │   └── CronController.php
    │
    ├── Steps/
    │   ├── Download.php        # 步骤1：调 Python 下载数据
    │   ├── Merge.php           # 步骤2：合并数据 + 日期校验
    │   ├── Convert.php         # 步骤3：EMF 提取转 PNG
    │   └── Push.php            # 步骤4：读 Excel 文字 + 推飞书
    │
    └── Services/
        ├── Feishu.php          # 飞书 webhook 消息发送
        └── ImageServer.php     # 内置图床服务
```

## 部署步骤

### 1. 上传到服务器

```bash
scp -r server-v2/ user@your-server:/var/www/shibao/
```

### 2. 运行安装脚本

```bash
cd /var/www/shibao
bash install.sh
```

安装脚本会自动：
- 安装 PHP 扩展 (mbstring, zip, gd, curl, xml, sqlite3)
- 安装 LibreOffice (EMF 转 PDF)
- 安装 ImageMagick (PDF 转 PNG)
- 安装 Composer 依赖 (phpoffice/phpspreadsheet)
- 安装 Playwright + Chromium

### 3. 初始化模板

将三个 Excel 模板文件放入 `templates/` 目录：

```bash
cp /path/to/10点.xlsx /var/www/shibao/server-v2/templates/
cp /path/to/14点.xlsx /var/www/shibao/server-v2/templates/
cp /path/to/18点.xlsx /var/www/shibao/server-v2/templates/
```

### 4. 生成密码哈希

```bash
php scripts/hash_password.php 你的密码
```

将输出的 bcrypt 哈希复制到 `config.php` 的 `password` 字段。

### 5. 修改配置

编辑 `config.php`，修改以下字段：

```php
'password' => '$2y$10$...',                         // 上一步生成的 bcrypt 哈希
'base_url' => 'https://你的域名.com',                 // 图床访问域名

'pzoom' => [
    'url'      => 'https://pzoom.com.cn/#/vivo-data',
    'username' => '你的pzoom账号',
    'password' => '你的pzoom密码',
],

'feishu' => [
    'webhook' => [
        'default' => 'https://open.feishu.cn/...',  // 默认推送机器人
        '10点'    => 'https://open.feishu.cn/...',  // 可选：10点专用机器人
        '14点'    => 'https://open.feishu.cn/...',  // 可选：14点专用机器人
        '18点'    => 'https://open.feishu.cn/...',  // 可选：18点专用机器人
    ],
    'error_webhook' => 'https://open.feishu.cn/...', // 报错通知机器人
],
```

**webhook 查找规则**：按模板名匹配 → 找不到用 `default` → 都没有则报错。每个模板可使用不同的飞书机器人。

### 6. 配置 Nginx

参考 `nginx.conf.example`，修改域名和路径后放入 `sites-enabled/`：

```bash
sudo cp nginx.conf.example /etc/nginx/sites-available/shibao
sudo sed -i 's/your-domain.com/你的实际域名/g' /etc/nginx/sites-available/shibao
sudo ln -s /etc/nginx/sites-available/shibao /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 7. 设置定时任务

在 Web 面板的「设置」页面添加定时任务，或者手动添加 crontab：

```bash
* * * * * curl -s https://你的域名/cron/tick > /dev/null 2>&1
```

## 设计原则

- **Fail-fast**：配置缺失或错误直接抛异常，不做静默降级
- **不向后兼容**：升级时直接改配置格式，不兼容旧格式
- **动态设置分离**：Web 面板修改的配置写入 `data/settings.json`，不修改 `config.php` 源码；`config.php` 负责静态默认值，JSON 动态覆盖

## Web 面板功能

| 页面 | 路径 | 说明 |
|------|------|------|
| 仪表盘 | / | 查看各模板执行状态，手动触发任务 |
| 日志 | /logs | 按日期筛选查看执行日志 |
| 模板 | /templates | 上传替换 Excel 模板，自动备份旧文件 |
| 图床 | /images | 查看/删除已上传的图片 |
| 设置 | /settings | 配置 pzoom 账号、飞书 webhook、定时任务 |
| 登录 | /login | Web 面板登录 |

所有页面（除登录和定时任务入口）需要登录后才能访问。

## 定时任务

系统使用被动调度模式：crontab 每分钟请求 `/cron/tick`，Scheduler 判断当前时间是否匹配预设的定时规则，匹配则执行对应模板的完整 4 步任务。每天同一模板只执行一次（防止重复）。

## 错误处理

任一步骤失败时：

1. 记录错误日志（可在 /logs 查看）
2. 通过飞书 `error_webhook` 推送报错卡片消息
3. 消息包含：失败日期、模板名、失败步骤、错误详情、日志链接

| 错误 | 原因 | 解决 |
|------|------|------|
| 下载失败 | pzoom 未登录或无当天数据 | 检查账号，确认当天有数据 |
| 日期校验失败 | 数据日期不是今天 | 检查 pzoom 数据日期 |
| 转换失败 | LibreOffice/ImageMagick 未安装 | 运行 install.sh |
| 推送失败 | webhook 未配置或已失效 | 在设置页更新 webhook |
| webhook 未配置 | 模板和 default 都未设置 | 在 config.php 或设置页配置 |

## 图床服务

- POST `/api/upload` — 上传图片，返回 URL
- GET `/images` — Web 页面查看所有上传图片
- 自动清理超过 7 天的旧图片
- 图片 URL 格式：`https://你的域名/uploads/文件名`

## 模板管理

在 `/templates` 页面可以：

- 查看当前模板列表和文件大小
- 上传新的 .xlsx 替换旧模板
- 自动校验：.xlsx 格式，≤ 10MB
- 替换前自动备份到 `templates/backup/`

## 技术栈

| 组件 | 技术 |
|------|------|
| Web 框架 | 原生 PHP 8.0+，无框架 |
| Excel 操作 | PhpSpreadsheet |
| 浏览器自动化 | Python + Playwright |
| 图片转换 | ZipArchive + LibreOffice + ImageMagick |
| 推送 | 飞书 Webhook |
| 持久化 | JSON 文件 |
| 日志 | 本地文件（按天分文件） |

## API 端点

| 方法 | 路径 | 说明 | 鉴权 |
|------|------|------|------|
| GET | / | 仪表盘 | ✅ |
| GET | /login | 登录页 | — |
| POST | /login | 提交登录 | — |
| GET | /logout | 登出 | — |
| GET | /logs | 日志查看 | ✅ |
| GET | /logs/view | 查看指定日期日志 | ✅ |
| GET | /templates | 模板管理 | ✅ |
| POST | /templates/upload | 上传替换模板 | ✅ |
| GET | /images | 图床管理 | ✅ |
| POST | /api/upload | 上传图片到图床 | token |
| DELETE | /api/images/{name} | 删除图床图片 | token |
| GET | /settings | 设置页 | ✅ |
| POST | /settings | 保存设置 | ✅ |
| POST | /run | 手动执行任务 | ✅ |
| GET | /cron/tick | 定时任务入口 | — |
