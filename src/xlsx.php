<?php
declare(strict_types=1);
/** Bağımlılıksız minimal XLSX yazıcı/okuyucu + MEDEK şablon üretimi ve içe aktarma. */

function xl_col(int $i): string { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; }
function xl_esc(string $s): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

/**
 * $sheets: [['name'=>, 'rows'=>[[ ['v'=>..,'s'=>styleIdx,'n'=>bool], ...]], 'widths'=>[..], 'valid'=>[['ref'=>'D5','list'=>[..]]]]]
 * hücre: string | ['v'=>, 's'=>]
 */
function xlsx_build(array $sheets): string {
    $tmp = tempnam(sys_get_temp_dir(), 'mx'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach ($sheets as $i => $sh) {
        $n = $i + 1;
        $ct .= "<Override PartName=\"/xl/worksheets/sheet$n.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        $wb .= '<sheet name="' . xl_esc(mb_substr($sh['name'], 0, 31)) . "\" sheetId=\"$n\" r:id=\"rId$n\"/>";
        $rel .= "<Relationship Id=\"rId$n\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$n.xml\"/>";
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if (!empty($sh['widths'])) { $x .= '<cols>'; foreach ($sh['widths'] as $ci => $w) $x .= '<col min="' . ($ci + 1) . '" max="' . ($ci + 1) . "\" width=\"$w\" customWidth=\"1\"/>"; $x .= '</cols>'; }
        $x .= '<sheetData>';
        foreach ($sh['rows'] as $ri => $row) {
            $x .= '<row r="' . ($ri + 1) . '">';
            foreach ($row as $ci => $cell) {
                if (!is_array($cell)) $cell = ['v' => $cell];
                $ref = xl_col($ci) . ($ri + 1); $s = $cell['s'] ?? 0; $v = (string)($cell['v'] ?? '');
                if ($v === '') $x .= "<c r=\"$ref\" s=\"$s\"/>";
                elseif (!empty($cell['n']) && is_numeric($v)) $x .= "<c r=\"$ref\" s=\"$s\"><v>" . xl_esc($v) . '</v></c>';
                else $x .= "<c r=\"$ref\" s=\"$s\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . xl_esc($v) . '</t></is></c>';
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($sh['valid'])) {
            $x .= '<dataValidations count="' . count($sh['valid']) . '">';
            foreach ($sh['valid'] as $dv) $x .= '<dataValidation type="list" allowBlank="1" showErrorMessage="0" sqref="' . $dv['ref'] . '"><formula1>"' . xl_esc(implode(',', $dv['list'])) . '"</formula1></dataValidation>';
            $x .= '</dataValidations>';
        }
        $x .= '</worksheet>';
        $z->addFromString("xl/worksheets/sheet$n.xml", $x);
    }
    $ct .= '</Types>'; $wb .= '</sheets></workbook>';
    $rel .= '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><i/><sz val="9"/><color rgb="FF666666"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts>'
        . '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF8DC"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEEEEEE"/></patternFill></fill></fills>'
        . '<borders count="2"><border/><border><left style="thin"><color rgb="FFBBBBBB"/></left><right style="thin"><color rgb="FFBBBBBB"/></right><top style="thin"><color rgb="FFBBBBBB"/></top><bottom style="thin"><color rgb="FFBBBBBB"/></bottom></border></borders>'
        . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="7"><xf/>'
        . '<xf fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
        . '<xf fontId="2" applyFont="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
        . '<xf fillId="3" borderId="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
        . '<xf fontId="3" applyFont="1"/>'
        . '<xf applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
        . '<xf fillId="4" borderId="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
        . '</cellXfs></styleSheet>';
    $z->addFromString('[Content_Types].xml', $ct);
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', $wb);
    $z->addFromString('xl/_rels/workbook.xml.rels', $rel);
    $z->addFromString('xl/styles.xml', $styles);
    $z->close();
    $bin = file_get_contents($tmp); unlink($tmp);
    return $bin;
}
const S_HEAD = 1, S_NOTE = 2, S_INPUT = 3, S_TITLE = 4, S_WRAP = 5, S_PRE = 6;

function xl_texts(SimpleXMLElement $e, string $ns): string {
    $e->registerXPathNamespace('m', $ns); $t = '';
    foreach ($e->xpath('.//m:t') ?: [] as $tt) $t .= (string)$tt;
    return $t;
}
/** XLSX → ['Sayfa adı' => [satır => [sütun idx => değer]]] */
function xlsx_read(string $file): array {
    $z = new ZipArchive();
    if ($z->open($file) !== true) throw new RuntimeException('Dosya geçerli bir XLSX değil.');
    $get = fn(string $n) => ($s = $z->getFromName($n)) === false ? null : $s;
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $strings = [];
    if (($ss = $get('xl/sharedStrings.xml')) !== null) {
        $x = simplexml_load_string($ss); $x->registerXPathNamespace('m', $ns);
        foreach ($x->xpath('//m:si') as $si) $strings[] = xl_texts($si, $ns);
    }
    $wb = simplexml_load_string($get('xl/workbook.xml') ?? ''); if (!$wb) throw new RuntimeException('Çalışma kitabı okunamadı.');
    $rels = simplexml_load_string($get('xl/_rels/workbook.xml.rels') ?? ''); $map = [];
    foreach ($rels->Relationship as $r) $map[(string)$r['Id']] = (string)$r['Target'];
    $wb->registerXPathNamespace('m', $ns); $out = [];
    foreach ($wb->xpath('//m:sheets/m:sheet') as $sh) {
        $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $path = ltrim($map[$rid] ?? '', '/'); if (!str_starts_with($path, 'xl/')) $path = 'xl/' . $path;
        $sx = simplexml_load_string($get($path) ?? ''); if (!$sx) continue;
        $sx->registerXPathNamespace('m', $ns); $rows = [];
        foreach ($sx->xpath('//m:sheetData/m:row') as $row) {
            $r = (int)$row['r'];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string)$c['r'], $mm); $ci = 0;
                foreach (str_split($mm[1] ?? 'A') as $ch) $ci = $ci * 26 + (ord($ch) - 64);
                $t = (string)$c['t']; $val = '';
                if ($t === 's') $val = $strings[(int)$c->v] ?? '';
                elseif ($t === 'inlineStr') $val = xl_texts($c->is, $ns);
                else $val = (string)$c->v;
                if ($val !== '') $rows[$r][$ci - 1] = $val;
            }
        }
        $out[(string)$sh['name']] = $rows;
    }
    $z->close();
    return $out;
}

