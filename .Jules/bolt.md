## 2026-05-14 - [Initial Performance Audit]
**Learning:** Identified critical lazy-loading and redundant computation patterns in core paths:
1. `AuthService::login` was fetching employees then lazy-loading `company`, adding an extra query to the critical login path.
2. `AttendanceController::today` (manager view) was performing container lookups for `current_company` and its timezone inside a loop of potentially hundreds of employees.
3. `AttendanceLog` queries in dashboard views were fetching `*` columns including heavy text fields (notes, photo paths) that are not displayed.
4. `AttendanceService` used manual `find()` calls for relationships, bypassing Laravel's internal relationship caching and preventing future eager loading.

**Action:** Implement eager loading in Auth, optimize dashboard loops with pre-resolved values, and switch to relationship-based property access.
