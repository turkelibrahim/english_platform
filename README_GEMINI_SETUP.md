# English Platform – Gemini Integration (Quick Setup)

## 1) Add your Gemini API key
Open:
- `config/gemini.php`

Set:
```php
define('GEMINI_API_KEY', 'YOUR_KEY_HERE');
```

Default model:
- `gemini-2.5-flash` (you can change `GEMINI_MODEL` if you want)

## 2) Database
Import `install.sql` into your MySQL database and make sure `includes/config.php` has correct DB credentials.

## 3) Login URLs
- Student portal: `/public/index.php`
- Admin portal: `/public/admin.php` (first admin can be created here)

## 4) Where AI is used
- Student practice:
  - If `explanation` / `example_sentence` is missing in DB, it is generated automatically after an answer is submitted.
  - In the Hint panel, if `hint` or `example_sentence` is missing, students can click “AI: generate hint & example”.
- Admin questions:
  - “AI Generator” button fills the Add Question form using Gemini.
