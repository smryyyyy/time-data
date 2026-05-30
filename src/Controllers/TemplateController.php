<?php
namespace App\Controllers;

/**
 * 模板管理 — 按时间点上传统计
 */
class TemplateController
{
    public function index(): void
    {
        $config  = $GLOBALS['hermes_config'];
        $hours   = $config['hours'];
        $tplDir  = $config['template_dir'];

        $templates = [];
        foreach ($hours as $h => $cfg) {
            if (empty($cfg['enabled'])) continue;
            $file = $tplDir . '/' . ($cfg['template'] ?? '');
            $templates[$h] = [
                'name'   => $cfg['template'],
                'size'   => file_exists($file) ? filesize($file) : 0,
                'exists' => file_exists($file),
                'sheets' => file_exists($file) ? scanExportSheets($file) : [],
                'hour'   => $h,
                'enabled'=> !empty($cfg['enabled']),
            ];
        }

        render('templates', compact('templates', 'hours'));
    }

    public function upload(): void
    {
        $config = $GLOBALS['hermes_config'];
        $logger = $GLOBALS['hermes_logger'];
        $hour   = (int)($_POST['hour'] ?? 0);
        $maxSize = $config['template_max_size'];

        if (!isset($config['hours'][$hour])) {
            jsonError('无效的时间点', 400);
        }

        $file = $_FILES['template'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            jsonError('文件上传失败', 400);
        }
        if ($file['size'] > $maxSize) {
            jsonError('文件过大（最大 ' . formatSize($maxSize) . '）', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            jsonError('仅支持 .xlsx 格式', 400);
        }

        $tplDir = $config['template_dir'];
        if (!is_dir($tplDir)) mkdir($tplDir, 0755, true);

        // 备份旧文件
        $oldName = $config['hours'][$hour]['template'] ?? '';
        if ($oldName && file_exists($tplDir . '/' . $oldName)) {
            $backupDir = $tplDir . '/backup';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            $backupName = date('Ymd_His') . '_' . $oldName;
            rename($tplDir . '/' . $oldName, $backupDir . '/' . $backupName);
            $logger->info("旧模板已备份: {$backupName}");
        }

        // 保存新模板（使用安全文件名，避免中文编码问题）
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = $hour . '.' . $ext;
        $dest = $tplDir . '/' . $newName;
        move_uploaded_file($file['tmp_name'], $dest);

        // 更新配置（直接写 settings.json，绕过 SettingsStore）
        $setFile = $config['data_dir'] . '/settings.json';
        $sets = [];
        if (file_exists($setFile)) {
            $c = json_decode(file_get_contents($setFile), true);
            if (is_array($c)) $sets = $c;
        }
        if (!isset($sets['hours'])) $sets['hours'] = [];
        if (!isset($sets['hours'][$hour])) $sets['hours'][$hour] = [];
        $sets['hours'][$hour]['template'] = $newName;
        file_put_contents($setFile, json_encode($sets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $logger->info("模板已更新 [{$hour}点]: {$newName}");
        $sheets = scanExportSheets($dest);

        $logger->info("模板已更新 [{$hour}点]: {$newName}");

        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'message'  => '上传成功',
            'template' => $newName,
            'sheets'   => $sheets,
        ], JSON_UNESCAPED_UNICODE);
    }
}
