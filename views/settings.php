<?php $view = 'settings'; ?>
<h1>设置</h1>

<form method="POST" action="/settings" id="settingsForm" class="settings-form">

<div class="card" id="globalSettings">
    <div class="card-header">
        <h2>全局设置</h2>
        <button type="submit" class="btn btn-primary btn-sm">保存全部设置</button>
    </div>
    <div class="form-grid">
        <label>pzoom 用户名
            <input type="text" name="pzoom_username" value="<?= h($pzoom['username'] ?? '') ?>">
        </label>
        <label>pzoom 密码
            <input type="password" name="pzoom_password" value="<?= h($pzoom['password'] ?? '') ?>">
        </label>
        <label>告警 Webhook
            <input type="text" name="alert_webhook" value="<?= h($alert) ?>" placeholder="失败时推送到此飞书机器人">
        </label>
        <label>飞书 App ID
            <input type="text" name="feishu_app_id" value="<?= h($feishu['app_id'] ?? '') ?>" placeholder="cli_xxxxxxxxxxxxxxxxxx">
        </label>
        <label>飞书 App Secret
            <input type="password" name="feishu_app_secret" value="<?= h($feishu['app_secret'] ?? '') ?>" placeholder="应用密钥">
        </label>
    </div>
</div>

<div class="card settings-card">
    <div class="card-header">
        <h2>时间点配置 <small>（开启 = 定时整点自动执行）</small></h2>
        <div class="filter-group">
            <a href="?filter=on" class="btn btn-sm <?= ($_GET['filter'] ?? 'all') === 'on' ? 'active' : '' ?>">已开启</a>
            <a href="?filter=all" class="btn btn-sm <?= ($_GET['filter'] ?? 'all') === 'all' ? 'active' : '' ?>">全部</a>
            <a href="?filter=off" class="btn btn-sm <?= ($_GET['filter'] ?? '') === 'off' ? 'active' : '' ?>">未开启</a>
        </div>
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
    <div class="hour-section<?= $collapsed ?>" id="hour<?= $h ?>">
        <div class="hour-header" onclick="toggleHour(<?= $h ?>)">
            <span class="hour-dot <?= empty($cfg['enabled']) ? '' : 'on' ?>"></span>
            <span class="hour-label"><?= $h ?>:00</span>
            <span class="hour-status"><?= empty($cfg['enabled']) ? '关闭' : '已开启' ?></span>
            <?php if (!empty($cfg['template'])): ?>
                <span class="hour-tmpl"><?= h($cfg['template']) ?></span>
            <?php endif; ?>
            <span class="hour-arrow">›</span>
        </div>

        <div class="hour-body">
            <label class="enable-label">
                <input type="hidden" name="hours[<?= $h ?>][enabled]" value="0">
                <input type="checkbox" name="hours[<?= $h ?>][enabled]" value="1" <?= empty($cfg['enabled']) ? '' : 'checked' ?>>
                <span>启用此时间点</span>
            </label>

            <div class="form-grid form-grid-sm">
                <label>数据前缀
                    <input type="text" name="hours[<?= $h ?>][data_prefix]"
                           value="<?= h($cfg['data_prefix'] ?? '') ?>" placeholder="例: 0940_时报">
                </label>
                <label>列范围
                    <div class="range-inputs">
                        <input type="text" name="hours[<?= $h ?>][copy_range_start]"
                               value="<?= h($cfg['copy_range'][0] ?? 'A') ?>" maxlength="2" placeholder="A">
                        <span class="range-sep">—</span>
                        <input type="text" name="hours[<?= $h ?>][copy_range_end]"
                               value="<?= h($cfg['copy_range'][1] ?? 'K') ?>" maxlength="2" placeholder="K">
                    </div>
                </label>
            </div>

            <div class="template-row">
                <span class="template-label">模板</span>
                <?php if (!empty($cfg['template'])): ?>
                    <code class="template-name"><?= h($cfg['template']) ?></code>
                <?php else: ?>
                    <span class="template-empty">未上传</span>
                <?php endif; ?>
                <form class="tpl-upload" data-hour="<?= $h ?>">
                    <input type="file" name="template" accept=".xlsx">
                    <button type="submit" class="btn btn-sm">上传</button>
                </form>
                <span class="tpl-msg" id="tplMsg<?= $h ?>"></span>
            </div>

            <?php if (!empty($sheets)): ?>
            <div class="exports-section">
                <div class="exports-heading">推送配置</div>
                <?php
                $exports = $cfg['exports'] ?? [];
                foreach ($sheets as $sheet):
                    $exp = $exports[$sheet] ?? '';
                    $wh = is_string($exp) ? $exp : ($exp['webhook'] ?? '');
                    $ranges = is_array($exp) ? ($exp['cell_ranges'] ?? []) : [];
                    $textStart = is_array($exp) ? ($exp['text_row_start'] ?? 50) : 50;
                    $textEnd = is_array($exp) ? ($exp['text_row_end'] ?? 60) : 60;
                ?>
                <div class="export-row">
                    <div class="export-row-head">
                        <span class="sheet-badge"><?= h($sheet) ?></span>
                        <div class="text-rows">
                            <span class="text-rows-label">文案行数</span>
                            <input type="number" name="hours[<?= $h ?>][exports][<?= h($sheet) ?>][text_row_start]"
                                   value="<?= h($textStart) ?>" min="1" max="9999" class="text-row-input">
                            <span class="text-rows-sep">—</span>
                            <input type="number" name="hours[<?= $h ?>][exports][<?= h($sheet) ?>][text_row_end]"
                                   value="<?= h($textEnd) ?>" min="1" max="9999" class="text-row-input">
                        </div>
                    </div>
                    <div class="export-webhook">
                        <input type="text" name="hours[<?= $h ?>][exports][<?= h($sheet) ?>][webhook]"
                               value="<?= h($wh) ?>" placeholder="飞书 webhook URL">
                    </div>
                    <div class="cell-ranges" data-sheet="<?= h($sheet) ?>" data-hour="<?= $h ?>">
                        <div class="cr-heading">截图区域</div>
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
                            <button type="button" class="btn-cr-del" onclick="this.parentElement.remove()">×</button>
                        </div>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-sm btn-cr-add" onclick="addRange(this)">+ 添加区域</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($cfg['template'])): ?>
                <p class="no-sheet-hint">模板中无导出 sheet</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endfor; ?>
    </div>
