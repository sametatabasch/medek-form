<?php
declare(strict_types=1);
/** Çekirdek: yollar, depolama, oturum, yetki, durum hesapları. */

define('ROOT', dirname(__DIR__));
define('DATA', rtrim(getenv('MEDEK_DATA') ?: ROOT . '/data', '/'));
const MAX_UPLOAD = 20 * 1024 * 1024;
const IZINLI_UZANTI = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'txt', 'csv', 'odt', 'ods', 'html'];

require ROOT . '/schema/maddeler.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---------- program verisi ----------
function programs(): array {
    static $p = null;
    if ($p === null) {
        $p = [];
        foreach (json_decode(file_get_contents(ROOT . '/schema/programs.json'), true) as $x) $p[$x['slug']] = $x;
    }
    return $p;
}
function prog(string $slug): ?array { return programs()[$slug] ?? null; }
function maddeler_for(?string $slug): array { return maddeler($slug ? prog($slug) : null); }
function madde_dir(string $slug, array $m): string { return DATA . '/programs/' . ($m['kapsam'] === 'birim' ? '_birim' : $slug); }
function mid_file(string $id): string { return str_replace('.', '_', $id); }
function sheet_name(string $fid): string { return 'T-' . preg_replace('/[^A-Za-z0-9_]/', '', $fid); }

// ---------- JSON depolama ----------
function json_read(string $file, $default = []) {
    if (!is_file($file)) return $default;
    $fh = fopen($file, 'r'); if (!$fh) return $default;
    flock($fh, LOCK_SH); $raw = stream_get_contents($fh); flock($fh, LOCK_UN); fclose($fh);
    $d = json_decode($raw ?: '', true);
    return is_array($d) ? $d : $default;
}
function json_write(string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Klasör oluşturulamadı: $dir");
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) throw new RuntimeException('Yazılamadı: ' . $file);
    @chmod($tmp, 0664);
    rename($tmp, $file);
}
function entry_file(string $slug, array $m): string { return madde_dir($slug, $m) . '/' . mid_file($m['id']) . '.json'; }
function entry_load(string $slug, array $m): array {
    $e = json_read(entry_file($slug, $m), []);
    return $e + ['v' => [], 'yayin' => '', 'yayin_url' => '', 'kanit_url' => '', 'not' => '', 'files' => [], 'updated' => null, 'by' => null];
}
function entry_save(string $slug, array $m, array $e): void {
    $e['updated'] = date('c'); $e['by'] = user()['ad'] ?? '?';
    json_write(entry_file($slug, $m), $e);
}

// ---------- alan değerleri ----------
function pre_count(array $fld): int { return isset($fld['rows'][0]) ? count($fld['rows'][0]) : 0; }
function table_rows_effective(array $fld, array $entry): array {
    $v = $entry['v'][$fld['id']] ?? null;
    if (is_array($v) && $v) return $v;
    $rows = [];
    foreach ($fld['rows'] ?? [] as $r) {
        $row = [];
        foreach ($fld['cols'] as $i => $col) $row[$col['k']] = $r[$i] ?? '';
        $rows[] = $row;
    }
    return $rows;
}
function field_value(array $fld, array $entry) {
    if ($fld['type'] === 'table') return table_rows_effective($fld, $entry);
    $v = $entry['v'][$fld['id']] ?? null;
    return $v === null ? ($fld['default'] ?? '') : $v;
}
function filled_rows(array $fld, array $rows): int {
    $pre = pre_count($fld); $n = 0;
    foreach ($rows as $r) {
        $vals = array_values(array_map(fn($c) => trim((string)($r[$c['k']] ?? '')), $fld['cols']));
        if (array_filter(array_slice($vals, $pre), fn($x) => $x !== '') || (!$pre && array_filter($vals, fn($x) => $x !== ''))) $n++;
    }
    return $n;
}
function field_done(array $fld, array $entry): bool {
    if ($fld['type'] === 'table') return filled_rows($fld, table_rows_effective($fld, $entry)) >= ($fld['min'] ?? 1);
    return trim((string)($entry['v'][$fld['id']] ?? '')) !== ''; // varsayılan değerler kaydedilene kadar sayılmaz
}
/** [yüzde, durum, dolu, toplam] */
function madde_status(array $m, array $entry): array {
    $tot = $done = 0; $any = false;
    foreach ($m['alanlar'] as $fld) {
        if ($fld['req']) { $tot++; if (field_done($fld, $entry)) $done++; }
        if ($fld['type'] === 'table' ? filled_rows($fld, $entry['v'][$fld['id']] ?? []) > 0 : trim((string)($entry['v'][$fld['id']] ?? '')) !== '') $any = true;
    }
    $pct = $tot ? (int)round($done * 100 / $tot) : 100;
    $st = ($tot && $done === $tot) ? 'tamam' : ($any || $done ? 'taslak' : 'bos');
    return ['pct' => $pct, 'durum' => $st, 'dolu' => $done, 'toplam' => $tot, 'kanit' => count($entry['files']) + count(array_filter(preg_split('/\R/', (string)$entry['kanit_url']) ?: [], 'trim'))];
}
const DURUM_ETIKET = ['bos' => 'Boş', 'taslak' => 'Taslak', 'tamam' => 'Tamam'];

