# CampusGlide Database Documentation

## Database Overview

This documentation covers the complete MySQL database schema for the CampusGlide Fleet Management System at Nueva Vizcaya State University.

**Database Name:** `campusglide_db`

---

## Table Descriptions

### 1. USERS TABLE
Stores all system users regardless of their role.

**Purpose:** Central user authentication and role management

**Fields:**
- `id` (INT, Primary Key) - Unique user identifier
- `email` (VARCHAR 255, UNIQUE) - Email address for login
- `password` (VARCHAR 255) - Hashed password (use bcrypt)
- `first_name` (VARCHAR 100) - User's first name
- `last_name` (VARCHAR 100) - User's last name
- `phone_number` (VARCHAR 20) - Contact number
- `role` (ENUM) - User type: administrator, driver, faculty, guard
- `is_active` (BOOLEAN) - Whether account is active
- `created_at` - Timestamp when record was created
- `updated_at` - Timestamp when record was last updated

**Usage:** Authenticate users and determine access permissions based on role.

---

### 2. VEHICLES TABLE
Contains information about all vehicles in the fleet.

**Purpose:** Track vehicle inventory and maintenance status

**Fields:**
- `id` (INT, Primary Key) - Unique vehicle identifier
- `plate_number` (VARCHAR 20, UNIQUE) - License plate number
- `vehicle_model` (VARCHAR 100) - Make and model (e.g., Toyota Hiace)
- `vehicle_type` (VARCHAR 50) - Type: van, suv, truck, sedan
- `color` (VARCHAR 50) - Vehicle color
- `manufacture_year` (INT) - Year vehicle was manufactured
- `capacity` (INT) - Number of passengers it can carry
- `mileage` (INT) - Current odometer reading
- `status` (ENUM) - available, in_use, maintenance, retired
- `last_maintenance_date` (DATE) - When last maintenance was performed
- `next_maintenance_date` (DATE) - When next maintenance is scheduled
- `is_active` (BOOLEAN) - Whether vehicle is still in service

**Usage:** Determine which vehicles are available for trips and track their condition.

---

### 3. DRIVERS TABLE
Stores driver-specific information linked to users.

**Purpose:** Manage driver licenses, availability, and vehicle assignments

**Fields:**
- `id` (INT, Primary Key) - Unique driver identifier
- `user_id` (INT, Foreign Key) - References users table
- `license_number` (VARCHAR 50, UNIQUE) - Driver's license number
- `license_expiry_date` (DATE) - When license expires
- `contact_number` (VARCHAR 20) - Driver's phone number
- `address` (VARCHAR 255) - Driver's home address
- `assigned_vehicle_id` (INT, Foreign Key) - Currently assigned vehicle
- `is_available` (BOOLEAN) - Whether driver is available for trips

**Usage:** Track driver qualifications and assign them to specific trips.

---

### 4. VEHICLE REQUESTS TABLE
Records all requests submitted by faculty and staff for vehicle use.

**Purpose:** Track the approval workflow for vehicle requests

**Fields:**
- `id` (INT, Primary Key) - Unique request identifier
- `requester_id` (INT, Foreign Key) - Who submitted the request (users table)
- `request_date` (DATETIME) - When request was submitted
- `trip_date` (DATE) - When the trip is needed
- `departure_time` (TIME) - Departure time needed
- `destination` (VARCHAR 255) - Where they're going
- `purpose` (VARCHAR 255) - Purpose of the trip
- `estimated_return_time` (TIME) - When they plan to return
- `number_of_passengers` (INT) - How many people going
- `status` (ENUM) - pending, approved, rejected, cancelled
- `rejection_reason` (VARCHAR 500) - Why it was rejected (if applicable)
- `approved_by` (INT, Foreign Key) - Which administrator approved it
- `approved_date` (DATETIME) - When it was approved

**Usage:** Faculty submit requests here. Administrators review and approve/reject them. Once approved, this triggers creation of a trip record.

---

### 5. TRIPS TABLE
Represents scheduled trips with assigned vehicles and drivers.

