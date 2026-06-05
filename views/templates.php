<?php $view = 'templates'; ?>
<h1>模板管理</h1>

<div class="tpl-grid">
<?php foreach ($templates as $h => $tpl): ?>
    <div class="tpl-card">
        <div class="tpl-header">
            <span class="tpl-hour"><?= $h ?>:00</span>
            <span class="tpl-dot <?= $tpl['enabled'] ? 'on' : '' ?>"></span>
        </div>
        <?php if ($tpl['exists']): ?>
            <div class="tpl-info">
                <div>文件 <code><?= h($tpl['name']) ?></code></div>
                <div>大小 <?= formatSize($tpl['size']) ?></div>
                <?php if (!empty($tpl['sheets'])): ?>
                    <div>导出 sheet <?= implode(', ', array_map('h', $tpl['sheets'])) ?></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="tpl-empty">未上传模板</div>
        <?php endif; ?>

        <form class="upload-form" data-hour="<?= $h ?>">
            <input type="file" name="template" accept=".xlsx">
            <button type="submit" class="btn btn-sm btn-primary">上传替换</button>
        </form>
        <div class="upload-msg" id="msg<?= $h ?>"></div>
    </div>
<?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.upload-form').forEach(f => {
    f.addEventListener('submit', async function(e) {
        e.preventDefault();
        const h = this.dataset.hour;
        const fd = new FormData();
        fd.set('hour', h);
        fd.set('template', this.querySelector('input[type=file]').files[0]);
        const msg = document.getElementById('msg' + h);
        msg.innerText = '上传中…';
        try {
            const r = await fetch('/templates/upload', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                msg.innerHTML = '<span class="msg-ok">✓ ' + d.message + '</span>';
                setTimeout(() => location.reload(), 1000);
            } else {
                msg.innerHTML = '<span class="msg-err">✗ ' + (d.error || d.message) + '</span>';
            }
        } catch(e) {
            msg.innerHTML = '<span class="msg-err">错误</span>';
        }
    });
});
</script>

<style>
.tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.tpl-card {
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 16px; background: var(--bg-card); transition: var(--transition);
}
.tpl-card:hover { border-color: var(--wood); }
.tpl-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.tpl-hour { font-size: 14px; font-weight: 400; }
.tpl-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: var(--transition); }
.tpl-dot.on { background: var(--accent); }
.tpl-info { font-size: 12px; line-height: 1.8; color: var(--text-secondary); font-weight: 300; }
.tpl-info code { background: var(--bg); padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.tpl-empty { font-size: 12px; color: var(--text-muted); font-weight: 300; margin-bottom: 8px; }
.upload-form { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
.upload-form input[type="file"] { font-size: 12px; max-width: 160px; }
.upload-msg { margin-top: 6px; font-size: 12px; }
.msg-ok { color: var(--accent); }
.msg-err { color: #c0504d; }
</style>
