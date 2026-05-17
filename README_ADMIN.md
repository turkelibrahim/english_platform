# Admin hesabı (site üzerinden kayıt yok)

Bu projede **admin kayıt ekranı kapalı**. Admin hesapları **sadece veritabanından** eklenir.

## 1) Şifre hash'ini üret
Terminal/PowerShell'de proje klasöründe şunu çalıştır:

```bash
php -r "echo password_hash('SIFREN', PASSWORD_DEFAULT);"
```

Çıkan uzun metni kopyala (ör: `$2y$10$...`).

> Not: XAMPP kullanıyorsan `php` komutu çalışmazsa, `C:\xampp\php\php.exe -r ...` şeklinde deneyebilirsin.

## 2) phpMyAdmin ile admin ekle

phpMyAdmin → SQL sekmesi:

```sql
INSERT INTO users (role, username, email, password_hash, full_name, last_active_at)
VALUES ('admin', 'admin_username', 'admin@email.com', 'BURAYA_HASH', 'Admin Name', NOW());
```

- `username` **unique** olmalı.
- `email` **unique** olmalı.

## 3) Giriş
Sonra:
- Admin portal: `/english_platform/public/admin.php`
- Giriş yap: email + şifre

