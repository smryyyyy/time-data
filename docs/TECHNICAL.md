# 时服推送系统 — 完整技术文档

> 日期：2026-05-25 | 版本：v2 (Docker/PHP)

---

## 一、系统概述

自动化 4 步 Pipeline：**pzoom 下载数据 → 保护公式合并 Excel → EMF 图表提取转 PNG → 飞书机器人推送**。

部署形态：Docker 容器（php:8.2-apache + LibreOffice 25.2 + Playwright），目标 AlmaLinux 9.7。

---

## 二、项目结构

```
server-v2/
├── Dockerfile              # 镜像构建
├── docker-compose.yml
├── apache.conf             # Apache 配置
├── config.php              # 24时间点配置 + 飞书凭据
├── index.php               # 入口
├── composer.json
├── src/
│   ├── App.php             # 路由分发
│   ├── Router.php
│   ├── Middleware.php      # 认证中间件
│   ├── Logger.php          # 日志
│   ├── Scheduler.php       # Cron 调度
│   ├── TaskRunner.php      # 4步执行编排
│   ├── helpers.php         # 工具函数
│   ├── xlsx_reader.php     # ZipArchive 直读文字
│   ├── SettingsStore.php   # JSON 持久化设置
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php  # 主页 + 手动执行
│   │   ├── CronController.php       # 自动定时
│   │   ├── SettingsController.php
│   │   ├── TemplateController.php
│   │   ├── ImageController.php
│   │   └── LogController.php
│   ├── Steps/
│   │   ├── Download.php    # 步骤1: Playwright 下载 pzoom
│   │   ├── Convert.php     # 步骤2: EMF提取+转PNG+裁白边
│   │   ├── Merge.php       # 步骤3: ZipArchive写今日/昨日 + LO重算
│   │   └── Push.php        # 步骤4: 飞书推送
│   └── Services/
│       ├── Feishu.php      # 飞书API: getToken/uploadImage/sendImage/sendText
│       └── ImageServer.php # 图床对接(已废弃)
├── scripts/
│   ├── download_pzoom.py   # Playwright 动态登录下载
│   ├── recalc_xlsx.py      # LO UNO 强制重算公式
│   ├── trim_white.py       # Pillow+numpy 白边裁剪
│   └── hash_password.php
├── views/                  # PHP 视图模板
├── assets/                 # CSS
├── templates/backup/       # Excel 模板 (含EMF图, 1.7MB)
├── data/                   # 下载的源数据 {日期}/{prefix}_{日期}.xlsx
├── tmp/                    # 临时输出 {日期}/{小时}/
├── logs/                   # 日志
├── uploads/                # (空，图床功能已废弃)
└── docs/
    ├── libreoffice-recalc.md
    └── STATUS.md
```

---

## 三、数据流详解

### 3.1 步骤1：下载 (Download.php → download_pzoom.py)

```
pzoom 网站 (sunshuo@pzoom.com/19971216)
    ↓ Playwright headless chromium
    │  1. 登录 (navigating JS redirects)
    │  2. 切换到"时报"筛选
    │  3. JS 勾选目标行
    │  4. 下载 Excel zip
    │  5. 重命名为 {prefix}_{日期}.xlsx
    ↓
data/{日期}/{prefix}_{日期}.xlsx  (源文件，含"广告主_报告" sheet)
```

**源文件格式**：`广告主_报告` sheet，列：日期 | 广告主名称 | 花费 | 曝光量 | 点击量 | 下载量 | 下载率 | 激活数 | 次留数 | 昨次留 | 昨激活

**广告主名称示例**：`UG_dbax_pz_sd1`, `UG_dbax_pz_xxl1`, `fanqiesd`, `fm_music_fqct` 等（UG 平台层级的广告组名称）

### 3.2 步骤2：提取转换 (Convert.php)

```
模板文件 (backup/xxx.xlsx, 1.7MB, 含EMF图表)
    ↓ ZipArchive 解析
    │  遍历所有 drawing rels → 提取 .emf 文件
    │  跳过 vmlDrawing 文件 (去重，只保留 drawing 的)
    ↓
6张 EMF 文件
    ↓ LibreOffice --headless --convert-to pdf
6张 PDF
    ↓ ImageMagick convert -density 200 -trim -fuzz 5%
6张 PNG (原始)
    ↓ scripts/trim_white.py (Pillow+numpy 白边裁剪)
6张 PNG (去白边)
    ↓ 输出到 tmp/{日期}/{小时}/
```

