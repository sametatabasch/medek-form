<?php
declare(strict_types=1);
// php -S ile geliştirmede statik dosyaları doğrudan sun
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($f !== __FILE__ && is_file($f)) return false;
}
error_reporting(E_ALL);
ini_set('display_errors', getenv('MEDEK_DEBUG') ? '1' : '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

require dirname(__DIR__) . '/src/lib.php';
require dirname(__DIR__) . '/src/xlsx.php';
require dirname(__DIR__) . '/src/render.php';
require dirname(__DIR__) . '/src/pages.php';

if (!is_dir(DATA) && !@mkdir(DATA, 0775, true)) { http_response_code(500); exit('data/ klasörü oluşturulamadı: ' . DATA); }
if (!is_writable(DATA)) { http_response_code(500); exit('data/ klasörü yazılabilir değil: ' . DATA . ' (deploy/install.sh çalıştırın)'); }
users_init();
boot_session();
try { route(); }
catch (Throwable $e) {
    error_log('[medekform] ' . $e);
    http_response_code(500);
    layout('Hata', '<h1>Beklenmeyen hata</h1><p>İşlem tamamlanamadı. Yönetici günlükten ayrıntıya bakabilir.</p>' . (getenv('MEDEK_DEBUG') ? '<pre>' . h((string)$e) . '</pre>' : ''));
}
