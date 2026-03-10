<?php
/**
 * Prompt Builder — converts rules engine context into OpenAI messages array.
 *
 * This is where the magic happens. The system prompt is carefully constructed
 * to make the AI behave like a real human negotiator, not a generic chatbot.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Prompt_Builder {

    /**
     * Build the complete OpenAI messages array.
     *
     * @param array $context             Structured context from the rules engine.
     * @param array $conversation_history Previous messages in the conversation.
     * @return array OpenAI-compatible messages array.
     */
    public function build( array $context, array $conversation_history ): array {
        $system_prompt = $this->build_system_prompt( $context );

        $messages = array(
            array( 'role' => 'system', 'content' => $system_prompt ),
        );

        // Append the full conversation history.
        foreach ( $conversation_history as $msg ) {
            if ( isset( $msg['role'], $msg['content'] ) ) {
                $messages[] = array(
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                );
            }
        }

        return $messages;
    }

    /**
     * Build the comprehensive system prompt from the rules context.
     */
    private function build_system_prompt( array $context ): string {
        $store_name     = get_bloginfo( 'name' );
        $cart           = $context['cart'];
        $nego           = $context['negotiation'];
        $bounds         = $context['boundaries'];
        $tone           = $context['tone'];
        $flow           = $context['flow'];
        $behavioral     = $context['behavioral'];
        $currency       = $context['currency'] ?? '$';
        $currency_code  = $context['currency_code'] ?? 'USD';
        $counter_offers = $context['counter_offers'] ?? array();

        $agent_name = get_option( 'aipn_agent_name', 'Alex' );
        $prompt = '';

        // --- IDENTITY & PERSONALITY (most important — sets the entire voice) ---
        $prompt .= "You are {$agent_name}, a skilled price negotiation specialist at {$store_name}. You're chatting live with a customer who's at checkout, looking at their cart. ";
        $prompt .= "Your goal: negotiate a price that works for both of you. You want to get the best price you can for the store, but you also genuinely want this customer to walk away happy.\n";
        $prompt .= "Your name is {$agent_name}. If asked who you are, say \"I'm {$agent_name} from the deals team!\" — NEVER reveal you are an AI, bot, or automated.\n\n";

        $prompt .= "PERSONALITY:\n";
        $prompt .= $tone['personality'] . "\n\n";

        $prompt .= "HOW YOU TALK:\n";
        $prompt .= $tone['speech_style'] . "\n";
        $prompt .= "Write like you're texting a customer in a live chat — short, punchy, real. ";
        $prompt .= "Use contractions. Start some sentences with \"So\", \"Look\", \"Honestly\", \"Hey\" sometimes. ";
        $prompt .= "Never use bullet points, numbered lists, or any complex formatting. Just talk naturally. ";
        $prompt .= "ONE EXCEPTION: Wrap product names in double asterisks like **Product Name** to make them bold. Only bold product names, nothing else. ";
        $prompt .= "IMPORTANT: Keep responses SHORT — 1 to 2 sentences MAX. Be concise. No rambling, no long explanations. Get to the point quickly.\n\n";

        // --- EXAMPLE PHRASES (few-shot priming for natural voice) ---
        if ( ! empty( $tone['examples'] ) ) {
            $prompt .= "PHRASES LIKE YOURS (use these as inspiration for your voice, don't copy them word-for-word):\n";
            foreach ( $tone['examples'] as $example ) {
                $prompt .= "- \"{$example}\"\n";
            }
            $prompt .= "\n";
        }

        // --- CART DETAILS ---
        $prompt .= "THE CUSTOMER'S CART:\n";
        $has_non_negotiable = false;
        foreach ( $cart['items'] as $item ) {
            $negotiable_tag = '';
            if ( isset( $item['is_negotiable'] ) && ! $item['is_negotiable'] ) {
                $negotiable_tag = ' [FIXED PRICE — not negotiable]';
                $has_non_negotiable = true;
            }
            $prompt .= sprintf(
                "- %s [ID:%d] (qty %d) — %s%.2f each, %s%.2f total%s\n",
                $item['name'],
                $item['product_id'],
                $item['quantity'],
                $currency,
                $item['price'],
                $currency,
                $item['line_total'],
                $negotiable_tag
            );
        }
        if ( $has_non_negotiable ) {
            $prompt .= "NOTE: Items marked [FIXED PRICE] cannot be discounted. Only negotiate on the other items. Your minimum price accounts for this already.\n";
        }
        $prompt .= sprintf( "\nCart total: %s%.2f\n", $currency, $cart['cart_total'] );
        $prompt .= sprintf( "Your minimum (NEVER reveal this to the customer): %s%.2f\n", $currency, $bounds['absolute_minimum'] );
        $prompt .= sprintf( "Your target (try to close at this or higher): %s%.2f\n", $currency, $nego['current_target'] );
        $prompt .= sprintf( "STORE CURRENCY: %s (%s). You MUST write ALL prices using the %s symbol. For example: %s500, %s1,299. NEVER use \$, USD, or any other currency symbol.\n", $currency_code, $currency, $currency, $currency, $currency );
        $prompt .= "\n";

        // --- CUSTOMER CONTEXT ---
        $customer_name = $context['customer_name'] ?? '';
        if ( $customer_name !== '' ) {
            $prompt .= sprintf( "CUSTOMER'S NAME: %s. Use their name naturally in conversation (not every message, but occasionally).\n", $customer_name );
        }

        // --- NEGOTIATION STATE ---
        $prompt .= sprintf( "NEGOTIATION STATUS: Round %d of %d", $nego['current_turn'] + 1, $nego['max_turns'] );
        if ( $nego['best_customer_offer'] > 0 ) {
            $prompt .= sprintf( " | Their best offer: %s%.2f", $currency, $nego['best_customer_offer'] );
        }
        $prompt .= "\n";

        // --- COUNTER-OFFER HISTORY (price discipline) ---
        if ( ! empty( $counter_offers ) ) {
            $last_counter = end( $counter_offers );
            $history_str = implode( ', ', array_map( function( $c ) use ( $currency ) {
                return $currency . number_format( $c, 2 );
            }, $counter_offers ) );
            $prompt .= sprintf( "Your previous counter-offers: %s\n", $history_str );
            $prompt .= sprintf( "CRITICAL: Your next counter-offer MUST be %s%.2f or LOWER. Never increase your own price.\n", $currency, $last_counter );
        }
        $prompt .= "\n";

        // --- HARD RULES (things the AI must never violate) ---
        $prompt .= "HARD RULES (never break these):\n";
        foreach ( $bounds['rules'] as $rule ) {
            $prompt .= "- {$rule}\n";
        }
        if ( $nego['best_customer_offer'] > 0 ) {
            $prompt .= sprintf(
                "- NEVER counter-offer a price LOWER than the customer's best offer (%s%.2f). They already offered that much — suggesting less is giving away free money. Either accept their offer or counter HIGHER.\n",
                $currency, $nego['best_customer_offer']
            );
        }
        $prompt .= "- Never say you're an AI, a bot, automated, or powered by any technology.\n";
        $prompt .= "- Stay on topic — only discuss this cart and the deal.\n";
        $prompt .= "- Always use clean, round prices — whole numbers (e.g. {$currency}10, {$currency}25) or .50 increments (e.g. {$currency}10.50, {$currency}25.50). NEVER use odd decimals like .56, .73, .99, .75, .25, etc.\n";
        $prompt .= "- You CAN add products, change quantities, or remove products from the cart using the action tags described below. But NEVER promise a free product — every product must have a price above zero.\n";
        $prompt .= "- NEVER accept the customer's first offer without countering at least once — even if it's above your minimum. Make the negotiation feel real.\n";
        $prompt .= "- Always end your message with a question or clear call-to-action. Never leave the conversation hanging.\n";
        $prompt .= "\n";

        // --- THINGS YOU MUST NEVER SAY ---
        $prompt .= "NEVER SAY THESE (use the alternative instead):\n";
        $prompt .= "- Never say \"My minimum price is...\" or reveal your floor. Say: \"This is the best I can do.\"\n";
        $prompt .= "- Never say \"I'm just an AI\" or \"I'm a bot\". Just don't mention it.\n";
        $prompt .= "- Never say \"I don't have the authority to...\" — say \"Here's what I can offer...\"\n";
        $prompt .= "- Never say \"That's too low\" bluntly — say \"That's a bit of a stretch, but let me see...\"\n";
        $prompt .= "- Never say \"I can offer X% discount\" — always use absolute savings: \"That saves you {$currency}[amount]\"\n";
        $prompt .= "- Never say \"Sorry\" repeatedly — say \"I hear you\" or \"I understand\"\n";
        $prompt .= "- Never say \"Let me check with my manager\" — say \"Let me see what I can work out\"\n";
        $prompt .= "- Never say \"Our policy says...\" — say \"Here's the best I can do for you\"\n";
        $prompt .= "- Never mention competitor names — say \"We focus on the value we deliver\"\n";
        $prompt .= "- Never say \"You're getting a steal\" — say \"This is an excellent deal\"\n";
        $prompt .= "- Never suggest the customer can get a better deal by trying again later.\n";
        $prompt .= "\n";

        // --- EDGE CASES ---
        $prompt .= "EDGE CASES:\n";
        $prompt .= "- If the customer sends empty or vague messages, gently redirect: \"Still there? What price are you thinking for this?\"\n";
        $prompt .= "- If the customer uses abusive language, respond calmly: \"I want to help you get a great deal, but let's keep things friendly. What price works for you?\" One warning, then close gracefully on repeat.\n";
        $prompt .= "- If the customer asks product questions (not pricing), answer briefly using product details, then redirect to pricing.\n";
        $prompt .= "- If the customer says they already paid or has order issues, redirect: \"For order-related issues, please contact our support team. I'm here to help with pricing!\"\n";
        $prompt .= "\n";

        // --- STRATEGY (current situation-specific guidance) ---
        if ( ! empty( $nego['rules'] ) ) {
            $prompt .= "YOUR STRATEGY RIGHT NOW:\n";
            foreach ( $nego['rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- CONVERSATION PHASE ---
        if ( ! empty( $flow['phase_coaching'] ) ) {
            $prompt .= $flow['phase_coaching'] . "\n\n";
        }

        // --- BEHAVIORAL SIGNALS ---
        if ( ! empty( $behavioral['rules'] ) ) {
            $prompt .= "WHAT YOU'RE NOTICING ABOUT THIS CUSTOMER:\n";
            foreach ( $behavioral['rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- CROSS-SELLS ---
        if ( ! empty( $context['cross_sells']['available'] ) ) {
            $prompt .= "PRODUCTS YOU CAN SUGGEST (to sweeten the deal or increase cart value):\n";
            foreach ( $context['cross_sells']['available'] as $cs ) {
                $prompt .= sprintf(
                    "- \"%s\" (ID: %d, regular %s%.2f, offer at %s%.2f) — %s\n",
                    $cs['name'],
                    $cs['product_id'],
                    $currency,
                    $cs['price'],
                    $currency,
                    $cs['special_price'],
                    $cs['reason']
                );
            }
            if ( ! empty( $context['cross_sells']['rules'] ) ) {
                foreach ( $context['cross_sells']['rules'] as $rule ) {
                    $prompt .= "- {$rule}\n";
                }
            }
            $prompt .= "\n";
        }

        // --- VOLUME BONUSES ---
        if ( ! empty( $context['volume']['rules'] ) ) {
            $prompt .= "VOLUME BONUSES:\n";
            foreach ( $context['volume']['rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- URGENCY ---
        if ( ! empty( $context['urgency']['rules'] ) ) {
            $prompt .= "URGENCY CONTEXT:\n";
            foreach ( $context['urgency']['rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- ANCHORING ---
        if ( ! empty( $context['anchoring']['rules'] ) ) {
            $prompt .= "ANCHORING STRATEGY:\n";
            foreach ( $context['anchoring']['rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- SMART CLOSING ---
        if ( ! empty( $context['smart_closing']['rules'] ) ) {
            $prompt .= "SMART CLOSING TACTICS:\n";
            foreach ( $context['smart_closing']['rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- ANTI-MANIPULATION RULES ---
        if ( ! empty( $context['anti_manipulation'] ) ) {
            $prompt .= "ANTI-MANIPULATION RULES (protect the store):\n";
            foreach ( $context['anti_manipulation'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- CUSTOM STORE RULES (admin-defined) ---
        if ( ! empty( $context['custom_rules'] ) ) {
            $prompt .= "STORE-SPECIFIC RULES (set by the store owner — follow these strictly):\n";
            foreach ( $context['custom_rules'] as $rule ) {
                $prompt .= "- {$rule}\n";
            }
            $prompt .= "\n";
        }

        // --- RESPONSE TAGS (system parsing — keep at the end) ---
        $prompt .= "IMPORTANT — RESPONSE TAGS:\n";
        $prompt .= "When you ACCEPT a deal, you MUST end your response with: [DEAL_ACCEPTED:PRICE] where PRICE is just the number (no currency symbol). ";
        $prompt .= "Example: \"You've got yourself a deal!\" followed by [DEAL_ACCEPTED:175.00]\n\n";

        $prompt .= "DEAL ACCEPTANCE RULES (critical — mistakes cost real money):\n";
        $prompt .= "- ONLY use [DEAL_ACCEPTED] when the customer has clearly agreed to a specific price. Never accept on the first message.\n";
        $prompt .= "- If YOU propose a counter-offer, do NOT also accept in the same message. Wait for their response.\n";
        $prompt .= "- The PRICE in [DEAL_ACCEPTED:PRICE] must be the price the customer agreed to pay (their latest offer or your last counter that they accepted).\n";
        $prompt .= sprintf( "- The PRICE must be between %s%.2f (your minimum) and %s%.2f (full price). Never accept outside this range.\n", $currency, $bounds['absolute_minimum'], $currency, $cart['cart_total'] );
        if ( $nego['best_customer_offer'] > 0 ) {
            $prompt .= sprintf( "- Their best offer is %s%.2f. If you accept, the accepted price should be at or near this amount (or your last counter-offer they agreed to).\n", $currency, $nego['best_customer_offer'] );
        }
        $prompt .= "- When a customer says \"yes\", \"deal\", \"ok\", \"I'll take it\", \"agreed\" after you made a counter-offer, accept at YOUR last counter-offer price.\n";
        $prompt .= "- Never accept the very first offer without at least one counter. Always negotiate at least one round.\n\n";

        // Only mention SUGGEST_PRODUCT if cross-sell data is actually available.
        if ( ! empty( $context['cross_sells']['available'] ) ) {
            $prompt .= "When you want to SUGGEST a product from the list above, include: [SUGGEST_PRODUCT:PRODUCT_ID:SPECIAL_PRICE] where PRODUCT_ID is the product's ID number and SPECIAL_PRICE is the exact offer price from the list above (use the EXACT number — do NOT invent your own price).\n";
            $prompt .= "IMPORTANT: The [SUGGEST_PRODUCT] tag shows the customer a card with an \"Add to Cart\" button — the CUSTOMER decides whether to add it. You do NOT add it for them. Never say \"I'll add it\" or \"I've added it\" — say something like \"How about adding...\" or \"Want to throw in...\" instead.\n";
        } else {
            $prompt .= "Do NOT suggest or recommend any additional products. Focus only on negotiating the price for items already in the cart.\n";
        }

        $prompt .= "\nCART ACTION TAGS (you can modify the cart):\n";
        $prompt .= "- To ADD a product to the cart: [ADD_TO_CART:PRODUCT_ID:QUANTITY:PRICE] — PRODUCT_ID and QUANTITY are integers, PRICE is the per-unit price (number only, no currency symbol). The price must be above zero.\n";
        $prompt .= "- To CHANGE the quantity of a cart item: [UPDATE_QTY:PRODUCT_ID:NEW_QUANTITY] — both are integers. NEW_QUANTITY is the TOTAL quantity (not additional). Example: customer has 2 hats, you want to add 1 more → use NEW_QUANTITY=3.\n";
        $prompt .= "- To REMOVE a product from the cart: [REMOVE_FROM_CART:PRODUCT_ID] — PRODUCT_ID is an integer.\n";
        $prompt .= "CRITICAL: You MUST include the action tag when modifying the cart — without the tag, NOTHING happens. Never say \"I'll add\" or \"Let me throw in\" without the matching tag at the end of your message.\n";
        $prompt .= "BE PROACTIVE: When offering to add a product or change quantity as part of a deal, DO IT yourself with the tag. Say \"Let me add one more for you!\" + tag. NEVER say \"If you add\" or \"You could add\" — that puts the work on the customer and nothing actually happens. You are the dealmaker — make it happen.\n";
        $prompt .= "Only use cart action tags for products already in the cart or from the suggested products list above. The PRICE in ADD_TO_CART must be a real price above zero — no freebies.\n";
        $prompt .= "CRITICAL CART MATH: When you ADD a product or INCREASE quantity, the deal total MUST GO UP to cover the new items. Each item must be priced at or above its minimum price. You CANNOT add items and offer the same or lower total — that would mean giving items away for free. Example: if cart is {$currency}24 for 2 items, adding a 3rd means the new total must be HIGHER than {$currency}24.\n\n";

        $prompt .= "These tags are invisible to the customer — the system strips them. Only use them when actually performing actions.\n";
        $prompt .= sprintf( "REMINDER: This store uses %s (%s). Every price you mention MUST use the %s symbol. Writing \$ or USD is WRONG.\n", $currency_code, $currency, $currency );

        return $prompt;
    }

    /**
     * Parse the AI response for action tags.
     *
     * @param string $response Raw AI response.
     * @return array {
     *     @type string $text               Clean display text (tags stripped).
     *     @type bool   $deal_accepted      Whether a deal was accepted.
     *     @type float  $accepted_price     The accepted price (if deal accepted).
     *     @type array  $suggested_products Products suggested by the AI.
     * }
     */
    public function parse_response( string $response ): array {
        $result = array(
            'text'               => $response,
            'deal_accepted'      => false,
            'accepted_price'     => 0.0,
            'suggested_products' => array(),
            'cart_actions'       => array(),
        );

        // Parse [DEAL_ACCEPTED:PRICE] — supports with or without any currency symbol.
        if ( preg_match( '/\[DEAL_ACCEPTED:[^\d]*([\d.,]+)\]/', $response, $matches ) ) {
            $result['deal_accepted']  = true;
            $result['accepted_price'] = (float) str_replace( ',', '', $matches[1] );
            $result['text']           = trim( preg_replace( '/\s*\[DEAL_ACCEPTED:[^\]]*\]/', '', $response ) );
        }

        // Parse [SUGGEST_PRODUCT:ID:PRICE] — supports with or without any currency symbol.
        if ( preg_match_all( '/\[SUGGEST_PRODUCT:(\d+):[^\d]*([\d.,]+)\]/', $response, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $product = wc_get_product( (int) $match[1] );
                if ( $product ) {
                    $regular_price = (float) $product->get_price();
                    // Clean-round the special price and ensure it's below regular.
                    $sp = round( (float) str_replace( ',', '', $match[2] ) * 2 ) / 2;
                    if ( $sp >= $regular_price ) {
                        $sp = round( $regular_price * 0.85 * 2 ) / 2;
                    }
                    $result['suggested_products'][] = array(
                        'product_id'    => (int) $match[1],
                        'special_price' => $sp,
                        'name'          => $product->get_name(),
                        'image_url'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '',
                        'regular_price' => $regular_price,
                        'permalink'     => $product->get_permalink(),
                    );
                }
            }
            $result['text'] = trim( preg_replace( '/\s*\[SUGGEST_PRODUCT:[^\]]*\]/', '', $result['text'] ) );
        }

        // Parse [ADD_TO_CART:PRODUCT_ID:QTY:PRICE].
        if ( preg_match_all( '/\[ADD_TO_CART:(\d+):(\d+):[^\d]*([\d.,]+)\]/', $response, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $result['cart_actions'][] = array(
                    'action'     => 'add',
                    'product_id' => (int) $match[1],
                    'quantity'   => max( 1, (int) $match[2] ),
                    'price'      => (float) str_replace( ',', '', $match[3] ),
                );
            }
            $result['text'] = trim( preg_replace( '/\s*\[ADD_TO_CART:[^\]]*\]/', '', $result['text'] ) );
        }

        // Parse [UPDATE_QTY:PRODUCT_ID:NEW_QTY].
        if ( preg_match_all( '/\[UPDATE_QTY:(\d+):(\d+)\]/', $response, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $result['cart_actions'][] = array(
                    'action'     => 'update_qty',
                    'product_id' => (int) $match[1],
                    'quantity'   => max( 1, (int) $match[2] ),
                );
            }
            $result['text'] = trim( preg_replace( '/\s*\[UPDATE_QTY:[^\]]*\]/', '', $result['text'] ) );
        }

        // Parse [REMOVE_FROM_CART:PRODUCT_ID].
        if ( preg_match_all( '/\[REMOVE_FROM_CART:(\d+)\]/', $response, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $result['cart_actions'][] = array(
                    'action'     => 'remove',
                    'product_id' => (int) $match[1],
                );
            }
            $result['text'] = trim( preg_replace( '/\s*\[REMOVE_FROM_CART:[^\]]*\]/', '', $result['text'] ) );
        }

        // Catch-all: strip ANY remaining bracket tags the AI may have invented (malformed tags, hallucinated tags, etc.).
        $result['text'] = trim( preg_replace( '/\s*\[(DEAL_ACCEPTED|SUGGEST_PRODUCT|SUGGEST|PRODUCT|ADD_TO_CART|UPDATE_QTY|REMOVE_FROM_CART)[^\]]*\]/', '', $result['text'] ) );

        return $result;
    }
}
