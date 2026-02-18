# Filament Blueprint — SaaS Asset Inventory Management System
# Implementation Plan for FilamentPHP v4

**Source Documents**: [PROJECT_SPEC_SAAS.md](PROJECT_SPEC_SAAS.md), [PRD_SAAS.md](PRD_SAAS.md)

---

## Context

This document is a **Filament Blueprint** — a detailed, unambiguous implementation specification that maps every domain concept, user flow, and state transition to concrete FilamentPHP v4 primitives. It serves as the single source of truth for building the SaaS Asset Inventory Management System.

**Tech Stack**: Laravel 11+, PHP 8.3+, FilamentPHP v4, PostgreSQL, Redis, S3/MinIO
**Multi-Tenancy**: Filament built-in tenancy via `->tenant(Organization::class)` + single-database `organization_id` isolation
**Panels**: Two — System Admin (`/system`) and Tenant App (`/app`)

---

## SECTION A: DOMAIN MODELS

> Convention: every tenant-scoped model uses the `BelongsToOrganization` trait which registers a `OrganizationScope` global scope and auto-sets `organization_id` on creating.

---

### A.1 Organization (Tenant)

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| name | `string` | |
| slug | `string` unique | Used in URL |
| owner_id | `foreignId` -> users | |
| plan_id | `foreignId` -> plans | nullable |
| logo_path | `string` | nullable, S3 |
| address | `text` | nullable |
| phone | `string` | nullable |
| settings | `json` | `array` cast, default `{}` |
| created_at / updated_at | timestamps | |

**Relationships**:
- `owner()` — belongsTo User
- `users()` — hasMany User
- `plan()` — belongsTo Plan
- `subscription()` — hasOne Subscription
- `assets()` — hasMany Asset
- `departments()` — hasMany Department
- `locations()` — hasMany Location

**Traits**: HasFactory, SoftDeletes
**Implements**: `HasCurrentTenantLabel` (Filament tenancy)

---

### A.2 User

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | nullable (super admins have null) |
| name | `string` | |
| email | `string` | unique per org |
| password | `string` | hashed |
| role | `string` | cast to `UserRole` enum |
| department_id | `foreignId` | nullable |
| phone | `string` | nullable |
| avatar_path | `string` | nullable |
| is_active | `boolean` | default true |
| email_verified_at | `timestamp` | nullable |
| two_factor_secret | `text` | nullable, encrypted |
| two_factor_confirmed_at | `timestamp` | nullable |
| last_login_at | `timestamp` | nullable |
| created_at / updated_at | timestamps | |

**Unique Index**: `(organization_id, email)`
**Relationships**:
- `organization()` — belongsTo Organization
- `department()` — belongsTo Department
- `assignments()` — hasMany Assignment
- `inventoryTasks()` — hasMany InventoryTask
- `maintenanceTickets()` — hasMany MaintenanceTicket (as technician)
- `auditLogs()` — hasMany AuditLog

**Traits**: HasFactory, SoftDeletes, BelongsToOrganization, Notifiable, HasApiTokens (Sanctum)
**Implements**: `FilamentUser`, `HasTenants` (Filament multi-tenancy)

---

### A.3 AssetCategory

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| parent_id | `foreignId` | nullable, self-ref for subcategories |
| name | `string` | |
| description | `text` | nullable |
| icon | `string` | nullable, icon picker |
| custom_fields_schema | `json` | `array` cast — defines the custom fields for this category |
| depreciation_method | `string` | nullable, cast `DepreciationMethod` enum |
| default_useful_life_months | `integer` | nullable |
| sort_order | `integer` | default 0 |
| created_at / updated_at | timestamps | |

**Unique Index**: `(organization_id, name, parent_id)`
**Relationships**:
- `parent()` — belongsTo AssetCategory
- `children()` — hasMany AssetCategory
- `assets()` — hasMany Asset
- `customFields()` — hasMany CustomField

**Traits**: HasFactory, BelongsToOrganization, SoftDeletes

---

### A.4 Manufacturer

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| name | `string` | |
| website | `string` | nullable |
| support_email | `string` | nullable |
| support_phone | `string` | nullable |
| notes | `text` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `assetModels()` — hasMany AssetModel
- `assets()` — hasManyThrough Asset, AssetModel

**Traits**: HasFactory, BelongsToOrganization

---

### A.5 AssetModel

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| manufacturer_id | `foreignId` | |
| category_id | `foreignId` -> asset_categories | |
| name | `string` | |
| model_number | `string` | nullable |
| description | `text` | nullable |
| image_path | `string` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `manufacturer()` — belongsTo Manufacturer
- `category()` — belongsTo AssetCategory
- `assets()` — hasMany Asset

**Traits**: HasFactory, BelongsToOrganization

---

### A.6 Location

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| parent_id | `foreignId` | nullable, self-ref for hierarchy |
| name | `string` | |
| address | `text` | nullable |
| city | `string` | nullable |
| country | `string` | nullable |
| coordinates | `json` | nullable, `{lat, lng}` |
| contact_person | `string` | nullable |
| contact_phone | `string` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `parent()` — belongsTo Location
- `children()` — hasMany Location
- `assets()` — hasMany Asset
- `departments()` — hasMany Department
- `assignments()` — hasMany Assignment (assigned_to location)

**Traits**: HasFactory, BelongsToOrganization, SoftDeletes

---

### A.7 Department

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| parent_id | `foreignId` | nullable, self-ref for hierarchy |
| location_id | `foreignId` | nullable |
| name | `string` | |
| manager_id | `foreignId` -> users | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `parent()` — belongsTo Department
- `children()` — hasMany Department
- `location()` — belongsTo Location
- `manager()` — belongsTo User
- `users()` — hasMany User
- `assignments()` — hasMany Assignment (assigned_to department)

**Traits**: HasFactory, BelongsToOrganization

---

### A.8 Asset ★ (Core Model)

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| asset_code | `string` | auto-generated, unique per org, e.g. `AST-00001` |
| name | `string` | |
| category_id | `foreignId` -> asset_categories | |
| model_id | `foreignId` -> asset_models | nullable |
| location_id | `foreignId` -> locations | |
| department_id | `foreignId` -> departments | nullable |
| serial_number | `string` | nullable |
| sku | `string` | nullable |
| status | `string` | cast `AssetStatus` enum, default `available` |
| purchase_date | `date` | nullable |
| purchase_cost | `decimal(12,2)` | nullable |
| vendor_id | `foreignId` | nullable |
| warranty_expiry | `date` | nullable |
| depreciation_method | `string` | nullable, cast `DepreciationMethod` |
| useful_life_months | `integer` | nullable |
| salvage_value | `decimal(12,2)` | nullable |
| retirement_date | `date` | nullable |
| barcode | `string` | auto-generated, unique per org |
| qr_code_path | `string` | nullable, stored file |
| notes | `text` | nullable |
| custom_field_values | `json` | `array` cast — values for category custom fields |
| created_at / updated_at | timestamps | |
| deleted_at | `softDelete` | |

**Unique Indexes**: `(organization_id, asset_code)`, `(organization_id, serial_number)` where not null, `(organization_id, barcode)`
**Relationships**:
- `category()` — belongsTo AssetCategory
- `model()` — belongsTo AssetModel
- `location()` — belongsTo Location
- `department()` — belongsTo Department
- `vendor()` — belongsTo Vendor (purchase vendor)
- `assignments()` — hasMany Assignment
- `currentAssignment()` — hasOne Assignment (where returned_at is null)
- `maintenanceTickets()` — hasMany MaintenanceTicket
- `preventiveSchedules()` — hasMany PreventiveMaintenanceSchedule
- `images()` — hasMany AssetImage
- `tags()` — morphToMany Tag
- `licenses()` — belongsToMany License via license_assignments
- `statusHistory()` — hasMany AssetStatusHistory
- `contracts()` — belongsToMany Contract via asset_contract pivot

**Traits**: HasFactory, BelongsToOrganization, SoftDeletes
**Boot logic**: auto-generate `asset_code` and `barcode` on creating

---

### A.9 AssetImage

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| asset_id | `foreignId` | |
| file_path | `string` | S3 path |
| caption | `string` | nullable |
| is_primary | `boolean` | default false |
| sort_order | `integer` | default 0 |
| created_at / updated_at | timestamps | |

**Relationships**:
- `asset()` — belongsTo Asset

**Traits**: HasFactory, BelongsToOrganization

---

### A.10 AssetTag

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| name | `string` | |
| type | `string` | nullable (e.g. `barcode`, `qr_code`, `nfc`, `custom`) |
| color | `string` | nullable, hex |
| created_at / updated_at | timestamps | |

**Relationships**:
- `assets()` — morphedByMany Asset

**Traits**: HasFactory, BelongsToOrganization

---

### A.11 CustomField

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| category_id | `foreignId` -> asset_categories | |
| name | `string` | |
| field_key | `string` | slug, used as JSON key |
| field_type | `string` | `text`, `number`, `date`, `select`, `boolean`, `url` |
| options | `json` | nullable, for select type |
| is_required | `boolean` | default false |
| sort_order | `integer` | default 0 |
| created_at / updated_at | timestamps | |

**Relationships**:
- `category()` — belongsTo AssetCategory

**Traits**: HasFactory, BelongsToOrganization

---

