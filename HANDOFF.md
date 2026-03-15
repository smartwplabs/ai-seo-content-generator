# AI SEO Content Generator - HANDOFF

**Last Updated:** March 2026  
**Current Version:** 2.1.21  
**Repo:** smartwplabs/ai-seo-content-generator (GitHub, private)  
**Plugin Location on Site:** Chaney's Jewelry (chaneysjewelry.com) staging + production

---

## What This Plugin Does

WordPress/WooCommerce plugin that generates SEO content for products using AI (Claude, ChatGPT, OpenRouter, etc.). User supplies their own API keys. Generates: focus keyword, title, short description, full description, meta description, tags, image alt text, and AI Search fields (FAQ, care instructions, highlights, pros/cons, alt names).

**Key design principle:** Customer supplies their own API keys. No SaaS dependency. Multi-provider AI support.

---

## Architecture Overview

### Background Processing (v2.0+)
Jobs are queued in the database and processed by WordPress Action Scheduler (bundled with WooCommerce). The browser polls for status every few seconds.

**Flow:**
1. User selects products → clicks Generate Content
2. `ajax-queue.php` creates batch + jobs in database → returns immediately
3. Action Scheduler processes jobs in background (one field at a time)
4. Browser polls `ajax-queue.php` for status
5. Results displayed when complete

### Key Files
```
ai-seo-content-generator.php          # Main plugin file, version constant
admin/dashboard.php                    # All admin UI (tabs: AI Settings, Prompts, Tools, AI Search)
admin/functions.php                    # Admin helper functions
includes/
  ajax.php                             # Legacy synchronous AJAX handler (still used for single product metabox)
  ajax-queue.php                       # Background processing AJAX endpoints
  utils.php                            # AI API calls (ai_seo_call_ai_engine), sanitization
  dependencies.php                     # Plugin dependency checks
  standalone-seo.php                   # SEO meta output when no SEO plugin present
  seo-provider-interface.php           # Provider abstraction
  providers/                           # Rank Math, Yoast, AIOSEO, SEOPress providers
  background/
    database.php                       # Creates/manages job queue tables
    class-batch-manager.php            # Batch CRUD, status tracking
    class-job-manager.php              # Job CRUD, FIELD_ORDER constant, dependency logic
    class-field-processor.php          # Action Scheduler worker, calls AI API
  ai-search/
    generation.php                     # AI Search field hooks (filters into main generation)
    prompts.php                        # AI Search prompts
    schema-output.php                  # Tab display + JSON-LD schema output
    metabox.php                        # AI Search metabox on product edit page
    license.php                        # License check for AI Search add-on
assets/
  js/ai-seo-admin.js                   # Main admin JS (product list UI, button, popup)
  js/ai-seo-queue.js                   # Background processing polling UI
  css/ai-seo-admin.css                 # Admin styles
```

### Database Tables
- `{prefix}ai_seo_generation_batches` — one row per "Generate" click
- `{prefix}ai_seo_generation_jobs` — one row per field per product
- Auto-cleanup after 7 days

### FIELD_ORDER (class-job-manager.php)
```php
'focus_keyword'    => ['order' => 1,  'dependencies' => [], ...]
'title'            => ['order' => 2,  'dependencies' => ['focus_keyword'], ...]
'short_description'=> ['order' => 3,  'dependencies' => ['focus_keyword', 'title'], ...]
'full_description' => ['order' => 4,  'dependencies' => ['focus_keyword', 'title'], ...]
'meta_description' => ['order' => 5,  'dependencies' => ['focus_keyword'], ...]
'tags'             => ['order' => 6,  'dependencies' => ['focus_keyword'], ...]
'image_alt'        => ['order' => 7,  'dependencies' => ['focus_keyword', 'title'], ...]
'product_summary'  => ['order' => 10, 'dependencies' => ['focus_keyword', 'title'], ...]
'faq_schema'       => ['order' => 11, 'dependencies' => ['focus_keyword', 'title'], ...]
'care_instructions'=> ['order' => 12, 'dependencies' => ['focus_keyword', 'title'], ...]
'product_highlights'=> ['order' => 13,'dependencies' => ['focus_keyword', 'title'], ...]
'pros_cons'        => ['order' => 14, 'dependencies' => ['focus_keyword', 'title'], ...]
'alt_names'        => ['order' => 15, 'dependencies' => ['focus_keyword'], ...]
```

---

## Generation Modes (Tools Tab)

- **Both** — SEO fields + AI Search fields
- **SEO Only** — focus_keyword through image_alt (orders 1-7)
- **AI Search Only** — AI Search fields only (orders 10-15)

**Critical bug fixed in v2.1.21:** In `ai_search_only` mode, AI Search jobs have dependencies on `focus_keyword` and `title`, which aren't created in the batch. The dependency checker was treating `null` status (job doesn't exist) as "not satisfied", blocking all jobs. Fix: `null` status now means "dependency not needed, skip it."

---

## AI Search Fields (Requires AI Search Add-on License)

5 proven fields (v2.1.0 reduced from 12):
- `faq_schema` — FAQPage JSON-LD + visible H2/H3 in product tab
- `care_instructions` — HowTo JSON-LD + visible ordered list in tab
- `product_highlights` — Product.additionalProperty + bullet list in tab
- `pros_cons` — visible pros/cons lists in tab
- `alt_names` — Product.alternateName in schema only

Display options: Combined Tab, Separate Tabs, Append to Description, Schema Only.

---

## WordPress Database Settings Keys

- `ai_seo_settings` — AI engine, model, API key, max tokens, temperature, etc.
- `ai_seo_api_key_{engine}` — Per-engine API keys (chatgpt, claude, google, openrouter, microsoft, xai)
- `ai_seo_prompts` — 6 SEO prompts
- `ai_seo_tools` — Tool toggles including generation_mode, enable_score_calculation, disable_image_optimization, score_wait_time, post_save_delay
- `ai_seo_search_tools` — AI Search field toggles
- `ai_seo_search_display` — Display mode settings

---

## Known Issues / Open Items

1. **Changelog file explosion** — Repo has 30+ individual CHANGELOG-vX.X.md files from Claude Code creating new files instead of appending. CHANGELOG.md now exists as the consolidated version. Old files should be deleted from repo.

2. **AI Search generation on second product** — Previously intermittent; root cause was cURL timeout on `full_description` (>60 seconds). Background processing in v2.0 should have resolved this, but monitor.

3. **Score calculation timing** — Configurable in Tools tab. Default 5 seconds. Sites with image optimizers (ShortPixel) need ~18 seconds. Image optimizer bypass feature available.

---

## Repo State

- **GitHub:** `smartwplabs/ai-seo-content-generator` (private)
- **Old messy repo:** `BC8144/ai-seo-content-generator` — ignore, superseded
- **Claude Code access:** Use "Full GitHub Access" environment (has GH_TOKEN with full repo scope)

---

## How to Start a Session on This Plugin

1. Read this file
2. Read `CHANGELOG.md` for version history
3. Check the specific files relevant to what you're fixing
4. The main plugin file is `ai-seo-content-generator.php` — version is defined there

---

## Testing Environment

- Chaney's Jewelry staging on Cloudways
- WooCommerce + Rank Math + WP All Import
- ShortPixel active (causes slow page saves ~17 seconds — use image bypass)
- Three jewelry supplier feeds (Silver Stars, J Goodin, Infinite Jewels)
