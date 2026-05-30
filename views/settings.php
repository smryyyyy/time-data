<?php $view = 'settings'; ?>
<h1>设置</h1>

<form method="POST" action="/settings" id="settingsForm">

<div class="card" id="globalSettings">
    <div class="card-header-flex">
        <h2>全局设置</h2>
        <button type="submit" class="btn btn-primary btn-sm">保存全部设置</button>
    </div>
    <div class="form-grid">
        <label>pzoom 用户名：
            <input type="text" name="pzoom_username" value="<?= h($pzoom['username'] ?? '') ?>">
        </label>
        <label>pzoom 密码：
            <input type="password" name="pzoom_password" value="<?= h($pzoom['password'] ?? '') ?>">
        </label>
        <label>告警 Webhook：
            <input type="text" name="alert_webhook" value="<?= h($alert) ?>" placeholder="失败时推送到此飞书机器人">
        </label>
    </div>
</div>
</div>

<div class="settings-main">
<div class="card">
    <h2>时间点配置 <small style="color:#888">（开启=定时整点自动执行）</small></h2>
    <div style="margin-bottom:12px">
        <a href="?filter=on" class="btn btn-sm <?= ($_GET['filter'] ?? 'all') === 'on' ? 'btn-primary' : '' ?>">已开启</a>
        <a href="?filter=all" class="btn btn-sm <?= ($_GET['filter'] ?? 'all') === 'all' ? 'btn-primary' : '' ?>">全部</a>
        <a href="?filter=off" class="btn btn-sm <?= ($_GET['filter'] ?? '') === 'off' ? 'btn-primary' : '' ?>">未开启</a>
    </div>
    <div class="hours-scroll">

    <?php
    $filter = $_GET['filter'] ?? 'all';
    for ($h = 0; $h < 24; $h++):
        $cfg = $hours[$h] ?? ['enabled' => false, 'data_prefix' => '', 'copy_range' => ['A', 'K'], 'template' => '', 'exports' => []];
        if ($filter === 'on' && empty($cfg['enabled'])) continue;
        if ($filter === 'off' && !empty($cfg['enabled'])) continue;
        $sheets = $hourSheets[$h] ?? [];
        $collapsed = empty($cfg['enabled']) ? ' collapsed' : '';
    ?>
    <div class="hour-section <?= $collapsed ?>" id="hour<?= $h ?>">
        <div class="hour-header" onclick="toggleHour(<?= $h ?>)">
            <span class="hour-toggle"><?= empty($cfg['enabled']) ? '○' : '●' ?></span>
            <strong><?= $h ?>点</strong>
            <span class="hour-status"><?= empty($cfg['enabled']) ? '关闭' : '开启（整点自动执行）' ?></span>
            <?php if (!empty($cfg['template'])): ?>
                <span class="hour-tmpl"><?= h($cfg['template']) ?></span>
            <?php endif; ?>
            <span class="hour-arrow">▼</span>
        </div>

        <div class="hour-body">
            <input type="hidden" name="hours[<?= $h ?>][enabled]" value="0">
            <label><input type="checkbox" name="hours[<?= $h ?>][enabled]" value="1" <?= empty($cfg['enabled']) ? '' : 'checked' ?>>
                开启（整点自动执行 <?= $h ?>:00）</label>

            <div class="form-grid" style="margin-top:8px">
                <label>数据前缀：
                    <input type="text" name="hours[<?= $h ?>][data_prefix]"
                           value="<?= h($cfg['data_prefix'] ?? '') ?>" placeholder="例: 0940_时报">
                </label>
                <label>列范围 从：
                    <input type="text" name="hours[<?= $h ?>][copy_range_start]"
                           value="<?= h($cfg['copy_range'][0] ?? 'A') ?>" maxlength="2" style="width:60px">
                    到
                    <input type="text" name="hours[<?= $h ?>][copy_range_end]"
                           value="<?= h($cfg['copy_range'][1] ?? 'K') ?>" maxlength="2" style="width:60px">
                </label>
            </div>

            <div style="margin-top:8px">
                <span>模板：</span>
                <?php if (!empty($cfg['template'])): ?>
                    <code><?= h($cfg['template']) ?></code>
                <?php else: ?>
                    <span style="color:#888">未上传</span>
                <?php endif; ?>
                <form class="tpl-upload" data-hour="<?= $h ?>" style="display:inline;margin-left:8px">
                    <input type="file" name="template" accept=".xlsx" style="font-size:12px">
                    <button type="submit" class="btn btn-sm">上传</button>
                </form>
                <span class="tpl-msg" id="tplMsg<?= $h ?>" style="margin-left:8px;font-size:12px"></span>
            </div>

            <?php if (!empty($sheets)): ?>
            <div class="exports-section" style="margin-top:12px">
                <strong>推送配置</strong>
                <p style="color:#666;font-size:12px;margin:4px 0">每个 sheet 配置飞书 webhook 和截图单元格范围</p>
                <?php
                $exports = $cfg['exports'] ?? [];
                foreach ($sheets as $sheet):
                    $exp = $exports[$sheet] ?? '';
                    $wh = is_string($exp) ? $exp : ($exp['webhook'] ?? '');
                    $ranges = is_array($exp) ? ($exp['cell_ranges'] ?? []) : [];
                    $rangesStr = implode(', ', $ranges);
                ?>
                    <div class="export-row">
                        <span class="sheet-badge"><?= h($sheet) ?></span>
                        <div class="export-fields">
                            <input type="text" name="hours[<?= $h ?>][exports][<?= h($sheet) ?>][webhook]"
                                   value="<?= h($wh) ?>" placeholder="飞书 webhook URL">
                        </div>
                        <div class="cell-ranges" data-sheet="<?= h($sheet) ?>" data-hour="<?= $h ?>">
                            <div class="cr-label">导出图片区域</div>
                            <?php foreach ($ranges as $ri => $r):
                                $parts = explode(':', str_replace('数据!', '', $r));
                                $start = $parts[0] ?? '';
                                $end = $parts[1] ?? '';
                            ?>
                            <div class="cr-row">
                                <span class="cr-num"><?= $ri + 1 ?>.</span>
                                <span class="cr-prefix">数据!</span>
                                <input type="text" name="hours[<?= $h ?>][exports][<?= h($sheet) ?>][cr_start][]"
                                       value="<?= h($start) ?>" placeholder="A1" class="cr-input">
                                <span class="cr-sep">:</span>
                                <input type="text" name="hours[<?= $h ?>][exports][<?= h($sheet) ?>][cr_end][]"
                                       value="<?= h($end) ?>" placeholder="N23" class="cr-input">
                                <button type="button" class="btn-sm btn-del" onclick="this.parentElement.remove()">−</button>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-sm btn-add" onclick="addRange(this)">+</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($cfg['template'])): ?>
                <p style="color:#888;margin-top:12px">模板中无导出 sheet</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endfor; ?>
    </div>
