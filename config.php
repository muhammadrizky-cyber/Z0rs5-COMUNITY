<?php
// ====== Pengaturan database (default XAMPP) ======
const DB_HOST = 'localhost';
const DB_NAME = 'linktree';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP default: kosong

session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Gagal konek database. Pastikan MySQL menyala dan database.sql sudah diimpor.');
        }
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function valid_url(string $u): bool {
    return (bool)preg_match('~^https?://~i', $u) && filter_var($u, FILTER_VALIDATE_URL) !== false;
}

// Gambar: boleh URL http(s) atau file lokal di folder img/ (contoh: img/avatar.jpg)
function valid_img(string $u): bool {
    return valid_url($u) || (bool)preg_match('~^img/[A-Za-z0-9_.\-]+$~', $u);
}