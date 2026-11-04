<?php
declare(strict_types=1);
/** Sayfa/denetleyici işlevleri ve yönlendirme. */

function not_found(string $msg = 'Sayfa bulunamadı.'): never { http_response_code(404); layout('404', '<h1>404</h1><p>' . h($msg) . '</p><p><a href="/">Ana sayfa</a></p>'); exit; }
function forbidden(): never { http_response_code(403); layout('Yetkisiz', '<h1>Erişim yok</h1><p>Bu sayfayı görüntüleme yetkiniz yok.</p><p><a href="/">Ana sayfa</a></p>'); exit; }
function send_file_download(string $name, string $mime, string $bin): never {
    header('Content-Type: ' . $mime);
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
    header('Content-Length: ' . strlen($bin)); echo $bin; exit;
}
function guard_program(string $slug): array { require_login(); $p = prog($slug) ?? not_found('Program bulunamadı.'); if (!can_view($slug)) forbidden(); return $p; }
function get_madde(string $slug, string $mid): array { $ms = maddeler_for($slug); return $ms[$mid] ?? not_found('Madde bulunamadı.'); }

function program_progress(string $slug): array {
    $ms = maddeler_for($slug); $by = []; $all = ['dolu' => 0, 'toplam' => 0, 'tamam' => 0, 'n' => 0];
    foreach ($ms as $m) {
        $st = madde_status($m, entry_load($slug, $m));
        $o = $m['olcut']; $by[$o] ??= ['dolu' => 0, 'toplam' => 0, 'tamam' => 0, 'n' => 0];
        foreach ([&$by[$o], &$all] as &$t) { $t['dolu'] += $st['dolu']; $t['toplam'] += $st['toplam']; $t['n']++; if ($st['durum'] === 'tamam') $t['tamam']++; }
        unset($t);
    }
    foreach ($by as &$b) $b['pct'] = $b['toplam'] ? (int)round($b['dolu'] * 100 / $b['toplam']) : 0; unset($b);
    $all['pct'] = $all['toplam'] ? (int)round($all['dolu'] * 100 / $all['toplam']) : 0;
    return ['by' => $by, 'all' => $all];
}

// ---------- genel ----------
function page_home(): void {
    $u = user();
    if (!$u) {
        $b = '<div class="hero"><h1>MEDEK Program Ölçütleri – Veri Toplama</h1><p>Giresun Üniversitesi Tirebolu Mehmet Bayrak MYO bölüm/program başkanları için MEDEK Önlisans Program Ölçütleri v2.0 (KL-007) veri giriş sistemi.</p><ol class="steps"><li><b>Şablonu indirin</b> – her madde için Excel şablonu</li><li><b>Doldurun</b> – çevrimdışı veya doğrudan formda</li><li><b>Yükleyin ve kanıt ekleyin</b></li><li><b>HTML çıktı alın</b> – program raporu</li></ol><p><a class="btn primary" href="/giris">Giriş yap</a> <a class="btn" href="/sablonlar">Boş şablonlar</a> <a class="btn ghost" href="/yardim">Nasıl çalışır?</a></p></div>';
        layout('Ana sayfa', $b); return;
    }
    if ($u['role'] === 'baskan') redirect('/p/' . $u['slug']);
    $ols = array_diff_key(OLCUTLER, [0 => 1]);
    $b = '<h1>Genel ilerleme</h1><p class="muted">Yüzdeler, her ölçütteki zorunlu alanların doluluk oranıdır.</p><div class="tablewrap"><table class="dt matrix"><thead><tr><th>Program</th>';
    foreach ($ols as $k => $v) $b .= '<th title="' . h($v) . '">Ölçüt ' . $k . '</th>';
    $b .= '<th>Toplam</th><th>Çıktı</th></tr></thead><tbody>';
    foreach (programs() as $slug => $p) {
        $pg = program_progress($slug);
        $b .= '<tr><td><a href="/p/' . h($slug) . '"><b>' . h($p['ad']) . '</b></a><br><small>' . h($p['bolum']) . '</small></td>';
        foreach ($ols as $k => $_) { $x = $pg['by'][$k]['pct'] ?? 0; $b .= '<td class="heat" style="--p:' . $x . '">%' . $x . '</td>'; }
        $b .= '<td class="heat" style="--p:' . $pg['all']['pct'] . '"><b>%' . $pg['all']['pct'] . '</b></td><td><a href="/export/' . h($slug) . '.html" target="_blank">HTML</a> · <a href="/export/' . h($slug) . '.zip">ZIP</a></td></tr>';
    }
    $b .= '</tbody></table></div><p><a class="btn" href="/export-tum.zip">⬇ Tüm programların çıktısı (ZIP)</a></p>';
    layout('Genel ilerleme', $b);
}
function page_login(): void {
    boot_session();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_check();
        if (login((string)($_POST['who'] ?? ''), (string)($_POST['pass'] ?? ''))) { $n = $_SESSION['next'] ?? '/'; unset($_SESSION['next']); redirect(str_starts_with($n, '/') && !str_starts_with($n, '//') ? $n : '/'); }
        flash('Kullanıcı veya şifre hatalı.', 'err');
    }
    $b = '<div class="card narrow"><h1>Giriş</h1><form method="post"><input type="hidden" name="_csrf" value="' . h(csrf()) . '"><div class="fld"><label>Hesap</label><select name="who"><option value="admin">Yönetici</option>';
    foreach (programs() as $s => $p) $b .= '<option value="' . h($s) . '">' . h($p['ad']) . ' – Program Başkanı</option>';
    $b .= '</select></div><div class="fld"><label>Şifre</label><input type="password" name="pass" required autofocus></div><button class="btn primary">Giriş</button></form></div>';
    layout('Giriş', $b);
}
function page_logout(): void { csrf_check(); boot_session(); $_SESSION = []; session_destroy(); redirect('/'); }

