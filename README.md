# Email Extractor

A Laravel-based web application that extracts email addresses from websites using a headless browser.

## Features

- Upload a CSV file containing website URLs
- Extract emails from visible content on the front-end (not from source code)
- Crawl common pages like home, about, contact, and services
- Display real-time progress with a progress bar
- Export results to a CSV file
- No database storage required
- Optimized for processing up to 7,000 websites within 1 hour

## Requirements

- PHP 8.1+
- Laravel 10+
- Composer
- Chrome/Chromium browser (for Laravel Dusk)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd email-extractor
```

2. Install dependencies:
```bash
composer install
```

3. Set up environment variables:
```bash
cp .env.example .env
php artisan key:generate
```

4. Install Chrome driver for Laravel Dusk:
```bash
php artisan dusk:install
```

5. Create storage directory for uploads:
```bash
mkdir -p storage/app/uploads
```

6. Set proper permissions:
```bash
chmod -R 775 storage bootstrap/cache
```

## Usage

1. Start the Laravel development server:
```bash
php artisan serve
```

2. Visit `http://localhost:8000` in your browser.

3. Upload a CSV file with a column named "url" containing the website URLs.

4. Click "Start Extraction" and wait for the process to complete.

5. Download the results as a CSV file.

## File Format

The input file should be in CSV format with a column named "url" containing the website URLs:

Example:
```
url
example.com
example.org
anotherwebsite.com
```

## Performance Optimization

The application is designed to process a large number of websites efficiently:

- Uses Laravel Dusk for headless browser automation
- Implements concurrent processing for better performance
- Optimized for memory usage with no database storage
- Uses caching for temporary data storage

## Troubleshooting

If you encounter issues:

1. Make sure Chrome/Chromium is installed on your system
2. Check that Laravel Dusk is properly installed and configured
3. Ensure your input file has the correct format with a "url" column
4. Check storage directory permissions
#   e m a i l - e x t r a c t o r  
 #   e m a i l - e x t r a c t o r  
 