# AI SEO Content Generator - v2.1.0 Changelog

**Release Date:** January 2025  
**Type:** Major Update - AI Search Feature Rebuild

## 🔬 Research-Based Rebuild

This release completely rebuilds the AI Search feature based on direct research with AI search engines (Perplexity, ChatGPT, Google AI) to determine what actually works vs. what doesn't.

## 🆕 New Features

### 1. Visible Tab Display
AI Search content now displays in WooCommerce product tabs where AI crawlers can actually read it.

**Display Options:**
- **Combined Tab** - One "Product Info" tab with FAQ, Care, Highlights, Pros/Cons
- **Separate Tabs** - Individual tabs for FAQ, Care Instructions, Details
- **Append to Description** - Add content below product description
- **Schema Only** - Output schema without displaying (not recommended)

**Why This Matters:**
AI search engines read visible content, not hidden meta tags. This change makes content extractable.

### 2. Standalone SEO Mode
Works without Rank Math, Yoast, AIOSEO, or SEOPress.

**When no SEO plugin detected, we output:**
- Meta title (`<title>` tag)
- Meta description
- Canonical URL
- Robots meta
- Open Graph tags (Facebook)
- Twitter Cards
- Product price/availability meta

**Shop owners now have two options:**
- Have an SEO plugin → We feed their fields
- No SEO plugin → We handle it directly

### 3. Snippet-Optimized Prompts
All prompts updated based on Perplexity's recommendations:

| Content | Target |
|---------|--------|
| FAQ answers | 40-80 words each, includes product name |
| Care steps | 1 sentence each |
| Highlights | 1 sentence each |
| Pros/Cons | 1 sentence each |

## ❌ Removed Features (No Proven Value)

| Field | Why Removed |
|-------|-------------|
| AI Summary (hidden) | Custom meta tag - AI ignores |
| Target Audience | No standard schema equivalent |
| Value Proposition | No standard schema equivalent |
| Use Cases | Custom meta tag - AI ignores |
| Problem Solved | No standard schema equivalent |
| Seasonal Relevance | No standard schema equivalent |
| Speakable | Only useful for news sites |
| All custom meta tags | AI search engines ignore them |

## ✅ Kept Features (Proven Value)

| Field | Schema Output | Display |
|-------|---------------|---------|
| FAQ | FAQPage JSON-LD | Visible in tab (H2/H3 structure) |
| Care Instructions | HowTo JSON-LD | Visible in tab (ordered list) |
| Product Highlights | Product.additionalProperty | Visible in tab (bullet list) |
| Pros & Cons | Visible content | Visible in tab (pros/cons lists) |
| Alt Names | Product.alternateName | Schema only |

## 📊 HTML Structure for AI Extraction

Content now uses proper semantic HTML:

```html
<h2>Frequently Asked Questions</h2>
<h3>What metal are these earrings made from?</h3>
<p>The 14K White Gold Diamond Earrings are crafted from... (40-80 words)</p>

<h2>How to Care for Your 14K White Gold Diamond Earrings</h2>
<ol>
  <li>Clean with mild soap and water...</li>
</ol>

<h2>Key Features</h2>
<ul>
  <li>1.89ct total diamond weight...</li>
</ul>

<h2>Pros and Cons</h2>
<h3>Pros</h3>
<ul><li>...</li></ul>
<h3>Cons</h3>
<ul><li>...</li></ul>
```

## 🔧 Technical Changes

### Files Added
- `includes/standalone-seo.php` - Standalone SEO mode

### Files Modified
- `includes/ai-search/prompts.php` - Updated with snippet optimization
- `includes/ai-search/generation.php` - Reduced to 5 fields
- `includes/ai-search/schema-output.php` - Added tab display, removed custom meta
- `includes/ai-search/metabox.php` - Updated field list
- `admin/dashboard.php` - Added Display settings subtab

### New Options
- `ai_seo_search_display` - Display mode and content toggles

## 📈 Benefits

1. **Actually works** - Based on Perplexity research, not guessing
2. **Faster generation** - 5 fields instead of 12
3. **Lower API costs** - Fewer calls per product
4. **Better extraction** - Visible content with proper H2/H3 structure
5. **Works anywhere** - Standalone mode for shops without SEO plugins
6. **Standard compliant** - Uses schema.org, not custom formats

## 🔄 Migration Notes

- Existing AI Search content remains in database
- New generations only create the 5 proven fields
- Old fields (ai_summary, target_audience, etc.) no longer generated
- Display settings default to "Combined Tab" with all content shown
- No action required - just update the plugin

## 📚 Research Sources

All decisions based on direct queries to Perplexity AI:
- What structured data AI search engines use
- How to optimize for AI citations
- Snippet length and structure for extraction
- Standard vs custom schema effectiveness
- HTML heading hierarchy for extraction
