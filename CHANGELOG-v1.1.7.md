# AI SEO Content Generator - v1.1.7 Changelog

**Release Date:** December 8, 2024  
**Status:** CRITICAL FIX - API Keys No Longer Lost When Switching Engines

---

## 🚨 CRITICAL BUG FIX

### API Keys Now Persist When Switching Between AI Engines

**Problem:** When users switched between AI engines (e.g., Claude → ChatGPT), the API key for the previous engine was LOST. Users had to re-enter API keys every time they switched engines.

**Root Cause:** The form only saved the API key for the CURRENTLY SELECTED engine. When you switched engines and clicked Save, only the new engine's key was saved, and the previous engine's key was discarded.

**Fix:** All engine-specific API keys are now saved automatically whenever you click "Save AI Settings", regardless of which engine is currently selected.

---

## 📋 WHAT WAS CHANGED

### Files Modified:

#### 1. `admin/dashboard.php`
**Added Hidden Fields:**
- Added hidden input fields for each AI engine's API key
- These fields are automatically updated when you switch engines
- All keys are submitted with the form

**Updated Sanitize Callback:**
- Now processes and saves API keys for ALL engines
- Saves to engine-specific options: `ai_seo_api_key_chatgpt`, `ai_seo_api_key_claude`, etc.
- No longer loses keys when switching engines

#### 2. `assets/js/ai-seo-admin.js`
**Added Real-Time Sync:**
- API key field now updates hidden fields as you type
- Switching engines updates both the visible field AND the hidden field
- All changes are tracked and ready to save

---

## 🔄 HOW IT WORKS NOW

### Before v1.1.7 (BUGGY):
```
1. Enter Claude API key
2. Switch to ChatGPT
3. Enter ChatGPT API key
4. Click "Save AI Settings"
5. ❌ Only ChatGPT key saved
6. ❌ Claude key LOST!
7. Switch back to Claude
8. ❌ API key field is EMPTY
```

### After v1.1.7 (FIXED):
```
1. Enter Claude API key
2. Switch to ChatGPT
3. Enter ChatGPT API key  
4. Click "Save AI Settings"
5. ✅ BOTH keys saved!
6. ✅ Claude key: ai_seo_api_key_claude
7. ✅ ChatGPT key: ai_seo_api_key_chatgpt
8. Switch back to Claude
9. ✅ Claude key still there!
```

---

## 🧪 TESTING AFTER UPGRADE

### Test Scenario 1: Fresh Setup
1. Install v1.1.7
2. Select Claude engine
3. Enter Claude API key
4. Click "Save AI Settings"
5. Switch to ChatGPT
6. Enter ChatGPT API key
7. Click "Save AI Settings"
8. **Switch back to Claude**
9. ✅ Claude API key should still be there

### Test Scenario 2: Existing Keys
If you had API keys before upgrading:
1. Install v1.1.7
2. Go to AI Settings
3. Your current engine's key should be there
4. Switch to another engine
5. If that engine had a key before, it should still be there
6. If not, enter a new key
7. Click "Save AI Settings"
8. Switch engines multiple times
9. ✅ All keys persist

### Test Scenario 3: Multiple Engines
1. Enter API keys for 3+ different engines:
   - Claude
   - ChatGPT
   - Google Gemini
2. Save
3. Switch between all engines
4. ✅ Each engine remembers its own API key

---

## 💾 HOW API KEYS ARE STORED

### WordPress Options Table:
```
ai_seo_api_key_chatgpt  = "sk-proj-..."
ai_seo_api_key_claude   = "sk-ant-api03-..."
ai_seo_api_key_google   = "AIza..."
ai_seo_api_key_openrouter = "sk-or-..."
ai_seo_api_key_microsoft = "..."
ai_seo_api_key_xai = "xai-..."
```

Each engine gets its own option in the WordPress database. When you switch engines, the plugin loads the correct key from the database.

---

## 🔧 TECHNICAL DETAILS

### Hidden Fields Implementation:
```html
<input type="hidden" name="ai_seo_api_key_chatgpt" id="ai-seo-hidden-key-chatgpt" value="..." />
<input type="hidden" name="ai_seo_api_key_claude" id="ai-seo-hidden-key-claude" value="..." />
<input type="hidden" name="ai_seo_api_key_google" id="ai-seo-hidden-key-google" value="..." />
...
```

