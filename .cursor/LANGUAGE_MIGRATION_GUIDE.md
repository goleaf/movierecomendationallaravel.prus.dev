# Language Migration Guide

## Overview
The language system has been converted from PHP arrays to JSON files for better performance and easier management.

## Changes Made
- All PHP language files (messages.php, web.php, js.php, etc.) have been converted to JSON format
- Each language now has a single JSON file: `lang/{language}.json`
- Translation keys are now flat or nested as needed

## Usage in Blade Templates

### Old Way (PHP arrays):
```blade
{{ __('messages.welcome') }}
{{ __('web.home') }}
```

### New Way (JSON):
```blade
{{ __('welcome') }}
{{ __('home') }}
```

## Usage in Controllers

### Old Way:
```php
$message = __('messages.success');
```

### New Way:
```php
$message = __('success');
```

## Adding New Translations

1. Edit the appropriate language JSON file: `lang/{language}.json`
2. Add your key-value pair:
```json
{
  "new_key": "New Translation",
  "nested": {
    "key": "Nested Translation"
  }
}
```

## Nested Translations
For nested translations, use dot notation:
```blade
{{ __('nested.key') }}
```

## Pluralization
JSON format supports pluralization:
```json
{
  "items": "{0} No items|{1} One item|[2,*] :count items"
}
```

Usage:
```blade
{{ trans_choice('items', $count) }}
```

## Next Steps
1. Update all Blade files to use the new translation keys
2. Remove old PHP language files after testing
3. Update language switching logic if needed
4. Test all translations thoroughly