</div>

</form>

<script>
function toggleHour(h) {
    document.getElementById('hour' + h).classList.toggle('collapsed');
}

document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const hoursData = {};
    const crMap = {};

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

    for (let [key, val] of fd.entries()) {
        if (key === 'alert_webhook' || key.startsWith('pzoom_') || key.startsWith('feishu_')) {
            if (!hoursData['_global']) hoursData['_global'] = {};
            hoursData['_global'][key] = val;
            continue;
        }
        const m = key.match(/^hours\[(\d+)\]\[(\w+)\](?:\[([^\]]+)\])?(?:\[([^\]]+)\])?$/);
        if (!m) continue;
        const [, hour, field, sub, sub2] = m;
        if (!hoursData[hour]) hoursData[hour] = {};
        if (field === 'exports') {
            if (!hoursData[hour].exports) hoursData[hour].exports = {};
            if (sub2 === 'cr_start' || sub2 === 'cr_end') continue;
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
            showToast(d.success ? '保存成功' : ('失败: ' + (d.error || d.message)), d.success);
            if (d.success) setTimeout(() => location.reload(), 800);
        });
});

function showToast(msg, ok) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.className = 'toast ' + (ok ? 'toast-ok' : 'toast-err');
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 500); }, 2000);
}

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
        msg.textContent = '上传中…';
        try {
            const r = await fetch('/templates/upload', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                msg.innerHTML = '<span class="msg-ok">成功</span>';
                document.getElementById('settingsForm').requestSubmit();
            } else {
                msg.innerHTML = '<span class="msg-err">' + (d.error || d.message) + '</span>';
            }
        } catch(e) {
            msg.innerHTML = '<span class="msg-err">错误</span>';
        }
    });
});

