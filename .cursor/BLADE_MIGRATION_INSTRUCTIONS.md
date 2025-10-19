# 🌐 BLADE TEMPLATE MIGRATION INSTRUCTIONS

## Overview
This document provides instructions for updating all Blade templates to use the new JSON-based translation system.

## Migration Steps

### 1. Update Translation Calls

**OLD FORMAT (PHP arrays):**
```blade
{{ __('messages.common.save') }}
{{ __('messages.job.job_title') }}
{{ trans('validation.required') }}
```

**NEW FORMAT (JSON keys):**
```blade
{{ __('messages.common.save') }}
{{ __('messages.job.job_title') }}
{{ __('validation.required') }}
```

### 2. Common Translation Patterns

#### Form Labels
```blade
<!-- OLD -->
<label>{{ __('messages.common.email') }}</label>

<!-- NEW -->
<label>{{ __('messages.common.email') }}</label>
```

#### Button Text
```blade
<!-- OLD -->
<button>{{ __('messages.common.save') }}</button>

<!-- NEW -->
<button>{{ __('messages.common.save') }}</button>
```

#### Flash Messages
```blade
<!-- OLD -->
@if(session('success'))
    <div class="alert alert-success">{{ __('messages.flash.success') }}</div>
@endif

<!-- NEW -->
@if(session('success'))
    <div class="alert alert-success">{{ __('messages.flash.success') }}</div>
@endif
```

### 3. Pluralization
```blade
<!-- OLD -->
{{ trans_choice('messages.job.jobs', $count) }}

<!-- NEW -->
{{ __('messages.job.jobs', ['count' => $count]) }}
```

### 4. Parameters
```blade
<!-- OLD -->
{{ __('messages.welcome', ['name' => $user->name]) }}

<!-- NEW -->
{{ __('messages.welcome', ['name' => $user->name]) }}
```

## Available Translations

### Common Translations
- `en.messages.common.search`: "Search"
- `en.messages.common.reset`: "Reset"
- `en.messages.common.actions`: "Actions"
- `en.messages.common.save`: "Save"
- `en.messages.common.cancel`: "Cancel"
- `en.messages.common.edit`: "Edit"
- `en.messages.common.delete`: "Delete"
- `en.messages.common.view`: "View"
- `en.messages.common.back`: "Back"
- `en.messages.common.loading`: "Loading..."
- `en.messages.common.confirmation`: "Confirmation"
- `en.messages.common.confirmation_message`: "Are you sure you want to perform this action?"
- `en.messages.common.yes`: "Yes"
- `en.messages.common.no`: "No"
- `en.messages.common.active`: "Active"
- `en.messages.common.inactive`: "Inactive"
- `en.messages.common.status`: "Status"
- `en.messages.common.date`: "Date"
- `en.messages.common.showing`: "Showing"
- `en.messages.common.to`: "to"
- ... and 201 more


## Language Switcher Usage

Add the language switcher to your layout:
```blade
<x-language-switcher />
```

## Validation

After migration, test all pages with different languages:
1. Switch to each language
2. Verify all text displays correctly
3. Check form validation messages
4. Test pluralization

## Notes
- All translation keys are now flat (dot notation)
- JSON files are located in `resources/lang/json/`
- Fallback language is English (en)
- RTL support is available for Arabic
