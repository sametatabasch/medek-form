# MEDEK Form

Bölüm başkanlarının MEDEK Önlisans Program Ölçütleri v2.0 (KL-007) verilerini girdiği sistem. Ayrıntılı tasarım: [docs/PLAN.md](docs/PLAN.md).

## Kurulum (http://medekform.loc)
```bash
./deploy/install.sh      # Apache vhost + /etc/hosts + izinler (sudo ister)
```
İlk şifreler `data/ILK-SIFRELER.txt` dosyasındadır; dağıttıktan sonra silin, `Yönetim` sayfasından değiştirin.

Apache olmadan deneme: `php -S 127.0.0.1:8081 -t public public/index.php` → http://127.0.0.1:8081

## Yapı
| Yol | Açıklama |
|---|---|
| `schema/maddeler.php` | 46 form (Künye + MEDEK 1.1–8.4): alanlar, gereklilikler, ön tanımlı satırlar |
| `schema/programs.json` | 11 program + MEDEK programa özgü 5 çıktı |
| `src/` | lib (depolama/oturum/durum), xlsx (şablon üret/içe al), render (form + HTML çıktı), pages (rotalar) |
| `data/` | JSON kayıtlar, yüklenen kanıtlar, hesaplar (yedek için bu klasörü kopyalayın) |
| `public/` | DocumentRoot |

## Mevcut site verilerini aktarma
`php bin/seed.php` — `schema/seed.json` içindeki (tmyo.giresun.edu.tr sayfalarından ve yerel BP/WTK taslaklarından derlenen) verileri forma yazar. Yalnızca boş alanları doldurur, girilmiş veriyi ezmez; tekrar çalıştırmak güvenlidir (`--dry` ile deneme). Aktarılan her formun "Not" alanında kaynak adresi yazar.

## Madde eklemek / değiştirmek
`schema/maddeler.php` içinde `$add(...)` bloklarını düzenleyin. Alan kimliklerini (`id`) değiştirmeyin; kayıtlı veriler bunlara bağlıdır. Yapıyı bozan değişiklikte `SCHEMA_VERSION` değerini artırın.

## Hesaplar
Yönetici + her program için bir program başkanı. Program başkanı yalnızca kendi programını görür; birim ortak formları (6.3, 7.2–7.6, 8.1, 8.3, 8.4) tüm programlarda aynı kaydı gösterir.