**Purpose:** Link approved requests to actual trip execution

**Fields:**
- `id` (INT, Primary Key) - Unique trip identifier
- `vehicle_request_id` (INT, Foreign Key, UNIQUE) - Links to approved request
- `vehicle_id` (INT, Foreign Key) - Which vehicle is assigned
- `driver_id` (INT, Foreign Key) - Which driver is assigned
- `trip_date` (DATE) - Date of the trip
- `departure_time` (TIME) - Scheduled departure time
- `estimated_return_time` (TIME) - When it should return
- `destination` (VARCHAR 255) - Trip destination
- `purpose` (VARCHAR 255) - Trip purpose
- `trip_status` (ENUM) - scheduled, in_progress, completed, cancelled
- `actual_departure_time` (TIME) - When vehicle actually left
- `actual_return_time` (TIME) - When vehicle actually returned
- `actual_mileage` (INT) - Odometer reading after trip
- `notes` (VARCHAR 500) - Any special notes about the trip

**Usage:** Administrators create trips by assigning vehicles and drivers to approved requests.

---

### 6. TRIP LOGS TABLE
Records departure and return times logged by guards.

**Purpose:** Maintain digital record of trip start/end times

**Fields:**
- `id` (INT, Primary Key) - Unique log entry identifier
- `trip_id` (INT, Foreign Key) - Which trip this log belongs to
- `guard_id` (INT, Foreign Key) - Which guard recorded this
- `departure_recorded_at` (DATETIME) - When departure was logged
- `return_recorded_at` (DATETIME) - When return was logged
- `guard_notes` (VARCHAR 500) - Notes from the guard
- `vehicle_condition_departure` (VARCHAR 255) - Condition when leaving
- `vehicle_condition_return` (VARCHAR 255) - Condition when returning
- `damages_reported` (VARCHAR 500) - Any damages noted

**Usage:** Guards log when vehicles leave and return campus. This replaces paper logs.

---

### 7. VEHICLE MAINTENANCE TABLE
Tracks all maintenance activities for vehicles.

**Purpose:** Monitor vehicle servicing and prevent breakdowns

**Fields:**
- `id` (INT, Primary Key) - Unique maintenance record identifier
- `vehicle_id` (INT, Foreign Key) - Which vehicle
- `maintenance_type` (ENUM) - oil_change, repair, refueling, inspection, tire_service, other
- `description` (VARCHAR 500) - Details of work performed
- `maintenance_date` (DATE) - When maintenance is/was scheduled
- `completion_date` (DATE) - When maintenance was completed
- `cost` (DECIMAL 10,2) - Cost of maintenance
- `performed_by` (VARCHAR 100) - Who performed the work
- `status` (ENUM) - scheduled, in_progress, completed, cancelled
- `notes` (VARCHAR 500) - Additional notes

**Usage:** Administrators log all maintenance. System tracks when vehicles need servicing.

---

### 8. ALLOCATIONS TABLE
Records the assignment of vehicles and drivers to trips.

**Purpose:** Track who approved which vehicle-driver assignments

**Fields:**
- `id` (INT, Primary Key) - Unique allocation identifier
- `trip_id` (INT, Foreign Key, UNIQUE) - Which trip
- `vehicle_id` (INT, Foreign Key) - Which vehicle assigned
- `driver_id` (INT, Foreign Key) - Which driver assigned
- `allocated_by` (INT, Foreign Key) - Which administrator made the assignment
- `allocation_date` (DATETIME) - When assignment was made
- `notes` (VARCHAR 500) - Any notes about the allocation

**Usage:** Maintains audit trail of who assigned which resources to which trips.

---

### 10. NOTIFICATIONS TABLE
Stores system notifications for users.

**Purpose:** Notify users of request approvals, trip assignments, etc.

