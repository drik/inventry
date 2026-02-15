# PROJECT_SPEC_SAAS.md
# Multi-Tenant SaaS Architecture Specification
# Asset Inventory Management System

---

## 1. Architecture Overview

This project is a Multi-Tenant SaaS Asset Inventory Management System built with:

- Laravel 10+
- PHP 8.2+
- FilamentPHP v3
- PostgreSQL
- Queue system (Redis recommended)
- Object storage (S3 compatible / MinIO)

The system must support:

- Multiple organizations (tenants)
- Strict tenant data isolation
- Scalable architecture
- Subscription-ready structure

Claude must strictly follow this specification.

---

## 2. Multi-Tenant Strategy

### Selected Approach: Single Database, Tenant Isolation via tenant_id

All tenant-owned tables must contain:

- tenant_id (indexed)
- Foreign key constraints where applicable

Global tables (no tenant_id):
- Plans
- Subscriptions
- System Roles
- Global Settings

---

## 3. Core Tenant Model

### Tenant
- id
- name
- slug
- owner_id
- plan_id
- subscription_status
- created_at
- updated_at

---

## 4. Multi-Tenant Data Model

All tenant-scoped entities must include:

- tenant_id (FK to tenants.id)
- Composite indexes where relevant

### Category
- id
- tenant_id
- name
- description
- timestamps

### Location
- id
- tenant_id
- name
- address
- timestamps

### Asset
- id
- tenant_id
- name
- serial_number (unique per tenant)
- status (enum)
- category_id
- location_id
- purchase_date
- warranty_expiry
- timestamps
- soft deletes

UNIQUE INDEX:
(tenant_id, serial_number)

### Assignment
- id
- tenant_id
- asset_id
- user_id
- assigned_at
- returned_at
- timestamps

### MaintenanceTicket
- id
- tenant_id
- asset_id
- title
- description
- status
- priority
- timestamps

### License
- id
- tenant_id
- name
- license_key
- seats
- expiry_date
- vendor_id
- timestamps

### Vendor
- id
- tenant_id
- name
- contact_email
- phone
- address
- timestamps

---

## 5. Tenant Isolation Rules

- All queries must be scoped by tenant_id
- Global scopes must enforce tenant filtering
- No cross-tenant queries allowed
- Policies must validate tenant ownership
- API must verify tenant context via subdomain or token

---

## 6. Authentication Model

Option A: Single database users table with tenant_id

User:
- id
- tenant_id
- name
- email (unique per tenant)
- role
- timestamps

UNIQUE INDEX:
(tenant_id, email)

Option B (future-ready):
Global user + tenant_user pivot table

---

## 7. Subscription & Billing Structure

### Plan
- id
- name
- max_users
- max_assets
- features_json
- price

### Subscription
- id
- tenant_id
- plan_id
- status
- start_date
- end_date
- payment_provider_id

Business Rules:
- Tenants cannot exceed plan limits
- System must enforce asset/user caps

---

## 8. Filament Multi-Panel Structure

Recommended Panels:

1. System Admin Panel
   - Manage tenants
   - Manage plans
   - View global metrics

2. Tenant Admin Panel
   - Manage assets
   - Manage users
   - Manage categories
   - Manage licenses

Panels must be separated.

---

## 9. SaaS-Specific Business Rules

- When subscription expires → tenant access restricted
- Plan downgrade must enforce limits
- Asset count must be validated before creation
- User count must respect plan

---

## 10. Security

- Tenant isolation middleware
- Policy per model
- Rate limiting per tenant
- Audit logging per tenant

---

## 11. Performance

- Index tenant_id on all tenant tables
- Use eager loading
- Cache tenant configuration
- Queue heavy operations

---

## 12. Future SaaS Extensions

- Subdomain-based tenancy
- Custom branding per tenant
- Usage metering
- API rate plans
- White-label option

---

END OF SAAS TECHNICAL SPECIFICATION
