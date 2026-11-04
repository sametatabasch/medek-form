# MEDEK Form – Plan

Adres: http://medekform.loc · Teknoloji: PHP 8 (Apache mod_php, veritabanı yok) · Veri: JSON dosyaları · Bağımlılık: yok (XLSX okuma/yazma dahili).

## 1. Amaç
Bölüm başkanlarının, MEDEK Önlisans Program Ölçütleri v2.0'daki her madde için gereken verileri girdiği bir arayüz.
Akış: **şablonu indir → doldur → yükle (veya doğrudan formda doldur) → kanıt ekle → HTML çıktı al.**

## 2. Kullanıcılar
| Rol | Yetki |
|---|---|
| Yönetici (müdür / MEDEK koordinatörü) | Tüm programlar, şifre sıfırlama, genel ilerleme matrisi, toplu çıktı |
| Bölüm başkanı (program başına 1 hesap) | Kendi programının formları + birim ortak formları |

Giriş: kullanıcı adı = program kısa adı, şifre ilk kurulumda üretilir (`data/ILK-SIFRELER.txt`).

## 3. Veri modeli
- **Madde** (`schema/maddeler.php`): MEDEK 1.1 … 8.4 (45 madde) + 0 Program Künyesi. Her maddenin: kapsam (program / birim), tür (W web, K kanıt, İ iç süreç), açıklama, alanları.
- **Alan türleri:** metin, uzun metin, sayı, tarih, URL, seçim, evet/hayır, **tablo** (satır ekle/sil, ön tanımlı satırlar).
- **Her maddede otomatik:** web'de yayın durumu + URL, kanıt URL'leri, kanıt dosyası yükleme, not.
- **Kayıt:** `data/programs/<program>/<madde>.json`; birim ortak veriler `data/programs/_birim/`; dosyalar `data/uploads/`.
- **Durum** (otomatik): Boş / Taslak / Tamam (zorunlu alanlar dolu mu).

## 4. Sayfalar
1. `/giris` – oturum
2. `/` – pano: program ilerlemesi, ölçüt bazında yüzdeler (yönetici için 11 program × 8 ölçüt matrisi)
3. `/p/<program>` – program sayfası: ölçüt bazlı madde listesi, durum, şablon/çıktı bağlantıları
4. `/p/<program>/<madde>` – form: doldur, kaydet, şablon indir, XLSX yükle, kanıt ekle, HTML önizleme
5. `/sablonlar` – tüm maddelerin boş şablonları (giriş gerekmez)
6. `/export/<program>.html` ve `.zip` – program raporu (HTML + kanıt dosyaları), tek madde HTML
7. `/yonetim` – hesaplar, şifre sıfırlama, toplu çıktı
8. `/yardim` – kullanım kılavuzu

## 5. Şablon (XLSX)
Her madde için: `Form` sayfası (alan, açıklama, değer; seçimler için açılır liste) + tablo alanları için ayrı sayfa (başlık satırı, ön tanımlı satırlar). A1 hücresi şablon kimliğini taşır (`MEDEKFORM|1.1|v1`). Doldurulmuş dosya sisteme yüklenince alanlar içeri alınır. Tüm şablonlar program bazında ZIP olarak indirilebilir.

## 6. HTML çıktı
Tek dosya, satır içi CSS, A4 yazdırmaya uygun. Kapak (program künyesi, ilerleme) + içindekiler + her madde (gereklilik, girilen veri, kanıtlar). ZIP çıktıda kanıt dosyaları `kanit/` altında göreli bağlantı olur.

## 7. Özel hesaplar
- **AKTS özeti** (4.2, 4.3, 4.5): ders planı tablosundan mesleki ≥60, programa özgü ≥20, uygulama/staj ≥15, dış paydaş ≥5 AKTS otomatik hesaplanır.
- **Programa özgü çıktılar** (3.2): MEDEK PDF'inden her program için 5 çıktı ön tanımlı gelir.
- **11 genel PÇ** (3.1): ön tanımlı satırlar.

## 8. Güvenlik
Oturum + CSRF belirteci, parola `password_hash`, dosya uzantı beyaz listesi ve boyut sınırı, yüklemeler `public` dışında ve yetkili indirme, yol geçişi (path traversal) kontrolü, atomik yazma (`flock`).

## 9. Kurulum
`deploy/install.sh` (sudo): Apache vhost + `/etc/hosts` + `data/` izinleri. Geliştirme için `php -S 127.0.0.1:8081 -t public public/index.php`.

## 10. Aşamalar
1. Şema + program verisi ✔ (programs.json)
2. Çekirdek: oturum, depolama, router
3. Form üretici + kaydet + kanıt yükleme
4. XLSX şablon üretimi / içe aktarma
5. HTML/ZIP çıktı
6. Pano + yönetim
7. Kurulum betiği + test
