<?php
require __DIR__ . '/config.php';
$pdo = db();
$p = $pdo->query('SELECT * FROM profile WHERE id=1')->fetch() ?: ['name' => 'Nama', 'bio' => '', 'avatar' => '', 'background' => ''];
$socials = $pdo->query('SELECT * FROM socials ORDER BY id')->fetchAll();
$links = $pdo->query('SELECT id,title,subtitle FROM links ORDER BY position, id')->fetchAll();

// Jika kolom di database kosong, otomatis pakai file di folder img/
if ($p['avatar'] === '' && is_file(__DIR__ . '/img/avatar.jpg')) $p['avatar'] = 'img/avatar.jpg';
if ($p['background'] === '' && is_file(__DIR__ . '/img/background.jpg')) $p['background'] = 'img/background.jpg';

$bgStyle = '';
if ($p['background'] !== '' && valid_img($p['background'])) {
    $bg = str_replace(["'", '"', '(', ')'], ['%27', '%22', '%28', '%29'], $p['background']);
    $bgStyle = "background-image:linear-gradient(rgba(0,0,0,.05),rgba(0,0,0,.25)),url('" . $bg . "')";
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($p['name']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="<?= e($bgStyle) ?>">
  <?php if ($p['avatar'] !== '' && valid_img($p['avatar'])): ?>
    <img class="avatar" src="<?= e($p['avatar']) ?>" alt="">
  <?php else: ?>
    <div class="avatar"></div>
  <?php endif; ?>
  <h1><?= e($p['name']) ?></h1>
  <p class="bio"><?= e($p['bio']) ?></p>

  <div class="socials">
    <?php foreach ($socials as $s): ?>
      <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" title="<?= e($s['type']) ?>"><?= e(substr($s['type'], 0, 2)) ?></a>
    <?php endforeach; ?>
  </div>

  <?php foreach ($links as $l): ?>
    <a class="link" href="go.php?id=<?= (int)$l['id'] ?>">
      <?= e($l['title']) ?>
      <?php if ($l['subtitle'] !== ''): ?><small><?= e($l['subtitle']) ?></small><?php endif; ?>
    </a>
  <?php endforeach; ?>

  <div class="foot"><b>Linktree*</b>Bergabung dengan <?= e($p['name']) ?> hari ini</div>
</div>
</body>
</html>