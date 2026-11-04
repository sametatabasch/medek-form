<?php
/**
 * MEDEK Önlisans Program Ölçütleri v2.0 (KL-007) – madde / alan şeması.
 * Sürüm değişirse SCHEMA_VERSION'ı artırın (şablon kimliğinde kullanılır).
 */
const SCHEMA_VERSION = 'v1';

// ---- yardımcılar ---------------------------------------------------------
function c(string $k, string $l, string $t = 'text', ?array $o = null): array { return ['k' => $k, 'l' => $l, 't' => $t, 'o' => $o]; }
/** alan: f(id, etiket, tür, seçenekler) – req varsayılan true */
function f(string $id, string $label, string $type = 'text', array $o = []): array { return ['id' => $id, 'label' => $label, 'type' => $type] + $o + ['req' => true]; }
function opt(string $id, string $label, string $type = 'text', array $o = []): array { return f($id, $label, $type, ['req' => false] + $o); }
function tbl(string $id, string $label, array $cols, array $o = []): array { return f($id, $label, 'table', $o + ['cols' => $cols, 'min' => 1]); }
const YN = ['Evet', 'Hayır'];
function yillar(): array { return array_map(fn($y) => [$y], ['2023-2024', '2024-2025', '2025-2026', '2026-2027']); }
const YAYIN = ['Yayında', 'Hazırlanıyor', 'Yok', 'Gerekmiyor'];

function geriBildirim(string $paydas, bool $secimli = false): array {
    $cols = [c('yil', 'Yıl / dönem'), c('yontem', 'Yöntem (anket, toplantı, görüşme…)'), c('katilim', 'Katılımcı sayısı', 'number')];
    if ($secimli) array_splice($cols, 1, 0, [c('paydas', 'Paydaş türü', 'select', ['Öğrenci', 'Öğretim elemanı', 'İdari personel'])]);
    array_push($cols, c('bulgu', 'Temel bulgu / talep'), c('aksiyon', 'Alınan karar / iyileştirme'), c('kanit', 'Kanıt (tutanak/rapor no, URL)'));
    return $cols;
}

// ---- ölçüt başlıkları ----------------------------------------------------
const OLCUTLER = [
    0 => 'Program Künyesi',
    1 => 'Öğrenci Kabulü, Gelişimi ve Mezuniyeti',
    2 => 'Program Eğitim Amaçları',
    3 => 'Program Çıktıları',
    4 => 'Öğretim Planı',
    5 => 'Sürekli İyileştirme',
    6 => 'Öğretim Kadrosu',
    7 => 'Altyapı',
    8 => 'Yönetim ve İdari Birimlerin Yapısı',
];

// MEDEK genel program çıktıları (PDF Bölüm 3, 11 madde)
const GENEL_PC = [
    'Mesleğe ilişkin güncel uygulamalı bilgiye sahiptir.',
    'İş sağlığı ve güvenliği ile kalite süreçlerine ilişkin bilgi sahibidir.',
    'Mesleğe ilişkin bilişim teknolojilerini kullanır.',
    'Mesleki problemleri analitik ve eleştirel biçimde değerlendirir.',
    'Yazılı ve sözlü iletişim kurar.',
    'Karmaşık sorunların çözümünde ekip üyesi olarak sorumluluk alır.',
    'Kariyerini planlar ve yaşam boyu öğrenme bilincine sahiptir.',
    'Veri toplama, değerlendirme ve duyurma süreçlerinde toplumsal, bilimsel, kültürel ve etik değerleri gözetir.',
    'Yabancı dil ile alanındaki gelişmeleri izler ve meslektaşlarıyla iletişim kurar.',
    'Birleşmiş Milletler Sürdürülebilir Kalkınma Amaçları konusunda farkındalığa sahiptir.',
    'Dijitalleşme, çevre bilinci, yeşil dönüşüm ve sürdürülebilirlik konularında farkındalığa sahiptir.',
];

function pcSecenek(): array { $a = []; for ($i = 1; $i <= 11; $i++) $a[] = "PÇ$i"; for ($i = 1; $i <= 5; $i++) $a[] = "ÖÇ$i"; return $a; }

