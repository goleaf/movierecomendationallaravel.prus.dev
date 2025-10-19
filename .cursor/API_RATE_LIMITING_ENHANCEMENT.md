# 🚦 API Rate Limiting Enhancement

## Overview
Enhanced API rate limiting with cache/Redis support for the Laravel Job Portal.

## Features
- Multi-level rate limiting (general, auth, search, upload)
- Proper HTTP headers for client feedback
- Cache-backed storage with Redis support
- Management commands for monitoring

## Usage

### Apply Middleware to Routes
```php
Route::middleware(["api", "advanced.rate.limit:api.general"])->group(function () {
    Route::get("/jobs", [JobController::class, "index"]);
});

Route::middleware(["api", "advanced.rate.limit:api.auth"])->group(function () {
    Route::post("/auth/login", [AuthController::class, "login"]);
});
```

### Check Statistics
```bash
php artisan rate-limit:stats
```

## Rate Limits
- General API: 60 requests/minute
- Authentication: 10 requests/minute  
- Search: 30 requests/minute
- Upload: 5 requests/minute

## Headers
- `X-RateLimit-Limit`: Maximum requests
- `X-RateLimit-Remaining`: Requests left
- `X-RateLimit-Reset`: Reset timestamp
- `Retry-After`: Seconds to wait (when exceeded)

Enhancement completed successfully! 🎉
