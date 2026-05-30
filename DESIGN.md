# 时服推送系统 v3 — 设计文档

## 一、整体架构

```
┌─────────────────────────────────────────────────────────┐
│                    Web 面板 (PHP + HTML)                  │
│  登录 → 仪表盘 │ 日志 │ 设置                                │
├─────────────────────────────────────────────────────────┤
│                      核心调度                             │
│  cron /cron/tick → Scheduler → TaskRunner (每时间点)       │
├─────────────────────────────────────────────────────────┤
│                四个步骤 (每时间点独立)                       │
│  1.Download   2.Merge   3.Convert   4.Push               │
│  (Python+     (PHP+     (ZipArchive+ (PHP curl+          │
│   Playwright) PhpSpreadsheet) LibreOffice+ Feishu        │
│                            ImageMagick)  Webhook)        │
├─────────────────────────────────────────────────────────┤
│                      数据存储                             │
│  data/{date}/源文件    templates/{时间点}-模板.xlsx         │
│  data/settings.json    data/schedule.json                 │
└─────────────────────────────────────────────────────────┘
```

## 二、数据模型

### 2.1 配置结构 (config.php + settings.json)

```php
return [
    // === 系统 ===
    'debug'    => false,
    'timezone' => 'Asia/Shanghai',
    'base_url' => 'https://your-domain.com',

    // === 用户认证 ===
    // 为多用户预留：当前 users 表只有一条 admin 记录
    'users' => [
        'admin' => [
            'password' => '$2y$10$...',   // bcrypt 哈希
        ],
    ],

    // === pzoom 公共凭据 ===
    // 多用户后移入各自 user 配置
    'pzoom' => [
        'username'  => 'sunshuo@pzoom.com',
        'password'  => '19971216',
        'login_url' => 'https://login.pzoom.com/',
        'overview'  => 'https://app.pzoom.com/pinzhi/mrs/overview/vivo',
    ],

    // === 全局告警 ===
    'alert_webhook' => 'https://open.feishu.cn/...',

    // === 图床 ===
    'image_server' => [
        'url_prefix' => 'https://your-domain.com/uploads/',
        'upload_dir' => ROOT . '/uploads',
        'max_age'    => 7,
    ],

    // === 24 时间点配置 ===
    // 多用户后：每个用户的 hours 独立存储
    'hours' => [
        10 => [
            'enabled'     => true,
            'data_prefix' => '0940_时报',      // 源文件名前缀
            'copy_range'  => ['A', 'K'],       // 列复制范围
            'template'    => '10点-番茄时报新.xlsx',
            'exports'     => [                 // 从模板自动扫描生成
                '豆包爱学' => '',
                '小说'     => '',
                '音乐'     => '',
            ],
        ],
        14 => [
            'enabled'     => true,
            'data_prefix' => '1340_时报',
            'copy_range'  => ['A', 'K'],
            'template'    => '14点-番茄时报新.xlsx',
            'exports'     => ['豆包爱学'=>'', '小说'=>'', '音乐'=>''],
        ],
        18 => [
            'enabled'     => true,
            'data_prefix' => '1740_时报',
            'copy_range'  => ['A', 'K'],
            'template'    => '18点-番茄时报新.xlsx',
            'exports'     => ['豆包爱学'=>'', '小说'=>'', '音乐'=>''],
        ],
        22 => [
            'enabled'     => true,
            'data_prefix' => '2140_时报',
            'copy_range'  => ['A', 'K'],
            'template'    => '22点-番茄时报新.xlsx',
            'exports'     => ['豆包爱学'=>'', '小说'=>'', '音乐'=>''],
        ],
        // 0-9,11-13,15-17,19-21,23: enabled=false（预置槽位）
    ],

    // === 目录 ===
    'data_dir'   => ROOT . '/data',
    'output_dir' => ROOT . '/tmp',
    'log_dir'    => ROOT . '/logs',
    'template_dir' => ROOT . '/templates',

    // === 工具路径 ===
    'libreoffice' => '/usr/bin/libreoffice',
    'imagemagick' => '/usr/bin/convert',
];
```

### 2.2 数据目录结构

```
data/
  {YYYY-MM-DD}/                        ← 按日期分目录，保留 5 天
    0940_时报(2026-05-27).xlsx          ← 10点源数据
    1340_时报(2026-05-27).xlsx          ← 14点源数据
    1740_时报(2026-05-27).xlsx          ← 18点源数据
    2140_时报(2026-05-27).xlsx          ← 22点源数据
  settings.json                         ← SettingsStore 管理
  schedule.json                         ← Scheduler 管理

templates/
  10点-番茄时报新.xlsx                  ← 每时间点独立模板
  14点-番茄时报新.xlsx
  18点-番茄时报新.xlsx
  22点-番茄时报新.xlsx
  backup/                               ← 上传时自动备份

uploads/                                ← 图床图片
tmp/                                    ← 临时输出（每日子目录）
logs/                                   ← 日志（按天分文件）
```

### 2.3 settings.json (动态覆盖)

