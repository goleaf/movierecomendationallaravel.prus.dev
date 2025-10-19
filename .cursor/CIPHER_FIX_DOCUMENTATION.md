# Laravel Cipher Error Fix

## Problem
The application was experiencing the following error:
```
Unsupported cipher or incorrect key length. Supported ciphers are: aes-128-cbc, aes-256-cbc, aes-128-gcm, aes-256-gcm.
```

## Root Cause
The `.env` file contained an invalid `APP_KEY` value:
```
APP_KEY=base64:your-app-key
```

This is a placeholder value that was never replaced with a proper encryption key.

## Solution
1. Generated a new application key using Laravel's built-in command:
   ```bash
   php artisan key:generate
   ```

2. This created a proper base64-encoded 256-bit encryption key:
   ```
   APP_KEY=base64:4JMAq1WLGNv8otiYdkhVXjV3bbdY2Y2/qQ4Ej2qATu0=
   ```

3. Cleared the configuration and application cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

## Verification
- Laravel routes can now be accessed without cipher errors
- The application starts properly
- All encryption/decryption functionality is restored

## Prevention
- Always run `php artisan key:generate` after setting up a new Laravel installation
- Never use placeholder values in production environment files
- The `.env.example` file contains a proper APP_KEY format for reference

## Related Configuration
- Cipher is correctly set to `aes-256-cbc` in `config/app.php`
- This matches Laravel's recommended encryption settings 