### 3.3 步骤3：合并+重算 (Merge.php + recalc_xlsx.py)

```
模板文件 (backup/xxx.xlsx, 1.7MB)
    ↓
┌─────────────────────────────────────┐
│ ZipArchive 直写 (保护公式和图表)      │
│                                     │
│ 1. 打开模板 xlsx (ZIP)              │
│ 2. 找到 今日 sheet 的 XML (xl/worksheets/sheetN.xml) │
│ 3. 替换整个 <sheetData> 块          │
│    - 源数据"广告主_报告"→ 写为行     │
│    - 数值: <c r="C2"><v>123.45</v></c> │
│    - 文本: <c r="B2" t="inlineStr"><is><t>xxx</t></is></c> │
│ 4. 同法写 昨日 sheet                │
│ 5. 其他 sheet (数据/导出/图表) 不动  │
│                                     │
│ 关键：不碰 sharedStrings.xml         │
│ 不碰 charts/ drawings/ vmlDrawing   │
└─────────────────────────────────────┘
    ↓ 合并后的 xlsx (1.7MB, 今日数据已替换)
    ↓
┌─────────────────────────────────────┐
│ LO UNO 公式重算 (recalc_xlsx.py)     │
│                                     │
│ 1. copy 到纯 ASCII 临时路径         │
│ 2. 启动 soffice --headless --accept │
│ 3. Python UNO 连接 → loadComponent  │
│ 4. doc.calculateAll() 强制重算      │
│ 5. storeToURL xlsx (840KB)         │
│ 6. 替换原文件                       │
└─────────────────────────────────────┘
    ↓ 最终 xlsx (840KB, 公式已重算)
```

**ZipArchive 直写 vs PhpSpreadsheet/openpyxl**：

| 方法 | 优点 | 缺点 |
|------|------|------|
| ZipArchive 直写 | 只改数据，100%保护公式/图/格式 | 嵌套 XML 操作复杂 |
| PhpSpreadsheet | 语义化 API | OOM (>1.5GB)，320MB 内存 |
| openpyxl | Python 方便 | 丢图 (1.7MB→23KB) |

### 3.4 步骤4：推送 (Push.php)

```
重算后的 xlsx
    ↓
┌─────────────────────────────────────┐
│ 文字提取 (xlsx_reader.php)           │
│ ZipArchive 读 导出 sheet A50:A60    │
│ 公式缓存值 <v> 即拼接好的文案        │
└─────────────────────────────────────┘
    ↓ 文案字符串
    ↓
┌─────────────────────────────────────┐
│ 图片上传 (Feishu.php)                │
│ POST /open-apis/im/v1/images        │
│ multipart: image_type=message       │
│ → image_key                        │
└─────────────────────────────────────┘
    ↓ image_key 数组
    ↓
┌─────────────────────────────────────┐
│ 发送到飞书群                         │
│ config.php 中按时间点配置:            │
│   sheets → webhook 映射              │
│                                     │
│ 豆包爱学: image1                    │
│ 小说: image3 + image4 + 文字         │
│ 音乐: image7 + image8 + image9 + 文字│
│                                     │
│ 图片: msg_type=image,                │
│       content=json({"image_key":"x"})│
│ 文字: msg_type=text, content=raw     │
└─────────────────────────────────────┘
```

---

## 四、公式系统分析

### 4.1 Excel 模板结构

| Sheet | 内容 |
|-------|------|
| 今日 | 今天拉取的原始广告数据（下载→写入） |
| 昨日 | 昨天拉取的原始广告数据 |
| 数据 | 按产品线聚合的 SUMIFS + 环比计算 |
| 导出 | 拼接最终推送文案 (=数据!E6 等) |
| 豆包爱学/... | 各产品线导出 sheet |

### 4.2 数据 sheet 公式

