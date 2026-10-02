<?php
require __DIR__ . '/config.php';
$pdo = db();

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

function flash(string $m): void { $_SESSION['flash'] = $m; }
function back(): void { header('Location: admin.php'); exit; }

$hasAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
$loggedIn = !empty($_SESSION['admin_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { http_response_code(400); exit('Token tidak valid'); }
    $a = $_POST['action'] ?? '';

    // --- Buat admin pertama ---
    if ($a === 'setup' && !$hasAdmin) {
        $u = trim($_POST['username'] ?? ''); $pw = $_POST['password'] ?? '';
        if ($u === '' || strlen($pw) < 6) { flash('Username wajib & password minimal 6 karakter.'); back(); }
        $pdo->prepare('INSERT INTO admins(username,password_hash) VALUES(?,?)')
            ->execute([substr($u, 0, 40), password_hash($pw, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$pdo->lastInsertId();
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        flash('Admin dibuat. Selamat datang!'); back();
    }

    // --- Login ---
    if ($a === 'login') {
        $st = $pdo->prepare('SELECT * FROM admins WHERE username=?');
        $st->execute([trim($_POST['username'] ?? '')]);
        $row = $st->fetch();
        if ($row && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        } else {
            sleep(1);
            flash('Username atau password salah.');
        }
        back();
    }

    // Semua aksi di bawah wajib login
    if (!$loggedIn) { http_response_code(401); exit('Harus login'); }

    switch ($a) {
        case 'logout':
            $_SESSION = []; session_destroy(); header('Location: admin.php'); exit;

        case 'save_profile':
            $av = trim($_POST['avatar'] ?? ''); $bg = trim($_POST['background'] ?? '');
            if (($av !== '' && !valid_img($av)) || ($bg !== '' && !valid_img($bg))) { flash('Gambar harus URL http(s):// atau path lokal seperti img/avatar.jpg'); back(); }
            $pdo->prepare('UPDATE profile SET name=?,bio=?,avatar=?,background=? WHERE id=1')->execute([
                mb_substr(trim($_POST['name'] ?? ''), 0, 60), mb_substr(trim($_POST['bio'] ?? ''), 0, 160), $av, $bg]);
            flash('Profil tersimpan.'); back();

        case 'add_link':
        case 'update_link':
            $t = mb_substr(trim($_POST['title'] ?? ''), 0, 80);
            $s = mb_substr(trim($_POST['subtitle'] ?? ''), 0, 120);
            $u = trim($_POST['url'] ?? '');
            if ($t === '' || !valid_url($u)) { flash('Judul wajib & URL harus http:// atau https://'); back(); }
            if ($a === 'add_link') {
                $pos = (int)$pdo->query('SELECT COALESCE(MAX(position),0)+1 FROM links')->fetchColumn();
                $pdo->prepare('INSERT INTO links(title,subtitle,url,position) VALUES(?,?,?,?)')->execute([$t, $s, $u, $pos]);
                flash('Link ditambahkan.');
            } else {
                $pdo->prepare('UPDATE links SET title=?,subtitle=?,url=?,position=? WHERE id=?')
                    ->execute([$t, $s, $u, (int)($_POST['position'] ?? 0), (int)($_POST['id'] ?? 0)]);
                flash('Link diperbarui.');
            }
            back();

        case 'delete_link':
            $pdo->prepare('DELETE FROM links WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('Link dihapus.'); back();

        case 'add_social':
            $ty = strtolower(mb_substr(trim($_POST['type'] ?? ''), 0, 20)); $u = trim($_POST['url'] ?? '');
            if ($ty === '' || !valid_url($u)) { flash('Jenis wajib & URL harus http:// atau https://'); back(); }
            $pdo->prepare('INSERT INTO socials(type,url) VALUES(?,?)')->execute([$ty, $u]);
            flash('Ikon sosial ditambahkan.'); back();

        case 'delete_social':
            $pdo->prepare('DELETE FROM socials WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('Ikon sosial dihapus.'); back();
    }
    back();
}

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
if ($loggedIn) {
    $p = $pdo->query('SELECT * FROM profile WHERE id=1')->fetch();
    $links = $pdo->query('SELECT * FROM links ORDER BY position,id')->fetchAll();
    $socials = $pdo->query('SELECT * FROM socials ORDER BY id')->fetchAll();
}
function hid(string $csrf, string $action): string {
    return '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="action" value="' . e($action) . '">';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin</title>
<style>
body{font-family:system-ui,Arial,sans-serif;max-width:760px;margin:24px auto;padding:0 14px;background:#f4f5f7;color:#222}
section{background:#fff;padding:16px;border-radius:12px;margin-bottom:16px;box-shadow:0 1px 3px #0002}
input[type=text],input[type=url],input[type=password],input[type=number]{padding:8px;margin:3px 0;border:1px solid #bbb;border-radius:6px;width:100%}
.r{display:flex;gap:6px;flex-wrap:wrap;align-items:center;border-top:1px solid #eee;padding:10px 0}
.r input{flex:1 1 150px;width:auto}.r input.s{flex:0 0 60px}
button{padding:8px 14px;border:0;border-radius:6px;background:#2a78a6;color:#fff;cursor:pointer}
button.d{background:#c0392b}.msg{background:#e8f4ff;border:1px solid #9cc7ee;padding:10px;border-radius:8px;margin-bottom:14px}
h3{margin:0 0 8px}small{color:#777}.top{display:flex;justify-content:space-between;align-items:center}
</style>
</head>
<body>
<div class="top"><h2>Admin Linktree</h2>
<?php if ($loggedIn): ?><form method="post"><?= hid($csrf, 'logout') ?><button class="d">Keluar</button></form><?php endif; ?></div>
<?php if ($flash): ?><div class="msg"><?= e($flash) ?></div><?php endif; ?>

<?php if (!$hasAdmin): ?>
<section><h3>Buat akun admin pertama</h3>
<form method="post"><?= hid($csrf, 'setup') ?>
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password (min. 6 karakter)" required>
<button>Buat admin</button></form></section>

<?php elseif (!$loggedIn): ?>
<section><h3>Masuk</h3>
<form method="post"><?= hid($csrf, 'login') ?>
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button>Masuk</button></form></section>

<?php else: ?>
<p><a href="index.php" target="_blank">Lihat halaman publik →</a></p>

<section><h3>Profil</h3>
<form method="post"><?= hid($csrf, 'save_profile') ?>
<input type="text" name="name" value="<?= e($p['name']) ?>" placeholder="Nama" maxlength="60" required>
<input type="text" name="bio" value="<?= e($p['bio']) ?>" placeholder="Bio" maxlength="160">
<input type="text" name="avatar" value="<?= e($p['avatar']) ?>" placeholder="Foto profil: img/avatar.jpg atau https://...">
<input type="text" name="background" value="<?= e($p['background']) ?>" placeholder="Gambar latar: img/background.jpg atau https://...">
<button>Simpan profil</button></form></section>

<section><h3>Ikon sosial</h3>
<?php foreach ($socials as $s): ?>
<form method="post" class="r"><?= hid($csrf, 'delete_social') ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<span><b><?= e($s['type']) ?></b> — <?= e($s['url']) ?></span><button class="d">Hapus</button></form>
<?php endforeach; ?>
<form method="post" class="r"><?= hid($csrf, 'add_social') ?>
<input class="s" type="text" name="type" placeholder="tiktok" required style="flex:0 0 110px">
<input type="url" name="url" placeholder="https://..." required><button>Tambah</button></form></section>

<section><h3>Link</h3>
<?php foreach ($links as $l): ?>
<div class="r">
<form method="post" class="r" style="border:0;padding:0;flex:1 1 100%"><?= hid($csrf, 'update_link') ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
<input type="text" name="title" value="<?= e($l['title']) ?>" placeholder="Judul" required>
<input type="text" name="subtitle" value="<?= e($l['subtitle']) ?>" placeholder="Subjudul">
<input type="url" name="url" value="<?= e($l['url']) ?>" placeholder="https://..." required>
<input class="s" type="number" name="position" value="<?= (int)$l['position'] ?>" title="Urutan">
<button>Simpan</button></form>
<form method="post"><?= hid($csrf, 'delete_link') ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
<button class="d" onclick="return confirm('Hapus link ini?')">Hapus</button></form>
<small><?= (int)$l['clicks'] ?> klik</small>
</div>
<?php endforeach; ?>
<form method="post" class="r"><?= hid($csrf, 'add_link') ?>
<input type="text" name="title" placeholder="Judul" required>
<input type="text" name="subtitle" placeholder="Subjudul (opsional)">
<input type="url" name="url" placeholder="https://..." required><button>Tambah link</button></form></section>
<?php endif; ?>
</body>
</html>