```json
{
    "pzoom": {
        "password": "..."               // 可从 Web 面板修改
    },
    "hours": {
        "10": {
            "exports": {
                "豆包爱学": "https://...",
                "小说": "https://..."
            }
        }
    }
}
```

## 三、每时间点执行流程 (TaskRunner)

```
┌─ 输入：date(Y-m-d), hour(0-23) ───────────────────┐
│                                                     │
│  1. Download                                        │
│     ├─ 检查 data/{date}/{prefix}({date}).xlsx 存在  │
│     ├─ 检查 data/{date-1}/{prefix}({date-1}).xlsx   │
│     │   └─ 任缺其一 → Playwright 登录 pzoom        │
│     │       匹配报告列表中的"时报"                   │
│     │       按日期+时间点下载 → 保存到 data/         │
│     │       失败 → alert_webhook 告警 + 终止         │
│     │                                               │
│  2. Merge                                           │
│     ├─ 确认模板文件存在                               │
│     ├─ 确认今日源文件: data/{date}/{prefix}({date})   │
│     ├─ 确认昨日源文件: data/{date-1}/{prefix}({date-1})│
│     ├─ 复制模板到 work/ 目录（不污染原文件）           │
│     ├─ 昨日源文件 广告主_报告 [A-K] → 模板昨日 [A-K]  │
│     ├─ 今日源文件 广告主_报告 [A-K] → 模板今日 [A-K]  │
│     │   └─ 失败 → alert_webhook 告警 + 终止           │
│     │                                               │
│  3. Convert                                         │
│     ├─ ZipArchive 打开 xlsx                          │
│     ├─ 提取 xl/media/image*.emf → emf/ 目录          │
│     ├─ LibreOffice emf → pdf (headless)             │
│     ├─ ImageMagick pdf → png (2400px 宽)             │
│     │   └─ 失败 → alert_webhook 告警 + 终止           │
│     │                                               │
│  4. Push                                            │
│     ├─ 扫描模板 sheet 列表                           │
│     │   排除: 数据, 今日, 昨日, microsoft:*           │
│     ├─ 对每个导出 sheet:                             │
│     │   ├─ 获取 sheet 内全部图片 → 图床上传           │
│     │   ├─ 读取 A50:A60 单元格文字                   │
│     │   ├─ 构建飞书卡片消息 (img_url 外链图片)        │
│     │   └─ 发送到对应 webhook                        │
│     ├─ 任何导出失败 → alert_webhook 告警              │
│     └─ 全部成功 → 记录日志                            │
│                                                     │
└─────────────────────────────────────────────────────┘
```

## 四、下载步骤详解 (Download)

```
1. Playwright 启动 headless Chromium
2. page.goto(pzoom.login_url)
3. 填写用户名/密码 → 点击登录
4. page.goto(pzoom.overview + '?t=' + timestamp)
5. 在报告列表表格中查找:
   - 模板名称 = "时报"
   - 报告数据日期范围包含目标日期
   - 按创建时间识别对应时间点的报告
6. 找到对应报告行
7. 用 expect_download 包裹 → 点击该行的"导出"按钮
8. download.save_as(data/{date}/{prefix}({date}).xlsx)
9. 如果下载的是 zip，解压取 xlsx
```

## 五、UI 设计

### 5.1 仪表盘 (/)

```
┌─────────────────────────────────────────────┐
│  时服推送系统              [admin] [退出]     │
├─────────────────────────────────────────────┤
│  仪表盘 │ 日志 │ 模板 │ 图床 │ 设置           │
├─────────────────────────────────────────────┤
│                                             │
│  2026-05-27                                 │
│                                             │
│  ┌── 筛选： [全部] [已开启] ────┐            │
│  │                             │            │
│  │  10点  ✅ 已执行   [手动执行] │            │
│  │  14点  ⏳ 等待中   [手动执行] │            │
│  │  18点  ❌ 失败     [手动执行] │  ← 仅显示  │
│  │  22点  ⏳ 等待中   [手动执行] │   已开启的  │
│  │                             │            │
│  └─────────────────────────────┘            │
│                                             │
└─────────────────────────────────────────────┘
```

手动执行弹窗：
```
┌─ 手动执行 ──────────────────────┐
│  时间点: 10点                    │
│  日期:   [2026-05-27] (可改)     │
│                                  │
│  步骤1 下载  ⏳ 执行中...        │
│  步骤2 合并  ⬜                  │
│  步骤3 转换  ⬜                  │
│  步骤4 推送  ⬜                  │
│                                  │
│  结果: ...                       │
│        [关闭]                    │
└──────────────────────────────────┘
```

### 5.2 设置页 (/settings)

