# Pipeline 推送飞书不送达 — 排查记录

## 已确认的事实

1. **手动 CLI 测试能送达**：`docker exec` + PHP CLI + `require` 加载 Feishu.php → 成功送达飞书 ✅
2. **Pipeline web 请求不能送达**：DashboardController 通过 autoloader 加载 Feishu → 飞书不显示 ❌
3. **两个路径用的同一个 Feishu.php 文件**：`/var/www/html/src/Services/Feishu.php`
4. **代码已验证**：
   - sendText content 格式：`json_encode(["text"=>$tx],JSON_UNESCAPED_UNICODE)`（JSON 字符串 ✅）
   - sendImage content 格式：`json_encode(["image_key"=>$ik])`（JSON 字符串 ✅）
   - CURLOPT_SSL_VERIFYPEER => false（已添加 ✅）
   - OPcache 已验证清除 + Apache 硬重启
5. **Webhook URL 正确**：config.php 里的 `exports[14]["豆包爱学"]["webhook"]` 指向 `2d5d7991...`

## 已验证非问题的项

- ❌ 不是 OPcache/缓存问题（Apache 硬重启 + opcache_reset）
- ❌ 不是文件路径问题（autoloader 正确加载 `/var/www/html/src/Services/Feishu.php`）
- ❌ 不是 SSL 证书问题（CURLOPT_SSL_VERIFYPEER => false）
- ❌ 不是 webhook URL 问题（手动测试用同一 URL 能送达）
- ❌ 不是 content 格式问题（手动测试用同一函数能送达）
- ❌ 不是网络权限问题（同一容器内）

## 最可疑的原因

**autoloader 加载的类 vs require 加载的类存在差异**。虽然文件路径相同，但 PHP 的 class resolution 可能因为以下原因产生差异：

1. **Composer autoloader 优先级**：`vendor/autoload.php` 先注册，可能捕获了 `App\Services\Feishu` 的加载请求并指向了其他路径
2. **PHP 8.2 的 class_alias 或命名空间冲突**
3. **Feishu.php 文件末尾或开头有不可见字符**，导致 CLI require 成功但 web 加载时解析不同

## 建议排查方向

**在 Feishu 类里加日志**，记录实际发送的 JSON payload 和 curl 响应码：

```php
// wh 函数加日志
$payload = json_encode($b, JSON_UNESCAPED_UNICODE);
file_put_contents('/tmp/feishu_debug.log', 
    date('H:i:s') . " PAYLOAD: " . $payload . "\n", FILE_APPEND);
$ch = curl_init($w);
curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>30,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>$payload]);
$rb = curl_exec($ch);
file_put_contents('/tmp/feishu_debug.log', 
    date('H:i:s') . " RESPONSE: HTTP=" . curl_getinfo($ch,CURLINFO_HTTP_CODE) . " BODY=" . $rb . "\n", FILE_APPEND);
curl_close($ch);
```

然后跑一次 pipeline，看 `/tmp/feishu_debug.log` 的输出。
