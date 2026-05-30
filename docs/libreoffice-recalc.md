# LibreOffice UNO 公式重算方案

## 最终方案：Python UNO API（已验证）

通过 Python 的 `uno` 模块启动 LibreOffice 并强制重算所有公式。

### 脚本：`scripts/recalc_xlsx.py`

```bash
python3 scripts/recalc_xlsx.py <input.xlsx> <output.xlsx>
```

流程：
1. 复制 input 到 `/var/www/html/tmp/_recalc/_in.xlsx`（绕过特殊字符路径）
2. 启动 `soffice --headless --accept=socket,...`
3. 通过 UNO 连接打开文件
4. `doc.calculateAll()` 强制重算
5. 保存为 `Calc MS Excel 2007 XML` 格式（840KB vs 原 1.7MB）
6. `os.replace()` 输出到目标路径
7. 清理临时文件和 LO 进程

### Docker 依赖

```dockerfile
libreoffice-calc       # 打开 xlsx（之前只有 impress，打不开）
python3-uno            # Python UNO 桥接
```

### PHP 集成（Merge.php 末尾）

```php
// Step 4: LO UNO 重算
$pyScript = ROOT . '/scripts/recalc_xlsx.py';
exec("python3 $pyScript $mergedFile $tmpRecalc ...");
rename($tmpRecalc, $mergedFile);
```

### 踩过的坑

| 问题 | 原因 | 解决 |
|------|------|------|
| LO 打不开 xlsx | 只装了 libreoffice-impress | 加 libreoffice-calc |
| `--convert-to` 不重算 | convert-to 保留公式缓存值 | 用 UNO calculateAll() |
| Basic 宏 exit 0 但不保存 | `--calc` 模式不自动存盘 | 用 UNO storeToURL() |
| 文件名含空格/括号/中文 | UNO file:// URL 不支持 | copy 到纯 ASCII 临时路径 |
| cleanup 时 PermissionError | LO 写文件 owner 不是 www-data | catch OSError 忽略 |
| pkill 失败导致 exit=1 | subprocess 抛异常 | 改用 subprocess.run() |
| 转换为 ods 再转回 | 格式丢失 | 直接 xlsx→xlsx 通过 UNO |

### 验证结果（2026-05-25 10点）

- 模板缓存：拉新消耗 28768 元
- 5/24 重算：拉新消耗 78221 元
- 5/25 重算：拉新消耗 47620 元
- 端到端测试：4 步全通过 ✅