```
┌─ 全局设置 ────────────────────────────────────┐
│  pzoom 用户名: [_______________]              │
│  pzoom 密码:   [_______________]              │
│  告警 webhook: [_______________]              │
│                              [保存]           │
└───────────────────────────────────────────────┘

┌─ 时间点配置 ──────────────────────────────────┐
│  筛选: [全部] [已开启] [已关闭]                │
│                                               │
│  ┌─ 10点 ── [●开启] ──────────────────────┐  │
│  │  数据前缀: [0940_时报]                  │  │
│  │  列范围:  [A] 到 [K]                    │  │
│  │                                        │  │
│  │  模板: 10点-番茄时报新.xlsx [查看/替换]  │  │
│  │                                        │  │
│  │  ┌─ 推送 webhook ────────────────────┐ │  │
│  │  │  豆包爱学: [_______________]      │ │  │
│  │  │  小说:     [_______________]      │ │  │
│  │  │  音乐:     [_______________]      │ │  │
│  │  └──────────────────────────────────┘ │  │
│  │                          [保存] [收起] │  │
│  └───────────────────────────────────────┘  │
│                                               │
│  ┌─ 14点 ── [●开启] ─── (展开同上) ────────┐ │
│  └──────────────────────────────────────────┘ │
│                                               │
│  ┌─ 11点 ── [○关闭] (折叠) ─────────────────┐ │
│  └──────────────────────────────────────────┘ │
│  ... (0-23 共 24 个，默认前 4 个已配置展开)     │
└───────────────────────────────────────────────┘
```

### 5.3 模板管理 (独立页 /templates)

```
┌─ 模板管理 ────────────────────────────────────┐
│  时间点: [10点 ▼]                             │
│                                               │
│  当前模板: 10点-番茄时报新.xlsx (1.7MB)        │
│  导出 sheet: 豆包爱学, 小说, 音乐             │
│                                               │
│  [上传新模板] (自动备份旧文件)                 │
└───────────────────────────────────────────────┘
```

## 六、API 端点

| 方法 | 路径 | 说明 | 鉴权 |
|------|------|------|------|
| GET | / | 仪表盘 | ✅ |
| GET | /login | 登录页 | - |
| POST | /login | 登录 | - |
| GET | /logout | 登出 | - |
| GET | /logs | 日志查看 | ✅ |
| GET | /logs/view?date= | 查看指定日期日志 | ✅ |
| GET | /templates | 模板管理 | ✅ |
| POST | /templates/upload | 上传模板 | ✅ |
| GET | /images | 图床管理 | ✅ |
| POST | /api/upload | 上传图片到图床 | token |
| DELETE | /api/images/{name} | 删除图床图片 | token |
| GET | /settings | 设置页 | ✅ |
| POST | /settings | 保存设置 | ✅ |
| POST | /run | 手动执行(某时间点某日期) | ✅ |
| GET | /cron/tick | 定时任务入口 | - |

## 七、文件结构

```
src/
  App.php                  # 应用引导
  Router.php               # URL 路由
  Middleware.php           # 登录鉴权
  Logger.php               # 结构化日志
  Scheduler.php             # 定时调度（不变）
  SettingsStore.php         # JSON 设置持久化（不变）
  TaskRunner.php            # 任务编排（重写）

  Controllers/
    DashboardController.php # 仪表盘: 24时间点状态 + 手动执行
    AuthController.php      # 登录/登出
    LogController.php       # 日志查看
    SettingsController.php  # 设置: 全局 + 24时间点配置
    TemplateController.php  # 模板管理
    ImageController.php     # 图床
    CronController.php      # 定时入口

  Steps/
    Download.php            # 步骤1: Playwright 智能下载
    Merge.php               # 步骤2: 独立填充今日/昨日
    Convert.php             # 步骤3: EMF转PNG（不变）
    Push.php                # 步骤4: 动态扫描sheet推送

  Services/
    Feishu.php              # 飞书 webhook 客户端
    ImageServer.php         # 内置图床

scripts/
  download_pzoom.py          # 删掉，改为 download.php 调用 Playwright
                             # 或保留为通用下载脚本(传入日期+前缀)
```

## 八、多用户预留

不写死单用户，但不实现完整多用户：

| 维度 | 当前做法 | 未来扩展 |
|------|----------|----------|
| 登录 | 单 admin 密码 | `users` 数组 → 独立 users 表 |
| 配置 | 全局 `hours` | 每用户独立的 hours 配置 |
| 数据 | `data/{date}/` | `data/{user_id}/{date}/` |
| 模板 | `templates/` | `templates/{user_id}/` |
| pzoom | 全局凭据 | 每用户独立凭据 |
| 操作隔离 | 单用户，不隔离 | session user_id 过滤 |

代码层面：
- `$GLOBALS['current_user']` 预设变量（当前固定 'admin'）
- 文件路径统一用 `getDataDir($user, $date)` 等辅助函数，不硬编码
- DB 不做（无必要），用户配置存 JSON

## 九、实现顺序

1. **config.php + settings.json** — 24 时间点配置结构
2. **Download 重写** — Playwright 动态匹配报告 + 检查今日/昨日都下载
3. **Merge 重写** — 今日/昨日各自独立源文件填充
4. **Push 重写** — 动态扫描 sheet + 每个独立 webhook
5. **设置页 UI** — 24 时间点卡片 + webhook 配置
6. **仪表盘 UI** — 状态展示 + 手动执行
7. **模板管理页** — 按时间点管理
8. **联调测试**