### A.12 AssetStatusHistory

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| asset_id | `foreignId` | |
| from_status | `string` | nullable, cast `AssetStatus` |
| to_status | `string` | cast `AssetStatus` |
| changed_by | `foreignId` -> users | |
| reason | `text` | nullable |
| created_at | timestamp | |

**Relationships**:
- `asset()` — belongsTo Asset
- `user()` — belongsTo User

**Traits**: BelongsToOrganization

---

### A.13 Assignment

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| asset_id | `foreignId` | |
| assignee_type | `string` | `user`, `department`, `location` — polymorphic |
| assignee_id | `ulid` | polymorphic |
| assigned_by | `foreignId` -> users | |
| assigned_at | `datetime` | |
| expected_return_at | `date` | nullable |
| returned_at | `datetime` | nullable |
| return_condition | `text` | nullable |
| return_accepted_by | `foreignId` -> users | nullable |
| notes | `text` | nullable |
| signature_path | `string` | nullable, S3 |
| created_at / updated_at | timestamps | |

**Relationships**:
- `asset()` — belongsTo Asset
- `assignee()` — morphTo (User, Department, Location)
- `assignedBy()` — belongsTo User
- `returnAcceptedBy()` — belongsTo User

**Scopes**:
- `active()` — where `returned_at` is null
- `returned()` — where `returned_at` is not null

**Traits**: HasFactory, BelongsToOrganization

---

### A.14 MaintenanceTicket

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| ticket_number | `string` | auto-generated `MT-00001` |
| asset_id | `foreignId` | |
| reported_by | `foreignId` -> users | |
| technician_id | `foreignId` -> users | nullable |
| title | `string` | |
| description | `text` | |
| priority | `string` | cast `MaintenancePriority` enum |
| status | `string` | cast `MaintenanceStatus` enum, default `open` |
| started_at | `datetime` | nullable |
| resolved_at | `datetime` | nullable |
| resolution_notes | `text` | nullable |
| total_cost | `decimal(12,2)` | default 0, computed |
| created_at / updated_at | timestamps | |

**Relationships**:
- `asset()` — belongsTo Asset
- `reporter()` — belongsTo User
- `technician()` — belongsTo User
- `costs()` — hasMany MaintenanceCost
- `attachments()` — morphMany Attachment

**Traits**: HasFactory, BelongsToOrganization

---

### A.15 MaintenanceCost

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| ticket_id | `foreignId` -> maintenance_tickets | |
| description | `string` | |
| amount | `decimal(12,2)` | |
| cost_type | `string` | `labor`, `parts`, `external`, `other` |
| incurred_at | `date` | |
| created_at / updated_at | timestamps | |

**Relationships**:
- `ticket()` — belongsTo MaintenanceTicket

**Traits**: HasFactory, BelongsToOrganization

---

### A.16 PreventiveMaintenanceSchedule

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| asset_id | `foreignId` | |
| title | `string` | |
| description | `text` | nullable |
| frequency_days | `integer` | interval in days |
| last_performed_at | `date` | nullable |
| next_due_at | `date` | computed |
| technician_id | `foreignId` -> users | nullable |
| is_active | `boolean` | default true |
| created_at / updated_at | timestamps | |

**Relationships**:
- `asset()` — belongsTo Asset
- `technician()` — belongsTo User
- `tickets()` — hasMany MaintenanceTicket (generated from this schedule)

**Traits**: HasFactory, BelongsToOrganization

---

### A.17 Vendor

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| name | `string` | |
| contact_name | `string` | nullable |
| email | `string` | nullable |
| phone | `string` | nullable |
| website | `string` | nullable |
| address | `text` | nullable |
| notes | `text` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `assets()` — hasMany Asset (purchased from)
- `licenses()` — hasMany License
- `contracts()` — hasMany Contract

**Traits**: HasFactory, BelongsToOrganization, SoftDeletes

---

### A.18 Contract

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| vendor_id | `foreignId` | |
| contract_number | `string` | nullable |
| type | `string` | cast `ContractType` enum |
| title | `string` | |
| start_date | `date` | |
| end_date | `date` | |
| value | `decimal(12,2)` | nullable |
| renewal_date | `date` | nullable |
| auto_renew | `boolean` | default false |
| document_path | `string` | nullable, S3 |
| notes | `text` | nullable |
| created_at / updated_at | timestamps | |

**Pivot**: `asset_contract` (asset_id, contract_id)
**Relationships**:
- `vendor()` — belongsTo Vendor
- `assets()` — belongsToMany Asset

**Traits**: HasFactory, BelongsToOrganization

---

### A.19 License

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| name | `string` | |
| license_key | `string` | encrypted |
| license_type | `string` | cast `LicenseType` enum |
| vendor_id | `foreignId` | nullable |
| total_seats | `integer` | |
| expiry_date | `date` | nullable |
| purchase_date | `date` | nullable |
| purchase_cost | `decimal(12,2)` | nullable |
| notes | `text` | nullable |
| created_at / updated_at | timestamps | |

**Computed**: `used_seats` (count of active LicenseAssignments), `available_seats` (total - used)
**Relationships**:
- `vendor()` — belongsTo Vendor
- `assignments()` — hasMany LicenseAssignment
- `assets()` — belongsToMany Asset via license_assignments

**Traits**: HasFactory, BelongsToOrganization

---

### A.20 LicenseAssignment

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| license_id | `foreignId` | |
| asset_id | `foreignId` | |
| assigned_by | `foreignId` -> users | |
| assigned_at | `date` | |
| revoked_at | `date` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `license()` — belongsTo License
- `asset()` — belongsTo Asset
- `assignedBy()` — belongsTo User

**Traits**: HasFactory, BelongsToOrganization

---

### A.21 InventorySession

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| name | `string` | |
| description | `text` | nullable |
| status | `string` | cast `InventorySessionStatus` enum, default `draft` |
| scope_type | `string` | `all`, `location`, `category`, `department` |
| scope_ids | `json` | `array` cast — IDs of scoped entities |
| created_by | `foreignId` -> users | |
| started_at | `datetime` | nullable |
| completed_at | `datetime` | nullable |
| total_expected | `integer` | computed on start |
| total_scanned | `integer` | computed |
| total_matched | `integer` | computed |
| total_missing | `integer` | computed |
| total_unexpected | `integer` | computed |
| created_at / updated_at | timestamps | |

**Relationships**:
- `creator()` — belongsTo User
- `tasks()` — hasMany InventoryTask
- `items()` — hasMany InventoryItem

**Traits**: HasFactory, BelongsToOrganization

---

### A.22 InventoryTask

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| session_id | `foreignId` -> inventory_sessions | |
| assigned_to | `foreignId` -> users | |
| location_id | `foreignId` -> locations | nullable |
| status | `string` | `pending`, `in_progress`, `completed` |
| started_at | `datetime` | nullable |
| completed_at | `datetime` | nullable |
| notes | `text` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `session()` — belongsTo InventorySession
- `assignee()` — belongsTo User
- `location()` — belongsTo Location
- `items()` — hasMany InventoryItem

**Traits**: HasFactory, BelongsToOrganization

---

### A.23 InventoryItem

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| session_id | `foreignId` -> inventory_sessions | |
| task_id | `foreignId` -> inventory_tasks | nullable |
| asset_id | `foreignId` | nullable (null for unexpected items) |
| status | `string` | cast `InventoryItemStatus` enum |
| scanned_at | `datetime` | nullable |
| scanned_by | `foreignId` -> users | nullable |
| condition_notes | `text` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `session()` — belongsTo InventorySession
- `task()` — belongsTo InventoryTask
- `asset()` — belongsTo Asset
- `scanner()` — belongsTo User

**Traits**: HasFactory, BelongsToOrganization

---

### A.24 Attachment (Polymorphic)

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| attachable_type | `string` | polymorphic |
| attachable_id | `ulid` | polymorphic |
| file_path | `string` | S3 |
| file_name | `string` | original name |
| file_size | `integer` | bytes |
| mime_type | `string` | |
| uploaded_by | `foreignId` -> users | |
| created_at / updated_at | timestamps | |

**Relationships**:
- `attachable()` — morphTo
- `uploader()` — belongsTo User

**Traits**: HasFactory, BelongsToOrganization

---

### A.25 AuditLog

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | nullable (null for system actions) |
| user_id | `foreignId` | nullable |
| auditable_type | `string` | polymorphic |
| auditable_id | `ulid` | polymorphic |
| event | `string` | `created`, `updated`, `deleted`, `status_changed`, `assigned`, `returned` |
| old_values | `json` | nullable |
| new_values | `json` | nullable |
| ip_address | `string` | nullable |
| user_agent | `string` | nullable |
| created_at | timestamp | |

**Relationships**:
- `user()` — belongsTo User
- `auditable()` — morphTo

**Note**: NOT tenant-scoped via trait — uses explicit organization_id for system-level queries.

---

### A.26 NotificationRule

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| event_type | `string` | `warranty_expiry`, `license_expiry`, `maintenance_due`, `contract_renewal`, `assignment` |
| days_before | `integer` | nullable, for expiry-type events |
| channels | `json` | `array` cast, e.g. `["mail", "database"]` |
| recipients_type | `string` | `role`, `user`, `assignee` |
| recipients_value | `json` | `array` cast |
| is_active | `boolean` | default true |
| created_at / updated_at | timestamps | |

**Traits**: HasFactory, BelongsToOrganization

---

