# 时服推送系统 v2 — 开发过程记录

> 最终更新：2026-05-27 23:45

---

## Pipeline

```
下载(pzoom) → 合并(ZipArchive+LO重算) → 截图(openpyxl+HTML+Playwright) → 推送(飞书webhook)
```

## 已实现功能

| 功能 | 状态 |
|------|------|
| pzoom 下载（按时间点匹配） | ✅ 实时流式输出，不存在则下载 |
| 昨日数据自动下载 | ✅ 检查今日+昨日，存在跳过 |
| Excel 合并（ZipArchive 直写） | ✅ 保护公式和图表 |
| 公式重算（LO UNO calculateAll） | ✅ |
| 截图导出（openpyxl→HTML→Playwright） | ✅ 合并单元格/隐藏行/格式保留 |
| 飞书推送（图片+文字） | ✅ |
| Web 仪表盘 | ✅ 状态卡片+手动执行 |
| 设置页 | ✅ 全局设置+时间点配置+截图范围(+/-) |
| 日志页 | ✅ 着色+倒序+3秒自动刷新 |
| 模板上传（集成到设置页） | ✅ 上传后自动保存 |
| Toast 提示（非 alert） | ✅ |
| 定时任务（cron/tick） | ✅ |
| 超时保护（set_time_limit(0)） | ✅ |
| 保存全部设置 | ✅ 权限和JS均已修复 |

## 待办

| 事项 | 说明 |
|------|------|
| Excel 模板适配 | 不同模板需在设置页手动配置 cell_ranges |
| 飞书推送限流 | usleep(500000) 已缓解，高并发可能仍需优化 |
| 部署到 Almalinux | 需 Docker Hub 恢复后构建镜像 |

## 踩坑记录

| 坑 | 原因 | 解决 |
|----|------|------|
| LO 打不开 xlsx | 只装了 libreoffice-impress | 加 libreoffice-calc |
| 公式不重算 | --convert-to 保留缓存值 | UNO calculateAll() |
| 图片不更新 | EMF 静态图 | openpyxl+HTML+Playwright 截图 |
| settings.json 权限 | root 拥有，www-data 写不了 | chmod 664 |
| 模板路径多 backup/ | 没用 basename() | 已修复 |
| Webhook 地址被覆盖 | settings.json 优先级高于 config.php | 保持一致 |
| 页面卡死 | exec() 阻塞 50秒 | 改用 popen 流式输出 |
| 保存按钮无效 | cr_start[]/cr_end[] 数组字段未正确处理 | JS 重写 |
| 下载中文乱码 | escapeshellarg 正常，ps axu 显示乱码 | 实际参数正确 |

## 配置格式

```php
// settings.json
{
  "hours": {
    "14": {
      "enabled": true,
      "data_prefix": "1340_时报",
      "template": "14点-模板.xlsx",
      "exports": {
        "豆包爱学": {
          "webhook": "https://open.feishu.cn/open-apis/bot/v2/hook/xxx",
          "cell_ranges": ["数据!A1:N23"]
        }
      }
    }
  }
}
```