</div>

</form>

<script>
function toggleHour(h) { document.getElementById('hour' + h).classList.toggle('collapsed'); }

document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const hoursData = {};
    
    // Collect cr_start/cr_end arrays per sheet first
    const crMap = {}; // "hour-sheet" => {starts:[], ends:[]}
    
    for (let [key, val] of fd.entries()) {
        let m = key.match(/^hours\[(\d+)\]\[exports\]\[([^\]]+)\]\[cr_start\]\[\]$/);
        if (m) {
            const k = m[1] + '-' + m[2];
            if (!crMap[k]) crMap[k] = {starts:[], ends:[]};
            crMap[k].starts.push(val);
            continue;
        }
        m = key.match(/^hours\[(\d+)\]\[exports\]\[([^\]]+)\]\[cr_end\]\[\]$/);
        if (m) {
            const k = m[1] + '-' + m[2];
            if (!crMap[k]) crMap[k] = {starts:[], ends:[]};
            crMap[k].ends.push(val);
            continue;
        }
    }
    
    // Now parse all fields
    for (let [key, val] of fd.entries()) {
        const m = key.match(/^hours\[(\d+)\]\[(\w+)\](?:\[([^\]]+)\])?(?:\[([^\]]+)\])?$/);
        if (!m) continue;
        const [, hour, field, sub, sub2] = m;
        if (!hoursData[hour]) hoursData[hour] = {};
        if (field === 'exports') {
            if (!hoursData[hour].exports) hoursData[hour].exports = {};
            if (sub2 === 'cr_start' || sub2 === 'cr_end') continue; // handled separately
            if (sub2) {
                if (!hoursData[hour].exports[sub]) hoursData[hour].exports[sub] = {};
                hoursData[hour].exports[sub][sub2] = val;
            } else {
                hoursData[hour].exports[sub] = val;
            }
        } else {
            hoursData[hour][field] = val;
        }
    }
    
    // Merge cr_start/cr_end into cell_ranges
    for (const [key, data] of Object.entries(crMap)) {
        const [hour, sheet] = key.split('-');
        const ranges = [];
        for (let i = 0; i < data.starts.length; i++) {
            const s = data.starts[i].trim();
            const e = (data.ends[i] || '').trim();
            if (s && e) ranges.push('数据!' + s + ':' + e);
        }
        if (!hoursData[hour]) hoursData[hour] = {};
        if (!hoursData[hour].exports) hoursData[hour].exports = {};
        if (!hoursData[hour].exports[sheet]) hoursData[hour].exports[sheet] = {};
        hoursData[hour].exports[sheet].cell_ranges = ranges;
    }
    
    fd.set('hours', JSON.stringify(hoursData));
    fetch('/settings', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showToast(d.success ? '保存成功 ✅' : ('失败: ' + (d.error || d.message)), d.success);
            if (d.success) setTimeout(() => location.reload(), 1000);
        });
});

