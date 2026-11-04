<?php
/**
 * Sitedeki mevcut verileri (schema/seed.json) forma aktarır.
 * Yalnızca BOŞ alanları doldurur; kullanıcının girdiği hiçbir veri ezilmez.
 * Kullanım: php bin/seed.php [--dry]
 */
require dirname(__DIR__) . '/src/lib.php';
$dry = in_array('--dry', $argv ?? [], true);
$seed = json_decode(file_get_contents(ROOT . '/schema/seed.json'), true);
if (!is_array($seed)) { fwrite(STDERR, "seed.json okunamadı\n"); exit(1); }
if (!is_dir(DATA)) mkdir(DATA, 0775, true);
$yeniAlan = $atlanan = $uyari = 0; $girdi = 0;
foreach ($seed as $target => $maddeler) {
    $ms = maddeler_for($target === '_birim' ? null : $target);
    if ($target !== '_birim' && !prog($target)) { echo "! bilinmeyen program: $target\n"; continue; }
    foreach ($maddeler as $mid => $s) {
        $m = $ms[$mid] ?? null; if (!$m) { echo "! bilinmeyen madde $mid\n"; continue; }
        $slug = $target === '_birim' ? '_birim' : $target;
        $e = entry_load($slug, $m); $byId = []; foreach ($m['alanlar'] as $f) $byId[$f['id']] = $f;
        $deg = 0;
        foreach ($s['v'] ?? [] as $k => $val) {
            $f = $byId[$k] ?? null; if (!$f) { echo "! $target $mid: bilinmeyen alan $k\n"; $uyari++; continue; }
            $cur = $e['v'][$k] ?? null;
            $dolu = $f['type'] === 'table' ? (is_array($cur) && filled_rows($f, $cur) > 0) : trim((string)$cur) !== '';
            if ($dolu) { $atlanan++; continue; }
            if ($f['type'] === 'table') {
                $rows = [];
                foreach ($val as $r) { $row = []; foreach ($f['cols'] as $c) $row[$c['k']] = (string)($r[$c['k']] ?? ''); $rows[] = $row; }
                $e['v'][$k] = $rows;
            } else $e['v'][$k] = (string)$val;
            $yeniAlan++; $deg++;
        }
        foreach (['yayin', 'yayin_url', 'kanit_url', 'not'] as $k) if (!empty($s[$k]) && trim((string)$e[$k]) === '') { $e[$k] = $s[$k]; $deg++; }
        if ($deg) {
            $girdi++;
            if (!$dry) { $e['updated'] = date('c'); $e['by'] = 'Siteden aktarım'; json_write(entry_file($slug, $m), $e); }
        }
    }
}
echo ($dry ? '[deneme] ' : '') . "$girdi madde güncellendi, $yeniAlan alan dolduruldu, $atlanan dolu alan korundu" . ($uyari ? ", $uyari uyarı" : '') . ".\n";