function page_sablonlar(): void {
    $ms = maddeler_for(null); $b = '<h1>Veri şablonları</h1><p>Her MEDEK maddesi için Excel şablonu. Doldurduktan sonra ilgili program sayfasından (<i>giriş gerekir</i>) yükleyin. Programa özel ön tanımlı satırlar (ör. programa özgü çıktılar) için şablonu program sayfanızdaki madde formundan indirin.</p>';
    $grp = []; foreach ($ms as $m) $grp[$m['olcut']][] = $m;
    foreach ($grp as $ol => $list) {
        $b .= '<h2>' . ($ol ? 'Ölçüt ' . $ol . ' – ' : '') . h(OLCUTLER[$ol]) . '</h2><table class="dt list"><tbody>';
        foreach ($list as $m) $b .= '<tr><td class="mid">' . h($m['id'] === '0' ? '—' : $m['id']) . '</td><td><b>' . h($m['baslik']) . '</b><br><small>' . h($m['gerek']) . '</small></td><td>' . tur_etiket($m['tur']) . ($m['kapsam'] === 'birim' ? ' <span class="tag tb">ortak</span>' : '') . '</td><td><a class="btn small" href="/sablon/' . h($m['id']) . '.xlsx">⬇ Excel</a></td></tr>';
        $b .= '</tbody></table>';
    }
    layout('Şablonlar', $b);
}
function page_yardim(): void {
    $b = '<h1>Yardım</h1><div class="prose"><h2>Çalışma akışı</h2><ol><li><b>Giriş:</b> Yönetimden aldığınız şifreyle kendi programınızın hesabıyla girin.</li><li><b>Madde seçin:</b> Program sayfasında ölçütler ve maddeler listelenir. Durum rozetleri: <i>Boş</i>, <i>Taslak</i>, <i>Tamam</i> (tüm zorunlu * alanlar dolu).</li><li><b>Şablon indirin:</b> Madde sayfasındaki “Excel şablonunu indir”. Programınıza özgü ön tanımlı satırlar (programa özgü çıktılar, PÇ listesi) şablonda hazır gelir. Toplu için program sayfasındaki “Tüm şablonlar (ZIP)”.</li><li><b>Doldurun:</b> Excel\'de yalnızca <b>sarı hücreleri</b> doldurun. Sayfa adlarını ve A sütunundaki kodları değiştirmeyin. Alternatif olarak doğrudan web formunu doldurabilirsiniz.</li><li><b>Yükleyin:</b> Madde sayfasındaki “Doldurulmuş şablonu yükle”. İçeri aktarılan veriler hemen kaydedilir; form üzerinde düzeltebilirsiniz.</li><li><b>Kanıt ekleyin:</b> Tutanak, anket sonucu, rapor gibi belgeleri “Kanıt dosyaları” bölümünden yükleyin ya da URL yazın.</li><li><b>Çıktı alın:</b> “Önizleme / HTML çıktı” tek madde; program sayfasındaki “Program raporu” tüm program için tek HTML dosyasıdır. ZIP seçeneği kanıt dosyalarını da içerir.</li></ol><h2>Madde türleri</h2><ul><li>' . tur_etiket('W') . ' Program web sayfasında yayımlanması gereken bilgi.</li><li>' . tur_etiket('K') . ' Başvuru dosyasında kanıt olarak tutulması gereken belge/kayıt.</li><li>' . tur_etiket('İ') . ' İç süreç; web\'de görünmesi şart değil, açıklaması istenir.</li></ul><p><b>Birim ortak formları</b> (6.3, 7.2–7.6, 8.1, 8.3, 8.4) tüm programlar için tektir; birden fazla program başkanı doldurabilir, son kaydeden geçerli olur.</p><h2>AKTS hesabı</h2><p>4.2 maddesindeki ders planı tablosundan mesleki ≥60, programa özgü çıktıyı destekleyen ≥20, uygulama/staj ≥15 ve dış paydaş katkılı ≥5 AKTS ölçütleri otomatik kontrol edilir.</p><h2>Sık sorulanlar</h2><p><b>Şablon yüklenmiyor:</b> Dosyayı .xlsx olarak kaydedin (xls/ods değil) ve şablonu siteden indirdiğinizden emin olun. <b>Şifremi unuttum:</b> Yöneticiye başvurun.</p></div>';
    layout('Yardım', $b);
}

