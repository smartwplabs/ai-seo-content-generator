# AI SEO Content Generator - Changelog v1.2.1.7b

## Version 1.2.1.7b (December 13, 2024) - CRITICAL HOTFIX

### 🐛 Critical Bug Fix - Settings Not Saving

**What Happened:**
v1.2.1.7 and v1.2.1.7a had a critical bug - the "Enable RankMath Score Calculation" checkbox was **visible and clickable**, but **not actually saving to the database**.

**The Problem:**
```
User unchecks "Enable RankMath Score Calculation" → Clicks Save
WordPress saves settings → But field is not registered!
Score calculation still shows as enabled (always returns '1')
```

**What the console showed:**
```
AI SEO: aiSeoEnableScoreCalculation = 1  (always 1, never 0!)
AI SEO: scoreCalcEnabled = true  (always true!)
```

**Root Cause:**
The checkbox was added to the UI in v1.2.1.7, but we **forgot to register** it with WordPress's settings system. WordPress didn't know this field existed, so it never saved the value.

### ✅ The Fix

**Added to settings registration:**
```php
// admin/dashboard.php line 90
register_setting('ai_seo_tools_group', 'ai_seo_tools', [
    'sanitize_callback' => function($input) {
        $sanitized = [];
        $tools = [
            'generate_meta_description',
            'add_meta_tag_to_head',
            // ... other tools ...
            'sticky_generate_button',
            'enable_score_calculation' // ← ADDED IN v1.2.1.7b!
        ];
        foreach ($tools as $tool) {
            $sanitized[$tool] = isset($input[$tool]) ? 1 : 0;
        }
        return $sanitized;
    }
]);
```

**Now when you save:**
```
Checked → Saves as 1 → JavaScript gets '1' → Shows checkbox
Unchecked → Saves as 0 → JavaScript gets '0' → Hides checkbox
```

### 🧪 How to Verify the Fix

**Test 1: Disable Score Calculation**
1. Go to AI SEO Content → Tools tab
2. **Uncheck** "Enable RankMath Score Calculation"
3. Click "Save Tools Settings"
4. Go to Products page
5. Generate content for 1 product
6. **Open browser console (F12)**
7. Look for:
   ```
   AI SEO: aiSeoEnableScoreCalculation = 0  ← Should be 0 now!
   AI SEO: scoreCalcEnabled = false  ← Should be false!
   AI SEO: Score calculation is DISABLED - showing only close button
   ```
8. **Verify:** Only "Close" button shows (no checkbox)

**Test 2: Enable Score Calculation**
1. Go to Tools tab
2. **Check** "Enable RankMath Score Calculation"
3. Click "Save Tools Settings"
4. Generate content for 1 product
5. **Open browser console**
6. Look for:
   ```
   AI SEO: aiSeoEnableScoreCalculation = 1  ← Should be 1!
   AI SEO: scoreCalcEnabled = true  ← Should be true!
   AI SEO: Score calculation is ENABLED - showing checkbox UI
   ```
7. **Verify:** Checkbox and buttons appear

### 📊 Enhanced Debug Logging

**Added detailed backend logging:**
```php
error_log('[AI SEO v1.2.1.7b] Tools array: ...');
error_log('[AI SEO v1.2.1.7b] enable_score_calculation in array: YES/NO');
error_log('[AI SEO v1.2.1.7b] enable_score_calculation value: 1/0/NOT SET');
error_log('[AI SEO v1.2.1.7b] Score calculation setting passed to JS: 1/0');
```

**Check your debug log** (wp-content/debug.log) to see:
```
[AI SEO v1.2.1.7b] Tools array: Array ( ... [enable_score_calculation] => 0 ... )
[AI SEO v1.2.1.7b] enable_score_calculation in array: YES
[AI SEO v1.2.1.7b] enable_score_calculation value: 0
[AI SEO v1.2.1.7b] Score calculation setting passed to JS: 0 (disabled)
```

### 🚀 Installation & Upgrade

**From v1.2.1.7 or v1.2.1.7a:**
1. Deactivate current version
2. Delete old plugin
3. Upload v1.2.1.7b
4. Activate
5. **IMPORTANT:** Go to Tools tab and re-save your setting
   - If you want it disabled: Uncheck and save
   - If you want it enabled: Check and save

**Why re-save?**
The old versions didn't save this field, so it doesn't exist in your database yet. You need to save it once to create the database entry.

### 📝 What Changed

| File | Line | Change |
|------|------|--------|
| admin/dashboard.php | 90 | Added 'enable_score_calculation' to $tools array |
| ai-seo-content-generator.php | 162-166 | Enhanced debug logging |
| ai-seo-content-generator.php | Version | 1.2.1.7a → 1.2.1.7b |

### 🔍 Comparison

| Version | Setting Saves? | Console Shows Correct Value? | Works? |
|---------|----------------|------------------------------|--------|
| v1.2.1.7 | ❌ No | ❌ Always '1' | ❌ Broken |
| v1.2.1.7a | ❌ No | ❌ Always '1' | ❌ Broken |
| v1.2.1.7b | ✅ Yes | ✅ Correct '0' or '1' | ✅ **FIXED** |

### ⚠️ Important Notes

**First-time save required:**
If you're upgrading from v1.2.1.7 or v1.2.1.7a, the setting doesn't exist in your database yet. You MUST:
1. Go to Tools tab
2. Set the checkbox how you want it
3. Click "Save Tools Settings"

**After that**, it will work correctly forever.

**Default behavior:**
If the setting doesn't exist in database (fresh install or before first save):
- Defaults to **ENABLED** for backward compatibility
- Shows checkbox after content generation
- User can choose to calculate or skip

### 🎯 Summary

**The Bug:**
- Checkbox appeared in Tools
- Clicking Save did nothing
- Always showed as enabled

**The Fix:**
- One line added to settings registration
- Now saves correctly
- Shows/hides based on actual saved value

**The Result:**
- Uncheck → Truly disabled (hides checkbox)
- Check → Truly enabled (shows checkbox)
- Works as designed!

---

## Technical Details

**Settings Registration:**
```php
// This array defines which fields get saved
$tools = [
    // ... existing tools ...
    'enable_score_calculation' // ← Missing in v1.2.1.7/7a, added in 7b
];

// This loop saves each field
foreach ($tools as $tool) {
    $sanitized[$tool] = isset($input[$tool]) ? 1 : 0;
}
```

**How WordPress Checkboxes Work:**
- Checked: Sends `enable_score_calculation=1` in POST
- Unchecked: Sends nothing in POST
- Our callback: `isset($input[$tool]) ? 1 : 0`
  - If in POST → Save as 1
  - If not in POST → Save as 0

**Why it broke:**
v1.2.1.7 didn't include 'enable_score_calculation' in the $tools array, so the foreach loop never processed it, so WordPress never saved it.

**One-line fix:**
Added 'enable_score_calculation' to the array. That's it!

---

**This is a critical fix that makes the feature actually work as designed. Please upgrade immediately!** 🔧
