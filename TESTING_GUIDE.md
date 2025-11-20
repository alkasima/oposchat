# Quick Testing Guide

## Prerequisites
1. Run migrations: `php artisan migrate`
2. Ensure Laravel scheduler is running: `php artisan schedule:work` (for local testing)
3. Have test Stripe accounts with Premium and Plus subscriptions

## Test Case 1: Downgrade (Plus → Premium)

**Expected Behavior:** Plan change is scheduled for end of billing period

### Steps:
1. Log in with a user who has an active Plus subscription
2. Navigate to Settings → Subscription
3. Click "Cambiar a Premium" on the Premium plan card
4. **Expected:** Success modal appears
5. **Expected:** Blue notification banner appears showing:
   - "Cambio de plan programado"
   - Target plan: Premium
   - Change date: End of current billing period
6. **Expected:** Current plan still shows as Plus
7. **Expected:** Page auto-refreshes after 2 seconds
8. **Expected:** After refresh, scheduled change banner is still visible

### Verify Scheduled Change:
```bash
# Check database
php artisan tinker
>>> $sub = \App\Models\Subscription::where('user_id', YOUR_USER_ID)->first();
>>> $sub->scheduled_plan_change_price_id; // Should show Premium price ID
>>> $sub->scheduled_plan_change_at; // Should show current_period_end date
```

### Process Scheduled Change Manually:
```bash
php artisan subscriptions:process-scheduled-changes
```

**Expected:** Plan changes to Premium immediately

---

## Test Case 2: Upgrade (Premium → Plus)

**Expected Behavior:** Confirmation modal appears, then immediate upgrade with payment

### Steps:
1. Log in with a user who has an active Premium subscription
2. Navigate to Settings → Subscription
3. Click "Mejorar al Plus" on the Plus plan card
4. **Expected:** Confirmation modal appears showing:
   - Current plan: Premium
   - New plan: Plus
   - Price difference (e.g., €5.00)
   - "Cargo adicional" message
5. Click "Cancelar"
6. **Expected:** Modal closes, no changes made
7. Click "Mejorar al Plus" again
8. Click "Confirmar cambio"
9. **Expected:** Redirect to Stripe payment page for the difference
10. Complete payment
11. **Expected:** Return to subscription page
12. **Expected:** Plan shows as Plus
13. **Expected:** Page auto-refreshes after 2 seconds

---

## Test Case 3: Auto-Refresh

**Expected Behavior:** Page refreshes automatically after plan changes

### Steps:
1. Make any plan change (upgrade or downgrade)
2. Watch for success modal
3. **Expected:** After 2 seconds, page refreshes automatically
4. **Expected:** New plan information is displayed
5. **Expected:** No manual F5 or refresh needed

### Verify in Browser Console:
```javascript
// Before plan change
console.log('Starting plan change...');

// Watch for reload
window.addEventListener('beforeunload', () => {
    console.log('Page is reloading...');
});
```

---

## Test Case 4: Scheduled Change Display

**Expected Behavior:** Scheduled changes are clearly displayed

### Steps:
1. Create a scheduled downgrade (see Test Case 1)
2. Refresh the page manually
3. **Expected:** Blue notification banner is visible
4. **Expected:** Banner shows:
   - Calendar icon
   - "Cambio de plan programado" heading
   - Target plan name
   - Change date
   - Message about keeping current plan until end of period

---

## Test Case 5: Cancel Scheduled Change (Manual)

**Currently:** No UI for this, must be done manually

### Steps:
```bash
php artisan tinker
>>> $sub = \App\Models\Subscription::where('user_id', YOUR_USER_ID)->first();
>>> $sub->update(['scheduled_plan_change_price_id' => null, 'scheduled_plan_change_at' => null]);
```

**Expected:** Scheduled change is removed, banner disappears on refresh

---

## Common Issues & Solutions

### Issue: Confirmation modal doesn't appear
**Solution:** Check browser console for errors. Verify `PlanChangeConfirmationModal.vue` is imported correctly.

### Issue: Page doesn't auto-refresh
**Solution:** Check browser console. Verify `setTimeout` is executing. Check for JavaScript errors.

### Issue: Scheduled change doesn't process
**Solution:** 
- Verify scheduler is running: `php artisan schedule:work`
- Check scheduled_plan_change_at is in the past
- Run command manually: `php artisan subscriptions:process-scheduled-changes`
- Check logs: `storage/logs/laravel.log`

### Issue: Downgrade happens immediately instead of being scheduled
**Solution:** 
- Check price configuration in `config/subscription.php`
- Verify price difference calculation is correct
- Check backend logs for the upgrade endpoint

### Issue: Upgrade doesn't require confirmation
**Solution:**
- Verify `confirmed` parameter is being sent correctly
- Check backend response for `requires_confirmation` flag
- Verify frontend is checking for this flag

---

## API Endpoints for Testing

### Get Subscription Status
```bash
curl -X GET http://localhost/api/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Upgrade (First Call - Should Return Confirmation Request)
```bash
curl -X POST http://localhost/api/subscriptions/upgrade \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"price_id": "PLUS_PRICE_ID"}'
```

### Upgrade (Confirmed)
```bash
curl -X POST http://localhost/api/subscriptions/upgrade \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"price_id": "PLUS_PRICE_ID", "confirmed": true}'
```

### Downgrade
```bash
curl -X POST http://localhost/api/subscriptions/upgrade \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"price_id": "PREMIUM_PRICE_ID"}'
```

---

## Database Queries for Verification

### Check Scheduled Changes
```sql
SELECT 
    id,
    user_id,
    stripe_price_id as current_price,
    scheduled_plan_change_price_id as scheduled_price,
    scheduled_plan_change_at as change_date,
    current_period_end
FROM subscriptions
WHERE scheduled_plan_change_price_id IS NOT NULL;
```

### Check Recent Plan Changes
```sql
SELECT 
    id,
    user_id,
    stripe_price_id,
    updated_at
FROM subscriptions
ORDER BY updated_at DESC
LIMIT 10;
```

---

## Monitoring

### Watch Logs in Real-Time
```bash
tail -f storage/logs/laravel.log
```

### Check Scheduled Tasks
```bash
php artisan schedule:list
```

### Test Scheduled Command
```bash
php artisan subscriptions:process-scheduled-changes --verbose
```
