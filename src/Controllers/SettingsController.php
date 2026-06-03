<?php
namespace App\Controllers;

/**
 * 设置页 — 全局设置 + 24 时间点配置
 */
class SettingsController
{
    public function index(): void
    {
        $config = $GLOBALS['hermes_config'];
        $hours  = $config['hours'];
        $pzoom  = $config['pzoom'];
        $alert  = $config['alert_webhook'] ?? '';
        $feishu = $config['feishu'] ?? [];
        $tplDir = $config['template_dir'];

        // 预扫描每个已启用时间点的模板导出 sheet
        $hourSheets = [];
        foreach ($hours as $h => $cfg) {
            if (empty($cfg['template'])) continue;
            $file = $tplDir . '/' . $cfg['template'];
            if (file_exists($file)) {
                $hourSheets[$h] = scanExportSheets($file);
            }
        }

        render('settings', compact('hours', 'pzoom', 'alert', 'feishu', 'hourSheets'));
    }

    public function save(): void
    {
        $config = $GLOBALS['hermes_config'];
        $logger = $GLOBALS['hermes_logger'];
        $store  = new \App\SettingsStore($config['data_dir']);
        $updates = [];

        if (!empty($_POST['pzoom_username'])) {
            $updates['pzoom']['username'] = $_POST['pzoom_username'];
        }
        if (!empty($_POST['pzoom_password'])) {
            $updates['pzoom']['password'] = $_POST['pzoom_password'];
        }
        if (isset($_POST['alert_webhook'])) {
            $updates['alert_webhook'] = $_POST['alert_webhook'];
        }
        if (!empty($_POST['feishu_app_id'])) {
            $updates['feishu']['app_id'] = $_POST['feishu_app_id'];
        }
        if (!empty($_POST['feishu_app_secret'])) {
            $updates['feishu']['app_secret'] = $_POST['feishu_app_secret'];
        }

        // 时间点配置
        $hoursPost = json_decode($_POST['hours'] ?? '{}', true);
        if (is_array($hoursPost)) {
            // 提取全局设置
            if (isset($hoursPost['_global'])) {
                $g = $hoursPost['_global'];
                if (isset($g['alert_webhook'])) {
                    $updates['alert_webhook'] = $g['alert_webhook'];
                }
                if (isset($g['pzoom_username'])) {
                    $updates['pzoom']['username'] = $g['pzoom_username'];
                }
                if (isset($g['pzoom_password'])) {
                    $updates['pzoom']['password'] = $g['pzoom_password'];
                }
                if (isset($g['feishu_app_id'])) {
                    $updates['feishu']['app_id'] = $g['feishu_app_id'];
                }
                if (isset($g['feishu_app_secret'])) {
                    $updates['feishu']['app_secret'] = $g['feishu_app_secret'];
                }
                unset($hoursPost['_global']);
            }
            foreach ($hoursPost as $h => $cfg) {
                $h = (int)$h;
                if ($h < 0 || $h > 23) continue;
                if (isset($cfg['enabled'])) {
                    $updates['hours'][$h]['enabled'] = (bool)$cfg['enabled'];
                }
                if (!empty($cfg['data_prefix'])) {
                    $updates['hours'][$h]['data_prefix'] = $cfg['data_prefix'];
                }
                if (!empty($cfg['copy_range_start']) && !empty($cfg['copy_range_end'])) {
                    $updates['hours'][$h]['copy_range'] = [$cfg['copy_range_start'], $cfg['copy_range_end']];
                }
                if (!empty($cfg['exports']) && is_array($cfg['exports'])) {
                    // Parse cell_ranges from comma-separated string to array
                    $parsed = [];
                    foreach ($cfg['exports'] as $k => $v) {
                        if (is_array($v)) {
                            // Handle cr_start[]/cr_end[] → cell_ranges
                            if (isset($v['cr_start']) && is_array($v['cr_start'])) {
                                $ranges = [];
                                foreach ($v['cr_start'] as $i => $s) {
                                    $s = trim($s);
                                    $e = trim($v['cr_end'][$i] ?? '');
                                    if ($s && $e) $ranges[] = '数据!' . $s . ':' . $e;
                                }
                                $v['cell_ranges'] = $ranges;
                                unset($v['cr_start'], $v['cr_end']);
                            } elseif (isset($v['cell_ranges'])) {
                                // Old format: comma-separated string
                                if (is_string($v['cell_ranges'])) {
                                    $v['cell_ranges'] = array_map('trim', explode(',', $v['cell_ranges']));
                                }
                            }
                            $parsed[$k] = $v;
                        } else {
                            $parsed[$k] = $v;
                        }
                    }
                    $updates['hours'][$h]['exports'] = $parsed;
                }
            }
        }

        if (!empty($updates)) {
            $store->merge($updates);
            $logger->info('配置已更新');
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '保存成功'], JSON_UNESCAPED_UNICODE);
    }
}
