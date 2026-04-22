# Bolt Critical Learnings

- **PHP Version Conflict**: The sandbox environment provides PHP 8.3.6, but the `composer.lock` requires PHP >= 8.4 (specifically for `symfony/clock` v8.0.8). This prevents running `composer install` and `php artisan test` in the current environment.
- **Over-fetching in Web Controllers**: Web controllers (Dashboard, Employee detail) were fetching all columns from `employees` and `attendance_logs`, including sensitive or large fields not needed for the display. Using targeted `select()` is recommended for performance and security.
- **Service Container Redundancy**: Repeatedly calling `app('current_company')` inside loops (e.g. `WebEmployeeController`) can be optimized by caching the result in a local variable before the loop.