function showToast(msg, ok) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;transition:.3s;' +
        (ok ? 'background:#16a34a;' : 'background:#dc2626;');
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
}

// Template upload
document.querySelectorAll('.tpl-upload').forEach(f => {
    f.addEventListener('submit', async function(e) {
        e.preventDefault();
        const h = this.dataset.hour;
        const fd = new FormData();
        fd.set('hour', h);
        const fi = this.querySelector('input[type=file]');
        if (!fi.files[0]) { document.getElementById('tplMsg' + h).textContent = '请选择文件'; return; }
        fd.set('template', fi.files[0]);
        const msg = document.getElementById('tplMsg' + h);
        msg.textContent = '上传中...';
        try {
            const r = await fetch('/templates/upload', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                msg.innerHTML = '<span style="color:green">\u2713 成功</span>';
                // Auto-save settings form
                document.getElementById('settingsForm').requestSubmit();
            } else {
                msg.innerHTML = '<span style="color:red">\u2717 ' + (d.error || d.message) + '</span>';
            }
        } catch(e) {
            msg.innerHTML = '<span style="color:red">错误: ' + e + '</span>';
        }
    });
});
</script>
<script>
function addRange(btn) {
    const container = btn.closest('.cell-ranges');
    const hour = container.dataset.hour;
    const sheet = container.dataset.sheet;
    const row = document.createElement('div');
    row.className = 'cr-row';
    row.innerHTML = '<span class="cr-num"></span><span class="cr-prefix">数据!</span><input type="text" name="hours[' + hour + '][exports][' + sheet + '][cr_start][]" value="" placeholder="A1" class="cr-input"><span class="cr-sep">:</span><input type="text" name="hours[' + hour + '][exports][' + sheet + '][cr_end][]" value="" placeholder="N23" class="cr-input"> <button type="button" class="btn-sm btn-del" onclick="this.parentElement.remove()">−</button>';
    container.insertBefore(row, btn);
    // Re-number all rows
    const rows = container.querySelectorAll('.cr-row');
    rows.forEach((r, i) => r.querySelector('.cr-num').textContent = (i+1) + '.');
}
</script>

<style>
.hour-section { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 8px; }
.hour-section.collapsed .hour-body { display: none; }
.hour-section.collapsed .hour-arrow { transform: rotate(-90deg); }
.hour-header { padding: 10px 14px; cursor: pointer; display: flex; align-items: center; gap: 10px; background: #f8f8f8; border-radius: 6px; user-select: none; }
.hour-header:hover { background: #eee; }
.hour-toggle { color: #4caf50; font-size: 18px; }
.hour-status { color: #888; font-size: 13px; }
.hour-tmpl { color: #666; font-size: 13px; margin-left: auto; }
.hour-arrow { transition: transform .2s; }
.hour-body { padding: 12px 14px; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; }
.form-grid label { display: block; }
.form-grid input[type="text"], .form-grid input[type="password"] { width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; }
.export-row { margin-bottom: 6px; }
.export-fields { display: flex; gap: 8px; margin-top: 4px; }
.export-fields input[type="text"] { flex: 1; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
.cr-row { display: flex; gap: 4px; margin-top: 4px; align-items: center; }
.cr-label { font-size: 13px; color: #555; margin-bottom: 4px; }
.cr-num { font-size: 13px; color: #888; min-width: 18px; }
.cr-prefix { color: #666; font-size: 13px; font-family: monospace; }
.cr-sep { color: #666; font-size: 13px; }
.cr-input { width: 100px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; text-align: center; }
.btn-del { background: #fee2e2; border: 1px solid #fecaca; border-radius: 4px; cursor: pointer; font-size: 14px; padding: 2px 8px; color: #dc2626; }
.btn-add { margin-top: 4px; }
.export-row label { display: block; font-size: 14px; }
.sheet-badge { display: inline-block; background: #e8f0fe; color: #1a73e8; padding: 2px 8px; border-radius: 3px; font-size: 13px; font-weight: 600; }
.btn-sm { font-size: 13px; padding: 4px 10px; }
.btn-lg { padding: 10px 24px; font-size: 16px; margin-top: 16px; }
.card-header-flex { display: flex; justify-content: space-between; align-items: center; }
.hours-scroll { max-height: calc(100vh - 380px); overflow-y: auto; }
/* 设置页整体不滚动 */
body { overflow: hidden; }
.container { overflow: hidden; }
</style>
