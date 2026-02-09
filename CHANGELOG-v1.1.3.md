# AI SEO Content Generator v1.1.3

## 🔑 Smart API Key Management

### Changes in v1.1.3:

✅ **API Keys Now Saved Per Engine**

**The Problem (v1.1.2):**
- Switching engines would clear your API key
- Had to re-enter keys every time you switched
- Annoying if you wanted to test different AI engines

**The Solution (v1.1.3):**
- **Each engine saves its own API key**
- Switch between engines freely
- Keys are automatically loaded when you switch back
- Never lose your keys again!

---

## 🎯 How It Works Now:

### Example Workflow:

1. **Select ChatGPT** → Enter ChatGPT API key → Save
   - ✅ ChatGPT key saved

2. **Switch to Claude** → Field is empty (first time)
   - Enter Claude API key → Save
   - ✅ Claude key saved
   - ✅ ChatGPT key still saved in background

3. **Switch back to ChatGPT**
   - ✅ **Your ChatGPT key automatically appears!**
   - ✅ Model dropdown shows ChatGPT models

4. **Switch to Claude again**
   - ✅ **Your Claude key automatically appears!**
   - ✅ Model dropdown shows Claude models

5. **Try Google Gemini** → Field is empty (first time)
   - Enter Google key → Save
   - ✅ All 3 keys now saved!

**You can now freely test different AI engines without losing your keys!**

---

## 🔧 Technical Details:

### API Key Storage:
- Each engine has its own option: `ai_seo_api_key_chatgpt`, `ai_seo_api_key_claude`, etc.
- Keys are saved when you switch away from an engine
- Keys are loaded when you switch to an engine
- All keys persist across WordPress sessions

### Model Dropdown:
- **Still switches automatically** when engine changes
- Shows correct models for each engine
- Selects recommended default model
- Does NOT save per engine (always uses dropdown default)

---

## 📋 Supported Engines:

Each saves its own key:
- ✅ ChatGPT (OpenAI)
- ✅ Claude (Anthropic)
- ✅ Google Gemini
- ✅ OpenRouter
- ✅ Microsoft Azure OpenAI
- ✅ X.AI Grok

---

## 🔄 Upgrade from v1.1.2:

1. Deactivate v1.1.2
2. Delete v1.1.2
3. Upload v1.1.3
4. Activate v1.1.3

**Note:** If you only have one API key entered in v1.1.2, you'll need to enter it once more for that specific engine. After that, it will be saved permanently for that engine.

---

## ✅ What Still Works:

- ✅ Collapsible popup prompts (from v1.1.2)
- ✅ Global Settings in Prompts tab (from v1.1.1)
- ✅ All 6 prompts in popup (from v1.1.1)
- ✅ Rank Math 90-100/100 optimization
- ✅ All 12 tools
- ✅ All 6 AI engines

**Plus now you can switch engines freely!** 🎉

---

## 💡 Why This Matters:

**Before v1.1.3:**
- "Let me try Claude... oh wait, I need to save my ChatGPT key first"
- "Okay, tried Claude, now let me switch back... darn, have to find my ChatGPT key again"

**With v1.1.3:**
- "Let me try Claude" → Switch → Done!
- "Back to ChatGPT" → Switch → Key is already there!
- "Let's test Google too" → Switch → Easy!

**Perfect for testing different AI engines to see which gives you the best Rank Math scores!**

---

## 📋 Full Version History:

### v1.1.3 - December 6, 2024 (Late Evening)
**Added:**
- Per-engine API key storage (6 separate keys)
- Automatic key saving when switching engines
- Automatic key loading when selecting engine
- Data attributes to track saved keys

**Improved:**
- No more confirmation dialogs
- Seamless engine switching
- Better user experience for multi-engine testing

### v1.1.2 - December 6, 2024 (Evening)
- Collapsible popup prompts
- Smart engine switching (replaced in v1.1.3)

### v1.1.1 - December 6, 2024 (Afternoon)
- Moved System Prompt & Content Length to Prompts tab
- Simplified Advanced Settings

### v1.1.0 - December 6, 2024 (Morning)
- Original major update with Rank Math optimization

---

**Version:** 1.1.3  
**Release Date:** December 6, 2024  
**Status:** Production Ready  
**Focus:** Multi-Engine API Key Management  
**Best For:** Users who want to test different AI engines without hassle
