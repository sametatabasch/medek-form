<?php
// Komut satırından ilk kurulum: data/ klasörü, hesaplar ve ilk şifreler (dosya sahibi çalıştıran kullanıcı olur).
require dirname(__DIR__) . '/src/lib.php';
if (!is_dir(DATA)) mkdir(DATA, 0775, true);
$yeni = !is_file(users_file());
users_init();
echo $yeni ? "Hesaplar oluşturuldu. İlk şifreler: " . DATA . "/ILK-SIFRELER.txt\n" : "Hesaplar zaten var (users.json), değiştirilmedi.\n";
