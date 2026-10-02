<?php
// Hitung klik lalu arahkan ke URL tujuan
require __DIR__ . '/config.php';
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT url FROM links WHERE id=?');
$st->execute([$id]);
$l = $st->fetch();
if (!$l || !valid_url($l['url'])) { http_response_code(404); exit('Link tidak ditemukan'); }
db()->prepare('UPDATE links SET clicks = clicks + 1 WHERE id=?')->execute([$id]);
header('Location: ' . $l['url'], true, 302);
exit;