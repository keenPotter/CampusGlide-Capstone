# FRONTEND — Vehicle & Driver Allocation (Vue 3)

Lahat ng UI ay `.vue` (Vue 3, `<script setup>`). Sumusunod sa **CampusGlide UI Standard v2**.
Walang Tailwind o ibang CSS library na kailangan — plain CSS lang (`styles/allocation.css`).

```
src/modules/vehicle-driver-allocation/
  AllocationsPage.vue            <- ang page (route component)
  api.js                         <- login, token (sessionStorage), fetch na may Bearer token, admin role
  format.js                      <- formatDate / formatTime (para sa printable form)
  styles/allocation.css          <- UI Standard v2 design tokens + styles (naka-scope sa .cg-app)
  components/
    AppHeader  SearchBar  AuthPanel  AccountChip  StatCard  StatusBadge  AppButton  Icon
    AllocationCard  RequestCard  AllocationModal  PrintableTripRequest
```
Ang `index.html`, `package.json`, `vite.config.js`, `src/main.js` at `src/App.vue` ay **pang-test lang**.
Kung may sarili nang Vue app ang team, ang folder na `src/modules/vehicle-driver-allocation/` lang ang kunin.

## A. Mag-test nang mag-isa (standalone)
Kailangan: Node.js 18+ (`node -v`) at tumatakbo ang Laravel API.

1. Sa Laravel project: `php artisan serve` (naka-on din ang MySQL).
2. Sa folder na ito (`frontend`):
```powershell
npm install
npm run dev
```
3. Buksan ang `http://localhost:5173`. Ang `/api` ay kusang ipinapasa ng Vite sa
   `http://127.0.0.1:8000` (walang CORS problem).

## B. Isama sa Vue app ng team
1. Kopyahin ang `src/modules/vehicle-driver-allocation/` sa `src/` ng app.
2. Magdagdag ng route (Vue Router):
```js
{
  path: '/allocations',
  component: () => import('@/modules/vehicle-driver-allocation/AllocationsPage.vue'),
}
```
3. Font: **Inter** (i-load sa `index.html`, tingnan ang kasamang halimbawa).
4. API URL: default ay `/api`. Kung hiwalay ang backend server, gumawa ng `.env`:
```
VITE_API_BASE=http://127.0.0.1:8000/api
```
   (kailangan ding i-enable ang CORS sa Laravel: `php artisan config:publish cors`).

## Paano gumagana
- **Dalawang user lang**: **Admin** at **End User**. Admin ang role na nasa `ADMIN_ROLES` ng `api.js`
  (`administrator`, `admin`); lahat ng iba ay End User. (Dapat tugma sa `AllocationService::ADMIN_ROLES` ng backend.)
- **Login**: `POST /api/login` → `{ token, role }`. Naka-save sa `sessionStorage`, kaya kailangang
  mag-login ulit pagkasara ng tab/browser. Kapag nag-expire ang token (401), kusang nagla-logout.
- **☰ menu / chip sa ibaba**: hindi naka-login → login form; naka-login → **Log out**.
- **Admin**: nakikita ang lahat; may **Needs allocation** (approved requests na wala pang vehicle at driver;
  may bilang sa 🔔), **+ New Allocation**, **Reassign** at **Cancel**. Ang Admin ang pumipili ng vehicle at driver.
- **Vehicle request**: End User fills out destination, purpose, trip date/time, and passenger count, then submits a
  request. It starts as `pending` and appears in the Admin's approval list. Once approved, it moves to **Needs allocation**.
  The API must provide `GET/POST /api/vehicle-requests` and
  `POST /api/vehicle-requests/{id}/approve` (implemented by `VehicleRequestController`).
- **Print / Save as PDF** (Admin lang, sa bawat allocation na hindi cancelled): bubukas ang **Trip Request** form
  (request details, vehicle at driver, Approved by + Approval date, at puwang para sa **physical signature ng Boss**).
  Pindutin ang **Print / Save as PDF**: i-print mismo, o piliin ang *Save as PDF* para maipasa sa Boss.
  Ang Boss ay Admin din, kaya pwede rin niyang buksan at i-print ito sa sarili niyang account.
  Walang Boss approval button o status (physical signature lang, ayon sa client).
- **Send to admin**: mula sa **Send to admin** button sa allocation card o sa Trip Request print dialog,
  puwedeng ipadala ng Admin ang Trip Request sa ibang Admin. Makikita ito ng tatanggap sa
  **Received trip requests**, kung saan maaari niya itong buksan, i-print, o i-save bilang PDF para maipasa sa Boss.
- **End User**: makakagawa ng vehicle request at makikita ang status ng sarili niyang requests at trips.
- **Driver** ay record lang (pangalan at contact number ang ipinapakita) — walang login.

## UI Standard v2 — saan makikita
Lahat ng sukat at kulay ay nasa `styles/allocation.css`, sa tokens ng `.cg-app` (palitan doon para magbago sa buong page).

| Standard | Token / halaga |
|---|---|
| Font | Inter |
| Page title | 24px mobile · 28px desktop (≥768px) |
| Section title | 20px mobile · 22px desktop |
| Normal / Small text | 16px / 14px |
| Page padding | 20px mobile · 24px desktop · 32px (≥1024px) |
| Card padding | 16px mobile · 20px desktop |
| Max content width | 1280px |
| Input / Button height | 44px |
| Border radius | 10px |
| Icon | 24px |
| Primary color | Green `#27AF30` (accents, focus, active dot) |
| Primary buttons | Dark green `#1C7D20` (shade ng #27AF30; mababasa ang puting text) |
| Warning | Orange `#FFA500` (dark text) |
| Destructive | Red |
| Secondary buttons | Puti, outlined sa Gray `#DBDBDB` |

## Test accounts (campusglide_db; password ay `password123` pagkatapos i-update)
Admin: `admin@nvsu.edu.ph` · End User: `faculty1@nvsu.edu.ph` (requester).

## Idinagdag (Oktubre)
- **Palit ng petsa**: Faculty = **Request date change** lang (nakikita ang status ng request). Admin = **Change date**
  (may reason) at **Approve / Cancel** sa seksyong *Date change requests*.
- **Conflict modal**: lalabas pagpindot ng **Allocate** (o Approve/Change date) kung may banggaan ang vehicle o driver.
- **Driver / Vehicle picker**: custom dropdown (pangalan + phone / model + plate), may **Add new driver** at **Add new vehicle**.
- **Pagkatapos mag-allocate**: kusang bubukas ang Trip Request — i-print mismo o *Save as PDF* para ipasa sa Boss.
- Bagong components: `PickerList`, `ConflictModal`, `QuickAddModal`, `RescheduleModal`, `RescheduleRequestCard`.
