# CampusGlide Improvements — Test Notes

This package is based on the supplied CampusGlide source ZIP. It includes the previously prepared dashboard, user, and trip-modal refinements plus the latest UI and maintenance workflow changes.

## Latest changes

- Added a reusable compact icon-only Refresh button and applied it to Dashboard, Vehicle Requests, Users, Maintenance, Trip Schedule, and Allocations views.
- Standardized compact action sizing for Create User, Add Maintenance, and the Users edit Cancel / Confirm Changes actions.
- Kept Start Maintenance as the blue primary action and changed Mark as Completed to a green success action.
- Consolidated the navigation concept: Preventive Checklist is no longer a separate sidebar item; it is opened from the Checklist action on a preventive maintenance record.
- The checklist screen can be opened focused on the vehicle associated with the selected maintenance record and loads that vehicle's checklist history only.
- Added a Preventive / Corrective maintenance category to maintenance records. Existing `repair` records are migrated as Corrective; other existing records default to Preventive and can be edited.
- Added a database index on maintenance status and date to support common status/date filtering.
- Improved allocation availability checks to compare full trip date/time intervals. A same-day assignment is allowed when its time range does not overlap; the backend rechecks conflicts when saving.

## Important setup

1. Keep your current project as a backup and extract this ZIP into a new folder.
2. Configure `backend/.env` from `backend/.env.example` with your local MySQL database details.
3. In `backend`, run `composer install` and then `php artisan migrate`.
4. In `frontend`, run `npm install` and then `npm run dev`.
5. Do not copy the original `.env`, `vendor`, `node_modules`, or `.git` folders into this test package.

The new migration is `2026_11_08_000001_add_maintenance_category_to_vehicle_maintenance.php`. Back up your database before running migrations.

## Manual test checklist

- Dashboard refresh reloads its summary.
- Vehicle Requests refresh reloads the current request list.
- Users refresh reloads users; Create User, Cancel, and Confirm Changes buttons are compact.
- Maintenance refresh reloads records.
- Add a Preventive maintenance record and verify its Checklist action opens the checklist for the same vehicle.
- Add a Corrective maintenance record and verify it does not show the preventive checklist action.
- Start Maintenance changes a scheduled record to In Progress.
- Mark as Completed changes an In Progress record to Completed and is green, while Start Maintenance is blue.
- Verify completed and cancelled records do not become Upcoming or Overdue based on dates.
- Trip Schedule and Allocations refresh buttons use the same compact icon-only style.
- Reassign a trip to a date where the same vehicle/driver has another trip at a different time; it should be available if the intervals do not overlap. Try an overlapping time as well; the backend should reject it.

## Performance note

The refresh/loading UI and maintenance date/status index are included. Precise time-based trip conflict validation is implemented in the allocation options and save-time checks. However, this package does not claim that all table-loading latency is fixed: API timing and database query profiling must be tested against your local database and realistic record volume. Server-side pagination across every module remains a separate work item and should not be considered completed by this ZIP.