Row 6 (小说/今日):
- E6: `=SUMIFS(今日!C:C, 今日!$B:$B, $D6)` — 小说今日花费
- J6: `=SUMIFS(今日!H:H, 今日!$B:$B, $D6)` — 小说今日激活
- K6: `=E6/J6` — 小说今日 CPA

Row 4 (小说/昨日):
- E4: `=SUMIFS(昨日!C:C, 昨日!$B:$B, $D4)`

Row 10 (音乐/昨日): 同结构，D10 为音乐短代码

Row 5/8: `=SUM(...)` 行小计

Row 9: `=(今日-昨日)/昨日` 环比增长

### 4.3 导出 sheet 公式

小说 sheet A50-A60:
```
="【番茄小说】"&CHAR(10)&"商店"&CHAR(10)&
 "拉新：消耗"&TRUNC(数据!E6,2)&"元，"&
 "实时新增数"&数据!J6&"，"&
 "CPA"&TRUNC(数据!K6,2)&"元，"&
 "大鱼预期cpa"&TRUNC(数据!L6,2)&CHAR(10)&
 ...
```

### 4.4 ⚠️ 公式匹配的广告主短代码

数据 sheet D列保存产品线短代码（共享字符串）：

| D列 | 短代码 | 产品线 |
|-----|--------|--------|
| D4 | xs_fc_zr | 小说-昨日 |
| D6 | xs_fc_dj | 小说-今日 |
| D10 | yy_fc_zr | 音乐-昨日 |
| D12 | yy_fc_dj | 音乐-今日 |

这些短代码用于 `SUMIFS(今日!C:C, 今日!$B:$B, $D6)` 匹配今日 sheet B列的广告主名称。

---

## 五、⚠️ 当前问题：数据映射不匹配

### 5.1 问题描述

SUMIFS 公式的匹配条件（D6="xs_fc_dj"）与今日 sheet B列的广告主名称（"UG_dbax_pz_sd1", "fanqiesd" 等）**不匹配**。

**模板原始数据**：今日 sheet B列使用与 D列对应的短代码
**pzoom 源数据**：今日 sheet B列使用完整的广告平台/组名称

SUMIFS("xs_fc_dj") 在 "UG_dbax_pz_sd1" 列表中查找 → 0 匹配行。

### 5.2 验证证据

```
数据 sheet D6 = sharedString[29] = "xs_fc_dj"

今日 sheet (重算后):
  Row 2: B = "UG_dbax_pz_sd1"     ≠ "xs_fc_dj"
  Row 3: B = "UG_dbax_pz_xxl1"    ≠ "xs_fc_dj"  
  Row 4: B = "fanqiesd"            ≠ "xs_fc_dj"
  Row 5: B = "fm_music_fqct"       ≠ "xs_fc_dj"
```

SUMIFS 不匹配任何行 → 理论上应返回 0。但实际返回 47620.92（LO 重算后），说明 LO 的 `calculateAll()` 存在某种缓存保持行为，或重算逻辑与预期不同。

### 5.3 可能原因

1. **源数据广告主名称与模板短代码不一致**：pzoom 导出的是平台广告组名，模板期望的是产品线短代码
2. **LO recalc 可能有缓存行为**：即使 calculateAll() 完成，对无匹配的 SUMIFS 可能保留了原始缓存值而非返回 0
3. **需要建立广告主名称→短代码映射表**：例如 "UG_dbax_pz_sd1" → "xs_fc_dj"

### 5.4 解决方向

方案 A：**映射表** — 在 config.php 中维护广告主名称→短代码的映射，Merge 时将源数据的 B列替换为对应短代码
方案 B：**修改模板** — 调整数据 sheet 的 D列短代码，使其匹配源数据的广告主名称
方案 C：**回退到 Mac 版方案** — 用 openpyxl merge（先做 convert 后 merge），参照原 Mac 版 push.py 的硬编码文案方案

---

## 六、配置结构 (config.php)