// ---------- program ----------
function page_program(string $slug): void {
    $p = guard_program($slug); $ms = maddeler_for($slug); $pg = program_progress($slug);
    $b = '<div class="mh"><h1>' . h($p['ad']) . '</h1><p class="muted">' . h($p['bolum']) . ' · MEDEK program no: ' . h($p['medekno']) . '</p><div class="status">' . bar($pg['all']['pct']) . ' <small>' . $pg['all']['tamam'] . '/' . $pg['all']['n'] . ' form tamam</small></div></div>';
    $b .= '<div class="toolbar"><a class="btn primary" target="_blank" href="/export/' . h($slug) . '.html">Program raporu (HTML)</a><a class="btn" href="/export/' . h($slug) . '.zip">⬇ Rapor + kanıtlar (ZIP)</a><a class="btn" href="/sablon-paket/' . h($slug) . '.zip">⬇ Tüm şablonlar (ZIP)</a></div>';
    $grp = []; foreach ($ms as $m) $grp[$m['olcut']][] = $m;
    foreach ($grp as $ol => $list) {
        $b .= '<h2>' . ($ol ? 'Ölçüt ' . $ol . ' – ' : '') . h(OLCUTLER[$ol]) . '</h2><table class="dt list"><tbody>';
        foreach ($list as $m) {
            $e = entry_load($slug, $m); $st = madde_status($m, $e);
            $b .= '<tr><td class="mid">' . h($m['id'] === '0' ? '—' : $m['id']) . '</td><td><a href="/p/' . h($slug) . '/' . h($m['id']) . '"><b>' . h($m['baslik']) . '</b></a>' . ($m['kapsam'] === 'birim' ? ' <span class="tag tb">ortak</span>' : '') . '<br><small>' . h($m['gerek']) . '</small></td><td>' . tur_etiket($m['tur']) . '</td><td>' . pill($st['durum']) . '<br>' . bar($st['pct']) . '</td><td><small>' . $st['kanit'] . ' kanıt</small></td><td><a class="btn small" href="/sablon/' . h($m['id']) . '.xlsx?p=' . h($slug) . '">⬇ Excel</a></td></tr>';
        }
        $b .= '</tbody></table>';
    }
    layout($p['ad'], $b, ['side' => sidebar($slug, null)]);
}
function page_madde(string $slug, string $mid): void {
    $p = guard_program($slug); $m = get_madde($slug, $mid); $e = entry_load($slug, $m); $ro = !can_edit($slug, $m);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if ($ro) forbidden(); csrf_check();
        $new = ['v' => parse_post($m, $_POST), 'yayin' => str_clean($_POST['yayin'] ?? ''), 'yayin_url' => str_clean($_POST['yayin_url'] ?? ''), 'kanit_url' => str_clean($_POST['kanit_url'] ?? ''), 'not' => str_clean($_POST['not'] ?? '')] + $e;
        entry_save($slug, $m, $new); flash('Kaydedildi.'); redirect('/p/' . $slug . '/' . $mid);
    }
    layout(($mid === '0' ? '' : $mid . ' ') . $m['baslik'] . ' · ' . $p['ad'], madde_form_html($slug, $m, $e, $ro), ['side' => sidebar($slug, $mid)]);
}
function action_import(string $slug, string $mid): void {
    guard_program($slug); $m = get_madde($slug, $mid); if (!can_edit($slug, $m)) forbidden(); csrf_check();
    $f = $_FILES['xlsx'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) { flash('Dosya yüklenemedi (hata ' . ($f['error'] ?? '?') . ').', 'err'); redirect("/p/$slug/$mid"); }
    if (ext_of($f['name']) !== 'xlsx') { flash('Yalnızca .xlsx dosyası yükleyin.', 'err'); redirect("/p/$slug/$mid"); }
    try {
        [$patch, $warn] = sablon_import($f['tmp_name'], $m, $slug);
        $e = entry_load($slug, $m);
        foreach ($patch['v'] as $k => $v) $e['v'][$k] = $v;
        foreach (['yayin', 'yayin_url', 'kanit_url', 'not'] as $k) if (!empty($patch[$k])) $e[$k] = $patch[$k];
        entry_save($slug, $m, $e);
        flash('Şablon içeri aktarıldı (' . $patch['_n'] . ' dolu alan/satır). Lütfen formu kontrol edin.');
        foreach ($warn as $w) flash($w, 'warn');
    } catch (Throwable $ex) { flash('İçe aktarma başarısız: ' . $ex->getMessage(), 'err'); }
    redirect("/p/$slug/$mid");
}
function action_upload(string $slug, string $mid): void {
    guard_program($slug); $m = get_madde($slug, $mid); if (!can_edit($slug, $m)) forbidden(); csrf_check();
    $e = entry_load($slug, $m); $dir = upload_dir($slug, $m); $ok = 0;
    $fs = $_FILES['dosya'] ?? ['name' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
    foreach ((array)$fs['name'] as $i => $name) {
        $err = $fs['error'][$i]; $size = (int)$fs['size'][$i];
        if ($err !== UPLOAD_ERR_OK) { flash(safe_name((string)$name) . ': yüklenemedi (hata ' . $err . ').', 'err'); continue; }
        if ($size > MAX_UPLOAD) { flash(safe_name((string)$name) . ': 20 MB sınırını aşıyor.', 'err'); continue; }
        $sn = safe_name((string)$name);
        if (!in_array(ext_of($sn), IZINLI_UZANTI, true)) { flash($sn . ': bu dosya türüne izin verilmiyor.', 'err'); continue; }
        $id = bin2hex(random_bytes(6));
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        if (move_uploaded_file($fs['tmp_name'][$i], "$dir/{$id}_$sn")) { $e['files'][] = ['id' => $id, 'name' => $sn, 'size' => $size, 'at' => date('c'), 'by' => user()['ad']]; $ok++; }
    }
    if ($ok) { entry_save($slug, $m, $e); flash("$ok dosya yüklendi."); }
    redirect("/p/$slug/$mid");
}
function action_file_delete(string $slug, string $mid): void {
    guard_program($slug); $m = get_madde($slug, $mid); if (!can_edit($slug, $m)) forbidden(); csrf_check();
    $e = entry_load($slug, $m); $id = (string)($_POST['id'] ?? '');
    foreach ($e['files'] as $i => $f) if ($f['id'] === $id) { @unlink(upload_dir($slug, $m) . "/{$f['id']}_{$f['name']}"); array_splice($e['files'], $i, 1); entry_save($slug, $m, $e); flash('Dosya silindi.'); break; }
    redirect("/p/$slug/$mid");
}
function action_file_get(string $slug, string $mid, string $id): void {
    guard_program($slug); $m = get_madde($slug, $mid); $e = entry_load($slug, $m);
    foreach ($e['files'] as $f) if ($f['id'] === $id) {
        $path = upload_dir($slug, $m) . "/{$f['id']}_{$f['name']}"; if (!is_file($path)) break;
        header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($f['name'])); header('Content-Length: ' . filesize($path)); readfile($path); exit;
    }
    not_found('Dosya bulunamadı.');
}

