# wkhtmltopdf PDF (Khmer Unicode) Integration

This project includes a wkhtmltopdf-based PDF generator for better Khmer Unicode rendering than Dompdf.

## What’s included

- Config: `config/pdf.php`
- Service: `app/Services/WkhtmltopdfPdfService.php`
- Example Blade template: `resources/views/pdf/stock_transfer.blade.php`
- Example controller: `app/Http/Controllers/StockTransferPdfController.php`
- Routes: `routes/pdf.php` (loaded by `routes/web.php`)

## 1) Install wkhtmltopdf (Ubuntu/Linux server)

### Option A: apt (may be older)

```bash
sudo apt-get update
sudo apt-get install -y wkhtmltopdf
which wkhtmltopdf
wkhtmltopdf --version
```

Set `.env`:

```env
WKHTMLTOPDF_ENABLED=true
WKHTMLTOPDF_BINARY=/usr/bin/wkhtmltopdf
```

### Option B: official .deb (recommended for stability)

Use the official wkhtmltopdf build for your Ubuntu version.
After install, confirm:

```bash
wkhtmltopdf --version
which wkhtmltopdf
```

Then set `WKHTMLTOPDF_BINARY` to that path.

Important for Khmer/Unicode:
- Prefer **wkhtmltopdf 0.12.6 (patched Qt)**. Older distro builds may render Khmer incorrectly even if fonts exist.

## 2) Install Khmer fonts (Ubuntu)

Recommended fonts:
- Noto Sans Khmer
- Battambang / Khmer OS Battambang

Install (commonly available):

```bash
sudo apt-get update
sudo apt-get install -y fonts-noto-core fonts-noto-extra
sudo fc-cache -f -v
fc-list | grep -i khmer | head
```

This integration loads fonts from `storage/fonts/` (so it works consistently on dev + server).

Copy Khmer fonts into:

```bash
mkdir -p storage/fonts
cp NotoSansKhmer-Regular.ttf storage/fonts/
cp KhmerOSbattambang.ttf storage/fonts/
```

Make sure the web user can read them.

If you prefer system fonts instead, install them and then update the Blade template paths.

```bash
sudo mkdir -p /usr/share/fonts/truetype/battambang
sudo cp Battambang-Regular.ttf /usr/share/fonts/truetype/battambang/
sudo fc-cache -f -v
```

## 3) Local development

Install wkhtmltopdf on your OS, then set:

```env
WKHTMLTOPDF_BINARY=/path/to/wkhtmltopdf
```

macOS (Homebrew) paths:
- Apple Silicon: `/opt/homebrew/bin/wkhtmltopdf`
- Intel: `/usr/local/bin/wkhtmltopdf`

## 4) Generate PDF from Blade

Example (used in `StockTransferPdfController`):

```php
$pdfService->saveViewToPdf('pdf.stock_transfer', $data, $pdfPath);
```

## 5) Telegram send

Example (also in `StockTransferPdfController`):

```php
$telegram->sendDocumentToChat($chatId, $pdfPath, 'វិក្កយបត្រ', basename($pdfPath));
```

### Telegram prerequisites (Ubuntu/public server)

Make sure the server can make outbound HTTPS requests to Telegram and PHP has the needed extensions:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates
sudo apt-get install -y php-curl
php -m | grep -E 'curl|openssl'
```

Set `.env`:

```env
TELEGRAM_BOT_TOKEN=123456:ABC-YourBotToken
```

If you use config cache on production, reload config after changing `.env`:

```bash
php artisan config:clear
php artisan cache:clear
```

## 6) Troubleshooting

### wkhtmltopdf path issue
- Error: “binary not executable”
- Fix: check `WKHTMLTOPDF_BINARY` and permissions:

```bash
ls -la /usr/bin/wkhtmltopdf
```

### Missing fonts / Khmer still broken
- Confirm fonts exist:

```bash
ls -la storage/fonts
```

- Ensure template uses UTF‑8:
  - `<meta charset="utf-8">`
  - `--encoding utf-8` (set in `config/pdf.php`)

- Ensure local fonts are accessible:
  - `--enable-local-file-access` is enabled in `config/pdf.php`
  - Template uses `file://{{ storage_path("fonts/...") }}` paths

### Permission issue writing PDFs
- Ensure `storage/` is writable by your web user.

### Telegram “corrupted PDF”
- Ensure PDF was written successfully before sending.
- Check file size > 0:

```bash
ls -la storage/app/temp/*.pdf
```

### Telegram send fails on server
- Check `config('telegram.bot_token')` is not empty (token present in `.env` and config is not stale).
- Check PHP extensions: `curl` + `openssl` are enabled.
- Check CA certs: `ca-certificates` installed (SSL verification errors).
- Check firewall/NAT allows outbound `https://api.telegram.org`.

### Khmer ok in browser but not in PDF
- Browser can use web fonts; wkhtmltopdf needs system fonts or local `file://` fonts.
- Install fonts on the server and verify with `fc-list`.