```php
return [
    'users' => ['admin' => '...bcrypt...'],
    'feishu_app_id' => 'cli_aa85486ded3c1bd1',
    'feishu_app_secret' => 'kEIIkuRosePqF3YCQY63Iba4Fm6vTEyQ',
    'data_dir' => ROOT . '/data',
    'tmp_dir' => ROOT . '/tmp',
    'log_dir' => ROOT . '/logs',
    'hours' => [
        10 => [
            'enabled' => true,
            'template' => 'backup/10点-番茄时报新-ss (1).xlsx',
            'data_prefix' => '0940_时报',
            'copy_range' => 'A:K',
            'feishu' => [
                'sheets' => [
                    '豆包爱学' => ['images' => [1], 'text' => null, 'webhook' => '...'],
                    '小说'     => ['images' => [3,4], 'text' => null, 'webhook' => '...'],
                    '音乐'     => ['images' => [7,8,9], 'text' => null, 'webhook' => '...'],
                ],
            ],
        ],
        // 14, 18, 22 同理...
    ],
];
```

---

## 七、Docker 环境

### 7.1 镜像内容

| 组件 | 用途 |
|------|------|
| php:8.2-apache | Web 服务 |
| libreoffice-calc + impress | 打开 xlsx + EMF→PDF 转换 + UNO 重算 |
| python3-uno | Python 操控 LO 重算 |
| imagemagick + ghostscript | PDF→PNG 转换 |
| python3 + playwright + chromium | pzoom 下载 |
| Pillow + numpy | 白边裁剪 |
| openpyxl | (备用，已弃用) |

### 7.2 关键配置

```
memory_limit = 1536M
HOME=/tmp
PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright
```

### 7.3 构建命令

```bash
docker build --no-cache -t shibao:v3 .
docker run -d --name shibao -p 8080:80 shibao:v3
```

---

## 八、LO UNO 重算脚本 (recalc_xlsx.py)

### 8.1 为什么用 UNO

| 方案 | 问题 |
|------|------|
| libreoffice --calc macro | exit 0 但不保存文件 |
| libreoffice --convert-to xlsx | 转换格式但不重算公式 |
| libreoffice --convert-to ods → xlsx | 文件损坏，格式丢失 |
| UNO calculateAll() + storeToURL | ✅ 可用 |

### 8.2 踩坑清单

1. **文件名特殊字符**：空格、括号、中文 → LO file:// URL 不支持 → copy 到 `/tmp/_recalc/_in.xlsx`
2. **权限问题**：www-data 无法删除 LO 写的文件 → cleanup 用 try/except
3. **pkill 竞争**：遗留 soffice 占端口 → 启动前 pkill -9
4. **输出文件 owner**：LO 写的文件 owner 不确定 → output 到 www-data 目录 (`/var/www/html/tmp/_recalc/`)

---

## 九、路由表

| 路径 | 方法 | 控制器 |
|------|------|--------|
| `/login` | GET/POST | AuthController |
| `/logout` | GET | AuthController |
| `/` `/dashboard` | GET | DashboardController |
| `/run` | POST | DashboardController::run |
| `/settings` | GET/POST | SettingsController |
| `/templates` | GET/POST | TemplateController |
| `/cron` | GET/POST | CronController |
| `/images` | GET/POST | ImageController |
| `/images/delete` | POST | ImageController::delete |
| `/logs` | GET | LogController |
| `/logs/view` | GET | LogController::view |
| `/scan-template` | GET | TemplateController::scan |

---

## 十、待解决问题

| 问题 | 严重度 | 解决方向 |
|------|--------|----------|
| 广告主名称不匹配 SUMIFS 条件 | 🔴 数据错误 | 需要映射表或改模板 |
| Docker Hub 不可达 (偶尔) | 🟡 构建阻塞 | 使用代理或等待恢复 |
| 14点 webhook 未配置 | 🟡 推送缺失 | 在 settings 中配置 |
| .recalc.tmp 可能有残留 | 🟢 微小 | 添加定期清理 |

---

## 十一、Mac 版参考对比

| 项目 | Mac 版 | Docker 版 |
|------|--------|-----------|
| 运行时 | macOS Python 脚本 | Docker PHP Web 面板 |
| 合并 | openpyxl (先 convert 后 merge) | ZipArchive 直写 |
| 重算 | 未实现 (文字硬编码) | LO UNO calculateAll() |
| 图片 | 飞书 API 上传 | 飞书 API 上传 |
| 推送 | 硬编码文案 | 动态读 Excel |
| 调度 | launchd | PHP cron + 手动执行 |
