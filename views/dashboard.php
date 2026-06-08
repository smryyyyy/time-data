<?php $view = 'dashboard'; ?>
<h1>仪表盘</h1>
<p style="color:var(--text-muted)">今天：<?= h($today) ?></p>

<!-- 存储容量 -->
<div class="storage-bar">
    <strong>历史数据存储</strong>
    <span class="storage-items">
    <?php foreach ($storage as $label => $s): ?>
        <span class="storage-item"><?= h($label) ?>: <?= formatSize($s['size']) ?> (<?= $s['files'] ?> 个)</span>
    <?php endforeach; ?>
    </span>
    <div class="storage-actions">
        <button onclick="updateCode()" class="btn btn-sm">在线更新</button>
        <button onclick="cleanupData(4)" class="btn btn-sm">清理 4 天前</button>
        <button onclick="cleanupData(0)" class="btn btn-sm btn-danger">全部清理</button>
    </div>
</div>

<div class="filter-bar">
    <a href="?filter=on" class="btn btn-sm <?= ($_GET['filter'] ?? 'on') === 'on' ? 'active' : '' ?>">已开启</a>
    <a href="?filter=all" class="btn btn-sm <?= ($_GET['filter'] ?? 'on') === 'all' ? 'active' : '' ?>">全部</a>
</div>

<div class="hour-grid">
<?php
$filter = $_GET['filter'] ?? 'on';
foreach ($hours as $h => $cfg):
    if (empty($cfg['enabled']) && $filter === 'on') continue;

    $status = $statuses[$h] ?? 'waiting';
    $statusIcon = ['done' => '✓', 'fail' => '✗', 'waiting' => '…'][$status];
    $statusText = ['done' => '已完成', 'fail' => '失败', 'waiting' => '等待中'][$status];

    if (empty($cfg['enabled'])) {
        $statusIcon = '○';
        $statusText = '已关闭';
    }
?>
    <div class="hour-card <?= empty($cfg['enabled']) ? 'disabled' : $status ?>">
        <div class="card-header">
            <span class="card-hour"><?= $h ?>:00</span>
            <span class="card-status card-status-<?= empty($cfg['enabled']) ? 'off' : $status ?>">
                <?= $statusIcon ?> <?= $statusText ?>
            </span>
        </div>
        <?php if (!empty($cfg['template'])): ?>
            <div class="card-tmpl"><?= h($cfg['template']) ?></div>
        <?php endif; ?>
        <?php if (!empty($cfg['enabled'])): ?>
            <button class="btn btn-sm btn-run" onclick="runHour(<?= $h ?>)">手动执行</button>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<!-- 手动执行弹窗 -->
<div id="runModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h3>手动执行 — <span id="runHourLabel"></span> 点</h3>
        <label style="margin:12px 0">日期
            <input type="date" id="runDate" value="<?= $today ?>">
        </label>
        <div style="display:flex;gap:8px;margin-top:12px">
            <button class="btn btn-primary" onclick="doRun()">开始执行</button>
            <button class="btn" onclick="document.getElementById('runModal').style.display='none'">取消</button>
        </div>
        <pre id="runOutput" class="run-output"></pre>
    </div>
</div>

<script>
let runHourVal = 0;
function runHour(h) {
    runHourVal = h;
    document.getElementById('runHourLabel').innerText = h;
    document.getElementById('runModal').style.display = 'flex';
    document.getElementById('runOutput').innerText = '';
    document.getElementById('runDate').value = '<?= $today ?>';
}

async function doRun() {
    const date = document.getElementById('runDate').value;
    const out = document.getElementById('runOutput');
    out.innerText = '';
    try {
        const fd = new FormData();
        fd.set('date', date);
        fd.set('hour', runHourVal);
        const r = await fetch('/run', { method: 'POST', body: fd });
        const reader = r.body.getReader();
        const dec = new TextDecoder();
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            out.innerText += dec.decode(value);
            out.scrollTop = out.scrollHeight;
        }
    } catch(e) {
        out.innerText = '错误: ' + e;
    }
}

async function cleanupData(days) {
    const label = days > 0 ? days + '天前' : '全部';
    if (!confirm('确定清理' + label + '的历史数据？此操作不可恢复！')) return;
    try {
        const fd = new FormData();
        fd.set('days', days);
        const r = await fetch('/cleanup', { method: 'POST', body: fd });
        const result = await r.json();
        alert(result.message);
        if (result.success) location.reload();
    } catch(e) {
        alert('清理失败: ' + e);
    }
}

async function updateCode() {
    if (!confirm('确定从 GitHub 拉取最新代码？容器将短暂重启。')) return;
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '更新中…';
    try {
        const r = await fetch('/update', { method: 'POST' });
        const result = await r.json();
        alert(result.message);
        if (result.success) location.reload();
    } catch(e) {
        alert('更新失败: ' + e);
        btn.disabled = false;
        btn.textContent = '在线更新';
    }
}
</script>

<style>
/* Storage bar */
.storage-bar {
    display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
    margin-bottom: 16px; padding: 14px 16px;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
    font-size: 13px; font-weight: 300;
}
.storage-items { display: flex; flex-wrap: wrap; gap: 4px 16px; }
.storage-item { color: var(--text-secondary); }
.storage-actions { display: flex; gap: 6px; margin-left: auto; }

/* Filter bar */
.filter-bar { display: flex; gap: 4px; margin-bottom: 16px; }
.filter-bar .btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Hour grid */
.hour-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.hour-card {
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 14px; background: var(--bg-card); transition: var(--transition);
}
.hour-card:hover { border-color: var(--wood); }
.hour-card.disabled { opacity: 0.4; }
.hour-card.done { border-left: 3px solid var(--accent); }
.hour-card.fail { border-left: 3px solid #c0504d; }
.hour-card.waiting { border-left: 3px solid var(--wood); }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.card-hour { font-size: 14px; font-weight: 400; }
.card-status { font-size: 12px; font-weight: 300; }
.card-status-done { color: var(--accent); }
.card-status-fail { color: #c0504d; }
.card-status-waiting { color: var(--wood); }
.card-status-off { color: var(--text-muted); }
.card-tmpl { font-size: 11px; color: var(--text-muted); margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'SF Mono', monospace; }
.btn-run { width: 100%; margin-top: 4px; }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(61,61,61,.3);
    display: flex; align-items: center; justify-content: center; z-index: 999;
}
.modal-box {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 24px; width: 500px; max-width: 90vw;
}
.modal-box h3 { font-size: 15px; font-weight: 400; margin-bottom: 12px; }
.run-output {
    margin-top: 12px; max-height: 300px; overflow: auto;
    background: #3d3d3d; color: #d4cdc5; padding: 12px;
    border-radius: var(--radius); font-size: 12px; font-family: 'SF Mono', monospace;
    font-weight: 300; line-height: 1.7; white-space: pre-wrap;
}
</style>
