# FRONTEND — Vehicle-Driver Allocation (Blade + JS)

Laman: `resources/views/allocations/index.blade.php` at `resources/views/components/*`.
Walang PHP logic dito — tumatawag lang ito sa BACKEND API.

## Kailangan mula sa backend
- `POST /api/login` → `{ token, role }`
- `GET /api/allocations?per_page=100`
- `GET /api/allocation-options[?vehicle_request_id=ID]`
- `POST /api/allocations`, `PUT/DELETE /api/allocations/{id}`

(Naka-package ito sa `allocation-backend.zip`.)

## 1. I-copy (PowerShell)
```powershell
$src = "C:\Users\ACER\Downloads\allocation-frontend"
$dst = "C:\Users\ACER\Downloads\campusglide-allocation"

Copy-Item "$src\resources\views\components" -Destination "$dst\resources\views\" -Recurse -Force
Copy-Item "$src\resources\views\allocations" -Destination "$dst\resources\views\" -Recurse -Force
```
> Kung gumamit ka ng Breeze, may sariling `components/primary-button.blade.php` — i-backup muna.

## 2. Web route (`routes/web.php`)
```php
Route::view('/allocations', 'allocations.index');
```
(Burahin ang lumang `Route::get('/allocations', [AllocationController::class, ...])` kung nandoon.)

## 3. API_BASE
Sa itaas ng `<script>` sa `index.blade.php`:
```js
const API_BASE = '/api';                       // iisang Laravel project (default)
// const API_BASE = 'http://127.0.0.1:8000/api'; // kung hiwalay ang backend server (kailangan ng CORS)
```

## 4. Test ng frontend
1. Patakbuhin ang backend: `php artisan serve`, tapos buksan ang `http://127.0.0.1:8000/allocations`.
2. F12 → Console: walang red errors.
3. ☰ → mag-login bilang **administrator** → "✅ Logged in", may **+ New Allocation**.
   Network tab: `POST /api/login` 200, `GET /api/allocations?per_page=100` 200.
4. **+ New Allocation** → piliin ang approved request → mag-reload ang vehicle/driver dropdowns → Save → lalabas ang card, tataas ang "Scheduled".
5. **Reassign** → palitan ang driver → Save. **Cancel** → confirm → "Cancelled" na.
6. Login bilang driver → walang buttons, sariling assignments lang.
7. Kapag may error: tingnan ang Network response at `storage/logs/laravel.log`.
   - "Cannot reach the server" → hindi tumatakbo ang `php artisan serve` o mali ang `API_BASE`.
   - CORS error sa Console → hiwalay ang server; i-enable ang CORS (INSTALL-BACKEND.md #5).
   - 401 → mag-login ulit. 403 → hindi `administrator` ang role.
