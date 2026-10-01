# BACKEND — Vehicle-Driver Allocation (API)

Laman: Models, `AllocationService`, `Api/AllocationController`, `Api/AuthController` (optional),
Requests, Resource, migration, `routes/allocations.php`, `tests/smoke.sh`.

## 1. I-copy sa Laravel project (PowerShell)
```powershell
$src = "C:\Users\ACER\Downloads\allocation-backend"
$dst = "C:\Users\ACER\Downloads\campusglide-allocation"

Copy-Item "$src\app" -Destination "$dst\" -Recurse -Force
Copy-Item "$src\database\migrations\*" -Destination "$dst\database\migrations\" -Force
Copy-Item "$src\tests\smoke.sh" -Destination "$dst\tests\" -Force
```
> Kung may sarili nang `Trip.php`, `Driver.php`, `VehicleRequest.php` ang teammates mo,
> i-merge ang relationships/scopes — huwag basta i-overwrite.

## 2. Database
Kailangan na nasa DB na: `users`, `vehicles`, `drivers`, `trips`, `vehicle_requests`
(galing sa `campusglide_db.sql` — wala sa package na ito). I-check:
```powershell
php artisan tinker
>>> Schema::hasTable('drivers')
```
Kung nandoon na rin ang `allocations` table, okay lang. Kung wala: `php artisan migrate`.

Kailangan din ng `Vehicle` model (`status`, `is_active`, `plate_number`, `vehicle_model`)
at `User` model (`role`, `is_active`). `Notification` model ay opsyonal.

## 3. API routes
```powershell
php artisan install:api      # kung wala pang routes/api.php (Laravel 11/12)
```
Kopyahin ang laman ng `routes/allocations.php` sa `routes/api.php`.
Alisin ang `/login` route kung may login na ang team mo.

## 4. Login (Sanctum)
- `App\Models\User` → `use Laravel\Sanctum\HasApiTokens;` at `use HasApiTokens;`
- Ang `POST /api/login` ay dapat mag-return ng `{ "token": "...", "role": "administrator" }`.
  Kung wala pa, gamitin ang kasamang `Api/AuthController.php`
  (palagay ko: `users.password`, `first_name`, `last_name` — i-adjust kung iba).

## 5. CORS (kung HIWALAY ang frontend server lang)
```powershell
php artisan config:publish cors
```
Sa `config/cors.php`: `'paths' => ['api/*']` at `'allowed_origins' => ['http://localhost:XXXX']`
(ang URL kung saan naka-host ang frontend).

## 6. I-check
```powershell
php artisan optimize:clear
php artisan route:list --path=allocation
php artisan serve
```
Dapat may: `POST api/login`, `GET api/allocation-options`, `GET|POST api/allocations`,
`GET|PUT|DELETE api/allocations/{allocation}`.

## 7. Test ng backend lang (walang frontend)
Git Bash (may curl + jq):
```bash
BASE=http://127.0.0.1:8000 EMAIL=admin@email.com PASSWORD=secret bash tests/smoke.sh
```
Mano-mano sa Postman (header: `Accept: application/json`, `Authorization: Bearer <token>`):
1. `POST /api/login` → 200 + token
2. `GET /api/allocations` → 200 (walang token → 401)
3. `GET /api/allocation-options` → 200 (driver → 403)
4. `POST /api/allocations` `{vehicle_request_id, vehicle_id, driver_id, notes}` → 201
   (DB: bagong `trips` + `allocations`, vehicle `in_use`, driver `is_available=0`)
5. Ulitin ang POST → 422 (already allocated)
6. `PUT /api/allocations/{id}` `{"driver_id": <ibang driver>}` → 200
7. `PUT` `{"status":"completed"}` → 200, balik available ang vehicle/driver
8. `DELETE /api/allocations/{id}` sa active na trip → 200; ulitin → 422
9. Login bilang driver: `POST` → 403, `GET` → sariling assignments lang