**Fields:**
- `id` (INT, Primary Key) - Unique notification identifier
- `recipient_id` (INT, Foreign Key) - Who receives the notification
- `sender_id` (INT, Foreign Key) - Who sent it (nullable)
- `notification_type` (VARCHAR 50) - Type: request_approved, trip_scheduled, etc.
- `title` (VARCHAR 200) - Notification title
- `message` (TEXT) - Notification message
- `related_entity_type` (VARCHAR 50) - What it relates to (request, trip, etc.)
- `related_entity_id` (INT) - ID of related item
- `is_read` (BOOLEAN) - Whether user has read it

**Usage:** Keep users informed about important events in the system.

---

### 11. AUDIT LOG TABLE
Records all significant system actions for security and accountability.

**Purpose:** Maintain complete audit trail of data changes

**Fields:**
- `id` (INT, Primary Key) - Unique log entry identifier
- `user_id` (INT, Foreign Key) - Which user performed the action
- `action` (VARCHAR 100) - What was done (created, updated, deleted)
- `entity_type` (VARCHAR 50) - What was acted upon (request, trip, etc.)
- `entity_id` (INT) - ID of the entity
- `old_values` (JSON) - Previous values (for updates)
- `new_values` (JSON) - New values (for updates)
- `ip_address` (VARCHAR 45) - IP address of requester
- `created_at` - When action occurred

**Usage:** Track who made what changes when for security and compliance.

---

## Database Relationships

### Key Relationships:

1. **users → drivers**: One user can be one driver (one-to-one)
2. **users → vehicle_requests**: One user can submit many requests (one-to-many)
3. **vehicle_requests → trips**: One request becomes one trip (one-to-one)
4. **trips → vehicles**: One trip uses one vehicle, vehicles used in many trips (many-to-one)
5. **trips → drivers**: One trip has one driver, drivers work many trips (many-to-one)
6. **vehicles → vehicle_maintenance**: One vehicle has many maintenance records (one-to-many)
7. **trips → trip_logs**: One trip has one guard log entry (one-to-one)
8. **trips → allocations**: One trip has one allocation (one-to-one)
9. **trips → routes**: One trip can have one route (one-to-one)

---

## Views Included

### 1. `pending_requests`
Shows all requests waiting for administrator approval. Useful for the dashboard to highlight work that needs attention.

### 2. `scheduled_trips`
Shows upcoming scheduled trips with vehicle, driver, and requester information. Useful for planning and coordination.

### 3. `maintenance_schedule`
Shows vehicles that need maintenance or have maintenance scheduled. Helps administrators plan maintenance windows.

### 4. `driver_availability`
Shows all drivers with their availability status and assigned vehicles. Useful when allocating drivers to new trips.

---

## How Data Flows Through the System

1. **Request Submission**: Faculty member submits a vehicle request → entry added to `vehicle_requests` table with status 'pending'

2. **Approval**: Administrator reviews request → updates status to 'approved' and fills `approved_by` and `approved_date` fields

3. **Trip Creation**: Administrator creates trip → new entry in `trips` table with vehicle and driver assignment

4. **Vehicle-Driver Assignment**: Administrator assigns driver and vehicle → entry added to `allocations` table

5. **Guard Logging**: When trip starts, guard logs departure → `trip_logs` table records departure time and vehicle condition

6. **Trip Execution**: Trip progresses normally

7. **Guard Return Logging**: When trip ends, guard logs return → `trip_logs` table records return time and final vehicle condition

8. **Trip Completion**: Administrator marks trip complete → updates `trips` table with actual times and mileage

9. **Maintenance Tracking**: Any maintenance performed gets logged → new entry in `vehicle_maintenance` table

---

## Indexes for Performance

Indexes have been created on commonly searched fields:
- `vehicle_requests.status` - For finding requests by status
- `vehicle_requests.trip_date` - For finding requests by date
- `trips.vehicle_id`, `trips.driver_id`, `trips.trip_date` - For trip searches
- `vehicle_maintenance.vehicle_id` and `vehicle_maintenance.maintenance_date` - For maintenance queries
- `drivers.user_id` - For driver lookups
- `notifications.recipient_id, notifications.is_read` - For notification queries
- `audit_logs.entity_type, audit_logs.entity_id` and `audit_logs.user_id` - For audit trail searches

