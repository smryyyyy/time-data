<?php $view = 'templates'; ?>
<h1>模板管理</h1>

<div class="hour-grid">
<?php foreach ($templates as $h => $tpl): ?>
    <div class="tpl-card">
        <h3><?= $h ?>点 <?= $tpl['enabled'] ? '●' : '○' ?></h3>
        <?php if ($tpl['exists']): ?>
            <div class="tpl-info">
                <div>文件: <code><?= h($tpl['name']) ?></code></div>
                <div>大小: <?= formatSize($tpl['size']) ?></div>
                <?php if (!empty($tpl['sheets'])): ?>
                    <div>导出 sheet: <?= implode(', ', array_map('h', $tpl['sheets'])) ?></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="color:#888">未上传模板</div>
        <?php endif; ?>

        <form class="upload-form" data-hour="<?= $h ?>" style="margin-top:8px">
            <input type="file" name="template" accept=".xlsx" style="font-size:13px">
            <button type="submit" class="btn btn-sm btn-primary">上传替换</button>
        </form>
        <div class="upload-msg" id="msg<?= $h ?>" style="margin-top:6px;font-size:13px"></div>
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
        msg.innerText = '上传中...';
        try {
            const r = await fetch('/templates/upload', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                msg.innerHTML = '<span style="color:green">✓ ' + d.message + '</span>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<span style="color:red">✗ ' + (d.error || d.message) + '</span>';
            }
        } catch(e) {
            msg.innerHTML = '<span style="color:red">错误: ' + e + '</span>';
        }
    });
});
</script>

<style>
.hour-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.tpl-card { border: 1px solid #ddd; border-radius: 6px; padding: 14px; }
.tpl-card h3 { margin: 0 0 8px; }
.tpl-info { font-size: 13px; line-height: 1.8; color: #555; }
.tpl-info code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
</style>
