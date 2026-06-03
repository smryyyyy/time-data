<?php $view = 'dashboard'; ?>
<h1>仪表盘</h1>
<p style="color:#888">今天：<?= h($today) ?></p>

<!-- 存储容量 -->
<div class="storage-bar" style="margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:6px;font-size:13px">
    <strong>历史数据存储</strong>
    <span style="margin-left:12px;color:#555">
    <?php foreach ($storage as $label => $s): ?>
        <span style="margin-right:16px"><?= h($label) ?>: <?= formatSize($s['size']) ?> (<?= $s['files'] ?> 个)</span>
    <?php endforeach; ?>
    </span>
    <button onclick="cleanupData()" class="btn btn-sm" style="float:right">清理4天前</button>
</div>

<div class="filter-bar" style="margin-bottom:16px">
    <a href="?filter=on" class="btn btn-sm <?= ($_GET['filter'] ?? 'all') === 'on' ? 'btn-primary' : '' ?>">已开启</a>
</div>

<div class="hour-grid">
<?php
$filter = $_GET['filter'] ?? 'on';
foreach ($hours as $h => $cfg):
    if (empty($cfg['enabled']) && $filter === 'on') continue;

    $status = $statuses[$h] ?? 'waiting';
    $statusIcon = ['done' => '✅', 'fail' => '❌', 'waiting' => '⏳'][$status];
    $statusText = ['done' => '已完成', 'fail' => '失败', 'waiting' => '等待中'][$status];

    if (empty($cfg['enabled'])) {
        $statusIcon = '⚫';
        $statusText = '已关闭';
    }
?>
    <div class="hour-card <?= empty($cfg['enabled']) ? 'disabled' : $status ?>">
        <div class="card-header">
            <strong><?= $h ?>点</strong>
            <span class="card-status"><?= $statusIcon ?> <?= $statusText ?></span>
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
<div id="runModal" style="display:none; position:fixed; top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999">
    <div style="background:#fff; width:500px; margin:80px auto; padding:24px; border-radius:8px">
        <h3>手动执行 - <span id="runHourLabel"></span>点</h3>
        <label>日期：<input type="date" id="runDate" value="<?= $today ?>"></label>
        <button class="btn btn-primary" onclick="doRun()" style="margin-left:8px">开始执行</button>
        <button class="btn" onclick="document.getElementById('runModal').style.display='none'">取消</button>
        <pre id="runOutput" style="margin-top:12px;max-height:300px;overflow:auto;background:#f4f4f4;padding:8px;font-size:13px"></pre>
    </div>
</div>

<script>
let runHourVal = 0;
function runHour(h) {
    runHourVal = h;
    document.getElementById('runHourLabel').innerText = h;
    document.getElementById('runModal').style.display = 'block';
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

async function cleanupData() {
    if (!confirm('确定清理4天前的历史数据？')) return;
    try {
        const r = await fetch('/cleanup', { method: 'POST' });
        const result = await r.json();
        alert(result.message);
        if (result.success) location.reload();
    } catch(e) {
        alert('清理失败: ' + e);
    }
}
</script>

<style>
.hour-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.hour-card { border: 1px solid #ddd; border-radius: 6px; padding: 12px; }
.hour-card.disabled { opacity: 0.4; }
.hour-card.done { border-left: 4px solid #4caf50; }
.hour-card.fail { border-left: 4px solid #f44336; }
.hour-card.waiting { border-left: 4px solid #ff9800; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.card-status { font-size: 13px; }
.card-tmpl { font-size: 12px; color: #888; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.btn-run { width: 100%; margin-top: 4px; }
.log-box { font-size: 13px; max-height: 400px; overflow: auto; background: #f8f8f8; padding: 10px; border-radius: 4px; white-space: pre-wrap; }
</style>
