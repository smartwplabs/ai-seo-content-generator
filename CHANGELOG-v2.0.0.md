# AI SEO Content Generator - v2.0.0 Changelog

**Release Date:** January 2025  
**Type:** Major Release - Background Processing

## 🚀 Major New Feature: Background Processing

This release completely redesigns how content generation works to eliminate timeout issues.

### The Problem (v1.x)
- 18 API calls per product (6 SEO + 12 AI Search fields)
- Each product took 60-120 seconds
- Browser had to stay open the entire time
- Server/browser timeouts on shared hosting
- Partial failures could leave products in broken state

### The Solution (v2.0.0)
- **Job Queue System**: Generation jobs are queued in the database
- **Background Worker**: WordPress Action Scheduler processes jobs independently
- **Instant Response**: Browser returns immediately after clicking "Generate"
- **Real-time Progress**: Polling shows field-by-field progress
- **Resumable**: Close browser, come back later, pick up where you left off
- **No Timeouts**: Each job is ~5-10 seconds, well under any timeout limit

## New Files Added

```
includes/background/
├── database.php           # Job queue tables
├── class-job-manager.php  # Individual job CRUD
├── class-batch-manager.php# Batch management
└── class-field-processor.php # Action Scheduler worker

includes/ajax-queue.php    # New AJAX endpoints
assets/js/ai-seo-queue.js  # Polling UI
```

## Database Changes

Two new tables are created on activation:
- `{prefix}ai_seo_generation_batches` - Tracks each "Generate" button click
- `{prefix}ai_seo_generation_jobs` - Tracks individual field generation tasks

Tables are automatically cleaned up after 7 days.

## New Settings

**Tools Tab → Background Processing:**
- Enable/disable background processing (enabled by default)
- Falls back to legacy synchronous mode if disabled

## How It Works Now

1. Select products → Click "Generate Content"
2. Browser sends product IDs to server (~100ms response)
3. Server creates jobs in database, schedules Action Scheduler
4. Browser starts polling every 3 seconds
5. Background: Action Scheduler picks up jobs one at a time
6. Progress UI shows: "Generating title for Product #123..."
7. When all done → Results displayed

## Technical Details

- Uses **Action Scheduler** (bundled with WooCommerce)
- 2-second delay between API calls (prevents rate limiting)
- Automatic retry on timeout errors (up to 2 retries)
- Critical field failures trigger backup restore
- Works on any WordPress hosting (no server config needed)

## Upgrade Notes

- **Automatic Migration**: Tables created on first load after update
- **Backward Compatible**: Legacy mode available via toggle
- **Existing Settings Preserved**: All your prompts and settings remain unchanged

## Known Limitations

- Requires WooCommerce (for Action Scheduler)
- Processing is sequential (one field at a time) to respect API rate limits
- Very large batches (100+ products) may take a while but will complete reliably

## Credits

Background processing architecture inspired by WooCommerce's own approach to handling bulk operations reliably across all hosting environments.
