<?php
declare(strict_types=1);
/** HTML üretimi: sayfa iskeleti, form bileşenleri, rapor (HTML çıktı). */

function layout(string $title, string $body, array $o = []): void {
    $u = user(); $flash = flash();
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · MEDEK Form</title><link rel="stylesheet" href="/assets/app.css"></head><body>';
    echo '<header class="top"><a class="brand" href="/"><b>MEDEK Form</b><span>Tirebolu Mehmet Bayrak MYO</span></a><nav>';
    echo '<a href="/sablonlar">Şablonlar</a><a href="/yardim">Yardım</a>';
    if ($u) {
        if ($u['role'] === 'admin') echo '<a href="/yonetim">Yönetim</a>';
        echo '<span class="who">' . h($u['ad']) . '</span><form method="post" action="/cikis" class="inline"><input type="hidden" name="_csrf" value="' . h(csrf()) . '"><button class="link">Çıkış</button></form>';
    } else echo '<a href="/giris">Giriş</a>';
    echo '</nav></header><div class="shell' . (isset($o['side']) ? ' with-side' : '') . '">';
    if (isset($o['side'])) echo '<aside class="side">' . $o['side'] . '</aside>';
    echo '<main>';
    foreach ($flash ?? [] as [$t, $msg]) echo '<div class="flash ' . h($t) . '">' . h($msg) . '</div>';
    echo $body . '</main></div><script src="/assets/app.js"></script></body></html>';
}
function pill(string $d): string { return '<span class="pill ' . $d . '">' . DURUM_ETIKET[$d] . '</span>'; }
function bar(int $pct): string { return '<span class="bar"><i style="width:' . $pct . '%"></i></span> <small>%' . $pct . '</small>'; }
function tur_etiket(string $t): string {
    $x = ['W' => ['Web\'de yayın', 'tw'], 'K' => ['Kanıt belgesi', 'tk'], 'İ' => ['İç süreç', 'ti']][$t] ?? [$t, ''];
    return '<span class="tag ' . $x[1] . '">' . $x[0] . '</span>';
}

/** Program sayfaları için sol menü */
function sidebar(string $slug, ?string $cur): string {
    $ms = maddeler_for($slug); $pr = prog($slug);
    $s = '<div class="side-h"><a href="/p/' . h($slug) . '">' . h($pr['ad']) . '</a></div>';
    $grp = [];
    foreach ($ms as $m) $grp[$m['olcut']][] = $m;
    foreach ($grp as $ol => $list) {
        $s .= '<div class="olcut"><b>' . ($ol ? 'Ölçüt ' . $ol : 'Genel') . '</b><small> ' . h(OLCUTLER[$ol]) . '</small></div><ul>';
        foreach ($list as $m) {
            $st = madde_status($m, entry_load($slug, $m));
            $s .= '<li class="' . ($cur === $m['id'] ? 'cur' : '') . '"><a href="/p/' . h($slug) . '/' . h($m['id']) . '"><i class="dot ' . $st['durum'] . '"></i>' . h($m['id'] === '0' ? '' : $m['id'] . ' ') . h($m['baslik']) . ($m['kapsam'] === 'birim' ? ' <em>ortak</em>' : '') . '</a></li>';
        }
        $s .= '</ul>';
    }
    return $s;
}