---

## Data Validation Rules

### ENUM Fields and Their Values:

**users.role:**
- `administrator` - Can approve requests and manage system
- `driver` - Can view assigned trips
- `faculty` - Can submit requests
- `guard` - Can log trip departures/returns

**vehicles.status:**
- `available` - Ready for trips
- `in_use` - Currently on a trip
- `maintenance` - In maintenance, not available
- `retired` - No longer in use

**vehicle_requests.status:**
- `pending` - Awaiting approval
- `approved` - Ready for trip creation
- `rejected` - Not approved
- `cancelled` - Requester cancelled

**trips.trip_status:**
- `scheduled` - Trip is scheduled but not started
- `in_progress` - Trip is currently happening
- `completed` - Trip has finished
- `cancelled` - Trip was cancelled

**vehicle_maintenance.maintenance_type:**
- `oil_change` - Regular oil service
- `repair` - General repairs
- `refueling` - Fuel up tank
- `inspection` - Safety inspection
- `tire_service` - Tire maintenance
- `other` - Other services

---

## Best Practices

1. **Always hash passwords** when inserting into users table using bcrypt or similar
2. **Use timestamps** for all date/time operations to ensure consistency
3. **Foreign keys are enforced** - deleting a vehicle won't work if it's assigned to a driver
4. **Audit log everything** - especially request approvals and allocations
5. **Keep maintenance records complete** - these help with vehicle upkeep decisions
6. **Use the views** for common queries instead of writing complex joins every time

---

## Sample Queries

### Get all pending requests:
```sql
SELECT * FROM pending_requests ORDER BY request_date DESC;
```

### Get today's scheduled trips:
```sql
SELECT * FROM scheduled_trips WHERE trip_date = CURDATE();
```

### Get vehicles needing maintenance:
```sql
SELECT * FROM maintenance_schedule WHERE status != 'completed';
```

### Find available drivers:
```sql
SELECT * FROM driver_availability WHERE is_available = TRUE;
```

### Check request approval history:
```sql
SELECT vr.*, CONCAT(u.first_name, ' ', u.last_name) as approved_by_name
FROM vehicle_requests vr
LEFT JOIN users u ON vr.approved_by = u.id
WHERE vr.status IN ('approved', 'rejected')
ORDER BY vr.approved_date DESC;
```



## 12. POST TRAVEL REPORTS TABLE
Stores the post-trip information from the NVSU Post Travel Report form.

**Key fields:** `vehicle_id`, `trip_id`, travel dates, places of travel, defects observed, defects incurred, remarks, drivers, and arrival time.

## 13. FUEL USAGE RECORDS TABLE
Stores gasoline/diesoline and related fluid usage recorded after a trip.

**Key fields:** `vehicle_id`, `trip_id`, record date, balance in tank, issuance from stock, fuel purchased, fuel used, end-trip balance, RIV/OR numbers and dates, lubricating oil, diesel/water, gear oil, brake fluid, flushing oil, grease, and drivers.

## 14. PREVENTIVE MAINTENANCE CHECKLISTS TABLE
Stores the monthly preventive maintenance inspection represented by the NVSU checklist.

**Key fields:** vehicle, PMUV number, inspection date, inspector/mechanic, mileage, previous service dates, condition ratings (Excellent/Good/Poor) for vehicle parts, other-parts notes, remarks, and supervisor recommendations.

### Backend API Endpoints

- `GET/POST /api/post-travel-reports`
- `PATCH /api/post-travel-reports/{id}`
- `GET/POST /api/fuel-usage-records`
- `PATCH /api/fuel-usage-records/{id}`
- `GET/POST /api/preventive-maintenance-checklists`
- `PATCH /api/preventive-maintenance-checklists/{id}`

These tables are linked to the existing `vehicles` table and, where applicable, the existing `trips` table. They are intended as backend data structures; frontend forms can be built on top of these APIs later.