// ---------- MEDEK şablonu ----------
function ek_alanlar(array $m): array {
    $a = [['_yayin', 'Web sitesinde yayın durumu', 'select', YAYIN, 'Bu bilgi program web sayfasında yayımlandı mı?'],
          ['_yayin_url', 'Yayın adresi (URL)', 'url', null, ''],
          ['_kanit_url', 'Kanıt bağlantıları (her satıra bir URL)', 'textarea', null, 'Dosya yüklemeleri sistem üzerinden yapılır.'],
          ['_not', 'Not', 'textarea', null, '']];
    return $a;
}
function sablon_xlsx(string $slug, array $m): string {
    $pr = $slug !== '' ? prog($slug) : null;
    $rows = [];
    $rows[] = [['v' => 'MEDEKFORM|' . $m['id'] . '|' . SCHEMA_VERSION . '|' . ($slug ?: '*'), 's' => S_NOTE], ['v' => 'MEDEK ' . $m['id'] . ' – ' . $m['baslik'] . ($pr ? ' · ' . $pr['ad'] : ''), 's' => S_TITLE]];
    $rows[] = [['v' => '', 's' => 0], ['v' => 'Gereklilik: ' . $m['gerek'], 's' => S_WRAP]];
    $rows[] = [['v' => '', 's' => 0], ['v' => 'Nasıl doldurulur: ' . $m['ipucu'] . ' Yalnızca sarı hücreleri doldurun; A sütununu ve sayfa adlarını değiştirmeyin. Tablo alanları “T-…” sayfalarındadır.', 's' => S_NOTE]];
    $rows[] = [['v' => 'Kod', 's' => S_HEAD], ['v' => 'Alan', 's' => S_HEAD], ['v' => 'Açıklama', 's' => S_HEAD], ['v' => 'Değer', 's' => S_HEAD]];
    $valid = []; $sheets = [];
    $entry = ['v' => [], 'files' => []];
    foreach ($m['alanlar'] as $f) {
        $r = count($rows) + 1;
        if ($f['type'] === 'table') {
            $rows[] = [['v' => $f['id'], 's' => S_NOTE], ['v' => $f['label'] . ($f['req'] ? ' *' : ''), 's' => S_WRAP], ['v' => 'En az ' . ($f['min'] ?? 1) . ' satır.', 's' => S_NOTE], ['v' => '→ “' . sheet_name($f['id']) . '” sayfasını doldurun', 's' => S_PRE]];
            $head = []; $w = []; $tv = [];
            foreach ($f['cols'] as $c) { $head[] = ['v' => $c['l'], 's' => S_HEAD]; $w[] = max(14, min(40, mb_strlen($c['l']) + 6)); }
            $trs = [$head]; $pre = pre_count($f);
            foreach (table_rows_effective($f, $entry) as $ri => $row) {
                $line = [];
                foreach ($f['cols'] as $ci => $c) $line[] = ['v' => $row[$c['k']] ?? '', 's' => $ci < $pre ? S_PRE : S_INPUT, 'n' => $c['t'] === 'number'];
                $trs[] = $line;
            }
            $empty = max(0, max(($f['min'] ?? 1) + 2, 6) - count($trs) + 1);
            for ($i = 0; $i < $empty; $i++) $trs[] = array_map(fn($c) => ['v' => '', 's' => S_INPUT], $f['cols']);
            foreach ($f['cols'] as $ci => $c) if ($c['t'] === 'select' && $c['o']) $tv[] = ['ref' => xl_col($ci) . '2:' . xl_col($ci) . (count($trs) + 30), 'list' => array_values(array_filter($c['o'], fn($x) => $x !== ''))];
            $sheets[] = ['name' => sheet_name($f['id']), 'rows' => $trs, 'widths' => $w, 'valid' => $tv];
        } else {
            $def = ($f['default'] ?? '');
            $rows[] = [['v' => $f['id'], 's' => S_NOTE], ['v' => $f['label'] . ($f['req'] ? ' *' : ''), 's' => S_WRAP], ['v' => (string)($f['help'] ?? ''), 's' => S_NOTE], ['v' => $def, 's' => S_INPUT, 'n' => $f['type'] === 'number']];
            if ($f['type'] === 'select' && !empty($f['options'])) $valid[] = ['ref' => "D$r", 'list' => $f['options']];
        }
    }
    foreach (ek_alanlar($m) as [$id, $lab, $t, $opts, $help]) {
        $r = count($rows) + 1;
        $rows[] = [['v' => $id, 's' => S_NOTE], ['v' => $lab, 's' => S_WRAP], ['v' => $help, 's' => S_NOTE], ['v' => '', 's' => S_INPUT]];
        if ($opts) $valid[] = ['ref' => "D$r", 'list' => $opts];
    }
    array_unshift($sheets, ['name' => 'Form', 'rows' => $rows, 'widths' => [14, 46, 46, 60], 'valid' => $valid]);
    return xlsx_build($sheets);
}

