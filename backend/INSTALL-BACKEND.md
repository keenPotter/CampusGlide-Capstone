# BACKEND — Vehicle & Driver Allocation (API)

Laman (allocation lang — walang kopya ng models o login ng ibang modules):

| File | Gamit |
|---|---|
| `app/Models/Allocation.php` | Ang tanging model ng module |
| `app/Services/AllocationService.php` | Rules (driver availability, vehicle readiness, conflicts) + admin/end-user check |
| `app/Http/Controllers/Api/AllocationController.php` | index, show, options, store, update, destroy |
| `app/Http/Controllers/Api/VehicleRequestController.php` | Paggawa ng vehicle request at admin approval |
| `app/Http/Requests/AllocationRequest.php` | Validation + admin-only (POST at PUT/PATCH sa iisang class) |
| `app/Http/Resources/AllocationResource.php` | Hugis ng JSON response |
| `database/migrations/…create_allocations_table.php` | Gumagawa ng table kung wala pa |
| `routes/allocations.php` | Staging ng routes (kopyahin sa `routes/api.php`) |

`GET /api/allocations/{id}/print` ang nagbibigay ng data para sa printable Trip Request (Admin lang).
| `tests/smoke.sh` | Mabilis na test ng API |

## Dalawang klase ng user (ayon sa client)
- **Admin** (`users.role` = `administrator` o `admin`; Chief Motorpool at Boss ay parehong Admin):
  nakikita ang lahat; siya lang ang pwedeng pumili ng vehicle at driver (create, reassign, cancel).
- **End user** (kahit anong ibang role, hal. Faculty): sariling request/trip lang ang nakikita, view-only.
- Kung ibang pangalan ang role ng admin sa DB, idagdag sa `AllocationService::ADMIN_ROLES`
  at sa `ADMIN_ROLES` ng frontend `api.js`.

## Driver ay record lang
Walang driver login/account. Ang pangalan at contact number ay binabasa sa mismong `drivers` record
(`name` o `first_name` + `last_name`, at `contact_number`); fallback ang lumang naka-link na user.
Kung iba ang column names ng Driver Management, i-adjust ang `AllocationService::driverName()`
at ang `contact_number` sa `AllocationResource` at controller.

## Ano ang dapat nasa project na (galing sa ibang modules)
- Models: `Trip`, `Driver`, `VehicleRequest`, `Vehicle`, `User`
  (`Vehicle`: `status`, `is_active`, `plate_number`, `vehicle_model`; `User`: `role`).
- Approved na ang request (`vehicle_requests.status = 'approved'`) bago ito lumabas para sa allocation.
- Ang bagong vehicle request ay `pending`; admin ang nag-a-approve nito bago ito lumabas sa `allocation-options`.
- Sanctum login: `POST /api/login` na nagre-return ng `{ "token": "...", "role": "..." }`,
  at `use HasApiTokens;` sa `User` model.
- Ang relationships na kailangan (`trip.vehicleRequest.requester`, `vehicleRequest.trip`)
  ay kusang nirerehistro ng `Allocation::registerSharedRelations()` — walang kailangang i-edit sa files ng teammates.

## 1. I-copy sa Laravel project (PowerShell)
```powershell
$src = "C:\Users\ACER\Downloads\CampusGlide-Capstone\backend"
$dst = "C:\Users\ACER\Downloads\CampusGlide-Capstone\campusglide-api"   # ang Laravel project

Copy-Item "$src\app" -Destination "$dst\" -Recurse -Force
Copy-Item "$src\database\migrations\*" -Destination "$dst\database\migrations\" -Force
Copy-Item "$src\tests\smoke.sh" -Destination "$dst\tests\" -Force
```

## 2. Database
Gamitin ang `campusglide_db`. Kung nandoon na ang `allocations` table, okay lang.
Kung wala: `php artisan migrate`.

## 3. API routes
```powershell
php artisan install:api      # kung wala pang routes/api.php (Laravel 11/12)
```
Kopyahin ang laman ng `routes/allocations.php` sa `routes/api.php`. Kasama rito ang
`GET|POST api/vehicle-requests` at `POST api/vehicle-requests/{vehicleRequest}/approve`.