// ---------- form bileşenleri ----------
function input_html(string $name, string $type, $val, ?array $opts = null, bool $ro = false, string $cls = ''): string {
    $dis = $ro ? ' disabled' : ''; $val = (string)$val;
    if ($type === 'select' || $type === 'bool') {
        $opts = $opts ?? YN; $o = '<select name="' . $name . '"' . $dis . ' class="' . $cls . '">';
        if (!in_array('', $opts, true)) $o .= '<option value="">— seçin —</option>';
        foreach ($opts as $x) $o .= '<option value="' . h($x) . '"' . ($x === $val ? ' selected' : '') . '>' . h($x === '' ? '—' : $x) . '</option>';
        if ($val !== '' && !in_array($val, $opts, true)) $o .= '<option value="' . h($val) . '" selected>' . h($val) . '</option>';
        return $o . '</select>';
    }
    if ($type === 'textarea') return '<textarea name="' . $name . '" rows="4"' . $dis . ' class="auto ' . $cls . '">' . h($val) . '</textarea>';
    if ($type === 'number') return '<input type="text" inputmode="decimal" name="' . $name . '" value="' . h($val) . '"' . $dis . ' class="' . $cls . '">';
    if ($type === 'date') return '<input type="date" name="' . $name . '" value="' . h($val) . '"' . $dis . ' class="' . $cls . '">';
    if ($type === 'url') return '<input type="url" name="' . $name . '" value="' . h($val) . '" placeholder="https://" ' . $dis . ' class="' . $cls . '">';
    return '<textarea name="' . $name . '" rows="1"' . $dis . ' class="auto one ' . $cls . '">' . h($val) . '</textarea>';
}
function table_row_html(array $fld, array $row, string $idx, bool $ro, int $rowNo): string {
    $pre = pre_count($fld);
    $r = '<tr><td class="no">' . $rowNo . '</td>';
    foreach ($fld['cols'] as $ci => $c) {
        $name = 't[' . h($fld['id']) . '][' . $idx . '][' . h($c['k']) . ']';
        $r .= '<td class="' . ($ci < $pre ? 'pre' : '') . '">' . input_html($name, $c['t'], $row[$c['k']] ?? '', $c['o'], $ro) . '</td>';
    }
    return $r . '<td class="act">' . ($ro ? '' : '<button type="button" class="del-row" title="Satırı sil">×</button>') . '</td></tr>';
}
function table_html(array $fld, array $entry, bool $ro): string {
    $rows = table_rows_effective($fld, $entry);
    $min = $fld['min'] ?? 1;
    while (count($rows) < $min) $rows[] = [];
    $o = '<div class="tablewrap"><table class="dt" data-id="' . h($fld['id']) . '"><thead><tr><th>#</th>';
    foreach ($fld['cols'] as $c) $o .= '<th>' . h($c['l']) . '</th>';
    $o .= '<th></th></tr></thead><tbody>';
    foreach (array_values($rows) as $i => $row) $o .= table_row_html($fld, $row, (string)$i, $ro, $i + 1);
    $o .= '</tbody></table>';
    if (!$ro) $o .= '<template>' . table_row_html($fld, [], '__I__', false, 0) . '</template><button type="button" class="btn small add-row">+ Satır ekle</button>';
    return $o . '</div>';
}
function akts_html(string $slug): string {
    $t = akts_ozet($slug);
    $o = '<div class="akts"><b>AKTS özeti</b> <small>(4.2 ders planından otomatik hesaplanır; toplam ' . rtrim(rtrim(number_format($t['toplam'], 1, ',', ''), '0'), ',') . ' AKTS)</small><table><tr><th>Madde</th><th>Ölçüt</th><th>Mevcut</th><th>Gereken</th><th></th></tr>';
    foreach ($t['olcutler'] as [$mid, $ad, $v, $g]) {
        $ok = $v >= $g;
        $o .= '<tr><td>' . $mid . '</td><td>' . h($ad) . '</td><td>' . rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') . '</td><td>≥ ' . $g . '</td><td><span class="pill ' . ($ok ? 'tamam' : 'bos') . '">' . ($ok ? 'Sağlıyor' : 'Eksik') . '</span></td></tr>';
    }
    return $o . '</table></div>';
}
function files_html(string $slug, array $m, array $e, bool $ro): string {
    $o = '<ul class="files">';
    foreach ($e['files'] as $f) {
        $o .= '<li><a href="/dosya/' . h($slug) . '/' . h($m['id']) . '/' . h($f['id']) . '">' . h($f['name']) . '</a> <small>' . round($f['size'] / 1024) . ' KB · ' . h(substr($f['at'], 0, 10)) . '</small>';
        if (!$ro) $o .= '<form method="post" action="/p/' . h($slug) . '/' . h($m['id']) . '/dosya-sil" class="inline" onsubmit="return confirm(\'Dosya silinsin mi?\')"><input type="hidden" name="_csrf" value="' . h(csrf()) . '"><input type="hidden" name="id" value="' . h($f['id']) . '"><button class="link danger">sil</button></form>';
        $o .= '</li>';
    }
    if (!$e['files']) $o .= '<li class="muted">Henüz dosya yüklenmedi.</li>';
    return $o . '</ul>';
}
function madde_form_html(string $slug, array $m, array $e, bool $ro): string {
    $base = '/p/' . h($slug) . '/' . h($m['id']);
    $st = madde_status($m, $e);
    $o = '<div class="mh"><div><span class="mid">' . ($m['id'] === '0' ? 'Genel' : 'MEDEK ' . h($m['id'])) . '</span> ' . tur_etiket($m['tur']) . ($m['kapsam'] === 'birim' ? ' <span class="tag tb">Birim ortak formu</span>' : '') . '</div>';
    $o .= '<h1>' . h($m['baslik']) . '</h1><p class="gerek"><b>Gereklilik:</b> ' . h($m['gerek']) . '</p><p class="ipucu">' . h($m['ipucu']) . '</p>';
    $o .= '<div class="status">' . pill($st['durum']) . ' ' . bar($st['pct']) . ' <small>' . $st['dolu'] . '/' . $st['toplam'] . ' zorunlu alan' . ($e['updated'] ? ' · son kayıt ' . h(date('d.m.Y H:i', strtotime($e['updated']))) . ' (' . h($e['by']) . ')' : '') . '</small></div></div>';
    $o .= '<div class="toolbar"><a class="btn" href="/sablon/' . h($m['id']) . '.xlsx?p=' . h($slug) . '">⬇ Excel şablonunu indir</a>';
    $o .= '<a class="btn" target="_blank" href="/export/' . h($slug) . '/' . h($m['id']) . '.html">Önizleme / HTML çıktı</a>';
    $o .= '<a class="btn ghost" href="/export/' . h($slug) . '/' . h($m['id']) . '.html?indir=1">⬇ HTML indir</a></div>';
    if (!$ro) {
        $o .= '<form method="post" action="' . $base . '/yukle" enctype="multipart/form-data" class="importbox"><input type="hidden" name="_csrf" value="' . h(csrf()) . '"><b>Doldurulmuş şablonu yükle:</b> <input type="file" name="xlsx" accept=".xlsx" required> <button class="btn small">İçeri aktar</button><small>Şablondaki alanlar mevcut verinin üzerine yazılır ve hemen kaydedilir; ardından formda düzeltebilirsiniz.</small></form>';
    }
    if (($m['hesap'] ?? '') === 'akts') $o .= akts_html($slug);
    $o .= '<form method="post" action="' . $base . '" id="mform" class="mform">' . '<input type="hidden" name="_csrf" value="' . h(csrf()) . '">';
    foreach ($m['alanlar'] as $fld) {
        $o .= '<div class="fld' . ($fld['type'] === 'table' ? ' wide' : '') . '"><label>' . h($fld['label']) . ($fld['req'] ? ' <span class="req">*</span>' : '') . '</label>';
        if (!empty($fld['help'])) $o .= '<small class="help">' . h($fld['help']) . '</small>';
        if ($fld['type'] === 'table') $o .= table_html($fld, $e, $ro);
        else $o .= input_html('f[' . h($fld['id']) . ']', $fld['type'], field_value($fld, $e), $fld['options'] ?? null, $ro);
        $o .= '</div>';
    }
    $o .= '<fieldset class="ek"><legend>Yayın ve kanıt</legend><div class="grid2"><div class="fld"><label>Web sitesinde yayın durumu</label>' . input_html('yayin', 'select', $e['yayin'], YAYIN, $ro) . '</div><div class="fld"><label>Yayın adresi (URL)</label>' . input_html('yayin_url', 'url', $e['yayin_url'], null, $ro) . '</div></div>';
    $o .= '<div class="fld"><label>Kanıt bağlantıları <small>(her satıra bir URL)</small></label>' . input_html('kanit_url', 'textarea', $e['kanit_url'], null, $ro) . '</div>';
    $o .= '<div class="fld"><label>Not</label>' . input_html('not', 'textarea', $e['not'], null, $ro) . '</div></fieldset>';
    if (!$ro) $o .= '<div class="savebar"><button class="btn primary" type="submit">Kaydet</button><span class="muted">Kaydetmeden sayfadan ayrılırsanız değişiklikler kaybolur.</span></div>';
    $o .= '</form>';
    $o .= '<fieldset class="ek"><legend>Kanıt dosyaları</legend>' . files_html($slug, $m, $e, $ro);
    if (!$ro) $o .= '<form method="post" action="' . $base . '/dosya" enctype="multipart/form-data" class="importbox"><input type="hidden" name="_csrf" value="' . h(csrf()) . '"><input type="file" name="dosya[]" multiple required> <button class="btn small">Yükle</button><small>İzinli: ' . implode(', ', IZINLI_UZANTI) . ' · en fazla 20 MB</small></form>';
    return $o . '</fieldset>';
}

