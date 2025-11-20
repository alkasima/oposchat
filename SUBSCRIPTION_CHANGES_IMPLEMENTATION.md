# Subscription Plan Changes - Implementation Summary

## Overview
This document outlines the implementation of three critical subscription management features:

1. **Scheduled Downgrades**: When downgrading from Plus to Premium, the change is scheduled for the end of the billing period
2. **Upgrade Confirmation**: When upgrading from Premium to Plus, a confirmation modal is shown before processing payment
3. **Auto-Refresh**: The page automatically refreshes after plan changes to reflect the new state

## Changes Made

### 1. Database Changes

#### Migration: `2025_11_20_000000_add_scheduled_plan_change_to_subscriptions.php`
- Added `scheduled_plan_change_price_id` column to store the target plan's price ID
- Added `scheduled_plan_change_at` column to store when the change should occur
- Added index on `scheduled_plan_change_at` for efficient querying

### 2. Model Updates

#### `app/Models/Subscription.php`
- Added new fields to `$fillable` array
- Added new fields to `$casts` array
- Added `hasScheduledPlanChange()` method to check if a scheduled change exists
- Added `getScheduledPlanChange()` method to retrieve scheduled change details

### 3. Backend Logic

#### `app/Http/Controllers/SubscriptionController.php`
**Updated `upgrade()` method:**
- Detects if the plan change is an upgrade or downgrade based on price difference
- For **downgrades** (Plus → Premium):
  - Schedules the change for the end of the current billing period
  - Returns a success response with scheduled change details
  - Does NOT immediately change the plan in Stripe
- For **upgrades** (Premium → Plus):
  - First call without `confirmed` flag returns a confirmation request
  - Second call with `confirmed=true` processes the upgrade
  - Creates an invoice for the price difference
  - Immediately updates the plan in Stripe

**Updated `index()` method:**
- Added `has_scheduled_plan_change` to the response
- Added `scheduled_plan_change` details to the response

#### `app/Console/Commands/ProcessScheduledPlanChanges.php`
- New command to process scheduled plan changes
- Runs hourly via Laravel scheduler
- Finds all subscriptions with scheduled changes that are due
- Updates the Stripe subscription to the new plan
- Clears the scheduled change fields after processing

#### `routes/console.php`
- Added scheduled task to run `subscriptions:process-scheduled-changes` hourly

### 4. Frontend Changes

#### `resources/js/components/PlanChangeConfirmationModal.vue`
- New modal component for confirming plan upgrades
- Shows current plan, target plan, and price difference
- Displays clear messaging about the upgrade charge
- Provides "Confirm" and "Cancel" buttons

#### `resources/js/pages/settings/Subscription.vue`
**Added state management:**
- `showConfirmationModal` - Controls confirmation modal visibility
- `pendingPlanChange` - Stores plan change details for confirmation

**Updated `handleUpgrade()` function:**
- Checks for `requires_confirmation` response from API
- Shows confirmation modal for upgrades
- Handles scheduled downgrades with success message
- Auto-refreshes page after 2 seconds on successful changes

**Added new functions:**
- `handleConfirmPlanChange()` - Processes confirmed upgrades
- `handleCancelPlanChange()` - Cancels pending plan changes

**Template updates:**
- Added scheduled plan change notification banner (blue card)
- Added confirmation modal component
- Shows scheduled change details with date

#### `resources/js/services/stripeService.js`
- Updated `upgrade()` method to accept `confirmed` parameter
- Handles `requires_confirmation` response without throwing error

## How It Works

### Scenario 1: Downgrade (Plus → Premium)

1. User clicks "Cambiar a Premium" on the Premium plan card
2. Frontend calls `stripeService.upgrade(premiumPriceId)`
3. Backend detects this is a downgrade (lower price)
4. Backend saves scheduled change to database:
   - `scheduled_plan_change_price_id` = Premium price ID
   - `scheduled_plan_change_at` = Current period end date
5. Backend returns success with `status: 'scheduled'`
6. Frontend shows success modal and scheduled change banner
7. Page auto-refreshes after 2 seconds
8. User sees blue notification showing the scheduled change
9. At the end of billing period, hourly cron job processes the change
10. Stripe subscription is updated to Premium plan
11. Scheduled change fields are cleared

### Scenario 2: Upgrade (Premium → Plus)

1. User clicks "Mejorar al Plus" on the Plus plan card
2. Frontend calls `stripeService.upgrade(plusPriceId, false)`
3. Backend detects this is an upgrade (higher price)
4. Backend returns `requires_confirmation: true` with plan details
5. Frontend shows confirmation modal with:
   - Current plan: Premium
   - New plan: Plus
   - Price difference
6. User clicks "Confirmar cambio"
7. Frontend calls `stripeService.upgrade(plusPriceId, true)`
8. Backend processes the upgrade:
   - Updates Stripe subscription immediately
   - Creates invoice for price difference
   - Returns redirect URL to payment page
9. User is redirected to pay the difference
10. After payment, user returns to subscription page
11. Page auto-refreshes to show new Plus plan

### Scenario 3: Auto-Refresh

After any successful plan change:
1. Frontend calls `loadData()` to fetch latest subscription info
2. Shows success modal
3. Sets a 2-second timeout
4. Calls `window.location.reload()` to refresh the page
5. User sees updated plan information without manual refresh

## Testing

### Test Downgrade
1. Subscribe to Plus plan
2. Click "Cambiar a Premium"
3. Verify no immediate charge
4. Verify blue notification shows scheduled change
5. Verify current plan remains Plus
6. Run command manually: `php artisan subscriptions:process-scheduled-changes`
7. Verify plan changes to Premium

### Test Upgrade
1. Subscribe to Premium plan
2. Click "Mejorar al Plus"
3. Verify confirmation modal appears
4. Click "Confirmar cambio"
5. Verify redirect to payment page
6. Complete payment
7. Verify plan changes to Plus immediately
8. Verify page auto-refreshes

### Test Auto-Refresh
1. Make any plan change
2. Wait 2 seconds after success modal
3. Verify page refreshes automatically
4. Verify new plan is displayed

## Configuration

The scheduled command runs hourly. To change the frequency, edit `routes/console.php`:

```php
Schedule::command('subscriptions:process-scheduled-changes')
    ->everyFifteenMinutes() // or ->daily(), ->twiceDaily(), etc.
    ->withoutOverlapping()
    ->runInBackground();
```

## Important Notes

1. **Scheduled changes are processed hourly** - There may be up to a 1-hour delay after the billing period ends before the downgrade is processed
2. **No refunds for downgrades** - Users keep their current plan until the end of the billing period
3. **Immediate charges for upgrades** - Users are charged the price difference immediately
4. **Auto-refresh timeout** - Set to 2 seconds to allow users to read the success message
5. **Stripe webhook compatibility** - The system still processes webhooks normally for other subscription events

## Future Enhancements

1. Allow users to cancel scheduled downgrades
2. Send email notifications when scheduled changes are processed
3. Add ability to schedule upgrades (currently only downgrades are scheduled)
4. Show countdown timer for scheduled changes
5. Add admin panel to view all scheduled changes
