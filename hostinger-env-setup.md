# Hostinger Environment Setup

## Fix for Storage Logs Permission Error

The error occurs because Hostinger's shared hosting doesn't allow creating directories in the default storage path.

### Solution 1: Use Error Log Driver (Recommended)
Add this to your `.env` file on Hostinger:

```
LOG_CHANNEL=errorlog
```

This uses PHP's error_log() function which works on shared hosting.

### Solution 2: Custom Log Path
If you need file logging, use an absolute writable path:

```
LOG_CHANNEL=single
LOG_PATH=/home/u593897020/domains/supplier.sa/public_html/api/storage/logs/laravel.log
```

### Solution 3: Null Logging (Temporary)
For testing, disable logging completely:

```
LOG_CHANNEL=null
```

## Fix for CORS Policy Error

The CORS error occurs because the frontend (supplier.sa) can't access the API (api.supplier.sa).

### Solution: Update CORS Configuration
The CORS config has been updated to allow your production domains:

```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:5173', 
    'https://supplier.sa',
    'https://www.supplier.sa',
    'https://api.supplier.sa'
],
```

### Required Hostinger Actions

1. Upload your files to Hostinger
2. Set the correct `.env` variables for production
3. Ensure storage directory is writable (755 permissions)
4. Run `php artisan cache:clear` and `php artisan config:clear`
5. **Important**: Clear CORS cache by restarting PHP or running `php artisan cache:clear`

## Common Hostinger Issues

- **Storage permissions**: Hostinger may restrict directory creation
- **Absolute paths**: Use full paths in `.env` for reliability
- **PHP version**: Ensure PHP 8.1+ is enabled in cPanel
- **Extensions**: Enable required PHP extensions (pdo, mbstring, etc.)
- **CORS caching**: Browser may cache CORS responses - clear browser cache

## Deployment Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure database credentials
- [ ] Set appropriate log channel
- [ ] Clear all caches
- [ ] Test endpoints
- [ ] Clear browser cache for CORS