// ---------- rapor (HTML çıktı) ----------
function report_css(): string {
    return 'body{font:14px/1.5 "Segoe UI",system-ui,Arial,sans-serif;color:#1b1f24;margin:0;background:#f4f6f9}.wrap{max-width:960px;margin:0 auto;padding:24px}'
        . 'h1{font-size:24px;margin:.2em 0}h2{font-size:18px;margin:1.6em 0 .4em;border-bottom:2px solid #1f3a5f;padding-bottom:4px;color:#1f3a5f}h3{font-size:15px;margin:1.2em 0 .3em}'
        . '.cover{background:#1f3a5f;color:#fff;padding:28px;border-radius:8px}.cover h1{color:#fff}.cover small{opacity:.8}'
        . 'section.madde{background:#fff;border:1px solid #d9dee5;border-radius:8px;padding:6px 20px 16px;margin:18px 0;page-break-inside:auto}'
        . 'table{border-collapse:collapse;width:100%;margin:6px 0 10px;font-size:13px}th,td{border:1px solid #cfd6df;padding:5px 8px;text-align:left;vertical-align:top}th{background:#e8edf4}'
        . 'dl{display:grid;grid-template-columns:240px 1fr;gap:6px 14px}dt{font-weight:600;color:#33415c}dd{margin:0;white-space:pre-wrap}.empty{color:#b3261e;font-style:italic}'
        . '.gerek{background:#eef3fa;border-left:4px solid #1f3a5f;padding:8px 12px;margin:8px 0}.meta{color:#5a6472;font-size:12px}'
        . '.pill{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;color:#fff}.pill.tamam{background:#1e8e4e}.pill.taslak{background:#c77c00}.pill.bos{background:#b3261e}'
        . '.toc li{margin:2px 0}.toc a{color:#1f3a5f;text-decoration:none}@media print{body{background:#fff}.wrap{padding:0}section.madde{border:0;padding:0;break-inside:auto}h2{break-after:avoid}}';
}
function report_value_html($fld, $val): string {
    if ($fld['type'] === 'table') {
        $rows = array_filter($val, fn($r) => array_filter($r, fn($x) => trim((string)$x) !== ''));
        $pre = pre_count($fld);
        $rows = array_filter($rows, fn($r) => array_filter(array_slice(array_values($r), $pre), fn($x) => trim((string)$x) !== '') || !$pre);
        if (!$rows) return '<span class="empty">Veri girilmedi</span>';
        $t = '<table><tr>'; foreach ($fld['cols'] as $c) $t .= '<th>' . h($c['l']) . '</th>'; $t .= '</tr>';
        foreach ($rows as $r) { $t .= '<tr>'; foreach ($fld['cols'] as $c) $t .= '<td>' . nl2br(h($r[$c['k']] ?? '')) . '</td>'; $t .= '</tr>'; }
        return $t . '</table>';
    }
    $v = trim((string)$val);
    if ($v === '') return '<span class="empty">Girilmedi</span>';
    if ($fld['type'] === 'url') return '<a href="' . h($v) . '">' . h($v) . '</a>';
    if ($fld['type'] === 'date' && preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $v, $mm)) return "$mm[3].$mm[2].$mm[1]";
    return nl2br(h($v));
}
function madde_report_html(string $slug, array $m, array $e, bool $zipMode): string {
    $st = madde_status($m, $e);
    $o = '<section class="madde" id="m' . h(mid_file($m['id'])) . '"><h2>' . ($m['id'] === '0' ? '' : 'MEDEK ' . h($m['id']) . ' – ') . h($m['baslik']) . ' <span class="pill ' . $st['durum'] . '">' . DURUM_ETIKET[$st['durum']] . '</span></h2>';
    $o .= '<div class="gerek">' . h($m['gerek']) . '</div><dl>';
    foreach ($m['alanlar'] as $fld) {
        if ($fld['type'] === 'table') { $o .= '</dl><h3>' . h($fld['label']) . '</h3>' . report_value_html($fld, table_rows_effective($fld, $e)) . '<dl>'; continue; }
        $o .= '<dt>' . h($fld['label']) . '</dt><dd>' . report_value_html($fld, field_value($fld, $e)) . '</dd>';
    }
    $o .= '</dl>';
    $o .= '<h3>Yayın ve kanıt</h3><dl><dt>Web sitesinde yayın durumu</dt><dd>' . h($e['yayin'] ?: '—') . '</dd>';
    if ($e['yayin_url']) $o .= '<dt>Yayın adresi</dt><dd><a href="' . h($e['yayin_url']) . '">' . h($e['yayin_url']) . '</a></dd>';
    $urls = array_filter(array_map('trim', preg_split('/\R/', (string)$e['kanit_url']) ?: []));
    if ($urls) { $o .= '<dt>Kanıt bağlantıları</dt><dd>'; foreach ($urls as $u) $o .= '<a href="' . h($u) . '">' . h($u) . '</a><br>'; $o .= '</dd>'; }
    if ($e['files']) {
        $o .= '<dt>Kanıt dosyaları</dt><dd>';
        foreach ($e['files'] as $f) $o .= ($zipMode ? '<a href="kanit/' . h(($m['kapsam'] === 'birim' ? '_birim' : $slug) . '/' . mid_file($m['id']) . '/' . $f['id'] . '_' . $f['name']) . '">' . h($f['name']) . '</a>' : h($f['name'])) . '<br>';
        $o .= '</dd>';
    }
    if ($e['not']) $o .= '<dt>Not</dt><dd>' . nl2br(h($e['not'])) . '</dd>';
    $o .= '</dl>';
    if ($e['updated']) $o .= '<p class="meta">Son güncelleme: ' . h(date('d.m.Y H:i', strtotime($e['updated']))) . ' · ' . h($e['by']) . '</p>';
    return $o . '</section>';
}
function report_page(string $title, string $inner, string $meta = ''): string {
    return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title><style>' . report_css() . '</style></head><body><div class="wrap">' . $inner . '<p class="meta">' . h($meta) . '</p></div></body></html>';
}
function export_madde_html(string $slug, array $m): string {
    $pr = prog($slug); $e = entry_load($slug, $m);
    $inner = '<div class="cover"><small>MEDEK Önlisans Program Ölçütleri v2.0 · Giresun Üniversitesi Tirebolu Mehmet Bayrak MYO</small><h1>' . h($pr['ad']) . '</h1><div>' . ($m['id'] === '0' ? '' : 'Madde ' . h($m['id'])) . '</div></div>';
    $inner .= madde_report_html($slug, $m, $e, false);
    if (($m['hesap'] ?? '') === 'akts') $inner .= akts_report($slug);
    return report_page($pr['ad'] . ' – MEDEK ' . $m['id'], $inner, 'Oluşturma: ' . date('d.m.Y H:i') . ' · MEDEK Form');
}
function akts_report(string $slug): string {
    $t = akts_ozet($slug); $o = '<section class="madde"><h2>AKTS özeti</h2><table><tr><th>Madde</th><th>Ölçüt</th><th>Mevcut</th><th>Gereken</th><th>Sonuç</th></tr>';
    foreach ($t['olcutler'] as [$mid, $ad, $v, $g]) $o .= '<tr><td>' . $mid . '</td><td>' . h($ad) . '</td><td>' . $v . '</td><td>≥ ' . $g . '</td><td>' . ($v >= $g ? 'Sağlıyor' : 'Eksik') . '</td></tr>';
    return $o . '</table></section>';
}
function export_program_html(string $slug, bool $zipMode = false): string {
    $pr = prog($slug); $ms = maddeler_for($slug);
    $toc = '<ol class="toc">'; $body = ''; $sum = ['tamam' => 0, 'taslak' => 0, 'bos' => 0];
    foreach ($ms as $m) {
        $e = entry_load($slug, $m); $st = madde_status($m, $e); $sum[$st['durum']]++;
        $toc .= '<li><a href="#m' . h(mid_file($m['id'])) . '">' . ($m['id'] === '0' ? '' : h($m['id']) . ' ') . h($m['baslik']) . '</a> ' . '<span class="pill ' . $st['durum'] . '">' . DURUM_ETIKET[$st['durum']] . '</span></li>';
        $body .= madde_report_html($slug, $m, $e, $zipMode);
        if ($m['id'] === '4.5') $body .= akts_report($slug);
    }
    $inner = '<div class="cover"><small>MEDEK Önlisans Program Ölçütleri v2.0 · Giresun Üniversitesi Tirebolu Mehmet Bayrak MYO</small><h1>' . h($pr['ad']) . ' Programı</h1><div>' . h($pr['bolum']) . ' · MEDEK ' . h($pr['medekno']) . '</div><br><small>Tamam: ' . $sum['tamam'] . ' · Taslak: ' . $sum['taslak'] . ' · Boş: ' . $sum['bos'] . ' (toplam ' . count($ms) . ' form)</small></div>';
    $inner .= '<h2>İçindekiler</h2>' . $toc . '</ol>' . $body;
    return report_page($pr['ad'] . ' – MEDEK Program Raporu', $inner, 'Oluşturma: ' . date('d.m.Y H:i') . ' · MEDEK Form');
}