## 4. I-check
```powershell
php artisan optimize:clear
php artisan route:list --path=allocation
php artisan serve
```
Dapat may: `GET api/allocation-options`, `GET|POST api/vehicle-requests`,
`POST api/vehicle-requests/{vehicleRequest}/approve`, `GET|POST api/allocations`,
`GET|PUT|DELETE api/allocations/{allocation}`, `GET api/allocations/{allocation}/print`.

## 5. Test ng backend lang
Git Bash (may curl + jq):
```bash
BASE=http://127.0.0.1:8000 EMAIL=admin@nvsu.edu.ph PASSWORD=password123 bash tests/smoke.sh
```
Mano-mano sa Postman (header: `Accept: application/json`, `Authorization: Bearer <token>`):
1. `POST /api/login` → 200 + token
2. `GET /api/allocations` → 200 (walang token → 401)
3. `GET /api/allocation-options` → 200 (end user → 403)
4. `POST /api/allocations` `{vehicle_request_id, vehicle_id, driver_id, notes}` → 201
   (DB: bagong `trips` + `allocations`, vehicle `in_use`, driver `is_available=0`)
5. Ulitin ang POST → 422 (already allocated)
6. `PUT /api/allocations/{id}` `{"driver_id": <ibang driver>}` → 200
7. `PUT` `{"status":"completed"}` → 200, balik available ang vehicle/driver
8. `DELETE /api/allocations/{id}` sa active na trip → 200; ulitin → 422
9. Login bilang end user (hal. `faculty1@nvsu.edu.ph`): `POST` / `PUT` / `DELETE` / `GET .../print` → 403,
   `GET` → sariling request lang
10. `GET /api/allocations/{id}/print` bilang admin → 200: `data.allocation` (trip, vehicle, driver) at
    `data.request` (requested_on, passengers, approved_by, approved_on)

## Idinagdag (Oktubre)
| File | Gamit |
|---|---|
| `database/migrations/2026_10_05_000001_create_reschedule_requests_table.php` | Table ng palit-petsa (kailangan ng `php artisan migrate`) |
| `app/Models/RescheduleRequest.php` | Model ng request / history ng palit-petsa |
| `app/Http/Controllers/Api/RescheduleRequestController.php` | index, store (faculty), approve, cancel, reschedule (admin) |
| `app/Http/Controllers/Api/AllocationQuickAddController.php` | Add new driver / add new vehicle (Admin) |
| `app/Models/TripRequestShare.php` + `app/Http/Controllers/Api/TripRequestShareController.php` | Ipadala ang Trip Request sa ibang Admin at basahin ito sa inbox |
| `database/migrations/2026_10_05_000002_create_trip_request_shares_table.php` | Records ng mga ipinadalang Trip Request |

Bagong routes (nasa `routes/allocations.php` — kopyahin ulit sa `routes/api.php`):
- `GET api/reschedule-requests` — Admin: lahat; Faculty: sarili lang
- `POST api/allocations/{id}/reschedule-requests` `{new_date, reason}` — **Faculty: request lang**
- `POST api/reschedule-requests/{id}/approve` — **Admin**; `.../cancel` — **Admin**, permanently deletes the pending request without changing the trip
- `POST api/allocations/{id}/reschedule` `{new_date, reason}` — **Admin: direktang palit, may reason**
- `POST api/allocation-drivers` `{name, contact_number, license_number, license_expiry_date}`
- `POST api/allocation-vehicles` `{plate_number, vehicle_model, capacity?}`
- `GET api/admins` — ibang Admin accounts na pwedeng pagpadalhan
- `POST api/trip-request-shares` `{allocation_id, recipient_id}` — ipadala ang Trip Request sa inbox ng Admin
- `GET api/trip-request-shares` — mga Trip Request na ipinadala sa naka-login na Admin

Conflict: ang 422 ng Allocate / Approve / Change date ay may `conflict` object
(`type, subject, date, start, end, destination, driver, vehicle`) para sa modal ng frontend.
`GET api/allocation-options` ay nagbabalik na ng LAHAT ng active vehicle at driver (hindi na sinasala ang busy);
ang banggaan ay sinusuri na lang kapag pinindot ang Allocate. Ang vehicle na `in_use` at driver na `is_available=0`
dahil sa ibang trip ay hindi na harang — petsa/oras (conflict check) na ang batayan.
Kung may ibang NOT NULL columns ang `drivers` / `vehicles`, idagdag sa `AllocationQuickAddController`.
