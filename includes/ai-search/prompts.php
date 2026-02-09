<?php
/**
 * AI SEO Search Optimization - Default Prompts
 * 
 * v2.1.0 - Updated based on AI search research:
 * - Product Summary: 40-80 words, keyword first, prepends to short description
 * - FAQ answers: 40-80 words each, include product name for standalone snippets
 * - Care instructions: 1 sentence per step
 * - Product highlights: 1 sentence per bullet
 * - Pros/Cons: 1 sentence per item
 * - Removed: ai_summary, target_audience, value_proposition, use_cases, problem_solved, seasonal, speakable
 * 
 * @package AI_SEO_Content_Generator
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get default prompts for AI Search Optimization
 */
function ai_seo_search_get_default_prompts() {
    return [
        'product_summary' => 'Write a product summary for this jewelry product.

Product: [product_title]
Focus Keyword: [focus_keyword]
Description: [current_full_description]
Attributes: [current_attributes]
Price: [current_price]

IMPORTANT RULES:
1. Start the FIRST sentence with the exact focus keyword "[focus_keyword]"
2. Write exactly 40-80 words (2-3 sentences total)
3. Answer three things: What is it? Who is it for? What is the main benefit?
4. Be specific and factual - only use information provided
5. Write in a natural, customer-friendly tone
6. Do NOT start with "This" or "The" - start directly with the focus keyword

Example format:
"[Focus Keyword] features [key specs]. Perfect for [who it is for], this [product type] offers [main benefit]."

Output ONLY the summary paragraph, no labels or extra text.',

        'faq_schema' => 'Based on this product information:

Product: [product_title]
Description: [current_full_description]
Attributes: [current_attributes]
Price: [current_price]

Generate exactly 4 frequently asked questions and answers that shoppers would ask about this product. Focus on:
- Materials/construction
- Sizing/dimensions/fit
- Care/maintenance
- Best uses/occasions

IMPORTANT RULES:
1. Only use facts from the information provided above. Do NOT invent specifications.
2. Each answer must be 40-80 words (2-3 sentences).
3. Include the product name "[product_title]" in EACH answer so it makes sense as a standalone snippet.
4. Write answers in complete sentences that would make sense if read without the question.

Format your response EXACTLY like this (no extra text):
Q: [question about the specific product]
A: [40-80 word answer that includes the product name]

Q: [question]
A: [answer]

Q: [question]
A: [answer]

Q: [question]
A: [answer]',

        'care_instructions' => 'Based on this product:

Product: [product_title]
Description: [current_full_description]
Attributes: [current_attributes]

Write 4-5 care and maintenance instructions for this product.

IMPORTANT RULES:
1. Each instruction must be exactly ONE sentence.
2. Be specific to the materials mentioned (e.g., gold, silver, diamonds, etc.)
3. Start each instruction with an action verb.

Format as numbered steps:
1. [one sentence instruction]
2. [one sentence instruction]
3. [one sentence instruction]
4. [one sentence instruction]
5. [one sentence instruction]

Only include relevant care tips based on the product type and materials mentioned.',

        'product_highlights' => 'Based on this product:

Product: [product_title]
Description: [current_full_description]
Attributes: [current_attributes]

List 4-5 key product highlights/features.

IMPORTANT RULES:
1. Each highlight must be exactly ONE sentence.
2. Be specific and factual - only use information provided.
3. Start with the feature, not "This product has..."

Format:
• [one sentence highlight]
• [one sentence highlight]
• [one sentence highlight]
• [one sentence highlight]
• [one sentence highlight]',

        'pros_cons' => 'Based on this product:

Product: [product_title]
Description: [current_full_description]
Attributes: [current_attributes]
Price: [current_price]

List 3 pros and 2 cons for this product. Be honest but fair.

IMPORTANT RULES:
1. Each pro and con must be exactly ONE sentence.
2. Be specific to THIS product, not generic statements.
3. Keep cons realistic but not deal-breakers (e.g., "requires regular cleaning" not "poor quality").

Format:
PROS:
+ [one sentence pro]
+ [one sentence pro]
+ [one sentence pro]

CONS:
- [one sentence con]
- [one sentence con]',

        'alt_names' => 'Based on this product:

Product: [product_title]
Description: [current_full_description]
Categories: [current_categories]

List 5-7 alternative names, synonyms, or related search terms people might use to find this product.

Format as comma-separated list:
[term 1], [term 2], [term 3], [term 4], [term 5]

Include variations, abbreviations, and related terms.'
    ];
}

/**
 * Get saved prompts (user customized or defaults)
 */
function ai_seo_search_get_prompts() {
    if (!ai_seo_search_is_licensed()) {
        return [];
    }
    
    $saved = get_option('ai_seo_search_prompts', []);
    $defaults = ai_seo_search_get_default_prompts();
    
    return array_merge($defaults, $saved);
}