### A.27 Plan (Global — no tenant_id)

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| name | `string` | |
| slug | `string` | unique |
| max_users | `integer` | -1 = unlimited |
| max_assets | `integer` | -1 = unlimited |
| features | `json` | `array` cast, feature flags |
| price_monthly | `decimal(8,2)` | |
| price_yearly | `decimal(8,2)` | nullable |
| is_active | `boolean` | default true |
| sort_order | `integer` | default 0 |
| created_at / updated_at | timestamps | |

**Relationships**:
- `subscriptions()` — hasMany Subscription
- `organizations()` — hasManyThrough Organization, Subscription

---

### A.28 Subscription (Global — no tenant_id)

| Attribute | Type | Cast/Notes |
|-----------|------|------------|
| id | `ulid` PK | |
| organization_id | `foreignId` | |
| plan_id | `foreignId` | |
| status | `string` | cast `SubscriptionStatus` enum |
| trial_ends_at | `date` | nullable |
| starts_at | `date` | |
| ends_at | `date` | nullable |
| cancelled_at | `date` | nullable |
| payment_provider | `string` | nullable |
| payment_provider_id | `string` | nullable |
| created_at / updated_at | timestamps | |

**Relationships**:
- `organization()` — belongsTo Organization
- `plan()` — belongsTo Plan

---

## SECTION B: ENUMS

All enums are PHP 8.3 backed string enums implementing `HasLabel` (Filament), `HasColor`, `HasIcon` for automatic Filament integration.

### AssetStatus
```
available      -> label: "Available",        color: success,  icon: heroicon-o-check-circle
assigned       -> label: "Assigned",         color: info,     icon: heroicon-o-user
under_maintenance -> label: "Under Maintenance", color: warning,  icon: heroicon-o-wrench
retired        -> label: "Retired",          color: gray,     icon: heroicon-o-archive-box
lost_stolen    -> label: "Lost/Stolen",      color: danger,   icon: heroicon-o-exclamation-triangle
disposed       -> label: "Disposed",         color: gray,     icon: heroicon-o-trash
```

### MaintenanceStatus
```
open           -> label: "Open",             color: danger,   icon: heroicon-o-exclamation-circle
in_progress    -> label: "In Progress",      color: warning,  icon: heroicon-o-arrow-path
on_hold        -> label: "On Hold",          color: gray,     icon: heroicon-o-pause-circle
resolved       -> label: "Resolved",         color: success,  icon: heroicon-o-check-circle
closed         -> label: "Closed",           color: gray,     icon: heroicon-o-x-circle
```

### MaintenancePriority
```
low            -> label: "Low",              color: info,     icon: heroicon-o-arrow-down
medium         -> label: "Medium",           color: warning,  icon: heroicon-o-minus
high           -> label: "High",             color: danger,   icon: heroicon-o-arrow-up
critical       -> label: "Critical",         color: danger,   icon: heroicon-o-fire
```

### InventorySessionStatus
```
draft          -> label: "Draft",            color: gray,     icon: heroicon-o-document
in_progress    -> label: "In Progress",      color: warning,  icon: heroicon-o-play
completed      -> label: "Completed",        color: success,  icon: heroicon-o-check
cancelled      -> label: "Cancelled",        color: danger,   icon: heroicon-o-x-mark
```

### InventoryItemStatus
```
expected       -> label: "Expected",         color: gray,     icon: heroicon-o-clock
found          -> label: "Found",            color: success,  icon: heroicon-o-check
missing        -> label: "Missing",          color: danger,   icon: heroicon-o-x-mark
unexpected     -> label: "Unexpected",       color: warning,  icon: heroicon-o-question-mark-circle
```

### LicenseType
```
perpetual      -> label: "Perpetual"
subscription   -> label: "Subscription"
open_source    -> label: "Open Source"
oem            -> label: "OEM"
trial          -> label: "Trial"
```

### ContractType
```
warranty       -> label: "Warranty"
service        -> label: "Service Agreement"
lease          -> label: "Lease"
maintenance    -> label: "Maintenance"
support        -> label: "Support"
```

### DepreciationMethod
```
straight_line  -> label: "Straight Line"
declining_balance -> label: "Declining Balance"
none           -> label: "No Depreciation"
```

### UserRole
```
super_admin    -> label: "Super Admin"    (system-level only)
admin          -> label: "Admin"
manager        -> label: "Manager"
technician     -> label: "Technician"
user           -> label: "User"
```

### SubscriptionStatus
```
active         -> label: "Active",          color: success
trial          -> label: "Trial",           color: info
past_due       -> label: "Past Due",        color: warning
expired        -> label: "Expired",         color: danger
cancelled      -> label: "Cancelled",       color: gray
```

---

## SECTION C: FILAMENT PANELS

### Panel 1: System Admin Panel

**Path**: `/system`
**Guard**: `web` with `super_admin` role gate
**Tenant**: NONE (global)
**Purpose**: Platform-wide management for the SaaS operator

**Resources**:
- OrganizationResource — manage all tenants
- PlanResource — manage subscription plans
- SubscriptionResource — manage all subscriptions
- SystemUserResource — manage super admin users

**Pages**:
- SystemDashboard (default)

**Widgets** (on SystemDashboard):
- TotalOrganizationsWidget (StatsOverview)
- PlanDistributionChart (Chart - doughnut)
- TotalAssetsAcrossTenantsWidget (StatsOverview)
- RecentRegistrationsWidget (Table)
- ExpiringSubscriptionsWidget (Table)

---

### Panel 2: Tenant App Panel

**Path**: `/app`
**Guard**: `web`
**Tenant**: `Organization::class` via `->tenant(Organization::class)`
**Purpose**: Day-to-day operations for organization users

**Resources** (grouped by navigation):

*Asset Management*:
- AssetResource — `heroicon-o-cube`
- AssetCategoryResource — `heroicon-o-tag`
- AssetModelResource — `heroicon-o-squares-2x2`
- ManufacturerResource — `heroicon-o-building-office`

*Organization*:
- LocationResource — `heroicon-o-map-pin`
- DepartmentResource — `heroicon-o-building-office-2`
- UserResource — `heroicon-o-users`
- TagResource — `heroicon-o-hashtag`

*Assignments*:
- AssignmentResource — `heroicon-o-arrow-right-circle`

*Maintenance*:
- MaintenanceTicketResource — `heroicon-o-wrench-screwdriver`
- PreventiveMaintenanceResource — `heroicon-o-calendar`

*Licenses*:
- LicenseResource — `heroicon-o-key`

*Vendors & Contracts*:
- VendorResource — `heroicon-o-truck`
- ContractResource — `heroicon-o-document-text`

*Inventory*:
- InventorySessionResource — `heroicon-o-clipboard-document-check`

*System*:
- AuditLogResource (view-only) — `heroicon-o-eye`
- NotificationRuleResource — `heroicon-o-bell`

**Custom Pages**:
- TenantDashboard (default)
- InventoryExecutionPage
- ReportsPage
- AssetScannerPage
- ApiTokensPage

---

## SECTION D: FILAMENT RESOURCES

### D.1 AssetResource ★ (Tenant Panel)

**Navigation**: Group "Asset Management", icon `heroicon-o-cube`, sort 1

**Form Schema** (Create / Edit):
```
Tabs:
  Tab "General":
    Section "Basic Information":
      - TextInput::name (required, max:255)
      - Select::category_id (required, relationship, reactive — loads custom fields)
      - Select::model_id (nullable, relationship, depends on category)
      - Select::location_id (required, relationship, searchable)
      - Select::department_id (nullable, relationship)
      - Select::status (AssetStatus enum, only on edit)

    Section "Identification":
      - TextInput::asset_code (disabled on edit, auto-generated on create)
      - TextInput::serial_number (nullable, unique per org)
      - TextInput::sku (nullable)
      - TextInput::barcode (disabled, auto-generated)
      - TagsInput::tags (morphToMany)

  Tab "Financial":
    Section "Purchase":
      - Select::vendor_id (nullable, relationship, searchable)
      - DatePicker::purchase_date
      - TextInput::purchase_cost (numeric, prefix: $)
    Section "Warranty":
      - DatePicker::warranty_expiry
    Section "Depreciation":
      - Select::depreciation_method (DepreciationMethod enum)
      - TextInput::useful_life_months (numeric)
      - TextInput::salvage_value (numeric, prefix: $)

  Tab "Custom Fields":
    - Dynamic fields rendered from category.custom_fields_schema
    - KeyValue or Repeater based on field definitions

  Tab "Images":
    - SpatieMediaLibraryFileUpload or Repeater of FileUpload::images (multiple, S3, image, reorderable)

  Tab "Notes":
    - RichEditor::notes
```

**Table Schema** (List):
```
Columns:
  - ImageColumn::primary_image (circular, from first AssetImage)
  - TextColumn::asset_code (searchable, sortable, copyable)
  - TextColumn::name (searchable, sortable, wrap)
  - TextColumn::category.name (sortable, badge)
  - TextColumn::location.name (sortable)
  - TextColumn::status (badge with color/icon from enum)
  - TextColumn::serial_number (searchable, toggleable)
  - TextColumn::currentAssignment.assignee.name (label: "Assigned To")
  - TextColumn::purchase_date (date, sortable, toggleable)
  - TextColumn::warranty_expiry (date, sortable, color: danger if past)
  - TextColumn::created_at (since, toggleable, hidden by default)

Default Sort: created_at desc

Filters:
  - SelectFilter::status (AssetStatus enum, multiple)
  - SelectFilter::category_id (relationship)
  - SelectFilter::location_id (relationship)
  - SelectFilter::department_id (relationship)
  - SelectFilter::vendor_id (relationship)
  - TernaryFilter::has_warranty (warranty_expiry not null)
  - Filter::warranty_expiring_soon (warranty_expiry within 30 days)
  - TrashedFilter (soft deletes)

Searchable: asset_code, name, serial_number, sku, barcode
```