/** Yüklenen XLSX'i madde girdisine çevir. [entryPatch, uyarılar] */
function sablon_import(string $file, array $m, ?string $slug): array {
    $wb = xlsx_read($file); $warn = [];
    $form = $wb['Form'] ?? null; if (!$form) throw new RuntimeException('“Form” sayfası bulunamadı. Bu dosya MEDEK Form şablonu değil.');
    $a1 = $form[1][0] ?? '';
    $p = explode('|', $a1);
    if (($p[0] ?? '') !== 'MEDEKFORM') throw new RuntimeException('Şablon kimliği (A1 hücresi) bulunamadı. Siteden indirdiğiniz şablonu kullanın.');
    if (($p[1] ?? '') !== $m['id']) throw new RuntimeException('Bu şablon MEDEK ' . ($p[1] ?? '?') . ' maddesine ait; yüklediğiniz sayfa ' . $m['id'] . '.');
    if (($p[2] ?? '') !== SCHEMA_VERSION) $warn[] = 'Şablon sürümü farklı (' . ($p[2] ?? '?') . '); alanlar eşleştirilebildiği kadarıyla alındı.';
    if ($slug && ($p[3] ?? '*') !== '*' && $p[3] !== $slug) $warn[] = 'Şablon başka bir programa (' . $p[3] . ') ait görünüyor; yine de içeri alındı.';
    $byId = []; foreach ($m['alanlar'] as $f) $byId[$f['id']] = $f;
    $patch = ['v' => []]; $n = 0;
    $serial = fn($x) => (is_numeric($x) && $x > 20000 && $x < 80000) ? gmdate('Y-m-d', (int)(($x - 25569) * 86400)) : $x;
    foreach ($form as $r => $cells) {
        if ($r < 5) continue;
        $key = trim($cells[0] ?? ''); $val = trim($cells[3] ?? '');
        if ($key === '') continue;
        if (in_array($key, ['_yayin', '_yayin_url', '_kanit_url', '_not'], true)) { $patch[ltrim($key, '_')] = $val; if ($val !== '') $n++; continue; }
        $f = $byId[$key] ?? null; if (!$f || $f['type'] === 'table') continue;
        if ($f['type'] === 'date') $val = $serial($val);
        if ($f['type'] === 'number') $val = str_replace(',', '.', $val);
        $patch['v'][$key] = $val; if ($val !== '') $n++;
    }
    foreach ($m['alanlar'] as $f) {
        if ($f['type'] !== 'table') continue;
        $sh = $wb[sheet_name($f['id'])] ?? null;
        if ($sh === null) { $warn[] = '“' . sheet_name($f['id']) . '” sayfası yok; ' . $f['label'] . ' atlandı.'; continue; }
        $rows = [];
        foreach ($sh as $r => $cells) {
            if ($r < 2) continue; $row = []; $any = false;
            foreach ($f['cols'] as $ci => $c) {
                $x = trim((string)($cells[$ci] ?? ''));
                if ($c['t'] === 'date') $x = $serial($x);
                if ($c['t'] === 'number') $x = str_replace(',', '.', $x);
                $row[$c['k']] = $x; if ($x !== '') $any = true;
            }
            if ($any) $rows[] = $row;
        }
        $patch['v'][$f['id']] = $rows; $n += count($rows);
    }
    $patch['_n'] = $n;
    return [$patch, $warn];
}