/** Program bazlı ön tanımlı satırlar için $prog = programs.json kaydı (veya null) */
function maddeler(?array $prog = null): array {
    $ozgu = $prog['ozgu'] ?? array_fill(0, 5, '');
    $yil = yillar();
    $pcKod = pcSecenek();
    $pcRows = array_map(fn($k) => [$k], $pcKod);
    $dersCols = [c('donem', 'Dönem', 'select', ['1', '2', '3', '4']), c('kod', 'Ders kodu'), c('ad', 'Ders adı'), c('akts', 'AKTS', 'number'),
        c('tur', 'Ders türü', 'select', ['Genel (ortak)', 'Mesleki']),
        c('ozgu', 'Destekledigi programa özgü çıktı', 'select', ['', 'ÖÇ1', 'ÖÇ2', 'ÖÇ3', 'ÖÇ4', 'ÖÇ5']),
        c('uyg', 'Uygulama niteliği', 'select', ['', 'Staj', 'İşletmede mesleki eğitim', 'Uygulamalı ders', 'Güncel mesleki yazılım']),
        c('dis', 'Dış paydaş önerisiyle oluşturuldu/güncellendi', 'select', ['', 'Evet'])];
    $dersCols[5]['l'] = 'Desteklediği programa özgü çıktı';

    $M = [];
    $add = function (string $id, string $baslik, string $kapsam, string $tur, string $gerek, string $ipucu, array $alanlar) use (&$M) {
        $M[$id] = ['id' => $id, 'olcut' => (int)explode('.', $id)[0], 'baslik' => $baslik, 'kapsam' => $kapsam, 'tur' => $tur, 'gerek' => $gerek, 'ipucu' => $ipucu, 'alanlar' => $alanlar];
    };

    // ======================= 0 Künye =======================
    $add('0', 'Program Künyesi', 'program', 'K', 'Programın temel bilgileri (başvuru dosyasının kapağı).', 'Programın resmî ve güncel bilgilerini girin.', [
        f('ad', 'Program adı', 'text', ['default' => $prog['ad'] ?? '']),
        f('bolum', 'Bölüm', 'text', ['default' => $prog['bolum'] ?? '']),
        opt('medek_no', 'MEDEK program numarası', 'text', ['default' => $prog['medekno'] ?? '']),
        opt('yok_kodu', 'YÖK Atlas / ÖSYM program kodu'),
        f('ogretim_turu', 'Öğretim türü', 'select', ['options' => ['Örgün (birinci öğretim)', 'İkinci öğretim', 'Her ikisi']]),
        f('dil', 'Öğretim dili', 'select', ['options' => ['Türkçe', 'İngilizce', 'Diğer']]),
        f('sure', 'Normal öğretim süresi (yıl)', 'number', ['default' => '2']),
        f('akts', 'Toplam AKTS', 'number', ['default' => '120']),
        f('tyyc', 'TYYÇ düzeyi', 'text', ['default' => '5. Düzey (Önlisans)']),
        f('unvan', 'Verilen diploma unvanı'),
        opt('kurulus', 'Programın açılış yılı', 'number'),
        opt('ilk_mezun', 'İlk mezun yılı', 'number'),
        f('baskan', 'Program/bölüm başkanı (unvan, ad soyad)'),
        f('eposta', 'İletişim e-postası', 'text'),
        opt('telefon', 'Telefon'),
        f('basvuru', 'MEDEK başvuru türü', 'select', ['options' => ['İlk başvuru', 'Yeniden başvuru', 'Ara değerlendirme']]),
        opt('onceki_akr', 'Önceki akreditasyon bilgisi (kurum, geçerlilik)'),
        opt('program_url', 'Program web sayfası', 'url'),
        opt('ebp_url', 'Ders bilgi paketi (EBP) bağlantısı', 'url'),
    ]);

    // ======================= Ölçüt 1 =======================
    $add('1.1', 'Öğrenci kabul süreçleri', 'program', 'W', 'Kabul süreçlerine ilişkin tanımlı, kamuya açık prosedürler bulunmalıdır; doluluk ve yerleşme göstergeleri izlenmelidir.',
        'Programa kabul yollarını, prosedür bağlantısını ve son yılların kontenjan/yerleşme verilerini girin (YÖK Atlas verisi kullanılabilir).', [
        f('kabul_yollari', 'Programa kabul yolları ve süreç açıklaması', 'textarea', ['help' => 'YKS/TYT, DGS, yatay geçiş, özel yetenek (varsa), yurt dışı öğrenci vb.']),
        opt('prosedur_url', 'Kabul prosedürü yayın adresi', 'url'),
        tbl('kontenjan', 'Kontenjan ve yerleşme verileri', [c('yil', 'Akademik yıl'), c('kontenjan', 'Kontenjan', 'number'), c('yerlesen', 'Yerleşen', 'number'), c('doluluk', 'Doluluk oranı (%)', 'number'),
            c('taban', 'Taban puan', 'number'), c('tavan', 'Tavan puan', 'number'), c('sira', 'Taban başarı sırası', 'number')], ['rows' => $yil, 'min' => 3]),
        opt('degerlendirme', 'Verilerin değerlendirilmesi ve alınan önlemler', 'textarea'),
    ]);
    $add('1.2', 'Öğrenci gelişiminin ve başarısının izlenmesi', 'program', 'K', 'Doluluk oranı, başarı, ayrılma ve mezun oranları izlenip değerlendirilmelidir.',
        'Öğrenci Bilgi Sistemi verilerinden yıllık göstergeleri girin; yorum ve alınan önlemleri yazın.', [
        tbl('gostergeler', 'Yıllık öğrenci göstergeleri', [c('yil', 'Akademik yıl'), c('kayitli', 'Kayıtlı öğrenci', 'number'), c('mezun', 'Mezun sayısı', 'number'),
            c('ayrilan', 'Ayrılan/kayıt sildiren', 'number'), c('gno', 'Genel not ortalaması', 'number'), c('mezuniyet_orani', 'Mezuniyet oranı (%)', 'number'), c('ayrilma_orani', 'Ayrılma oranı (%)', 'number')], ['rows' => $yil, 'min' => 3]),
        f('yorum', 'Değerlendirme ve alınan önlemler', 'textarea'),
        opt('izleme', 'İzleme sorumlusu ve periyodu', 'text'),
    ]);
    $add('1.3', 'Oryantasyon / uyum programı', 'program', 'W', 'Yeni öğrenciler için oryantasyon ve uyum programı yürütülmelidir.', 'Her yıl yapılan oryantasyon etkinliklerini ve içeriğini girin.', [
        f('icerik', 'Oryantasyon programının içeriği', 'textarea'),
        tbl('etkinlikler', 'Oryantasyon etkinlikleri', [c('yil', 'Akademik yıl'), c('etkinlik', 'Etkinlik'), c('tarih', 'Tarih', 'date'), c('katilimci', 'Katılımcı sayısı', 'number'), c('kanit', 'Kanıt (duyuru/foto URL)')], ['rows' => $yil]),
    ]);
    $add('1.4', 'Öğrenci merkezli öğretim', 'program', 'İ', 'Öğretim, farklı öğrenme ihtiyaçlarını gözeten öğrenci merkezli yöntemlerle yürütülmelidir.', 'Kullanılan yöntemleri ve somut örnekleri yazın.', [
        f('yontemler', 'Kullanılan öğretim yöntemleri', 'textarea'),
        f('farkli_ihtiyac', 'Farklı öğrenme ihtiyaçları nasıl gözetiliyor?', 'textarea'),
        tbl('ornekler', 'Örnek uygulamalar', [c('ders', 'Ders'), c('uygulama', 'Uygulama (proje, vaka, atölye, laboratuvar…)'), c('aciklama', 'Açıklama')], ['min' => 2]),
    ]);
    $add('1.5', 'Önceki öğrenmenin tanınması', 'program', 'W', 'Muafiyet, intibak, yatay/dikey geçiş gibi önceki öğrenmenin tanınmasına ilişkin prosedürler olmalıdır.', 'İlgili yönerge bağlantısını ve yıllık uygulama sayılarını girin.', [
        f('yonerge_url', 'Yönerge / yönetmelik adresi', 'url'),
        f('surec', 'Süreç açıklaması (başvuru, intibak komisyonu, karar)', 'textarea'),
        tbl('sayilar', 'Uygulama sayıları', [c('yil', 'Akademik yıl'), c('muafiyet', 'Muafiyet başvurusu', 'number'), c('kabul', 'Kabul edilen', 'number'), c('yatay_gelen', 'Yatay geçişle gelen', 'number'), c('yatay_giden', 'Yatay geçişle giden', 'number'), c('dgs', 'DGS ile devam', 'number')], ['rows' => $yil]),
    ]);
    $add('1.6', 'Ölçme-değerlendirme yöntemleri', 'program', 'İ', 'Ölçme-değerlendirme yöntemleri çeşitli olmalı; geri bildirim öğrenmeyi yönlendirmelidir.', 'Kullanılan yöntemleri ve geri bildirim uygulamasını yazın.', [
        tbl('yontemler', 'Ölçme-değerlendirme yöntemleri', [c('yontem', 'Yöntem (vize, final, proje, uygulama sınavı…)'), c('dersler', 'Kullanıldığı dersler'), c('agirlik', 'Başarı notuna etkisi (%)', 'number')], ['min' => 3]),
        f('geri_bildirim', 'Öğrenciye geri bildirim nasıl veriliyor?', 'textarea'),
        opt('yonerge_url', 'Ölçme-değerlendirme yönergesi adresi', 'url'),
    ]);
    $add('1.7', 'Ölçme-değerlendirme kriterleri ve itiraz', 'program', 'W', 'Ölçme-değerlendirme kriterleri önceden ilan edilmeli ve itiraz mekanizması bulunmalıdır.', 'Kriterlerin nerede ilan edildiğini ve itiraz sürecini belirtin.', [
        f('kriter_ilan', 'Kriterler önceden ilan ediliyor mu?', 'select', ['options' => YN]),
        f('kriter_yer', 'Nerede/ne zaman ilan ediliyor?', 'textarea'),
        f('itiraz', 'İtiraz süreci (süre, başvuru yeri, karar organı)', 'textarea'),
        opt('mevzuat_url', 'İlgili mevzuat adresi', 'url'),
        tbl('itiraz_sayi', 'İtiraz sayıları', [c('yil', 'Akademik yıl'), c('itiraz', 'İtiraz sayısı', 'number'), c('kabul', 'Kabul edilen', 'number')], ['rows' => $yil]),
    ]);
    $add('1.8', 'Öğrenci görüş, talep, şikâyet mekanizması', 'program', 'W', 'Öğrenci görüş, talep, itiraz ve şikâyetleri için tanımlı bir mekanizma olmalıdır.', 'Kanalları (BİMER, form, danışman, öğrenci temsilcisi) ve sonuçlanma sürelerini girin.', [
        f('kanallar', 'Başvuru kanalları ve süreç', 'textarea'),
        opt('form_url', 'Başvuru formu / sayfa adresi', 'url'),
        tbl('basvurular', 'Başvuru istatistikleri', [c('yil', 'Akademik yıl'), c('gelen', 'Gelen başvuru', 'number'), c('sonuclanan', 'Sonuçlanan', 'number'), c('sure', 'Ort. sonuçlanma süresi (gün)', 'number')], ['rows' => $yil]),
    ]);
    $add('1.9', 'Öğrenci hareketliliği', 'program', 'W', 'Öğrenci hareketliliği (Erasmus, Farabi, Mevlana) düzenlemeleri bulunmalıdır.', 'Yıllık hareketlilik verilerini ve anlaşmaları girin.', [
        opt('url', 'Hareketlilik bilgilendirme sayfası', 'url'),
        tbl('hareketlilik', 'Hareketlilik verileri', [c('yil', 'Akademik yıl'), c('erasmus_giden', 'Erasmus giden', 'number'), c('erasmus_gelen', 'Erasmus gelen', 'number'), c('farabi', 'Farabi', 'number'), c('mevlana', 'Mevlana', 'number'), c('anlasma', 'Anlaşma yapılan kurum sayısı', 'number')], ['rows' => $yil]),
        opt('aciklama', 'Ders eşdeğerliği / intibak uygulaması', 'textarea'),
    ]);
    $add('1.10', 'Akademik danışmanlık', 'program', 'W', 'Öğrencilere akademik danışmanlık hizmeti sunulmalıdır.', 'Danışmanlık sürecini ve danışman atamalarını girin.', [
        f('surec', 'Danışmanlık sürecinin işleyişi (görüşme sıklığı, kayıt yöntemi)', 'textarea'),
        opt('liste_url', 'Danışman listesi adresi', 'url'),
        tbl('danismanlar', 'Danışman atamaları', [c('ad', 'Danışman (unvan, ad soyad)'), c('sinif', 'Sınıf/şube'), c('ogrenci', 'Öğrenci sayısı', 'number')], ['min' => 2]),
    ]);
    $add('1.11', 'Kariyer planlama ve mezun izleme', 'program', 'W', 'Kariyer planlama ve mezuniyet sonrası rehberlik hizmeti verilmelidir.', 'Kariyer etkinliklerini ve mezun istihdam verilerini girin.', [
        tbl('etkinlikler', 'Kariyer etkinlikleri', [c('yil', 'Yıl'), c('etkinlik', 'Etkinlik'), c('tarih', 'Tarih', 'date'), c('katilimci', 'Katılımcı', 'number'), c('kanit', 'Kanıt (URL)')], ['min' => 2]),
        tbl('istihdam', 'Mezun istihdam / devam durumu', [c('yil', 'Mezuniyet yılı'), c('mezun', 'Mezun sayısı', 'number'), c('calisan', 'Çalışan (%)', 'number'), c('dgs', 'Lisans tamamlama (%)', 'number'), c('ulasilan', 'Ulaşılan mezun sayısı', 'number')], ['rows' => $yil]),
        opt('mezun_izleme', 'Mezun izleme sistemi / yöntemi', 'textarea'),
    ]);
    $add('1.12', 'Mezuniyet koşullarının kontrolü', 'program', 'W', 'Mezuniyet koşulları sistematik olarak kontrol edilmelidir.', 'Mezuniyet koşullarını ve kontrol sürecini yazın.', [
        f('kosullar', 'Mezuniyet koşulları (AKTS, GNO, staj…)', 'textarea'),
        f('kontrol', 'Kontrol süreci ve sorumluları', 'textarea'),
        opt('yonetmelik_url', 'Yönetmelik adresi', 'url'),
        tbl('mezun', 'Mezuniyet verileri', [c('yil', 'Akademik yıl'), c('basvuran', 'Mezuniyet başvurusu', 'number'), c('mezun', 'Mezun olan', 'number'), c('uzayan', 'Süresinde mezun olmayan', 'number')], ['rows' => $yil]),
    ]);

    // ======================= Ölçüt 2 =======================
    $add('2.1', 'Program eğitim amaçları (PEA)', 'program', 'W', 'PEA tanımlanmalı ve kamuoyuyla paylaşılmalıdır.', 'Sitede yayımlanan (veya yayımlanacak) program eğitim amaçlarını yazın. Mezunların mezuniyetten birkaç yıl sonra ulaşması beklenen durumları ifade eder.', [
        tbl('pea', 'Program eğitim amaçları', [c('no', 'PEA no'), c('metin', 'Eğitim amacı')], ['rows' => [['PEA1'], ['PEA2'], ['PEA3'], ['PEA4']], 'min' => 3]),
        opt('guncelleme', 'Son güncelleme tarihi', 'date'),
    ]);
    $add('2.2', 'PEA – misyon/vizyon uyumu', 'program', 'W', 'PEA, kurum/birim/program misyon ve vizyonuyla uyumlu olmalıdır.', 'Üniversite ve MYO misyon-vizyonunu yazın; her PEA ile ilişkisini açıklayın.', [
        f('kurum_misyon', 'Üniversite ve birim misyon/vizyonu', 'textarea'),
        opt('program_misyon', 'Program misyon/vizyonu (varsa)', 'textarea'),
        tbl('uyum', 'PEA – misyon/vizyon ilişkisi', [c('pea', 'PEA'), c('iliski', 'Misyon/vizyonun hangi ifadesiyle ilişkili ve nasıl?')], ['rows' => [['PEA1'], ['PEA2'], ['PEA3'], ['PEA4']], 'min' => 3]),
    ]);
    $add('2.3', 'PEA performans göstergeleri', 'program', 'K', 'PEA\'ya ulaşma düzeyi tanımlı performans göstergeleriyle izlenmelidir.', 'Her PEA için ölçülebilir gösterge, hedef ve son ölçüm sonucunu girin.', [
        tbl('gostergeler', 'Performans göstergeleri', [c('pea', 'PEA'), c('gosterge', 'Gösterge'), c('hedef', 'Hedef'), c('arac', 'Ölçüm aracı (mezun anketi, işveren anketi…)'), c('periyot', 'Periyot'), c('sonuc', 'Son ölçüm sonucu'), c('sorumlu', 'Sorumlu')], ['rows' => [['PEA1'], ['PEA2'], ['PEA3'], ['PEA4']], 'min' => 3]),
    ]);

    // ======================= Ölçüt 3 =======================
    $genelRows = [];
    foreach (GENEL_PC as $i => $m) $genelRows[] = ['MEDEK PÇ' . ($i + 1), $m];
    $add('3.1', 'Genel program çıktıları (11 MEDEK çıktısı)', 'program', 'W', 'Program çıktıları MEDEK\'in 11 genel çıktısını karşılamalı ve paylaşılmalıdır.', 'Her MEDEK çıktısının programınızdaki karşılığını (PÇ numarası ve cümlesi) yazın. Karşılığı yoksa boş bırakmayın; eksik olduğunu belirtin.', [
        tbl('esleme', 'MEDEK genel çıktıları ↔ program çıktılarınız', [c('medek', 'MEDEK çıktısı'), c('medek_metin', 'MEDEK çıktı metni'), c('pc_kod', 'Programdaki PÇ no'), c('pc_metin', 'Programdaki PÇ metni'), c('dersler', 'Destekleyen başlıca dersler')], ['rows' => $genelRows, 'min' => 11]),
        tbl('mevcut_pc', 'Programın mevcut (yayımlanmış) program çıktıları', [c('kod', 'PÇ no'), c('metin', 'Program çıktısı metni')], ['req' => false, 'min' => 0, 'help' => 'Eşleştirme için kaynak liste; sitede yayımlanan çıktıları buraya girin.']),
        opt('yayin_url', 'PÇ\'lerin yayımlandığı adres', 'url'),
    ]);
    $ozguRows = [];
    foreach ($ozgu as $i => $m) $ozguRows[] = ['ÖÇ' . ($i + 1), $m];
    $add('3.2', 'Programa özgü çıktılar (5 MEDEK çıktısı)', 'program', 'W', 'Program çıktıları, MEDEK\'in ' . ($prog['ad'] ?? 'programa') . ' için tanımladığı programa özgü 5 çıktıyı karşılamalı ve paylaşılmalıdır.', 'MEDEK çıktıları hazır gelir. Programdaki karşılığını ve onu destekleyen dersleri yazın.', [
        tbl('esleme', 'Programa özgü MEDEK çıktıları ↔ program çıktılarınız', [c('medek', 'MEDEK çıktısı'), c('medek_metin', 'MEDEK çıktı metni'), c('pc_kod', 'Programdaki PÇ no'), c('pc_metin', 'Programdaki PÇ metni'), c('dersler', 'Destekleyen başlıca dersler')], ['rows' => $ozguRows, 'min' => 5]),
    ]);
    $matCols = array_merge([c('pc', 'PÇ')], array_map(fn($i) => c("pea$i", "PEA$i", 'select', ['', 'Yüksek', 'Orta', 'Düşük']), range(1, 4)));
    $add('3.3', 'PÇ – PEA ilişkisi', 'program', 'K', 'Program çıktıları ile program eğitim amaçları arasındaki uyum gösterilmelidir.', 'Her PÇ\'nin hangi PEA\'ya ne düzeyde katkı verdiğini seçin.', [
        tbl('matris', 'PÇ – PEA matrisi', $matCols, ['rows' => $pcRows, 'min' => 11]),
    ]);
    $dersPc = array_merge([c('kod', 'Ders kodu'), c('ad', 'Ders adı')], array_map(fn($k) => c(strtolower($k), $k, 'select', ['', 'X']), $pcKod));
    $add('3.4', 'PÇ – ders öğrenme çıktıları ilişkisi', 'program', 'K', 'Program çıktıları ile ders öğrenme çıktıları arasındaki ilişki gösterilmelidir.', 'Her dersin katkı verdiği PÇ\'leri X ile işaretleyin. Ayrıntı ders bilgi paketinde (EBP) yer almalıdır.', [
        tbl('matris', 'PÇ – ders matrisi', $dersPc, ['min' => 10]),
        opt('ebp_url', 'Ders bilgi paketi adresi', 'url'),
    ]);
    $add('3.5', 'PÇ kazanılma düzeyinin izlenmesi', 'program', 'K', 'Program çıktılarının kazanılma düzeyi sistematik izlenmelidir.', 'Her PÇ için ölçüm aracı, hedef eşik ve son sonuçları girin.', [
        tbl('izleme', 'PÇ izleme', [c('pc', 'PÇ'), c('arac', 'Ölçüm aracı / ders'), c('hedef', 'Hedef düzey'), c('donem', 'Ölçüm dönemi'), c('sonuc', 'Sonuç'), c('degerlendirme', 'Değerlendirme / alınan önlem')], ['rows' => $pcRows, 'min' => 11]),
    ]);

    // ======================= Ölçüt 4 =======================
    $add('4.1', 'Ders bilgi paketleri (AKTS)', 'program', 'W', 'AKTS\'ye dayalı ders bilgi paketleri kamuoyuyla paylaşılmalıdır.', 'EBP\'de yayımlı ders sayısını ve eksikleri girin.', [
        f('ebp_url', 'Program ders bilgi paketi adresi', 'url'),
        f('toplam', 'Planlanan toplam ders sayısı', 'number'),
        f('yayinli', 'Bilgi paketi eksiksiz yayımlı ders sayısı', 'number'),
        opt('eksik', 'Eksik/yetersiz bilgi paketleri ve tamamlanma planı', 'textarea'),
    ]);
    $add('4.2', 'Mesleki dersler ve AKTS dağılımı', 'program', 'K', 'Programa özgü mesleki dersler en az 60 AKTS olmalı; bunların en az 20 AKTS\'si programa özgü çıktıları destekleyen derslerden oluşmalıdır.', 'Tüm ders planını (4 dönem) bu tabloya girin. 4.3 ve 4.5 maddelerindeki AKTS hesapları da buradan otomatik yapılır.', [
        tbl('ders_plani', 'Ders planı', $dersCols, ['min' => 20]),
        opt('aciklama', 'Not', 'textarea'),
    ]);
    $M['4.2']['hesap'] = 'akts';
    $add('4.3', 'Uygulama / staj AKTS oranı', 'program', 'K', 'En az 15 AKTS işletmede mesleki eğitim, staj, uygulamalı ders veya güncel mesleki yazılım içeren derslerden oluşmalıdır.', 'Ders planında “uygulama niteliği” seçilen derslerden hesaplanır. Burada staj ayrıntılarını girin.', [
        f('staj_model', 'Staj / işletmede mesleki eğitim modeli', 'textarea', ['help' => 'Süre, dönem, AKTS, 3+1 vb.']),
        tbl('isletmeler', 'Anlaşmalı işletmeler', [c('isletme', 'İşletme / kurum'), c('sektor', 'Sektör'), c('protokol', 'Protokol tarihi', 'date'), c('kontenjan', 'Yıllık kontenjan', 'number')], ['min' => 2]),
        opt('degerlendirme_url', 'Staj değerlendirme formu / yönergesi', 'url'),
    ]);
    $M['4.3']['hesap'] = 'akts';
    $add('4.4', 'Staj / işletmede mesleki eğitim uygulaması', 'program', 'W', 'İşletmede mesleki eğitim ve/veya staj ve/veya uygulamalı ders işletilmelidir.', 'Son yıllardaki uygulama verilerini girin.', [
        opt('yonerge_url', 'Staj yönergesi adresi', 'url'),
        tbl('uygulama', 'Staj uygulaması', [c('yil', 'Akademik yıl'), c('ogrenci', 'Stajı yapan öğrenci', 'number'), c('isletme', 'İşletme sayısı', 'number'), c('sure', 'Süre (iş günü)', 'number'), c('degerlendirme', 'Değerlendirme sonucu (özet)')], ['rows' => $yil, 'min' => 3]),
    ]);
    $add('4.5', 'Dış paydaş katkılı dersler', 'program', 'K', 'Dış paydaş önerisiyle oluşturulan/güncellenen derslerin toplamı en az 5 AKTS olmalıdır.', 'Dış paydaş önerisiyle güncellenen dersleri ve dayanak tutanakları girin. Ders planında “dış paydaş” işaretini de doldurun.', [
        tbl('dersler', 'Dış paydaş katkılı dersler', [c('ders', 'Ders'), c('akts', 'AKTS', 'number'), c('paydas', 'Dış paydaş (kurum/kişi)'), c('tarih', 'Öneri tarihi', 'date'), c('oneri', 'Öneri özeti'), c('degisiklik', 'Yapılan değişiklik'), c('kanit', 'Kanıt (tutanak no)')], ['min' => 1]),
    ]);
    $M['4.5']['hesap'] = 'akts';

    // ======================= Ölçüt 5 =======================
    $add('5.1', 'PEA\'nın periyodik gözden geçirilmesi', 'program', 'K', 'PEA, paydaş geri bildirimi ve sektörel gelişmelerle periyodik gözden geçirilmelidir.', 'Gözden geçirme toplantılarını ve kararlarını girin.', [
        f('periyot', 'Gözden geçirme periyodu ve yöntemi', 'text'),
        tbl('toplantilar', 'Gözden geçirme kayıtları', [c('tarih', 'Tarih', 'date'), c('katilanlar', 'Katılan paydaşlar'), c('bulgu', 'Değerlendirme'), c('karar', 'Karar / değişiklik'), c('kanit', 'Tutanak no / URL')], ['min' => 1]),
    ]);
    $add('5.2', 'PÇ\'nin periyodik gözden geçirilmesi', 'program', 'K', 'Program çıktıları periyodik gözden geçirilmelidir.', 'PÇ güncellemelerini ve gerekçelerini girin.', [
        f('periyot', 'Gözden geçirme periyodu ve yöntemi', 'text'),
        tbl('toplantilar', 'Gözden geçirme kayıtları', [c('tarih', 'Tarih', 'date'), c('katilanlar', 'Katılan paydaşlar'), c('bulgu', 'Değerlendirme'), c('karar', 'Karar / değişiklik'), c('kanit', 'Tutanak no / URL')], ['min' => 1]),
    ]);
    $add('5.3', 'Öğretim planı ve ders içeriklerinin güncellenmesi', 'program', 'K', 'Öğretim planı ve ders içerikleri mevzuat, teknoloji ve dijital gelişmelere göre periyodik güncellenmelidir.', 'Son yıllarda yapılan plan/ders içeriği değişikliklerini girin.', [
        tbl('guncellemeler', 'Plan / içerik değişiklikleri', [c('yil', 'Akademik yıl'), c('ders', 'Ders / değişiklik'), c('gerekce', 'Gerekçe', 'select', ['Mevzuat değişikliği', 'Teknolojik gelişme', 'Dijital dönüşüm', 'Paydaş talebi', 'Diğer']), c('aciklama', 'Açıklama'), c('karar', 'Kurul karar no / tarih')], ['min' => 2]),
    ]);
    $add('5.4', 'Mezun geri bildirimi', 'program', 'K', 'Mezun geri bildirimleri toplanıp iyileştirmede kullanılmalıdır.', 'Anket/görüşme yöntemi, bulgular ve alınan aksiyonları girin.', [
        tbl('geri_bildirim', 'Mezun geri bildirimleri', geriBildirim('Mezun'), ['rows' => $yil, 'min' => 2]),
    ]);
    $add('5.5', 'İç paydaş geri bildirimi', 'program', 'K', 'İç paydaş (öğrenci, öğretim elemanı, idari personel) geri bildirimleri iyileştirmede kullanılmalıdır.', 'Öğrenci memnuniyet anketi, kurul görüşleri vb. kayıtları girin.', [
        tbl('geri_bildirim', 'İç paydaş geri bildirimleri', geriBildirim('İç', true), ['min' => 2]),
    ]);
    $add('5.6', 'Dış paydaş geri bildirimi', 'program', 'K', 'Dış paydaş (işveren, sektör temsilcileri, meslek kuruluşları) geri bildirimleri iyileştirmede kullanılmalıdır.', 'Danışma kurulu üyelerini ve toplantı kararlarını girin.', [
        tbl('danisma', 'Danışma kurulu / dış paydaşlar', [c('ad', 'Ad soyad'), c('kurum', 'Kurum / görev'), c('sektor', 'Sektör')], ['min' => 3]),
        tbl('geri_bildirim', 'Dış paydaş geri bildirimleri', geriBildirim('Dış'), ['min' => 1]),
    ]);

    // ======================= Ölçüt 6 =======================
    $add('6.1', 'Öğretim kadrosu', 'program', 'W', 'Yeterli sayıda kadro bulunmalı; uzmanlık alanı ile verilen dersler uyumlu olmalıdır.', 'Programda ders veren tüm öğretim elemanlarını listeleyin.', [
        tbl('kadro', 'Öğretim elemanları', [c('ad', 'Unvan, ad soyad'), c('kadro', 'Kadro türü', 'select', ['Kadrolu', 'Görevlendirme', 'Saatli/ücretli']), c('alan', 'Lisansüstü / uzmanlık alanı'), c('dersler', 'Verdiği dersler'), c('yuk', 'Haftalık ders saati', 'number'), c('profil', 'Profil / CV adresi', 'url')], ['min' => 4]),
        f('ogrenci_oran', 'Öğrenci / öğretim elemanı oranı ve yeterlilik değerlendirmesi', 'textarea'),
    ]);
    $add('6.2', 'Ders dağılımı ve iş yükü', 'program', 'İ', 'Ders dağılımı adil olmalı, iş yükü dengeli olmalıdır.', 'Dağıtım ölçütlerini ve uzmanlık uyumunu belirtin.', [
        f('kriter', 'Ders dağıtım ölçütleri ve süreci', 'textarea'),
        tbl('dagilim', 'Ders dağılımı', [c('ogretim_elemani', 'Öğretim elemanı'), c('ders', 'Ders'), c('saat', 'Haftalık saat', 'number'), c('uyum', 'Uzmanlık uyumu', 'select', ['Yüksek', 'Orta', 'Düşük'])], ['min' => 4]),
    ]);
    $add('6.3', 'Teşvik ve ödüllendirme', 'birim', 'W', 'Teşvik ve ödüllendirme mekanizmaları adil ve şeffaf olmalıdır.', 'Birimdeki teşvik/ödül mekanizmalarını açıklayın (ortak form).', [
        f('mekanizma', 'Mekanizmanın açıklaması ve kriterleri', 'textarea'),
        opt('yonerge_url', 'Yönerge adresi', 'url'),
        tbl('uygulama', 'Uygulamalar', [c('yil', 'Yıl'), c('tur', 'Teşvik / ödül türü'), c('kisi', 'Kişi sayısı', 'number')], ['min' => 1]),
    ]);
    $add('6.4', 'Mesleki ve pedagojik gelişim', 'program', 'K', 'Öğretim elemanlarına mesleki-pedagojik gelişim olanakları sunulmalıdır.', 'Son yıllarda katılınan eğitimleri girin.', [
        tbl('egitimler', 'Eğitimler', [c('yil', 'Yıl'), c('egitim', 'Eğitim / sertifika'), c('duzenleyen', 'Düzenleyen'), c('katilimci', 'Katılan öğretim elemanı sayısı', 'number'), c('saat', 'Süre (saat)', 'number')], ['min' => 2]),
    ]);

    // ======================= Ölçüt 7 =======================
    $add('7.1', 'Derslik, laboratuvar ve atölyeler', 'program', 'W', 'Program için yeterli sayı ve nitelikte derslik, laboratuvar, atölye ve uygulama ortamı bulunmalıdır.', 'Programın kullandığı mekânları ve donanımlarını girin.', [
        tbl('mekanlar', 'Mekânlar', [c('ad', 'Mekân adı'), c('tur', 'Tür', 'select', ['Derslik', 'Laboratuvar', 'Atölye', 'Uygulama ortamı']), c('kapasite', 'Kapasite', 'number'), c('alan', 'Alan (m²)', 'number'), c('donanim', 'Donanım özeti'), c('dersler', 'Kullanılan dersler'), c('foto', 'Fotoğraf (URL)')], ['min' => 2]),
        f('yeterlilik', 'Yeterlilik değerlendirmesi', 'textarea'),
    ]);
    $add('7.2', 'BİT altyapısı, kütüphane, e-kaynaklar', 'birim', 'W', 'BİT altyapısı, kütüphane ve elektronik kaynaklara erişim sağlanmalıdır.', 'Birim genelindeki olanakları girin (ortak form).', [
        f('bit', 'Bilgisayar, ağ, internet ve yazılım olanakları', 'textarea'),
        tbl('yazilim', 'Lisanslı / kullanılan yazılımlar', [c('yazilim', 'Yazılım'), c('lisans', 'Lisans türü'), c('adet', 'Adet', 'number'), c('ders', 'Kullanıldığı dersler')], ['min' => 1]),
        f('kutuphane', 'Kütüphane ve e-kaynak olanakları (kitap, veritabanı, erişim)', 'textarea'),
        opt('kutuphane_url', 'Kütüphane / e-kaynak adresi', 'url'),
    ]);
    $add('7.3', 'Beslenme, dinlenme, çalışma ortamları', 'birim', 'W', 'Beslenme, dinlenme ve çalışma ortamları sağlanmalıdır.', 'Yemekhane, kantin, okuma salonu gibi alanları girin (ortak form).', [
        tbl('alanlar', 'Sosyal ve çalışma alanları', [c('alan', 'Alan'), c('kapasite', 'Kapasite', 'number'), c('aciklama', 'Açıklama'), c('foto', 'Fotoğraf (URL)')], ['min' => 3]),
    ]);
    $add('7.4', 'Sosyal, kültürel, sportif olanaklar', 'birim', 'W', 'Sosyal, kültürel ve sportif etkinlik olanakları sunulmalıdır.', 'Kulüpleri, tesisleri ve etkinlikleri girin (ortak form).', [
        tbl('kulupler', 'Öğrenci kulüpleri', [c('kulup', 'Kulüp'), c('uye', 'Üye sayısı', 'number'), c('etkinlik', 'Yıllık etkinlik sayısı', 'number')], ['min' => 1]),
        f('tesisler', 'Spor ve kültür tesisleri', 'textarea'),
        tbl('etkinlikler', 'Örnek etkinlikler', [c('yil', 'Yıl'), c('etkinlik', 'Etkinlik'), c('katilimci', 'Katılımcı', 'number'), c('kanit', 'Kanıt (URL)')], ['min' => 2]),
    ]);
    $add('7.5', 'İSG risk değerlendirme raporu', 'birim', 'K', 'Birime özgü İSG risk değerlendirme raporu hazırlanmalı ve düzeltici tedbirler izlenmelidir.', 'Raporu kanıt olarak yükleyin; riskleri ve tedbirleri tabloya girin (ortak form).', [
        f('rapor_tarihi', 'Rapor tarihi', 'date'),
        f('hazirlayan', 'Hazırlayan kişi/kurum'),
        opt('gecerlilik', 'Geçerlilik / yenileme tarihi', 'date'),
        tbl('riskler', 'Riskler ve tedbirler', [c('risk', 'Tespit edilen risk'), c('duzey', 'Risk düzeyi', 'select', ['Düşük', 'Orta', 'Yüksek']), c('tedbir', 'Düzeltici/önleyici tedbir'), c('sorumlu', 'Sorumlu'), c('termin', 'Termin', 'date'), c('durum', 'Durum', 'select', ['Tamamlandı', 'Devam ediyor', 'Başlanmadı'])], ['min' => 3]),
        opt('acil', 'Acil durum planı ve tatbikatlar', 'textarea'),
    ]);
    $add('7.6', 'Erişilebilirlik ve özel gereksinimli öğrenci desteği', 'birim', 'W', 'Özel gereksinimli öğrenciler için fiziksel erişilebilirlik ve akademik destek sağlanmalıdır.', 'Fiziksel düzenlemeleri ve destek hizmetlerini girin (ortak form).', [
        tbl('olanaklar', 'Erişilebilirlik olanakları', [c('olanak', 'Olanak (rampa, asansör, engelli WC, rehber şerit…)'), c('durum', 'Durum', 'select', ['Var', 'Kısmen var', 'Yok']), c('aciklama', 'Açıklama / plan')], ['min' => 4]),
        f('destek', 'Akademik destek hizmetleri (engelli öğrenci birimi, danışmanlık, sınav uyarlaması)', 'textarea'),
        opt('birim_url', 'Engelli öğrenci birimi adresi', 'url'),
    ]);

    // ======================= Ölçüt 8 =======================
    $add('8.1', 'Organizasyon yapısı, kurullar ve komisyonlar', 'birim', 'W', 'Organizasyon yapısı, kurul ve komisyonlar tanımlı; görev-yetki-sorumluluk belirli olmalıdır.', 'Kurul/komisyonları ve görevlerini girin; organizasyon şemasını kanıt olarak yükleyin (ortak form).', [
        tbl('kurullar', 'Kurul ve komisyonlar', [c('ad', 'Kurul / komisyon'), c('uyeler', 'Üyeler'), c('gorev', 'Görev ve yetkiler'), c('sikligi', 'Toplantı sıklığı'), c('url', 'Yönerge / sayfa adresi')], ['min' => 4]),
        opt('sema_url', 'Organizasyon şeması adresi', 'url'),
    ]);
    $add('8.2', 'Güncel program bilgileri', 'program', 'W', 'Program hakkındaki güncel bilgiler kamuoyunda bulunmalıdır.', 'Program sayfasındaki içerikleri ve son güncelleme tarihlerini kontrol edip girin.', [
        tbl('icerik', 'Web içerik kontrolü', [c('icerik', 'İçerik', 'select', ['Program tanıtımı', 'Eğitim amaçları', 'Program çıktıları', 'Ders planı', 'Ders bilgi paketleri', 'Öğretim kadrosu', 'Staj bilgileri', 'Duyurular', 'İletişim']), c('url', 'Adres', 'url'), c('tarih', 'Son güncelleme', 'date'), c('durum', 'Durum', 'select', ['Güncel', 'Eski', 'Yok'])],
            ['rows' => [['Program tanıtımı'], ['Eğitim amaçları'], ['Program çıktıları'], ['Ders planı'], ['Ders bilgi paketleri'], ['Öğretim kadrosu'], ['Staj bilgileri'], ['Duyurular'], ['İletişim']], 'min' => 9]),
        f('sorumlu', 'Güncellemeden sorumlu kişi ve periyot', 'text'),
    ]);
    $add('8.3', 'Öğrencilerin karar mekanizmalarında temsili', 'birim', 'K', 'Öğrenciler program/kurum karar mekanizmalarında temsil edilmelidir.', 'Temsil edilen kurulları ve seçim yöntemini girin (ortak form).', [
        tbl('temsil', 'Öğrenci temsili', [c('kurul', 'Kurul / komisyon'), c('temsilci', 'Öğrenci temsilcisi sayısı', 'number'), c('secim', 'Seçilme yöntemi'), c('toplanti', 'Katıldığı toplantı sayısı', 'number')], ['min' => 2]),
        opt('aciklama', 'Açıklama', 'textarea'),
    ]);
    $add('8.4', 'İdari personelin mesleki gelişimi', 'birim', 'K', 'İdari personele mesleki gelişim eğitimleri verilmelidir.', 'Son yıllardaki eğitimleri girin (ortak form).', [
        tbl('egitimler', 'İdari personel eğitimleri', [c('yil', 'Yıl'), c('egitim', 'Eğitim'), c('duzenleyen', 'Düzenleyen'), c('personel', 'Katılan personel sayısı', 'number'), c('saat', 'Süre (saat)', 'number')], ['min' => 2]),
    ]);

    return $M;
}