// ---------- şablon / çıktı ----------
function action_template(string $mid): void {
    $slug = (string)($_GET['p'] ?? ''); if ($slug !== '' && !prog($slug)) $slug = '';
    $m = get_madde($slug, $mid);
    send_file_download('MEDEK-' . str_replace('.', '_', $mid) . ($slug ? '-' . $slug : '') . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', sablon_xlsx($slug, $m));
}
function zip_to_string(callable $fill): string {
    $tmp = tempnam(sys_get_temp_dir(), 'mz'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE); $fill($z); $z->close();
    $b = file_get_contents($tmp); unlink($tmp); return $b;
}
function action_template_pack(string $slug): void {
    guard_program($slug);
    $bin = zip_to_string(function (ZipArchive $z) use ($slug) { foreach (maddeler_for($slug) as $m) $z->addFromString('MEDEK-' . str_pad(str_replace('.', '_', $m['id']), 4, '0', STR_PAD_LEFT) . '-' . preg_replace('/[^\p{L}\p{N}]+/u', '-', $m['baslik']) . '.xlsx', sablon_xlsx($slug, $m)); });
    send_file_download('MEDEK-sablonlar-' . $slug . '.zip', 'application/zip', $bin);
}
function add_program_zip(ZipArchive $z, string $slug, string $prefix = ''): void {
    $z->addFromString($prefix . 'rapor.html', export_program_html($slug, true));
    foreach (maddeler_for($slug) as $m) {
        $e = entry_load($slug, $m); $scope = $m['kapsam'] === 'birim' ? '_birim' : $slug;
        foreach ($e['files'] as $f) { $p = upload_dir($slug, $m) . "/{$f['id']}_{$f['name']}"; if (is_file($p)) $z->addFile($p, $prefix . "kanit/$scope/" . mid_file($m['id']) . "/{$f['id']}_{$f['name']}"); }
    }
}
function action_export_program(string $slug, string $ext): void {
    guard_program($slug);
    if ($ext === 'zip') send_file_download('MEDEK-' . $slug . '.zip', 'application/zip', zip_to_string(fn($z) => add_program_zip($z, $slug)));
    $html = export_program_html($slug);
    if (isset($_GET['indir'])) send_file_download('MEDEK-' . $slug . '.html', 'text/html; charset=utf-8', $html);
    header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
}
function action_export_madde(string $slug, string $mid): void {
    guard_program($slug); $m = get_madde($slug, $mid); $html = export_madde_html($slug, $m);
    if (isset($_GET['indir'])) send_file_download('MEDEK-' . $slug . '-' . str_replace('.', '_', $mid) . '.html', 'text/html; charset=utf-8', $html);
    header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
}
function action_export_all(): void {
    require_login(); if (!is_admin()) forbidden();
    send_file_download('MEDEK-tum-programlar.zip', 'application/zip', zip_to_string(function ($z) { foreach (programs() as $s => $_) add_program_zip($z, $s, $s . '/'); }));
}

// ---------- yönetim ----------
function page_admin(): void {
    require_login(); if (!is_admin()) forbidden();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_check(); $u = json_read(users_file(), []); $who = (string)($_POST['who'] ?? ''); $pw = (string)($_POST['pass'] ?? '');
        if (strlen($pw) < 8) flash('Şifre en az 8 karakter olmalı.', 'err');
        elseif ($who === 'admin') { $u['admin']['hash'] = password_hash($pw, PASSWORD_DEFAULT); json_write(users_file(), $u); flash('Yönetici şifresi güncellendi.'); }
        elseif (isset($u['baskan'][$who])) { $u['baskan'][$who]['hash'] = password_hash($pw, PASSWORD_DEFAULT); json_write(users_file(), $u); flash(prog($who)['ad'] . ' şifresi güncellendi.'); }
        else flash('Hesap bulunamadı.', 'err');
        redirect('/yonetim');
    }
    $b = '<h1>Yönetim</h1><h2>Şifre sıfırlama</h2><form method="post" class="inlineform"><input type="hidden" name="_csrf" value="' . h(csrf()) . '"><select name="who"><option value="admin">Yönetici</option>';
    foreach (programs() as $s => $p) $b .= '<option value="' . h($s) . '">' . h($p['ad']) . '</option>';
    $b .= '</select> <input type="text" name="pass" placeholder="Yeni şifre (en az 8)" minlength="8" required> <button class="btn">Güncelle</button></form>';
    $b .= '<h2>Veri</h2><p><a class="btn" href="/export-tum.zip">⬇ Tüm programlar (ZIP)</a></p><p class="muted">Veri klasörü: <code>' . h(DATA) . '</code>. Yedek için bu klasörü kopyalamanız yeterlidir.' . (is_file(DATA . '/ILK-SIFRELER.txt') ? ' <b>İlk şifre dosyası hâlâ duruyor (ILK-SIFRELER.txt); şifreleri dağıttıktan sonra silin.</b>' : '') . '</p>';
    layout('Yönetim', $b);
}

