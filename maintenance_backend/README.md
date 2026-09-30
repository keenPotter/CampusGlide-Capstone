# CampusGlide Sprint 3 — Laravel + Vue

A standalone Sprint 3 maintenance monitoring project using Laravel 12, Vue 3, Vite, Axios, and the supplied CampusGlide MySQL schema.

## Run with Laravel Herd + XAMPP MySQL
1. Put/link this project in your Herd directory.
2. In XAMPP, start **MySQL only**. Apache is not needed.
3. Import `database/campusglide_db.sql` into MySQL/phpMyAdmin if `campusglide_db` is not already imported.
4. In the project terminal:

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan config:clear
php artisan migrate
npm run dev
```

`.env` database values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campusglide_db
DB_USERNAME=root
DB_PASSWORD=
```

Do **not** run `php artisan migrate:fresh` on the existing CampusGlide database.

Herd serves the Laravel page at the Herd `.test` domain assigned to the project. Vite serves the development assets while `npm run dev` is running.

## API
- GET `/api/maintenance-logs`
- POST `/api/maintenance-logs`
- PATCH `/api/maintenance-logs/{maintenanceLog}`
- GET `/api/vehicles/{vehicle}/status`
- GET `/api/vehicles`

The supplied database's `vehicle_maintenance` table does not initially include `next_due_date`; the included migration adds it.

This standalone Sprint 3 package leaves authentication middleware off the maintenance routes so the Vue UI can be tested immediately. When integrating into the complete CampusGlide application, restore your existing Sanctum/administrator middleware group.
