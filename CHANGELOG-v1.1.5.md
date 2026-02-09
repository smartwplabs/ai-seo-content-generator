# AI SEO Content Generator - v1.1.5 Changelog

**Release Date:** December 7, 2024  
**Status:** CRITICAL FIX - Claude API Support Added

---

## 🚨 CRITICAL BUG FIX

### Claude (Anthropic) API Support Added

**Problem:** The plugin listed "Claude (Anthropic)" as an AI engine option but didn't actually have the API integration code implemented. This caused all Claude API calls to fail with "Unsupported AI engine: claude" error.

**Fix:** Added complete Claude API integration:
- Implemented `ai_seo_call_claude()` function
- Added Claude case to AI engine switch statement
- Uses Anthropic Messages API (v2023-06-01)
- Proper error handling for Claude-specific responses
- Supports all Claude models (3.5 Sonnet, 3 Opus, 3 Haiku)

---

## 📋 WHAT WAS ADDED

### Claude API Function
```php
function ai_seo_call_claude($api_key, $model, $prompt, $max_tokens, $temperature)
```

**Features:**
- Endpoint: `https://api.anthropic.com/v1/messages`
- Headers: Proper x-api-key and anthropic-version
- Request format: Messages API format
- Response parsing: Extracts text from content array
- Error handling: Returns descriptive WP_Error on failure

**Supported Parameters:**
- `model`: claude-3-5-sonnet-20241022, claude-3-opus-20240229, etc.
- `max_tokens`: 1024-8192 (configurable in settings)
- `temperature`: 0.0-1.0 (default 0.7)

---

## 🔍 DEBUG IMPROVEMENTS (Carried from v1.1.4.4)

### Enhanced Error Reporting
The plugin now shows detailed debug information when generation fails:

**Debug Fields:**
- `status`: Current processing status
- `ai_engine`: Which AI engine was selected
- `api_key_present`: Whether API key exists
- `attempting_focus_keyword`: Confirms API call attempt
- `error_step`: Which generation step failed
- `api_error`: Exact error message from API
- `product_type`: Simple, variable, etc.
- `post_status`: publish, draft, etc.
- `title`: Product title

**Example Debug Output:**
```json
{
  "229037": {
    "status": "api_error",
    "title": "Silver or Gold 3D Cowboy Hat Charm",
    "product_type": "variable",
    "ai_engine": "claude",
    "api_key_present": true,
    "error_step": "focus_keyword_generation",
    "api_error": "Invalid API key"
  }
}
```

---

## ⚙️ TECHNICAL DETAILS

### API Request Format (Claude)
```json
{
  "model": "claude-3-5-sonnet-20241022",
  "max_tokens": 4096,
  "temperature": 0.7,
  "messages": [
    {
      "role": "user",
      "content": "Generate SEO keyword for..."
    }
  ]
}
```

### API Response Format (Claude)
```json
{
  "content": [
    {
      "type": "text",
      "text": "Generated content here"
    }
  ]
}
```

---

## 🐛 BUG FIXED

**Issue:** "Unsupported AI engine: claude"  
**Cause:** Missing Claude API implementation  
**Status:** ✅ FIXED in v1.1.5

**Before v1.1.5:**
- Dropdown showed "Claude (Anthropic)" option
- Selecting Claude caused error
- All generations failed with "Unsupported AI engine"

**After v1.1.5:**
- Claude option works correctly
- Calls Anthropic Messages API
- Generates content successfully
- Proper error messages if API key invalid

---

## 💰 COST INFORMATION

### Claude API Pricing (as of Dec 2024)
- **Claude 3.5 Sonnet:** $3.00 per 1M input tokens, $15.00 per 1M output tokens
- **Per product (average):** ~$0.008 (less than a penny)
- **100 products:** ~$0.80
- **1000 products:** ~$8.00

### Credits Required
Users must add credits to Anthropic account at: https://console.anthropic.com/settings/billing

Minimum recommended: $5-10 for testing

---

## 🔄 UPGRADE INSTRUCTIONS

### From v1.1.4.x:
1. Deactivate current version
2. Delete current version  
3. Upload v1.1.5 ZIP
4. Activate
5. Hard refresh browser (Ctrl+Shift+R)
6. Verify version shows "1.1.5" in Plugins
7. Verify JavaScript shows `ver=1.1.5` in console

### Settings Preserved:
✅ All AI Settings (API keys, models, parameters)  
✅ All Tool Settings (checkboxes, options)  
✅ All Prompts (custom or default)  
✅ Per-engine API keys remain intact

---

## ✅ TESTING CHECKLIST

After upgrading to v1.1.5:

**1. Verify Installation:**
- [ ] Plugins page shows "Version 1.1.5"
- [ ] Browser console shows `ai-seo-admin.js?ver=1.1.5`

**2. Test Claude API:**
- [ ] AI Settings → AI Engine: "Claude (Anthropic)"
- [ ] Enter Claude API key (from console.anthropic.com)
- [ ] Select model: "Claude 3.5 Sonnet (Recommended)"
- [ ] Click "Save AI Settings"

**3. Generate Content:**
- [ ] Select 1 product
- [ ] Click "Generate Content"
- [ ] Click "Start Generation"
- [ ] Should see success (not "Unsupported AI engine")
- [ ] Product should have new content generated

**4. Verify Anthropic Console:**
- [ ] Go to: https://console.anthropic.com/settings/keys
- [ ] Your API key should show "Last used: [today's date]"
- [ ] Should see usage/cost incremented

---

## 🎯 EXPECTED RESULTS

### Successful Generation:
```
✓ Successfully Generated Content
Products Processed: 1

Product ID 229037:
  • Keyword: Silver Gold 3D Western Cowboy Hat Charm
  • Title: Premium Silver or Gold 3D Western Cowboy Hat Charm
  • Meta: Authentic silver or gold 3D cowboy hat charm...
```

### If API Key Invalid:
```
⚠️ Debug Information
Product ID 229037:
  • AI Engine: claude
  • API Key Present: Yes
  • Failed At: focus_keyword_generation
  • API Error: invalid x-api-key
```

---

## 🔗 RELATED RESOURCES

- Anthropic Console: https://console.anthropic.com
- Claude API Docs: https://docs.anthropic.com/en/api
- Get API Key: https://console.anthropic.com/settings/keys
- Pricing Info: https://www.anthropic.com/pricing

---

## 📝 NOTES

### Why Claude API Wasn't Implemented Initially
The plugin framework was built with multiple AI engine support (ChatGPT, Google Gemini, OpenRouter, etc.) but Claude integration was incomplete in the initial releases. This has now been corrected.

### Alternative Free Option
If you don't want to pay for Claude API:
- Use **Google Gemini** (free tier available)
- Change AI Engine to "Google Gemini"
- Get free API key from: https://aistudio.google.com/app/apikey
- No credit card required for free tier

---

## 🚀 WHAT'S NEXT

### Future Versions:
- v1.1.6: Remove debug code, clean up console logging
- v1.2.0: Bulk generation improvements, content preview
- v1.3.0: Cost tracking, A/B testing, automatic model selection

---

**Version:** 1.1.5  
**Build Date:** December 7, 2024  
**Critical Fix:** Claude API Support Added  
**Status:** Production Ready with Claude Support
