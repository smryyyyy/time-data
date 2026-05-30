<?php
/**
 * 生成 bcrypt 密码哈希
 * 用法: php scripts/hash_password.php <your_password>
 */
if ($argc < 2) {
    die("用法: php hash_password.php <password>\n");
}
echo password_hash($argv[1], PASSWORD_BCRYPT) . "\n";