### JavaScript Sync:
```javascript
// When API key is typed, update hidden field
$('#ai-seo-api-key').on('input change', function() {
    const currentEngine = $('#ai-seo-ai-engine').val();
    const apiKey = $(this).val();
    $('#ai-seo-hidden-key-' + currentEngine).val(apiKey);
});

// When engine is switched, update hidden field for previous engine
$('#ai-seo-ai-engine').on('change', function() {
    const previousEngine = $(this).data('previous-engine');
    const currentKey = $('#ai-seo-api-key').val();
    $('#ai-seo-hidden-key-' + previousEngine).val(currentKey);
});
```

### PHP Save Handler:
```php
// Save all engine-specific keys
$engines = ['chatgpt', 'claude', 'google', 'openrouter', 'microsoft', 'xai'];
foreach ($engines as $eng) {
    if (isset($input['ai_seo_api_key_' . $eng])) {
        $eng_key = sanitize_text_field($input['ai_seo_api_key_' . $eng]);
        if (!empty($eng_key)) {
            update_option('ai_seo_api_key_' . $eng, $eng_key);
        }
    }
}
```

---

## 🔄 UPGRADE INSTRUCTIONS

### From Any Previous Version:
1. **Deactivate** current plugin version
2. **Delete** current version
3. **Upload** v1.1.7 ZIP file
4. **Activate** plugin
5. **Go to:** AI SEO Content → AI Settings
6. **Check:** Your current engine's API key should still be there
7. **If keys were lost previously:** Re-enter them once
8. **Save AI Settings**
9. **From now on:** Keys will persist when switching!

### Settings Preserved:
✅ Current engine's API key (if not lost before)  
✅ All prompts  
✅ All tool settings  
✅ Temperature, max tokens, etc.  
⚠️ If keys were lost before upgrading, you'll need to re-enter them once

---

## 🐛 BUG FIXED

**Issue:** API keys lost when switching AI engines  
**Severity:** Critical - users had to re-enter keys constantly  
**Affected Versions:** v1.1.0 through v1.1.6  
**Status:** ✅ FIXED in v1.1.7

**User Impact:**
- Before: Had to memorize/store all API keys externally
- Before: Had to re-enter keys every time switching engines
- After: Enter each key ONCE, it's saved forever
- After: Switch engines freely without losing keys

---

## 📊 VERSION HISTORY

- **v1.1.3:** Attempted per-engine API key storage (but buggy)
- **v1.1.4-v1.1.6:** Various improvements, but key storage bug remained
- **v1.1.7:** ✅ **API KEY PERSISTENCE ACTUALLY WORKS NOW**

---

## 🎯 EXPECTED BEHAVIOR

### Scenario: Testing Multiple Engines

**Day 1:**
1. Set up Claude with API key
2. Generate 10 products
3. Works great!

**Day 2:**
1. Want to try ChatGPT
2. Switch to ChatGPT
3. Enter ChatGPT API key
4. Generate 5 products
5. Works great!

**Day 3:**
1. Want to compare quality
2. Switch back to Claude
3. ✅ Claude API key still there!
4. Generate content
5. ✅ Works immediately!
6. Switch to ChatGPT
7. ✅ ChatGPT key still there!

**No more re-entering API keys!**

---

## 💡 RECOMMENDED WORKFLOW

Now that keys persist, you can:

1. **Set up all your API keys once:**
   - Claude (for best quality)
   - ChatGPT (for testing/backup)
   - Google Gemini (for free tier)

2. **Switch engines freely:**
   - Use Claude for production
   - Use ChatGPT when Claude has issues
   - Use Gemini for high-volume testing

3. **Never re-enter keys again!**

---

## 🔗 RELATED IMPROVEMENTS

This fix also improves:
- **User experience:** No more frustration with lost keys
- **Workflow efficiency:** Test multiple engines without hassle
- **Reliability:** Keys are stored securely in WordPress database
- **Backup strategy:** Keep multiple engines ready as fallbacks

---

**Version:** 1.1.7  
**Build Date:** December 8, 2024  
**Critical Fix:** API Keys Now Persist Across Engine Switches  
**Status:** Production Ready - Key Management Fixed!

---

## 🙏 USER FEEDBACK

This fix was identified by user testing! A user noticed:
> "The API Key that was there for Claude is no longer there after I set up ChatGPT, so it seems it is not retaining the API after I changed it to the other. This is a problem as it's a pain to set up the API to begin with."

Thank you for reporting this critical issue! This fix ensures no one else has to deal with lost API keys.
