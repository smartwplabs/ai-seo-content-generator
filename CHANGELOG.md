# AI SEO Content Generator - Changelog

## v2.1.21 - March 2026
**Bug Fix: AI Search Only Mode**
- Fixed critical bug where `ai_search_only` mode generated no content. AI Search jobs have dependencies on SEO fields (`focus_keyword`, `title`) that don't exist in the batch when in `ai_search_only` mode. The dependency checker treated `null` (job doesn't exist) the same as `pending` (job not yet done), blocking all AI Search jobs. Fix: a missing dependency job is now treated as satisfied.

## v2.1.20 - February 2026
- Added Description Length setting (standard/long/premium)

## v2.1.0 - January 2025
**Major Update: AI Search Feature Rebuild**
- Research-based rebuild based on direct testing with Perplexity, ChatGPT, Google AI
- AI Search content now displays in visible WooCommerce product tabs (H2/H3 structure) instead of hidden meta tags
- Reduced from 12 AI Search fields to 5 proven fields: FAQ, Care Instructions, Product Highlights, Pros/Cons, Alt Names
- Removed fields with no proven AI value: AI Summary, Target Audience, Value Proposition, Use Cases, Problem Solved, Seasonal Relevance, Speakable, all custom meta tags
- Added Standalone SEO mode (works without Rank Math/Yoast/AIOSEO/SEOPress)
- Added Display Settings subtab with Combined Tab, Separate Tabs, Append to Description, Schema Only options
- Updated all prompts for snippet optimization (FAQ answers 40-80 words, care steps 1 sentence each)
- Faster generation and lower API costs (5 fields vs 12)

## v2.0.0 - January 2025
**Major Release: Background Processing**
- Complete redesign to eliminate timeout issues
- Job Queue System: generation jobs queued in database
- Background Worker: WordPress Action Scheduler processes jobs independently
- Instant response: browser returns immediately after clicking Generate
- Real-time polling shows field-by-field progress
- Resumable: close browser, come back later
- No timeouts: each job is ~5-10 seconds
- New database tables: `ai_seo_generation_batches` and `ai_seo_generation_jobs`
- Auto-cleanup after 7 days
- Automatic retry on timeout errors (up to 2 retries)
- Critical field failures trigger backup restore

## v1.3.1a - December 14, 2024
**Dashboard Reorganization**
- Reorganized Tools tab into clearer sections
- Provider-specific settings separated from universal performance settings
- New "Performance & Timing Controls" section for timing sliders
- Reset Button Position moved to UI section

## v1.3.1 - December 14, 2024
**Performance & Timing Controls**
- Image Optimizer Bypass: temporarily disable ShortPixel/WP Smush/Imagify/EWWW/Optimole during generation (saves ~12-15 sec/product)
- Configurable Score Calculation Wait Time slider (3-25 seconds)
- Configurable Post-Save Processing Delay slider (0-5 seconds)
- Real-time slider value updates
- Fixed browser password save prompts on API key field (added autocomplete="off")
- Improved default title prompt: titles now START with focus keyword

## v1.3.0b - December 13, 2024
- Improved "Generate Content" button tooltip clarity

## v1.3.0a - December 13, 2024
**Provider-Aware UI**
- Dashboard adapts labels to detected SEO plugin (Rank Math, Yoast, AIOSEO, SEOPress)
- SEO Provider status box in Tools tab
- Score calculation section shows/hides based on provider capabilities
- Yoast/SEOPress users see informative message instead of non-applicable score options

## v1.3.0 - December 13, 2024
**Multi-SEO Plugin Support**
- Added provider abstraction layer
- Support for Rank Math, Yoast SEO, All in One SEO, SEOPress
- Standalone mode when no SEO plugin detected

## v1.2.1.13 - December 13, 2024
**Accordion Interference Fix**
- Root cause of dropdown double-click bug: accordion header click handler was toggling on every click including form element clicks
- Fixed accordion to ignore clicks on select, input, textarea, button, a elements
- Dropdowns in Prompts tab now work on first click

## v1.2.1.12 - December 13, 2024
- Added stopPropagation to dropdown clicks (partial fix, root cause fixed in v1.2.1.13)

## v1.2.1.11 - December 13, 2024
**Button UX Fix**
- Generate Content button no longer required double-click
- Removed mouseenter/mouseleave cursor handlers; cursor only changes to "move" while actively dragging