// ---------- router ----------
function route() {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $path = rtrim($path, '/') ?: '/';
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $r = fn(string $re) => preg_match('#^' . $re . '$#u', $path, $GLOBALS['__m']) === 1;
    $M = fn() => $GLOBALS['__m'];
    $mid = '(\d{1,2}(?:\.\d{1,2})?)';
    if ($path === '/') return page_home();
    if ($path === '/giris') return page_login();
    if ($path === '/cikis' && $post) return page_logout();
    if ($path === '/yardim') return page_yardim();
    if ($path === '/sablonlar') return page_sablonlar();
    if ($path === '/yonetim') return page_admin();
    if ($path === '/export-tum.zip') return action_export_all();
    if ($r("/sablon/$mid\\.xlsx")) return action_template($M()[1]);
    if ($r('/sablon-paket/([a-z0-9-]+)\.zip')) return action_template_pack($M()[1]);
    if ($r('/export/([a-z0-9-]+)\.(html|zip)')) return action_export_program($M()[1], $M()[2]);
    if ($r("/export/([a-z0-9-]+)/$mid\\.html")) return action_export_madde($M()[1], $M()[2]);
    if ($r('/dosya/([a-z0-9-]+)/(\d+(?:\.\d+)?)/([a-f0-9]{12})')) return action_file_get($M()[1], $M()[2], $M()[3]);
    if ($r('/p/([a-z0-9-]+)')) return page_program($M()[1]);
    if ($r("/p/([a-z0-9-]+)/$mid")) return page_madde($M()[1], $M()[2]);
    if ($post && $r("/p/([a-z0-9-]+)/$mid/yukle")) return action_import($M()[1], $M()[2]);
    if ($post && $r("/p/([a-z0-9-]+)/$mid/dosya")) return action_upload($M()[1], $M()[2]);
    if ($post && $r("/p/([a-z0-9-]+)/$mid/dosya-sil")) return action_file_delete($M()[1], $M()[2]);
    not_found();
}
