# Settings to Hide from Tenants (Keep Visible for Super Admin)

## Overview
This document lists all settings pages that should be hidden from regular tenants but remain visible to super admins.

## Settings Pages to Hide

### User Details Settings
1. **Two-Factor Authentication**
   - Route: `/settings/user_details/enable_two_factor`
   - Hide from: Tenants
   - Keep visible for: Super Admin

2. **Notifications**
   - Route: `/settings/user_details/notifications`
   - Hide from: Tenants
   - Keep visible for: Super Admin

3. **Custom Fields**
   - Route: `/settings/user_details/custom_fields`
   - Hide from: Tenants
   - Keep visible for: Super Admin

4. **Preferences**
   - Route: `/settings/user_details/preferences`
   - Hide from: Tenants
   - Keep visible for: Super Admin

### Payment Settings
5. **Online Payments**
   - Route: `/settings/online_payments`
   - Hide from: Tenants
   - Keep visible for: Super Admin

### Workflow Settings
6. **Workflow Settings**
   - Route: `/settings/workflow_settings`
   - Hide from: Tenants
   - Keep visible for: Super Admin

### Account Management
7. **Account Management**
   - Route: `/settings/account_management/`
   - Hide from: Tenants
   - Keep visible for: Super Admin

### Backup & Restore
8. **Backup & Restore**
   - Route: `/settings/backup_restore`
   - Hide from: Tenants
   - Keep visible for: Super Admin

### Import & Export
9. **Import & Export**
   - Route: `/settings/import_export`
   - Hide from: Tenants
   - Keep visible for: Super Admin

### Advanced Settings
10. **All Advanced Settings**
    - Route: `/settings/advanced_settings` (or similar)
    - Hide from: Tenants
    - Keep visible for: Super Admin

## Implementation Notes

- Need to check user role/permissions
- Super Admin should have access to all settings
- Tenants should not see these menu items or be able to access these routes
- Should implement both:
  - Navigation/menu hiding (UI level)
  - Route protection (backend/guard level)

## Files to Modify

1. Navigation/Sidebar components (React)
2. Route guards/definitions
3. Settings menu components
4. Any settings index/listing pages