**Table Actions**:
- `ViewAction` — opens View page
- `EditAction` — opens Edit page
- `CheckOutAction` (custom) — modal: assign to user/dept/location (see Actions catalog)
- `CheckInAction` (custom) — modal: return asset (see Actions catalog)
- `DeleteAction` — soft delete
- `RestoreAction` — restore from trash
- `ForceDeleteAction`

**Bulk Actions**:
- `BulkChangeStatusAction` — modal with status select
- `BulkReassignAction` — modal to assign to new location/department
- `BulkDeleteAction`
- `ExportBulkAction` — CSV/Excel export of selected rows

**Header Actions**:
- `CreateAction`
- `ImportAction` — CSV/Excel import modal (see Actions catalog)

**Relation Managers** (on Edit/View page):
- `AssignmentsRelationManager`
- `MaintenanceTicketsRelationManager`
- `ImagesRelationManager`
- `LicensesRelationManager`
- `StatusHistoryRelationManager`
- `ContractsRelationManager`

**Infolist Schema** (View page):
```
Tabs:
  Tab "Overview":
    Section "Details":
      - TextEntry::asset_code (copyable, badge)
      - TextEntry::name
      - TextEntry::category.name
      - TextEntry::model.name
      - TextEntry::status (badge)
      - TextEntry::location.name
      - TextEntry::department.name
      - TextEntry::serial_number (copyable)
      - TextEntry::barcode (copyable)
    Section "Current Assignment":
      - TextEntry::currentAssignment.assignee.name
      - TextEntry::currentAssignment.assigned_at
      - TextEntry::currentAssignment.expected_return_at
    Section "QR Code":
      - ImageEntry::qr_code (generated on the fly or stored)
  Tab "Financial":
      - TextEntry::purchase_cost (money)
      - TextEntry::purchase_date
      - TextEntry::vendor.name
      - TextEntry::warranty_expiry (color: danger if expired)
      - TextEntry::depreciation_method
      - TextEntry::current_book_value (computed accessor)
  Tab "Images":
      - RepeatableEntry of ImageEntry
  Tab "History":
      - Relation: StatusHistoryRelationManager
      - Relation: AssignmentsRelationManager
```

**Page-Level Actions** (View page header):
- `CheckOutAction` (if status = available)
- `CheckInAction` (if status = assigned)
- `SendToMaintenanceAction` (changes status to under_maintenance, creates ticket)
- `RetireAction` (changes status to retired)
- `GenerateQrAction` (generates & stores QR code)
- `PrintLabelAction` (opens print-ready view)
- `EditAction`

---

### D.2 AssetCategoryResource (Tenant Panel)

**Navigation**: Group "Asset Management", icon `heroicon-o-tag`

**Form Schema**:
```
Section "Category Details":
  - TextInput::name (required)
  - Textarea::description
  - Select::parent_id (self-relationship, nullable, searchable)
  - IconPicker::icon (nullable)
Section "Depreciation Defaults":
  - Select::depreciation_method (enum)
  - TextInput::default_useful_life_months (numeric)
Section "Custom Fields":
  - Repeater::customFields:
    - TextInput::name
    - TextInput::field_key (auto-slug from name)
    - Select::field_type (text, number, date, select, boolean, url)
    - TagsInput::options (visible when field_type = select)
    - Toggle::is_required
    - TextInput::sort_order (numeric)
```

**Table Schema**:
```
Columns:
  - TextColumn::name (searchable)
  - TextColumn::parent.name
  - TextColumn::assets_count (counts)
  - TextColumn::custom_fields_count (count of customFields)
Actions: View, Edit, Delete
```

**Relation Managers**: `CustomFieldsRelationManager`, `AssetsRelationManager` (simple table)

---

### D.3 ManufacturerResource (Tenant Panel)

**Navigation**: Group "Asset Management", icon `heroicon-o-building-office`

**Form**: name, website, support_email, support_phone, notes
**Table**: name, website, asset_models_count, assets_count
**Relation Managers**: `AssetModelsRelationManager`

---

### D.4 AssetModelResource (Tenant Panel)

**Navigation**: Group "Asset Management", icon `heroicon-o-squares-2x2`

**Form**: name, model_number, manufacturer_id (Select, relationship), category_id (Select), description, image (FileUpload)
**Table**: name, model_number, manufacturer.name, category.name, assets_count
**Relation Managers**: `AssetsRelationManager`

---

### D.5 LocationResource (Tenant Panel)

**Navigation**: Group "Organization", icon `heroicon-o-map-pin`

**Form**: name, parent_id (tree select), address, city, country, contact_person, contact_phone
**Table**: name (with tree indentation), parent.name, address, assets_count, departments_count
**Relation Managers**: `AssetsRelationManager`, `DepartmentsRelationManager`

---

### D.6 DepartmentResource (Tenant Panel)

**Navigation**: Group "Organization", icon `heroicon-o-building-office-2`

**Form**: name, parent_id, location_id, manager_id (Select user)
**Table**: name, parent.name, location.name, manager.name, users_count
**Relation Managers**: `UsersRelationManager`, `AssetsRelationManager` (via assignments)

---

### D.7 UserResource (Tenant Panel)

**Navigation**: Group "Organization", icon `heroicon-o-users`

**Form Schema**:
```
Section "Personal":
  - TextInput::name (required)
  - TextInput::email (required, unique per org)
  - TextInput::phone
  - FileUpload::avatar_path (avatar, S3)
Section "Organization":
  - Select::role (UserRole enum, except super_admin)
  - Select::department_id (relationship)
  - Toggle::is_active
Section "Security" (create only):
  - TextInput::password (password, confirmed)
```

**Table**: name, email, role (badge), department.name, is_active (icon), last_login_at
**Actions**: Edit, Deactivate/Activate (toggle), ResetPassword, Delete
**Relation Managers**: `AssignmentsRelationManager` (assets assigned to this user)

---

### D.8 AssignmentResource (Tenant Panel)

**Navigation**: Group "Assignments", icon `heroicon-o-arrow-right-circle`

**Form Schema**:
```
Section "Assignment":
  - Select::asset_id (required, relationship, searchable, only available assets)
  - Select::assignee_type (radio: user, department, location)
  - Select::assignee_id (dynamic based on assignee_type, searchable)
  - DateTimePicker::assigned_at (default now)
  - DatePicker::expected_return_at (nullable)
  - Textarea::notes
  - FileUpload::signature_path (nullable, S3)
```

**Table**:
```
Columns:
  - TextColumn::asset.name (with asset_code)
  - TextColumn::assignee.name (polymorphic)
  - TextColumn::assignee_type (badge)
  - TextColumn::assigned_at (datetime)
  - TextColumn::expected_return_at (date, color: danger if overdue)
  - TextColumn::returned_at (datetime, placeholder: "Active")
  - TextColumn::assignedBy.name

Filters:
  - TernaryFilter::is_active (returned_at null)
  - SelectFilter::assignee_type
  - Filter::overdue (expected_return_at < now AND returned_at is null)

Default sort: assigned_at desc
```

**Actions**: View, CheckInAction (return), Delete (only if active)

---

### D.9 MaintenanceTicketResource (Tenant Panel)

**Navigation**: Group "Maintenance", icon `heroicon-o-wrench-screwdriver`

**Form Schema**:
```
Section "Ticket":
  - TextInput::ticket_number (disabled, auto-generated)
  - Select::asset_id (required, relationship, searchable)
  - TextInput::title (required)
  - RichEditor::description
  - Select::priority (MaintenancePriority enum, required)
  - Select::status (MaintenanceStatus enum, only on edit)
  - Select::technician_id (nullable, relationship: users where role=technician)
Section "Resolution" (visible when status = resolved or closed):
  - RichEditor::resolution_notes
  - DateTimePicker::resolved_at
```

**Table**:
```
Columns:
  - TextColumn::ticket_number (searchable)
  - TextColumn::title (searchable, wrap)
  - TextColumn::asset.name
  - TextColumn::priority (badge with color/icon)
  - TextColumn::status (badge with color/icon)
  - TextColumn::technician.name
  - TextColumn::total_cost (money)
  - TextColumn::created_at (since)

Filters:
  - SelectFilter::status (enum, multiple)
  - SelectFilter::priority (enum)
  - SelectFilter::technician_id

Default sort: created_at desc
```

**Actions**: View, Edit, AssignTechnicianAction, StartWorkAction, ResolveAction, CloseAction
**Bulk Actions**: BulkAssignTechnician, BulkClose
**Relation Managers**: `CostsRelationManager`, `AttachmentsRelationManager`

---

### D.10 PreventiveMaintenanceResource (Tenant Panel)

**Navigation**: Group "Maintenance", icon `heroicon-o-calendar`