## v1.2.1.10 - December 13, 2024
**Smart Keyword Sanitization**
- AI sometimes included markdown headers and labels in focus keyword output
- New `ai_seo_sanitize_focus_keyword()` function strips markdown headers, label patterns, bullet points, duplicate lines
- Preserves legitimate content (e.g. "SEO Analysis Tool" product keyword)

## v1.2.1.9 - December 13, 2024
- Smart progress display: single product shows simple "Processing..." message, 2+ products show full progress bar

## v1.2.1.8a - December 13, 2024
- Fixed progress bar timing: bar now updates AFTER each product completes, not before it starts

## v1.2.1.8 - December 13, 2024
**Progress Bar**
- Animated progress bar for bulk generation
- Real-time product counter (3 of 10), percentage, status message
- ETA calculator based on actual elapsed time
- Sequential processing (one product at a time via AJAX) replacing batch processing

## v1.2.1.7b - December 13, 2024
- Fixed Enable RankMath Score Calculation setting not saving (missing from settings registration array)

## v1.2.1.7a - December 13, 2024
- Hotfix: increased RankMath calculation wait from 5 to 7 seconds for variable products

## v1.2.1.7 - December 13, 2024
- Added global enable/disable setting for RankMath Score Calculation in Tools tab
- Updated tooltip with concrete timing examples per batch size

## v1.2.1.6 - December 12, 2024
- Optimized timing: 12 seconds → 5 seconds wait (measured actual calculation at 3.53 seconds)
- 53% faster score calculation

## v1.2.1.5 - December 12, 2024
- Made score calculation optional: checkbox + "Calculate Scores Now" / "Close Without Calculating" buttons

## v1.2.1.4 - December 12, 2024
- Fixed score calculation: now actually clicks the Update button inside iframe to trigger WordPress save
- Multiple button selector fallbacks (#publish, .editor-post-publish-button, button[type="submit"])

## v1.2.1.3 - December 12, 2024
- Proper iframe approach: load edit page, wait 12 seconds, backend save
- RankMath calculates client-side JS; must load actual page

## v1.2.0 - December 8, 2024
**Draggable Button + Auto Score Updates**
- Drag & drop "Generate Content" button to any position (saved per user)
- Auto RankMath score update after generation (triggers save_post hooks)
- Removed debug popup

## v1.1.9 - December 8, 2024
- Help tooltips on all settings
- Working Buffer implementation (delay between products to prevent rate limits, default 3 seconds)
- Accordion UI for Prompts tab
- Optional sticky "Generate Content" button

## v1.1.8 - December 8, 2024
**RankMath 90-100/100 Optimization**
- All 6 prompts now use [current_attributes] - preserves product specifications
- Power words updated to RankMath-recognized only (removed "chic", added "Stunning", "Perfect", "Brilliant")
- Full descriptions mandated to use short paragraphs (2-3 sentences max)
- Image alt tags enabled by default
- Claude 4.5 models added to dropdown

## v1.1.7 - December 8, 2024
- Fixed API keys being lost when switching between AI engines (added hidden fields for all 6 engines)

## v1.1.6 - December 7, 2024
- Fixed Claude model name (claude-3-5-sonnet-20240620, was 20241022)

## v1.1.5 - December 7, 2024
- Added Claude (Anthropic) API implementation (was listed but not implemented)
- Added debug fields to error responses

## v1.1.4 - December 7, 2024
**CSP Compliance + Prompt Updates**
- Removed all inline event handlers (oninput="") - now CSP compliant
- Works with Cloudways + Cloudflare strict security without exceptions
- All 6 prompts rewritten with category-aware logic (Fine vs Fashion jewelry)
- Max tokens increased to 8192
- Custom model input fix

## v1.1.3 - December 6, 2024
- Per-engine API key storage (6 separate options in database)

## v1.1.2 - December 6, 2024
- Smart engine switching with confirmation dialog
- Collapsible popup prompts

## v1.1.1 - December 6, 2024
- Moved System Prompt and Content Length to Prompts tab
- Simplified Advanced Settings (removed Top P, Buffer)
- All 6 prompts in popup

## v1.1.0 - December 6, 2024
- Original major update with Claude support, Rank Math 90-100/100 optimization, 12 tools, enhanced prompts
