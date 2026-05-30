<?php $view = 'logs'; ?>
<h1>日志</h1>

<div class="card">
    <div class="log-toolbar">
        <select id="dateSelect" onchange="loadLog(this.value)">
            <?php foreach ($dates as $d): ?>
            <option value="<?= h($d) ?>" <?= $d === $selected ? 'selected' : '' ?>><?= h($d) ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="refreshLog()" class="btn btn-sm">刷新</button>
    </div>
    <pre id="logContent" class="log-box"><?= h($logText ?: '(暂无日志)') ?></pre>
</div>

<script>
function loadLog(date) {
    fetch('/logs/view?date=' + date)
        .then(r => r.text())
        .then(t => {
            const el = document.getElementById('logContent');
            if (!t || t === '(空)') {
                el.textContent = '(暂无日志)';
                return;
            }
            const lines = t.split('\n').filter(l => l).reverse();
            let html = '';
            for (const l of lines) {
                if (l.includes('[ERROR]') || l.includes('失败') || l.includes('错误')) html += '<span style="color:#b91c1c">' + l + '</span>\n';
                else if (l.includes('[WARN]')) html += '<span style="color:#166534">' + l + '</span>\n';
                else html += l + '\n';
            }
            el.innerHTML = html;
        });
}
function refreshLog() { loadLog(document.getElementById('dateSelect').value); }
document.addEventListener('DOMContentLoaded', refreshLog);
// Auto-refresh every 3s
let timer = setInterval(refreshLog, 3000);
</script>