**Form**: asset_id, title, description, frequency_days, technician_id, next_due_at, is_active (toggle)
**Table**: title, asset.name, frequency_days, next_due_at (color: danger if overdue), technician.name, is_active, last_performed_at
**Actions**: View, Edit, GenerateTicketAction (creates a MaintenanceTicket), ToggleActiveAction

---

### D.11 LicenseResource (Tenant Panel)

**Navigation**: Group "Licenses", icon `heroicon-o-key`

**Form Schema**:
```
Section "License Details":
  - TextInput::name (required)
  - TextInput::license_key (password, copyable)
  - Select::license_type (LicenseType enum)
  - Select::vendor_id (relationship, searchable)
  - TextInput::total_seats (numeric, required)
  - DatePicker::expiry_date
  - DatePicker::purchase_date
  - TextInput::purchase_cost (numeric, prefix: $)
  - RichEditor::notes
```

**Table**:
```
Columns:
  - TextColumn::name (searchable)
  - TextColumn::license_type (badge)
  - TextColumn::vendor.name
  - TextColumn::total_seats
  - TextColumn::used_seats (computed, color: danger if >= total)
  - TextColumn::available_seats (computed)
  - TextColumn::expiry_date (date, color: danger if expired or within 30 days)

Filters:
  - SelectFilter::license_type
  - Filter::expiring_soon (within 30 days)
  - Filter::over_assigned (used >= total)
```

**Actions**: View, Edit, AssignToAssetAction (modal), Delete
**Relation Managers**: `LicenseAssignmentsRelationManager`

---

### D.12 VendorResource (Tenant Panel)

**Navigation**: Group "Vendors & Contracts", icon `heroicon-o-truck`
**Form**: name, contact_name, email, phone, website, address, notes
**Table**: name, contact_name, email, assets_count, contracts_count, licenses_count
**Relation Managers**: `AssetsRelationManager`, `ContractsRelationManager`, `LicensesRelationManager`

---

### D.13 ContractResource (Tenant Panel)

**Navigation**: Group "Vendors & Contracts", icon `heroicon-o-document-text`

**Form Schema**:
```
Section "Contract":
  - TextInput::contract_number
  - TextInput::title (required)
  - Select::type (ContractType enum)
  - Select::vendor_id (relationship, searchable)
  - DatePicker::start_date (required)
  - DatePicker::end_date (required)
  - TextInput::value (numeric, prefix: $)
  - DatePicker::renewal_date
  - Toggle::auto_renew
  - FileUpload::document_path (S3, pdf/doc)
  - RichEditor::notes
Section "Linked Assets":
  - Select::assets (multiple, relationship, searchable)
```

**Table**: title, type (badge), vendor.name, start_date, end_date (color danger if expired), value (money), assets_count
**Filters**: SelectFilter::type, Filter::expiring_soon, SelectFilter::vendor_id

---

### D.14 InventorySessionResource (Tenant Panel)

**Navigation**: Group "Inventory", icon `heroicon-o-clipboard-document-check`

**Form Schema**:
```
Section "Session":
  - TextInput::name (required)
  - Textarea::description
  - Select::scope_type (radio: all, location, category, department)
  - Select::scope_ids (multiple, dynamic based on scope_type, visible when scope_type != all)
Section "Team" (via Repeater or RelationManager):
  - Repeater::tasks:
    - Select::assigned_to (user, required)
    - Select::location_id (nullable)
```

**Table**:
```
Columns:
  - TextColumn::name (searchable)
  - TextColumn::status (badge)
  - TextColumn::scope_type (badge)
  - TextColumn::total_expected
  - TextColumn::total_scanned
  - TextColumn::total_matched
  - TextColumn::total_missing (color: danger if > 0)
  - TextColumn::creator.name
  - TextColumn::started_at
  - TextColumn::completed_at

Filters:
  - SelectFilter::status (enum)

Default sort: created_at desc
```

**Actions**: View, Edit (only when draft), StartSessionAction, CompleteSessionAction, CancelSessionAction, ViewDiscrepancyReportAction
**Page-Level Action on View**: `ExecuteInventoryAction` -> navigates to InventoryExecutionPage

**Relation Managers**: `InventoryTasksRelationManager`, `InventoryItemsRelationManager`

---

### D.15 AuditLogResource (Tenant Panel — View Only)

**Navigation**: Group "System", icon `heroicon-o-eye`
**canCreate**: false, **canEdit**: false, **canDelete**: false

**Table**:
```
Columns:
  - TextColumn::created_at (datetime, sortable)
  - TextColumn::user.name
  - TextColumn::event (badge)
  - TextColumn::auditable_type (formatted model name)
  - TextColumn::description (computed summary)

Filters:
  - SelectFilter::event
  - SelectFilter::auditable_type
  - SelectFilter::user_id
  - Filter::date_range (created_at)

Default sort: created_at desc
```

**Infolist** (View page): timestamp, user, event, model, old_values (KeyValue), new_values (KeyValue), ip_address

---

### D.16 NotificationRuleResource (Tenant Panel)

**Navigation**: Group "System", icon `heroicon-o-bell`

**Form**: event_type (Select), days_before (numeric, visible for expiry types), channels (CheckboxList: mail, database), recipients_type (radio), recipients_value (dynamic Select), is_active (Toggle)
**Table**: event_type (badge), days_before, channels (badges), is_active (toggle), recipients_type

---

### D.17 OrganizationResource (System Admin Panel)

**Table**: name, slug, owner.name, plan.name, subscription.status (badge), users_count, assets_count, created_at
**Actions**: View (infolist), Edit, ImpersonateAction (login as tenant admin), SuspendAction, DeleteAction
**Infolist**: all fields + subscription details + usage stats

---

### D.18 PlanResource (System Admin Panel)

**Form**: name, slug, max_users, max_assets, price_monthly, price_yearly, features (KeyValue), is_active, sort_order
**Table**: name, price_monthly (money), max_users, max_assets, subscriptions_count, is_active (toggle)
**Actions**: Edit, Delete (only if no active subscriptions)

---

### D.19 SubscriptionResource (System Admin Panel)

**Table**: organization.name, plan.name, status (badge), starts_at, ends_at, trial_ends_at
**Actions**: View, ChangeStatusAction (modal with status select), ChangePlanAction

---

## SECTION E: RELATION MANAGERS

| Relation Manager | Parent Resource | Related Model | Key Columns | Actions |
|---|---|---|---|---|
| AssignmentsRelationManager | AssetResource | Assignment | assignee.name, assigned_at, returned_at, status | Create (CheckOut), CheckIn, View |
| MaintenanceTicketsRelationManager | AssetResource | MaintenanceTicket | ticket_number, title, priority, status, technician | Create, View |
| ImagesRelationManager | AssetResource | AssetImage | image (ImageColumn), caption, is_primary | Create (upload), SetPrimary, Delete, Reorder |
| LicensesRelationManager | AssetResource | License (pivot) | name, license_type, expiry_date, seats | Attach, Detach |
| StatusHistoryRelationManager | AssetResource | AssetStatusHistory | from_status, to_status, user.name, reason, created_at | View only (no create/edit) |
| ContractsRelationManager | AssetResource | Contract (pivot) | title, type, vendor, end_date | Attach, Detach |
| CostsRelationManager | MaintenanceTicketResource | MaintenanceCost | description, amount, cost_type, incurred_at | Create, Edit, Delete |
| AttachmentsRelationManager | MaintenanceTicketResource | Attachment (morph) | file_name, mime_type, file_size, uploader | Create (upload), Download, Delete |
| CustomFieldsRelationManager | AssetCategoryResource | CustomField | name, field_type, is_required, sort_order | Create, Edit, Delete, Reorder |
| AssetModelsRelationManager | ManufacturerResource | AssetModel | name, model_number, category, assets_count | Create, Edit, Delete |
| AssetsRelationManager | LocationResource / DepartmentResource / AssetCategoryResource | Asset | asset_code, name, status | View (navigate to AssetResource) |
| DepartmentsRelationManager | LocationResource | Department | name, manager, users_count | Create, Edit |
| UsersRelationManager | DepartmentResource | User | name, email, role | Edit (role change), Deactivate |
| LicenseAssignmentsRelationManager | LicenseResource | LicenseAssignment | asset.name, assigned_at, assigned_by | Create (assign), Revoke |
| ContractsRelationManager | VendorResource | Contract | title, type, end_date | Create, View |
| LicensesRelationManager | VendorResource | License | name, license_type, expiry_date | View |
| InventoryTasksRelationManager | InventorySessionResource | InventoryTask | assignee.name, location.name, status, items scanned | Edit, StartTask, CompleteTask |
| InventoryItemsRelationManager | InventorySessionResource | InventoryItem | asset.asset_code, asset.name, status, scanned_at | MarkFound, MarkMissing |

---

## SECTION F: CUSTOM PAGES

### F.1 TenantDashboard

**Type**: Custom Filament Dashboard (default page for Tenant Panel)
**Widgets**: See Section G
**No custom actions** — widgets drive interaction via clickable stats

---

### F.2 InventoryExecutionPage

**Type**: Custom Page, registered in Tenant Panel
**Route**: `/app/{tenant}/inventory-sessions/{session}/execute`
**Purpose**: Real-time interface for auditors to scan assets and record findings during an inventory session.

