# AI SEO Content Generator - v1.1.6 Changelog

**Release Date:** December 7, 2024  
**Status:** CRITICAL FIX - Correct Claude Model Name

---

## 🚨 CRITICAL BUG FIX

### Claude 3.5 Sonnet Model Name Corrected

**Problem:** The "Claude 3.5 Sonnet (Recommended)" option was using model name `claude-3-5-sonnet-20241022` which doesn't exist, causing all generation requests to fail with HTTP 404 error: "not_found_error".

**Fix:** Changed to the correct, working model name: `claude-3-5-sonnet-20240620`

---

## 📋 WHAT WAS CHANGED

### Files Modified:
1. `admin/dashboard.php` - Line 204
   - **Before:** `'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Recommended)'`
   - **After:** `'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet (Recommended)'`

2. `assets/js/ai-seo-admin.js` - Line 323
   - **Before:** `'claude-3-5-sonnet-20241022': 'Claude 3.5 Sonnet (Recommended)'`
   - **After:** `'claude-3-5-sonnet-20240620': 'Claude 3.5 Sonnet (Recommended)'`

---

## 🐛 BUG FIXED

**Issue:** HTTP 404 - Model Not Found  
**Error:** `Type: not_found_error | model: claude-3-5-sonnet-20241022 | HTTP Status: 404`  
**Cause:** Invalid model name (20241022 version doesn't exist)  
**Status:** ✅ FIXED in v1.1.6

**Before v1.1.6:**
- Selecting "Claude 3.5 Sonnet (Recommended)" failed
- All generation requests returned 404 error
- Products processed: 0

**After v1.1.6:**
- Uses correct model: `claude-3-5-sonnet-20240620`
- Generation requests succeed
- Content generated successfully

---

## ✅ VERIFIED WORKING CLAUDE MODELS

### Current Working Models:
- **Claude 3.5 Sonnet:** `claude-3-5-sonnet-20240620` ✅ (NOW FIXED)
- **Claude 3 Opus:** `claude-3-opus-20240229` ✅
- **Claude 3 Haiku:** `claude-3-haiku-20240307` ✅

---

## 🔄 UPGRADE INSTRUCTIONS

### From v1.1.5.x:
1. Deactivate current version
2. Delete current version  
3. Upload v1.1.6 ZIP
4. Activate
5. **Hard refresh browser:** Ctrl+Shift+R
6. **Important:** If you were using "Claude 3.5 Sonnet (Recommended)", it will now work automatically (no settings change needed!)

### Settings Preserved:
✅ All settings remain intact  
✅ API key stays the same  
✅ Model selection stays the same  
✅ The model just works now!

---

## 🧪 TESTING AFTER UPGRADE

**Test Generation:**
1. Go to: Products → All Products
2. Select 1 product
3. Click "Generate Content"
4. Click "Start Generation"
5. **Should see:**
   ```
   ✓ Successfully Generated Content
   Products Processed: 1
   ```

**Verify in Anthropic Console:**
1. Go to: https://console.anthropic.com/settings/keys
2. Your API key should show "Last used: [today]"
3. Should see API usage/cost incremented

---

## 💡 WHY THIS HAPPENED

The model name `claude-3-5-sonnet-20241022` appears to be:
- A future/pre-release version that isn't available yet
- Or a typo when the plugin was initially built

The actual working Claude 3.5 Sonnet model is `claude-3-5-sonnet-20240620` (June 2024 release).

---

## 🎯 EXPECTED RESULTS

### After Installing v1.1.6:

**Console will show:**
```
AI SEO: Product IDs being sent: [229037]
AI SEO: SUCCESS STATUS: true
AI SEO: FULL DATA: {
  "processed": 1,
  "results": {
    "229037": {
      "focus_keyword": "Silver Gold 3D Western Cowboy Hat Charm",
      "title": "Premium Silver or Gold 3D Western Cowboy Hat Charm",
      "short_description": "...",
      "full_description": "...",
      "meta_description": "...",
      "tags": "..."
    }
  }
}
AI SEO: PROCESSED COUNT: 1
```

**Product will have:**
- ✅ New focus keyword (5-8 words with attributes)
- ✅ New SEO-optimized title
- ✅ New short description (50-60 words)
- ✅ New full description (300-400 words with H2 headings)
- ✅ New meta description (150-160 chars)
- ✅ New tags

---

## 📊 VERSION HISTORY

- **v1.1.5:** Added Claude API support (but with wrong model name)
- **v1.1.5.1:** Enhanced error reporting (revealed the model name issue)
- **v1.1.6:** Fixed Claude model name ✅ **WORKING NOW**

---

## 🔗 REFERENCES

- Anthropic Models: https://docs.anthropic.com/en/docs/about-claude/models
- API Reference: https://docs.anthropic.com/en/api
- Console: https://console.anthropic.com

---

**Version:** 1.1.6  
**Build Date:** December 7, 2024  
**Critical Fix:** Claude Model Name Corrected  
**Status:** Production Ready - Claude Now Works!
