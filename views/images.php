<?php $view = 'images'; ?>
<h1>图床管理</h1>

<div class="card">
    <h2>已上传图片（<?= count($images) ?> 张）<small style="color:#888;font-weight:normal">— 超过7天自动清理</small></h2>
    <?php if (empty($images)): ?>
    <p style="color:#888">暂无图片</p>
    <?php else: ?>
    <div class="image-grid">
    <?php foreach ($images as $img): ?>
    <div class="image-card">
        <img src="<?= h($img['url']) ?>" alt="<?= h($img['name']) ?>" loading="lazy">
        <div class="image-info">
            <span class="image-name" title="<?= h($img['name']) ?>"><?= h($img['name']) ?></span>
            <span class="image-size"><?= formatSize($img['size']) ?></span>
            <span class="image-time"><?= h($img['time']) ?></span>
        </div>
        <div class="image-actions">
            <button onclick="copyUrl('<?= h($img['url']) ?>', this)" class="btn btn-sm">复制URL</button>
            <button onclick="deleteImg('<?= h($img['name']) ?>')" class="btn btn-sm btn-danger">删除</button>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function copyUrl(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        btn.textContent = '已复制';
        setTimeout(() => btn.textContent = '复制URL', 1500);
    });
}

async function deleteImg(name) {
    if (!confirm('确定删除 ' + name + '？')) return;
    try {
        const resp = await fetch('/api/images/' + name, { method: 'DELETE' });
        const data = await resp.json();
        if (data.success) location.reload();
        else alert('删除失败');
    } catch (err) { alert('请求失败'); }
}
</script>