**Layout**:
```
Header: Session name, status, progress bar (scanned / expected)

Split Layout:
  Left Panel (60%):
    - TextInput for barcode/QR scan (auto-focus, on-enter: look up asset)
    - Camera scanner button (triggers device camera for QR)
    - Table of InventoryItems for this session:
        - asset_code, asset_name, expected_location, status (badge), scanned_at
        - Inline actions: MarkFound, MarkMissing, AddNote
    - Filter tabs: All | Expected | Found | Missing | Unexpected

  Right Panel (40%):
    - Stats: Total Expected, Scanned, Matched, Missing, Unexpected
    - Pie chart: found vs missing vs unexpected
    - Last scanned asset card (name, image, location, status)
    - "Add Unexpected Asset" button (opens modal to scan/enter asset not on list)
```

**Actions on this page**:
- `ScanAssetAction` — looks up asset by barcode/QR, marks as found
- `MarkMissingAction` — marks unscanned asset as missing
- `AddUnexpectedAction` — modal to add asset found but not expected
- `CompleteSessionAction` — page-level action, changes session status, computes discrepancy stats
- `ExportDiscrepancyReportAction` — generates PDF/CSV of findings

---

### F.3 ReportsPage

**Type**: Custom Page, registered in Tenant Panel
**Route**: `/app/{tenant}/reports`
**Purpose**: Generate and view custom reports

**Layout**:
```
Report Selector (Select component):
  - Asset Allocation Report
  - Asset Depreciation Report
  - Inventory Discrepancy Report
  - Maintenance Cost Report
  - License Compliance Report
  - Warranty Expiry Report

Filter Section (dynamic per report type):
  - Date range picker
  - Location filter
  - Category filter
  - Status filter
  - Department filter

Results Section:
  - Data table with report results
  - Summary statistics above table
  - Chart visualization (where applicable)

Export Actions (Header):
  - ExportCsvAction
  - ExportExcelAction
  - ExportPdfAction
```

---

### F.4 AssetScannerPage

**Type**: Custom Page, registered in Tenant Panel
**Route**: `/app/{tenant}/scanner`
**Purpose**: Quick asset lookup by scanning QR/barcode

**Layout**:
```
- Camera viewer (for QR scanning via JS library, e.g. html5-qrcode)
- TextInput for manual barcode entry
- Asset detail card (appears after scan):
    - Image, name, asset_code, status, location, current assignment
    - Quick actions: CheckOut, CheckIn, CreateMaintenanceTicket, ViewFull
```

---

### F.5 ApiTokensPage

**Type**: Custom Page, registered in Tenant Panel
**Route**: `/app/{tenant}/api-tokens`
**Purpose**: Manage Laravel Sanctum API tokens

**Layout**:
```
- Table of existing tokens: name, abilities, last_used_at, created_at
- Header Action: CreateTokenAction (modal: name, abilities checkboxes)
    -> shows token value ONCE after creation
- Row Action: RevokeTokenAction (confirmation dialog)
```

---

### F.6 Registration Page (Public)

**Type**: Custom Livewire page outside Filament panels
**Route**: `/register`
**Purpose**: New organization signup

**Flow**: Organization name -> Admin user details -> Auto-create: Organization, User (admin), Subscription (trial) -> Redirect to `/app`

---

## SECTION G: WIDGETS

### Tenant Dashboard Widgets

| Widget | Type | Data | Position |
|--------|------|------|----------|
| AssetStatsOverview | StatsOverview | Total assets, Available, Assigned, Under Maintenance, Retired | full width, row 1 |
| AssetsByStatusChart | Chart (doughnut) | Count per AssetStatus | col-span-1, row 2 |
| AssetsByCategoryChart | Chart (bar) | Count per AssetCategory (top 10) | col-span-1, row 2 |
| AssetsByLocationChart | Chart (horizontal bar) | Count per Location (top 10) | col-span-1, row 2 |
| RecentAssignmentsTable | Table widget | Last 5 assignments (asset, assignee, date) | col-span-1, row 3 |
| UpcomingWarrantyExpiries | Table widget | Assets with warranty expiring in 30 days | col-span-1, row 3 |
| OpenMaintenanceTickets | StatsOverview | Open, In Progress, High Priority, Total Cost MTD | full width, row 4 |
| ExpiringLicensesTable | Table widget | Licenses expiring in 30 days | col-span-1, row 5 |
| OverduePmSchedules | Table widget | Preventive maintenance overdue | col-span-1, row 5 |
| MaintenanceCostTrend | Chart (line) | Monthly maintenance cost (last 12 months) | col-span-2, row 6 |

### System Admin Dashboard Widgets

| Widget | Type | Data |
|--------|------|------|
| PlatformStatsOverview | StatsOverview | Total orgs, Total users, Total assets, Active subscriptions |
| PlanDistributionChart | Chart (doughnut) | Organizations per plan |
| RecentRegistrations | Table widget | Last 10 org registrations |
| ExpiringSubscriptions | Table widget | Subscriptions expiring in 30 days |
| AssetGrowthChart | Chart (line) | Total assets created per month (last 12 months) |

---

## SECTION H: STATE TRANSITIONS

### H.1 Asset Status Transitions

```
                    ┌──────────────────────────────────┐
                    │                                  │
                    ▼                                  │
  ┌──────────┐  CheckOut  ┌──────────┐  CheckIn  ┌────┴─────┐
  │ Available ├──────────►│ Assigned ├──────────►│ Available│
  └─────┬─────┘           └────┬─────┘           └──────────┘
        │                      │
        │ SendToMaint.         │ SendToMaint.
        ▼                      ▼
  ┌──────────────────┐
  │ Under Maintenance ├───── ReturnToService ────► Available
  └────────┬─────────┘
           │
           │ Retire
           ▼
     ┌──────────┐
     │ Retired  │
     └──────────┘

  Any Status ──── MarkLostStolen ────► Lost/Stolen
  Any Status ──── Dispose ────► Disposed
  Lost/Stolen ──── Recover ────► Available
```

| Transition | From Status(es) | To Status | Filament Action | Side Effects |
|---|---|---|---|---|
| Check Out | `available` | `assigned` | `CheckOutAction` (modal) | Creates Assignment record; sends notification to assignee; logs status history |
| Check In | `assigned` | `available` | `CheckInAction` (modal) | Sets Assignment.returned_at; optionally records condition; logs status history |
| Send to Maintenance | `available`, `assigned` | `under_maintenance` | `SendToMaintenanceAction` (modal) | Creates MaintenanceTicket; if was assigned, auto-returns assignment; logs status history |
| Return to Service | `under_maintenance` | `available` | `ReturnToServiceAction` | Requires open tickets resolved; logs status history |
| Retire | `available`, `under_maintenance` | `retired` | `RetireAction` (confirmation) | Sets retirement_date; logs status history |
| Mark Lost/Stolen | any except `disposed` | `lost_stolen` | `MarkLostStolenAction` (modal with reason) | If was assigned, creates return record; sends alert to admin; logs status history |
| Recover | `lost_stolen` | `available` | `RecoverAction` | Logs status history |
| Dispose | `retired` | `disposed` | `DisposeAction` (confirmation) | Final state; logs status history |

**Authorization**: Admin, Manager can perform all. Technician can SendToMaintenance and ReturnToService. User can only report (creates ticket, not status change directly).

---

### H.2 Maintenance Ticket Status Transitions

```
  ┌──────┐  Assign   ┌─────────────┐  StartWork  ┌─────────────┐
  │ Open ├──────────►│   Open      ├───────────►│ In Progress │
  └──────┘           │(w/technician)│            └──────┬──────┘
                     └──────────────┘                   │
                                                        │ PutOnHold
                                                        ▼
                                                  ┌──────────┐
                                                  │ On Hold  ├──► ResumeWork ──► In Progress
                                                  └──────────┘
                                                        │
                              In Progress ──── Resolve ──┤
                                                         ▼
                                                   ┌──────────┐
                                                   │ Resolved ├──► Close ──► Closed
                                                   └──────────┘
```

| Transition | From | To | Action | Side Effects |
|---|---|---|---|---|
| Assign Technician | `open` | `open` | `AssignTechnicianAction` | Sets technician_id; notifies technician |
| Start Work | `open` | `in_progress` | `StartWorkAction` | Sets started_at; logs |
| Put On Hold | `in_progress` | `on_hold` | `PutOnHoldAction` (modal: reason) | Logs |
| Resume Work | `on_hold` | `in_progress` | `ResumeWorkAction` | Logs |
| Resolve | `in_progress` | `resolved` | `ResolveAction` (modal: resolution_notes) | Sets resolved_at; computes total_cost from costs relation |
| Close | `resolved` | `closed` | `CloseAction` | If asset status = under_maintenance AND no other open tickets for this asset -> set asset to available |
| Reopen | `resolved`, `closed` | `open` | `ReopenAction` (modal: reason) | Clears resolved_at; logs |

---

### H.3 Inventory Session Status Transitions

```
  ┌───────┐  Start    ┌─────────────┐  Complete  ┌───────────┐
  │ Draft ├─────────►│ In Progress ├──────────►│ Completed │
  └───┬───┘           └──────┬──────┘           └───────────┘
      │                      │
      │ Cancel               │ Cancel
      ▼                      ▼
  ┌───────────┐        ┌───────────┐
  │ Cancelled │        │ Cancelled │
  └───────────┘        └───────────┘
```