function addRange(btn) {
    const container = btn.closest('.cell-ranges');
    const hour = container.dataset.hour;
    const sheet = container.dataset.sheet;
    const row = document.createElement('div');
    row.className = 'cr-row';
    row.innerHTML = '<span class="cr-num"></span><span class="cr-prefix">数据!</span>' +
        '<input type="text" name="hours[' + hour + '][exports][' + sheet + '][cr_start][]" placeholder="A1" class="cr-input">' +
        '<span class="cr-sep">:</span>' +
        '<input type="text" name="hours[' + hour + '][exports][' + sheet + '][cr_end][]" placeholder="N23" class="cr-input">' +
        '<button type="button" class="btn-cr-del" onclick="this.parentElement.remove()">×</button>';
    container.insertBefore(row, btn);
    container.querySelectorAll('.cr-row').forEach((r, i) => r.querySelector('.cr-num').textContent = (i+1) + '.');
}
</script>

<style>
/* ===== Settings Page — Scandinavian ===== */

.settings-form { flex: 1; display: flex; flex-direction: column; gap: 16px; min-height: 0; }

/* Card header */
.card-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; flex-wrap: wrap; gap: 8px;
}

/* Filter buttons */
.filter-group { display: flex; gap: 4px; }
.filter-group .btn.active {
    background: var(--accent); color: #fff; border-color: var(--accent);
}

/* Form grid */
.form-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;
}
.form-grid-sm { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 16px; }

/* Range inputs */
.range-inputs { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.range-inputs input { width: 60px; text-align: center; }
.range-sep { color: var(--text-muted); font-weight: 200; }

/* Template row */
.template-row {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);
}
.template-label { font-size: 13px; color: var(--text-secondary); font-weight: 300; }
.template-name {
    font-size: 12px; background: var(--bg); padding: 3px 8px;
    border-radius: 4px; color: var(--text); font-family: 'SF Mono', monospace;
}
.template-empty { font-size: 12px; color: var(--text-muted); }
.tpl-upload { display: flex; align-items: center; gap: 6px; }
.tpl-upload input[type="file"] { font-size: 12px; max-width: 160px; }
.tpl-msg { font-size: 12px; }
.msg-ok { color: var(--accent); }
.msg-err { color: #c0504d; }

/* Hours scroll */
.settings-card { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.settings-card > .card-header { flex-shrink: 0; }
.hours-scroll { flex: 1; overflow-y: auto; padding: 2px; }

/* Hour section */
.hour-section {
    border: 1px solid var(--border); border-radius: var(--radius);
    margin-bottom: 8px; background: var(--bg-card);
    transition: var(--transition);
}
.hour-section:hover { border-color: var(--wood); }
.hour-section.collapsed .hour-body { display: none; }
.hour-section.collapsed .hour-arrow { transform: rotate(0deg); }

.hour-header {
    padding: 12px 16px; cursor: pointer;
    display: flex; align-items: center; gap: 12px;
    user-select: none; transition: var(--transition);
}
.hour-header:hover { background: var(--bg); }

/* Dot indicator */
.hour-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--border); flex-shrink: 0;
    transition: var(--transition);
}
.hour-dot.on { background: var(--accent); }

.hour-label { font-size: 14px; font-weight: 400; color: var(--text); min-width: 40px; }
.hour-status { font-size: 12px; color: var(--text-muted); font-weight: 300; }
.hour-tmpl {
    font-size: 11px; color: var(--text-muted); font-weight: 300;
    margin-left: auto; font-family: 'SF Mono', monospace;
    background: var(--bg); padding: 2px 6px; border-radius: 3px;
}
.hour-arrow {
    color: var(--text-muted); font-size: 16px; font-weight: 200;
    transition: transform 0.3s ease-in-out; margin-left: 8px;
    transform: rotate(90deg);
}

