# 时服推送系统 v2 — 项目状态

> 更新：2026-05-27 23:30

---

## 当前 Pipeline

```
下载(pzoom) → 合并(ZipArchive+LO重算) → 截图(openpyxl+HTML+Playwright) → 推送(飞书webhook)
```

## 已完成

| 模块 | 状态 | 备注 |
|------|------|------|
| pzoom 下载 | ✅ | Playwright登录+按时间点匹配+实时输出 |
| 数据合并 | ✅ | ZipArchive直写今日/昨日 sheet |
| 公式重算 | ✅ | LO UNO calculateAll() |
| 截图导出 | ✅ | openpyxl→HTML→Playwright，6格式支持 |
| 飞书推送 | ✅ | 上传图片+文字到webhook |
| 定时任务 | ✅ | cron/tick 每分钟检查 |
| 仪表盘 | ✅ | 状态卡片+手动执行 |
| 设置页 | ✅ | 全局设置+时间点配置+截图范围配 |
| 日志页 | ✅ | 着色+倒序+自动刷新 |
| 模板管理 | ✅ | 集成到设置页，上传自动保存 |

## 已解决

| 问题 | 方案 |
|------|------|
| 下载超时 | 改用 popen 实时流式输出，PHP `set_time_limit(0)` 防止超时 |
| 昨日数据下载 | 下载前检查文件是否存在，不存在则自动下载 |
| TaskRunner/DashboardController 代码重复 | 两者均调用同一套 Merge/Push 类，仅 download 流式输出不同 |
| 设置保存提示 | 已改为 Toast 弹窗（非 alert） |
| 上传模板后需要手动保存 | 上传成功后自动触发 form 提交保存 |

## 关键配置说明

**config.php** 为默认值，**data/settings.json** 为动态保存。`index.php` 用 `array_replace_recursive` 合并两者。

### exports 格式
```php
'exports' => [
    '豆包爱学' => [
        'webhook' => 'https://...',
        'cell_ranges' => ['数据!A1:N23'],
    ],
]
```

cell_ranges 支持跨 sheet 引用：`数据!A1:N23`。

## 运行命令

```bash
# 手动执行
curl -X POST -d 'date=2026-05-27&hour=14' http://localhost:8080/run

# 定时触发
curl http://localhost:8080/cron/tick
```

## 部署

基于 Docker (php:8.2-apache)，含 LibreOffice Calc、Playwright、pandas、openpyxl。

构建：`docker build --no-cache -t shibao:v3 .`
运行：`docker run -d --name shibao -p 8080:80 shibao:v3`