| Transition | From | To | Action | Side Effects |
|---|---|---|---|---|
| Start Session | `draft` | `in_progress` | `StartSessionAction` | Populates InventoryItems from scoped assets (status = expected); sets started_at; sets total_expected |
| Complete Session | `in_progress` | `completed` | `CompleteSessionAction` | Computes total_matched, total_missing, total_unexpected; sets completed_at; marks remaining unscanned items as missing |
| Cancel Session | `draft`, `in_progress` | `cancelled` | `CancelSessionAction` (confirmation) | Logs reason |

---

### H.4 Assignment Lifecycle (not enum-based, but date-driven)

```
  Created (assigned_at set) ──────► Active (returned_at = null)
                                          │
                                    CheckIn│
                                          ▼
                                    Returned (returned_at set)
```

| Action | Trigger | Side Effects |
|---|---|---|
| Create Assignment (CheckOut) | `CheckOutAction` on Asset | Asset status -> assigned; notification to assignee |
| Return Assignment (CheckIn) | `CheckInAction` on Asset | Asset status -> available; return_condition recorded; notification to asset manager |

---

### H.5 License Assignment

| Action | Trigger | Side Effects |
|---|---|---|
| Assign to Asset | `AssignToAssetAction` on License | Creates LicenseAssignment; checks if used_seats >= total_seats (warn/block) |
| Revoke from Asset | `RevokeAction` on LicenseAssignment | Sets revoked_at |

**Alert**: If `used_seats >= total_seats` after assignment -> send over-assignment alert notification.

---

### H.6 Subscription Status Transitions (System Admin)

```
  ┌───────┐         ┌────────┐         ┌─────────┐
  │ Trial ├────────►│ Active ├────────►│ Expired │
  └───────┘         └────┬───┘         └─────────┘
                         │
                         │ Cancel
                         ▼
                    ┌───────────┐
                    │ Cancelled │
                    └───────────┘

  Expired ──── Reactivate ──► Active
  Past Due ──── Pay ──► Active
  Past Due ──── Expire ──► Expired
```

---

## SECTION I: PRIMARY USER FLOWS (END-TO-END)

### Flow 1: Asset Onboarding

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Navigate to Asset Categories | `AssetCategoryResource` List page | Sidebar -> Asset Management -> Categories |
| 2 | Create a category | `AssetCategoryResource` Create page | Fill name, description, set custom fields via Repeater |
| 3 | Navigate to Assets | `AssetResource` List page | Sidebar -> Asset Management -> Assets |
| 4 | Click "New Asset" | `AssetResource` Create page | Header `CreateAction` |
| 5 | Fill asset form | Form Schema Tab "General" | Name, category (triggers custom fields load), model, location, serial number |
| 6 | Fill financial details | Form Schema Tab "Financial" | Purchase date, cost, vendor, warranty, depreciation |
| 7 | Fill custom fields | Form Schema Tab "Custom Fields" | Dynamic fields based on selected category |
| 8 | Upload images | Form Schema Tab "Images" | Multi-image FileUpload to S3 |
| 9 | Save | Form submit | `asset_code` and `barcode` auto-generated on creating; QR code generated and stored |
| 10 | View asset | `AssetResource` View page (Infolist) | See full detail with QR code displayed |
| 11 | Print label | `PrintLabelAction` on View page | Opens print-ready page with QR code, asset_code, name, serial |
| 12 | Bulk import (alternative) | `ImportAction` on List page header | Upload CSV -> map columns -> validate per row -> create assets -> error report |

---

### Flow 2: Asset Check-Out / Check-In

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Find asset | `AssetResource` List page | Search by asset_code, name, serial_number, or scan via AssetScannerPage |
| 2 | Click "Check Out" | `CheckOutAction` (Table row action or View page action) | Visible only when status = `available` |
| 3 | Fill assignment modal | Action modal form | Select assignee_type (user/dept/location), select assignee, set expected_return_at, optional notes, optional signature upload |
| 4 | Confirm | Action submit | Creates Assignment record; changes asset status to `assigned`; creates AssetStatusHistory entry; sends Filament + email notification to assignee |
| 5 | (Time passes) | — | Dashboard widget shows overdue assignments if expected_return_at < now |
| 6 | Find assigned asset | `AssetResource` List page | Filter by status = assigned, or find via search |
| 7 | Click "Check In" | `CheckInAction` (row action or View page action) | Visible only when status = `assigned` |
| 8 | Fill return modal | Action modal form | Return condition notes (textarea), accepted by (auto-set to current user) |
| 9 | Confirm | Action submit | Sets Assignment.returned_at; changes asset status to `available`; creates status history; notification to original assignee |

---

### Flow 3: Inventory Campaign

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Create session | `InventorySessionResource` Create page | Name, description, scope_type (location/category/department/all), scope_ids |
| 2 | Assign auditors | Repeater in form OR `InventoryTasksRelationManager` | Create tasks: assign user + optional location per task |
| 3 | Save as draft | Form submit | Session status = `draft` |
| 4 | Start session | `StartSessionAction` on View page | System populates InventoryItems from all assets matching scope; each item status = `expected`; session -> `in_progress` |
| 5 | Auditor opens session | `InventoryExecutionPage` | Custom page with scan input, asset table, stats |
| 6 | Scan asset | TextInput (barcode) or Camera scan | System looks up asset by barcode/QR -> if found in expected list: mark item as `found`, set scanned_at/scanned_by; if NOT in expected list: create item with status `unexpected` |
| 7 | Manual mark | Inline table action | `MarkMissingAction` for assets auditor confirms are not present |
| 8 | Monitor progress | Stats widgets on InventoryExecutionPage | Real-time: scanned/expected, pie chart |
| 9 | Complete session | `CompleteSessionAction` page-level action | All unscanned items auto-marked `missing`; compute stats; session -> `completed` |
| 10 | View discrepancy report | `ViewDiscrepancyReportAction` or ReportsPage | Table of missing + unexpected items; export to PDF/CSV |

---

### Flow 4: Maintenance Request

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Report issue | `MaintenanceTicketResource` Create page OR `SendToMaintenanceAction` on Asset | Title, description, asset, priority |
| 2 | System sets status | Auto | Ticket status = `open`; if triggered from asset action: asset status -> `under_maintenance` |
| 3 | Admin assigns technician | `AssignTechnicianAction` on ticket | Modal: select user (role = technician); notification sent |
| 4 | Technician starts work | `StartWorkAction` table/view action | Status -> `in_progress`; started_at set |
| 5 | Technician records costs | `CostsRelationManager` on ticket Edit/View | Add cost entries: description, amount, type |
| 6 | Technician attaches docs | `AttachmentsRelationManager` | Upload photos/documents to S3 |
| 7 | Technician resolves | `ResolveAction` | Modal: resolution_notes; status -> `resolved`; resolved_at set |
| 8 | Manager closes | `CloseAction` | Status -> `closed`; asset status -> `available` (if no other open tickets for this asset) |

---

### Flow 5: Preventive Maintenance

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Create schedule | `PreventiveMaintenanceResource` Create page | Asset, title, frequency_days, technician, description |
| 2 | System computes next_due_at | Model observer / mutator | `next_due_at = last_performed_at + frequency_days` (or created_at if never performed) |
| 3 | Scheduled command runs daily | `app:check-preventive-maintenance` | Finds schedules where `next_due_at <= today`; sends reminder notifications to assigned technicians |
| 4 | Dashboard shows overdue | `OverduePmSchedules` widget | Table widget filtered to overdue schedules |
| 5 | Generate ticket | `GenerateTicketAction` on schedule | Creates a MaintenanceTicket pre-filled with asset, title, technician; updates `last_performed_at` and recomputes `next_due_at` |
| 6 | Technician completes ticket | (same as Flow 4, steps 4-8) | Standard maintenance flow |

---

### Flow 6: License Management

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Create vendor (if needed) | `VendorResource` Create page | Name, contact info |
| 2 | Create license | `LicenseResource` Create page | Name, key (encrypted), type, vendor, seats, expiry, cost |
| 3 | Assign to assets | `AssignToAssetAction` on License view OR `LicenseAssignmentsRelationManager` | Select asset; check available_seats; if used_seats >= total_seats -> warning notification |
| 4 | Monitor compliance | `LicenseResource` List page | Filter: over-assigned; column: used_seats vs total_seats |
| 5 | Expiry alert | Scheduled command `app:check-license-expiry` | Finds licenses expiring within NotificationRule.days_before; sends notification |
| 6 | Dashboard widget | `ExpiringLicensesTable` | Shows licenses expiring in 30 days |
| 7 | Revoke assignment | `RevokeAction` on LicenseAssignment | Sets revoked_at; frees seat |

---

### Flow 7: Vendor & Contract Management

| Step | User Action | Filament Primitive | Detail |
|------|------------|-------------------|--------|
| 1 | Create vendor | `VendorResource` Create page | Name, contacts, website |
| 2 | Create contract | `ContractResource` Create page (or `ContractsRelationManager` on VendorResource) | Title, type, vendor, dates, value, upload document, link assets |
| 3 | Link assets | MultiSelect in Contract form OR `ContractsRelationManager` on AssetResource | Pivot table asset_contract |
| 4 | Monitor expiry | `ContractResource` List, Filter: expiring_soon | Contracts with end_date within 30 days highlighted |
| 5 | Renewal alert | Scheduled command `app:check-contract-renewals` | Finds contracts with renewal_date within NotificationRule.days_before; sends notification |
| 6 | View vendor portfolio | `VendorResource` View page | RelationManagers show all contracts, licenses, assets |

