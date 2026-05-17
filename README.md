# English Learning Platform (Yapay Zeka Destekli)

Bu proje, öğrencilere kişiselleştirilmiş öğrenme deneyimleri sunmak için tasarlanmış yapay zeka (Gemini AI) destekli bir İngilizce öğrenme platformudur. Geleneksel sistemlerin aksine, bu platform öğrenci performans verilerine dayalı olarak içerik, geri bildirim ve öğrenme yollarını dinamik olarak uyarlar.

## 🚀 Kurulum (Nasıl Çalıştırılır?)

Platform, **PHP** ve **MySQL** tabanlı bir uygulamadır. Kendi bilgisayarınızda çalıştırmak için **XAMPP** (veya benzeri bir lokal sunucu) kullanabilirsiniz.

### 1. Dosyaları Taşıyın
1. Bilgisayarınıza XAMPP kurun.
2. Bu projeyi indirip XAMPP içerisindeki `htdocs` klasörüne kopyalayın:
   - Örnek yol: `C:\xampp\htdocs\english_platform`

### 2. Sunucuyu Başlatın
- **XAMPP Control Panel**'i açın.
- **Apache** ve **MySQL** servislerini `Start` diyerek başlatın.

### 3. Veritabanı (Database) Kurulumu
1. Tarayıcınızda `http://localhost/phpmyadmin/` adresini açın.
2. Yeni bir veritabanı oluşturun.
3. İçeri aktar (Import) sekmesinden proje ana dizinindeki `install.sql` dosyasını seçin ve yükleyin. 
   *(Veritabanı bağlantı ayarları `includes/config.php` içerisinde yer alır, varsayılan olarak XAMPP ayarları ile uyumludur)*.

### 4. Yapay Zeka (Gemini AI) Entegrasyonu
Sistemin soru üretme, geri bildirim verme ve ipucu sağlama gibi özelliklerini kullanabilmek için Gemini API anahtarına ihtiyacınız var:
1. `config/gemini.php` dosyasını açın.
2. Kendi API anahtarınızı aşağıdaki gibi ekleyin:
   ```php
   define('GEMINI_API_KEY', 'BURAYA_KENDI_API_ANAHTARINIZI_YAZIN');
   ```

---

## 🔐 Kullanıcı Rolleri ve Giriş

Sistemde iki farklı rol bulunmaktadır: **Öğrenci** ve **Admin**.

### 👨‍🎓 Öğrenci (Student) Girişi
- **URL:** `http://localhost/english_platform/public/index.php`
- Öğrenciler anasayfadan sisteme doğrudan kayıt olabilir ve kendi seviye tespit sınavlarına girerek öğrenme süreçlerine başlayabilirler.

### 🛠️ Admin (Yönetici) Girişi
- **URL:** `http://localhost/english_platform/public/admin.php`
- **Not:** Güvenlik nedeniyle admin hesapları uygulama içerisinden *kayıt olamaz*. Sadece veritabanından manuel olarak eklenebilir.

#### Nasıl Admin Eklenir?
1. Öncelikle şifrenizin hashlenmiş halini üretmeniz gerekir. Terminalden şu komutu çalıştırın:
   ```bash
   php -r "echo password_hash('GIRIS_SIFRENIZ', PASSWORD_DEFAULT);"
   ```
2. Çıkan sonucu kopyalayın (Örn: `$2y$10$...`)
3. `phpMyAdmin`'de `users` tablosuna SQL sekmesinden aşağıdaki komutla ekleme yapın:
   ```sql
   INSERT INTO users (role, username, email, password_hash, full_name, last_active_at)
   VALUES ('admin', 'admin_isim', 'admin@email.com', 'KOPYALADIGINIZ_HASH', 'Admin Adı', NOW());
   ```
4. Artık admin paneline oluşturduğunuz email ve şifre ile giriş yapabilirsiniz.

---

## 🌟 Projenin Temel Özellikleri

- **Seviye Belirleme Sınavı:** Yeni kullanıcıların CEFR (A1-B2) seviyesini belirler.
- **Yapay Zeka Destekli Pratik:** Eksik açıklamaları ve ipuçlarını Gemini AI ile otomatik olarak oluşturur.
- **Detaylı Analitik (Chart.js):** Öğrenci gelişimini görselleştirir ve başarı oranını ölçer.
- **Oyunlaştırma (Gamification):** Puan, rozetler ve günlük seriler ile motivasyonu artırır.
- **Öğrenme Modeli Tespiti:** Kullanıcının okuyarak mı yoksa dinleyerek mi daha iyi öğrendiğini analiz eder.

---
**Not:** *Eski sürümlerden kalma veritabanı güncellemeleri için `sql/` klasörü altındaki dosyaları kullanmanıza gerek yoktur; sıfır kurulumlar için `install.sql` yeterlidir.*