/** 4.2 ders planından AKTS ölçütleri */
function akts_ozet(string $slug): array {
    $ms = maddeler_for($slug); $m = $ms['4.2']; $e = entry_load($slug, $m);
    $rows = $e['v']['ders_plani'] ?? [];
    $t = ['toplam' => 0.0, 'mesleki' => 0.0, 'ozgu' => 0.0, 'uyg' => 0.0, 'dis' => 0.0];
    foreach ($rows as $r) {
        $a = (float)str_replace(',', '.', (string)($r['akts'] ?? 0));
        $t['toplam'] += $a;
        $mes = ($r['tur'] ?? '') === 'Mesleki';
        if ($mes) $t['mesleki'] += $a;
        if ($mes && ($r['ozgu'] ?? '') !== '') $t['ozgu'] += $a;
        if (($r['uyg'] ?? '') !== '') $t['uyg'] += $a;
        if (($r['dis'] ?? '') === 'Evet') $t['dis'] += $a;
    }
    $t['olcutler'] = [
        ['4.2', 'Mesleki ders AKTS', $t['mesleki'], 60], ['4.2', 'Programa özgü çıktıyı destekleyen mesleki AKTS', $t['ozgu'], 20],
        ['4.3', 'Staj / uygulama / güncel yazılım içeren AKTS', $t['uyg'], 15], ['4.5', 'Dış paydaş katkılı ders AKTS', $t['dis'], 5],
    ];
    return $t;
}

// ---------- oturum / yetki ----------
function boot_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('medekform');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'lifetime' => 0, 'path' => '/']);
        session_start();
    }
}
function csrf(): string { boot_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_check(): void {
    boot_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) { http_response_code(400); exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'); }
}
function flash(?string $msg = null, string $tip = 'ok') {
    boot_session();
    if ($msg !== null) { $_SESSION['flash'][] = [$tip, $msg]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function users_file(): string { return DATA . '/users.json'; }
function users_init(): void {
    if (is_file(users_file())) return;
    $rnd = function (): string { $a = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = ''; for ($i = 0; $i < 10; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; return $s; };
    $u = ['admin' => ['ad' => 'Yönetici', 'hash' => ''], 'baskan' => []]; $txt = "MEDEK Form – ilk şifreler (giriş yaptıktan sonra Yönetim sayfasından değiştirin; bu dosyayı silin)\n\n";
    $p = $rnd(); $u['admin']['hash'] = password_hash($p, PASSWORD_DEFAULT); $txt .= "Yönetici                         : $p\n";
    foreach (programs() as $slug => $pr) {
        $p = $rnd(); $u['baskan'][$slug] = ['ad' => $pr['ad'] . ' Program Başkanı', 'hash' => password_hash($p, PASSWORD_DEFAULT)];
        $txt .= str_pad($pr['ad'], 45) . ": $p\n";
    }
    json_write(users_file(), $u);
    file_put_contents(DATA . '/ILK-SIFRELER.txt', $txt); @chmod(DATA . '/ILK-SIFRELER.txt', 0664);
}
function user(): ?array { boot_session(); return $_SESSION['user'] ?? null; }
function require_login(): array {
    $u = user();
    if (!$u) { $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? '/'; redirect('/giris'); }
    return $u;
}
function is_admin(): bool { return (user()['role'] ?? '') === 'admin'; }
function can_view(string $slug): bool { $u = user(); return $u && ($u['role'] === 'admin' || $u['slug'] === $slug); }
function can_edit(string $slug, array $m): bool {
    $u = user(); if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    return $u['slug'] === $slug || false; // birim formlarına da kendi program sayfasından erişilir
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
function login(string $who, string $pass): bool {
    $u = json_read(users_file(), []);
    if ($who === 'admin') { $rec = $u['admin'] ?? null; $sess = ['role' => 'admin', 'slug' => null, 'ad' => 'Yönetici']; }
    else { $rec = $u['baskan'][$who] ?? null; $sess = ['role' => 'baskan', 'slug' => $who, 'ad' => $rec['ad'] ?? $who]; }
    if (!$rec || !password_verify($pass, $rec['hash'])) { usleep(800000); return false; }
    session_regenerate_id(true); $_SESSION['user'] = $sess; return true;
}

// ---------- form girdisi ----------
function str_clean($v): string { return trim(str_replace("\r\n", "\n", (string)$v)); }
/** POST verisini şemaya göre normalize et */
function parse_post(array $m, array $post): array {
    $v = [];
    foreach ($m['alanlar'] as $fld) {
        if ($fld['type'] === 'table') {
            $rows = [];
            foreach ((array)($post['t'][$fld['id']] ?? []) as $r) {
                $row = []; $any = false;
                foreach ($fld['cols'] as $c) { $x = str_clean($r[$c['k']] ?? ''); $row[$c['k']] = $x; if ($x !== '') $any = true; }
                if ($any) $rows[] = $row;
            }
            $v[$fld['id']] = $rows;
        } else {
            $x = str_clean($post['f'][$fld['id']] ?? '');
            if ($fld['type'] === 'number') $x = str_replace(',', '.', $x);
            $v[$fld['id']] = $x;
        }
    }
    return $v;
}

// ---------- dosya yükleme ----------
function upload_dir(string $slug, array $m): string { return DATA . '/uploads/' . ($m['kapsam'] === 'birim' ? '_birim' : $slug) . '/' . mid_file($m['id']); }
function safe_name(string $n): string {
    $n = basename(str_replace('\\', '/', $n));
    $n = preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $n) ?: 'dosya';
    return mb_substr($n, 0, 120);
}
function ext_of(string $n): string { return strtolower(pathinfo($n, PATHINFO_EXTENSION)); }