---

## SECTION J: ACTIONS CATALOG

### Table / Record Actions

| Action Name | Resource | Type | Modal? | What It Does | Auth |
|---|---|---|---|---|---|
| CheckOutAction | AssetResource | Table + View Page | Yes: assignee_type, assignee_id, expected_return_at, notes, signature | Creates Assignment; changes Asset status to assigned; sends notification; logs status history | Admin, Manager |
| CheckInAction | AssetResource | Table + View Page | Yes: return_condition, notes | Sets Assignment.returned_at; changes Asset to available; logs history | Admin, Manager |
| SendToMaintenanceAction | AssetResource | View Page | Yes: title, description, priority, technician | Creates MaintenanceTicket; changes Asset to under_maintenance; logs history | Admin, Manager, Technician |
| RetireAction | AssetResource | View Page | Confirmation | Changes Asset to retired; sets retirement_date; logs | Admin |
| MarkLostStolenAction | AssetResource | View Page | Yes: reason | Changes Asset to lost_stolen; auto-returns if assigned; alert to admin | Admin, Manager |
| RecoverAction | AssetResource | View Page | Confirmation | Changes Asset to available; logs | Admin |
| DisposeAction | AssetResource | View Page | Confirmation | Changes Asset to disposed (final); logs | Admin |
| ReturnToServiceAction | AssetResource | View Page | Confirmation | Changes Asset from under_maintenance to available; validates no open tickets | Admin, Manager |
| GenerateQrAction | AssetResource | View Page | No | Generates QR code image; stores to S3; updates asset.qr_code_path | Admin, Manager |
| PrintLabelAction | AssetResource | View Page | No (opens new tab) | Renders printable label with QR, asset_code, name, serial | All |
| AssignTechnicianAction | MaintenanceTicketResource | Table + View | Yes: technician_id | Sets technician; sends notification | Admin, Manager |
| StartWorkAction | MaintenanceTicketResource | Table + View | Confirmation | Status -> in_progress; sets started_at | Admin, Manager, Technician (own) |
| PutOnHoldAction | MaintenanceTicketResource | View | Yes: reason | Status -> on_hold | Admin, Manager, Technician (own) |
| ResumeWorkAction | MaintenanceTicketResource | View | Confirmation | Status -> in_progress | Admin, Manager, Technician (own) |
| ResolveAction | MaintenanceTicketResource | View | Yes: resolution_notes | Status -> resolved; sets resolved_at; computes total_cost | Admin, Manager, Technician (own) |
| CloseAction | MaintenanceTicketResource | Table + View | Confirmation | Status -> closed; asset -> available if no other open tickets | Admin, Manager |
| ReopenAction | MaintenanceTicketResource | View | Yes: reason | Status -> open; clears resolved_at | Admin, Manager |
| GenerateTicketAction | PreventiveMaintenanceResource | Table + View | Confirmation | Creates MaintenanceTicket from schedule; updates last_performed_at; recomputes next_due_at | Admin, Manager |
| ToggleActiveAction | PreventiveMaintenanceResource | Table | No (toggle) | Toggles is_active on schedule | Admin, Manager |
| AssignToAssetAction | LicenseResource | View | Yes: asset_id | Creates LicenseAssignment; checks seat count; warns if over-assigned | Admin, Manager |
| RevokeAction | LicenseAssignmentsRelationManager | Table | Confirmation | Sets revoked_at on LicenseAssignment | Admin, Manager |
| StartSessionAction | InventorySessionResource | View | Confirmation | Populates InventoryItems; session -> in_progress | Admin, Manager |
| CompleteSessionAction | InventorySessionResource / InventoryExecutionPage | View / Page | Confirmation | Auto-marks unscanned as missing; computes stats; session -> completed | Admin, Manager |
| CancelSessionAction | InventorySessionResource | View | Yes: reason | Session -> cancelled | Admin, Manager |
| ScanAssetAction | InventoryExecutionPage | Page | No (instant) | Looks up asset by barcode; marks InventoryItem as found | All |
| MarkMissingAction | InventoryExecutionPage | Table (inline) | Confirmation | Marks InventoryItem as missing | Admin, Manager |
| AddUnexpectedAction | InventoryExecutionPage | Page | Yes: barcode/asset_code | Creates InventoryItem with status unexpected | Admin, Manager |
| DeactivateUserAction | UserResource | Table | Confirmation | Sets is_active = false | Admin |
| ActivateUserAction | UserResource | Table | Confirmation | Sets is_active = true | Admin |
| ResetPasswordAction | UserResource | Table | Yes: new password | Resets password; optionally sends email | Admin |
| ImpersonateAction | OrganizationResource (System) | Table | Confirmation | Logs system admin into tenant as owner | Super Admin |
| SuspendAction | OrganizationResource (System) | Table | Yes: reason | Sets subscription to suspended | Super Admin |
| ChangeStatusAction | SubscriptionResource (System) | Table | Yes: new status | Changes subscription status | Super Admin |
| ChangePlanAction | SubscriptionResource (System) | Table | Yes: plan_id | Changes subscription plan; enforces limits | Super Admin |
| CreateTokenAction | ApiTokensPage | Page Header | Yes: name, abilities | Creates Sanctum token; shows value once | Admin |
| RevokeTokenAction | ApiTokensPage | Table | Confirmation | Deletes token | Admin |

### Bulk Actions

| Action Name | Resource | What It Does | Auth |
|---|---|---|---|
| BulkChangeStatusAction | AssetResource | Modal: select new status; applies to all selected; validates transition; logs each | Admin, Manager |
| BulkReassignAction | AssetResource | Modal: new location + department; updates all selected | Admin, Manager |
| BulkDeleteAction | AssetResource | Soft-deletes all selected | Admin |
| ExportBulkAction | AssetResource | Exports selected rows to CSV/Excel | All |
| BulkAssignTechnicianAction | MaintenanceTicketResource | Modal: select technician; assigns to all selected open tickets | Admin, Manager |
| BulkCloseAction | MaintenanceTicketResource | Closes all selected resolved tickets | Admin, Manager |

### Header Actions

| Action Name | Resource | What It Does |
|---|---|---|
| ImportAction | AssetResource | CSV/Excel import with column mapping, per-row validation, error report |
| ExportAction | AssetResource, MaintenanceTicketResource, LicenseResource | Export current filtered table to CSV/Excel |
| CreateAction | (all Resources) | Standard Filament create button |

---

## SECTION K: SCHEDULED COMMANDS

| Command | Schedule | Purpose |
|---|---|---|
| `app:check-warranty-expiry` | Daily | Find assets with warranty_expiry within NotificationRule days; send notifications |
| `app:check-license-expiry` | Daily | Find licenses with expiry_date within NotificationRule days; send notifications |
| `app:check-contract-renewals` | Daily | Find contracts with renewal_date within NotificationRule days; send notifications |
| `app:check-preventive-maintenance` | Daily | Find schedules with next_due_at <= today; send reminder to technician |
| `app:check-overdue-assignments` | Daily | Find assignments with expected_return_at < today AND returned_at is null; notify manager |
| `app:expire-subscriptions` | Daily | Find subscriptions with ends_at < today AND status = active; set status = expired; restrict tenant access |

---

## SECTION L: KEY SERVICES

| Service | Purpose | Used By |
|---|---|---|
| `AssetCodeGenerator` | Auto-generates unique asset_code per organization (e.g. `AST-00001`) | Asset model boot |
| `BarcodeGenerator` | Generates unique barcode strings | Asset model boot |
| `QrCodeGenerator` | Generates QR code image from asset data, stores to S3 | GenerateQrAction |
| `PlanEnforcement` | Checks max_users, max_assets, feature flags against current plan | User/Asset creation hooks |
| `DepreciationCalculator` | Computes current book value based on method, cost, useful_life, salvage_value | Asset accessor, Reports |
| `InventorySessionService` | Populates expected items from scope, computes stats | StartSessionAction, CompleteSessionAction |
| `NotificationDispatcher` | Resolves notification rules, sends via configured channels | Scheduled commands, Actions |
| `AuditLogger` | Records audit log entries for model events | Model observers on all tenant models |
| `AssetImportService` | Parses CSV/Excel, validates rows, creates assets, returns error report | ImportAction |

---

## Verification Plan

1. **Multi-tenancy isolation**: Log in as two different orgs; verify zero data crossover on every resource
2. **Asset lifecycle**: Create -> Assign -> Maintenance -> Resolve -> Return to Service -> Retire -> Dispose; verify all status history entries
3. **Inventory campaign**: Create session -> Start -> Scan assets -> Complete -> Verify discrepancy report matches expectations
4. **Plan enforcement**: Set plan to max_assets=5; create 5 assets; verify 6th is blocked with clear error
5. **RBAC**: Login as each role (admin/manager/technician/user); verify resource visibility and action authorization match spec
6. **Notifications**: Seed asset with warranty_expiry = tomorrow; run `app:check-warranty-expiry`; verify database notification + email sent
7. **Dashboard widgets**: Seed 100+ assets across statuses, locations, categories; verify all widgets render with correct counts
8. **Import/Export**: Import CSV with valid + invalid rows; verify created assets and error report; export filtered list
9. **API**: Generate Sanctum token; call `/api/v1/assets`; verify JSON response and tenant scoping
10. **Audit log**: Perform various CRUD operations; verify AuditLogResource shows entries with old/new values