/* Hour body */
.hour-body { padding: 16px; border-top: 1px solid var(--border-light); }

/* Enable label */
.enable-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--text); cursor: pointer; font-weight: 300;
}
.enable-label input[type="checkbox"] {
    width: 14px; height: 14px; margin: 0; accent-color: var(--accent);
}

/* Exports section */
.exports-section {
    margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-light);
}
.exports-heading {
    font-size: 13px; font-weight: 400; color: var(--text);
    letter-spacing: 0.04em; margin-bottom: 12px;
}

/* Export row */
.export-row {
    background: var(--bg); border: 1px solid var(--border-light);
    border-radius: var(--radius); padding: 14px; margin-bottom: 10px;
    transition: var(--transition);
}
.export-row:hover { border-color: var(--wood); }

.export-row-head {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 10px;
}
.sheet-badge {
    display: inline-block; background: #eef3f0; color: var(--accent);
    padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 400;
    letter-spacing: 0.02em; white-space: nowrap;
}

/* Text rows */
.text-rows { display: flex; align-items: center; gap: 6px; }
.text-rows-label { font-size: 12px; color: var(--text-muted); white-space: nowrap; font-weight: 300; }
.text-row-input {
    width: 64px; padding: 4px 6px; text-align: center;
    border: 1px solid var(--border); border-radius: 4px;
    font-size: 12px; font-weight: 300; color: var(--text);
    background: var(--bg-card); transition: var(--transition); outline: none;
}
.text-row-input:focus { border-color: var(--accent); }
.text-rows-sep { color: var(--text-muted); font-weight: 200; }

/* Webhook input */
.export-webhook { margin-bottom: 10px; }
.export-webhook input {
    width: 100%; padding: 7px 10px;
    border: 1px solid var(--border); border-radius: var(--radius);
    font-size: 12px; font-weight: 300; color: var(--text);
    background: var(--bg-card); transition: var(--transition); outline: none;
}
.export-webhook input:focus { border-color: var(--accent); }
.export-webhook input::placeholder { color: var(--text-muted); }

/* Cell ranges */
.cr-heading { font-size: 11px; color: var(--text-muted); font-weight: 300; margin-bottom: 6px; letter-spacing: 0.04em; text-transform: uppercase; }
.cr-row { display: flex; gap: 4px; align-items: center; margin-bottom: 4px; }
.cr-num { font-size: 11px; color: var(--text-muted); min-width: 18px; text-align: right; font-weight: 300; }
.cr-prefix { font-size: 11px; color: var(--text-muted); font-family: 'SF Mono', monospace; }
.cr-input {
    width: 72px; padding: 4px 6px; text-align: center;
    border: 1px solid var(--border); border-radius: 4px;
    font-size: 12px; font-weight: 300; color: var(--text);
    background: var(--bg-card); transition: var(--transition); outline: none;
}
.cr-input:focus { border-color: var(--accent); }
.cr-sep { color: var(--text-muted); font-weight: 200; }
.btn-cr-del {
    background: none; border: none; color: var(--text-muted);
    cursor: pointer; font-size: 14px; padding: 2px 6px; line-height: 1;
    transition: var(--transition); border-radius: 3px;
}
.btn-cr-del:hover { color: #c0504d; background: #fdf5f4; }
.btn-cr-add { margin-top: 6px; color: var(--accent); border-color: var(--accent); }
.btn-cr-add:hover { background: #eef3f0; }

/* No sheet hint */
.no-sheet-hint { font-size: 12px; color: var(--text-muted); margin-top: 12px; font-weight: 300; }

/* Toast */
.toast {
    position: fixed; bottom: 24px; right: 24px;
    padding: 12px 20px; border-radius: var(--radius);
    font-size: 13px; font-weight: 300; z-index: 9999;
    transform: translateY(8px); opacity: 0;
    transition: all 0.5s ease-in-out;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast-ok { background: var(--accent); color: #fff; }
.toast-err { background: #c0504d; color: #fff; }

/* Page-level scroll override */
body { overflow: hidden; }
</style>
