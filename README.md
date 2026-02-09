# AI SEO Content Generator v2.0.0

## 🚀 What's New in v2.0.0 - Background Processing

### Major Feature: Background Processing
The plugin now processes content generation in the background, eliminating timeout issues completely.

**Before (v1.x):**
- Browser waited 60-120 seconds per product
- Frequent timeouts on shared hosting
- Had to keep browser open

**After (v2.0.0):**
- Click "Generate" → Browser returns instantly
- Real-time progress: "Generating title for Product #123..."
- Close browser, come back later, generation continues
- Works on ANY WordPress hosting

### How It Works
1. Select products → Click "Generate Content"
2. Jobs queued in database → Action Scheduler processes in background
3. Browser polls every 3 seconds for updates
4. See progress field-by-field across all products
5. When complete → Results displayed

### Technical Details
- Uses WordPress Action Scheduler (same as WooCommerce)
- 18 fields per product processed sequentially
- Automatic retry on timeout errors
- Critical failures trigger backup restore
- 7-day automatic cleanup of old job data

---

## 📥 Installation
5. Configure AI Settings (add API key)

### Upgrade from v1.0.x:
1. **Deactivate** current plugin (Settings will be preserved)
2. **Delete** old plugin
3. Upload new v1.1.0 ZIP
4. **Activate** new version
5. **Review settings** - New options have been added with smart defaults

---

## ⚙️ Quick Setup

### 1. AI Settings Tab
- **AI Engine**: Choose your preferred AI (ChatGPT, Claude, Google, etc.)
- **API Key**: Enter your API key
- **Model**: Select from dropdown (shows recommendations)
- **System Prompt** (Optional): Global instructions like "Write in a friendly tone for budget-conscious shoppers"
- **Content Length**: Choose Standard (default), Long, or Premium

### 2. Tools Tab (Enable Recommended)
**Content Generation:**
- ☑ Generate Product Title
- ☑ Generate Meta Description  
- ☑ Include Power Word in Title
- ☐ Include Number in Title (optional)

**SEO Integration:**
- ☑ Update Rank Math Fields

**URL Optimization:**
- ☑ Focus Keyword in URL
- ☑ Permalink Manager Pro Support (if you use that plugin)

**Integrations:**
- ☐ Update Image Alt Tags (if you use Auto Image Attributes plugin)

### 3. Prompts Tab
- Default prompts are optimized for Rank Math 100/100
- Customize if needed (click "Available Placeholders" for help)
- Use System Prompt for brand voice consistency

---

## 🎯 Expected Rank Math Scores

**Before v1.1.0:** 50-70/100 (Yellow/Red)
**After v1.1.0:** 90-100/100 (Green) ✅

### What Improved:
- ✅ Focus keyword in first 10% of content
- ✅ Focus keyword in H2 headings
- ✅ Optimal keyword density (2-3%)
- ✅ Power words in titles
- ✅ Proper content length
- ✅ Clean, keyword-rich URLs
- ✅ Image alt tags with keywords

---

## 🤖 AI Engines Supported

1. **ChatGPT (OpenAI)**
   - Models: GPT-4o, GPT-4o Mini, GPT-4 Turbo, o1-preview, o1-mini
   - Best for: General content, fast generation

2. **Claude (Anthropic)** ✨ NEW
   - Models: Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Haiku
   - Best for: Creative descriptions, brand voice consistency

3. **Google Gemini**
   - Models: Gemini 1.5 Pro, Gemini 1.5 Flash
   - Best for: Technical content, structured data

4. **OpenRouter**
   - Access to multiple models
   - Best for: Advanced users, testing different models

5. **Microsoft Azure OpenAI**
   - Your custom deployments
   - Best for: Enterprise, compliance requirements

6. **X.AI Grok**
   - Grok Beta
   - Best for: Conversational, up-to-date content

---

## 📝 New Default Prompts

All prompts have been rewritten for Rank Math optimization:

**Focus Keyword:**
- Generates specific, targetable 2-4 word phrases
- Emphasizes searchability and ranking potential

**Title:**
- Starts with focus keyword
- Includes power words
- Under 60 characters
- Optional numbers/statistics

**Descriptions:**
- HTML structure with H2 headings
- Keyword in first sentence
- 2-3% keyword density
- 7th-8th grade reading level
- Call-to-action included

**Meta Description:**
- Exactly 150-160 characters
- Starts with focus keyword
- Compelling and click-worthy

---

## 🔧 Troubleshooting

### Rank Math Score Still Low?
1. Verify "Generate Title from Keywords" is enabled in Tools
2. Check focus keyword was generated (view product edit page)
3. Ensure descriptions have HTML (view source code)
4. Review `/wp-content/ai-seo-debug.log` for errors

### Model Dropdown Not Working?
1. Clear browser cache
2. Disable other plugins temporarily
3. Check JavaScript console for errors

### Image Alt Tags Not Updating?
1. Verify "Update Image Alt Tags" is enabled in Tools
2. Check if product has images attached
3. If using Auto Image Attributes plugin, verify it's active

---

## 📊 Changelog

### v1.1.0 - December 6, 2024
**Added:**
- Claude (Anthropic) AI engine support
- Model dropdown with presets per engine
- System Prompt field for global AI instructions
- Content Length preference (Standard/Long/Premium)
- Include Number in Title tool
- Image Alt Tags integration
- Permalink Manager Pro compatibility
- Enhanced placeholder documentation
- Reorganized Tools into 4 categories

**Fixed:**
- Focus keyword generation order (now FIRST)
- Rank Math keyword usage (saves immediately)
- Short & full descriptions now generate
- HTML structure in descriptions
- Keyword placement optimization
- Power Words implementation
- Sentiment in Title implementation

**Improved:**
- All default prompts optimized for Rank Math
- Better UI organization
- More helpful tooltips and descriptions
- Clearer settings labels

### v1.0.1 - Previous Version
- Initial release with 5 AI engines
- Basic Rank Math integration
- Prompts tab implementation

---

## 🆘 Support

**Need Help?**
- Check the debug log: `/wp-content/ai-seo-debug.log`
- Review Tools settings (most issues are disabled tools)
- Verify API key is correct and has permissions
- Test on a single product before bulk use

**Common Issues:**
- **No generation happening**: Check API key is entered
- **Wrong keyword used**: Make sure using v1.1.0 (generation order fixed)
- **Missing HTML**: Re-save Prompts tab to use new defaults
- **Low Rank Math score**: Enable all recommended Tools

---

## ✅ Requirements

- WordPress 6.0 or higher
- WooCommerce 7.0 or higher
- Rank Math SEO 1.0.95 or higher
- PHP 7.3 or higher
- Valid API key for your chosen AI engine

---

## 📄 License

GPL v2 or later

---

**Version:** 1.1.0  
**Release Date:** December 6, 2024  
**Status:** Production Ready  
**Rank Math Optimization:** 90-100/100 target score

🎊 Enjoy your improved SEO scores! 🎊
