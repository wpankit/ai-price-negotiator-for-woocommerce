<?php
/**
 * Advanced Rules — volume discounts and urgency messaging.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Advanced_Rules {

    /**
     * Register hooks.
     */
    public function register(): void {
        // Core advanced intelligence — replaces Free's basic rules with advanced versions.
        add_filter( 'aipn_rules_context', array( $this, 'add_customer_context' ), 5, 3 );
        add_filter( 'aipn_rules_context', array( $this, 'enhance_negotiation' ), 12, 3 );
        add_filter( 'aipn_rules_context', array( $this, 'enhance_behavioral' ), 14, 3 );

        // Advanced feature modules.
        add_filter( 'aipn_rules_context', array( $this, 'add_volume_rules' ), 20, 3 );
        add_filter( 'aipn_rules_context', array( $this, 'add_urgency_rules' ), 30, 3 );
        add_filter( 'aipn_rules_context', array( $this, 'add_pro_anti_manipulation_rules' ), 40, 3 );
        add_filter( 'aipn_rules_context', array( $this, 'add_anchoring_rules' ), 50, 3 );
        add_filter( 'aipn_rules_context', array( $this, 'add_smart_closing_rules' ), 60, 3 );
    }

    /**
     * Add customer name to context.
     */
    public function add_customer_context( array $context, array $cart_data, array $session ): array {
        $context['customer_name'] = $session['customer_name'] ?? '';
        return $context;
    }

    /**
     * Replace Free's linear negotiation with the concession formula.
     *
     * Advanced intelligence: generosity levels, diminishing concessions (40/30/20/10),
     * stock-based pricing, detailed 6-tier offer classification, first counter rule,
     * counter-offer history, and cost-price awareness.
     */
    public function enhance_negotiation( array $context, array $cart_data, array $session ): array {
        $max_turns       = $context['negotiation']['max_turns'];
        $current_turn    = $context['negotiation']['current_turn'];
        $remaining_turns = $context['negotiation']['remaining_turns'];
        $best_offer      = $context['negotiation']['best_customer_offer'];
        $currency        = $context['currency'] ?? '$';

        $cart_total  = $cart_data['cart_total'];
        $floor_total = $cart_data['floor_total'];
        $cost_total  = $cart_data['cost_total'] ?? 0.0;
        $margin      = $cart_total - $floor_total;

        $generosity       = get_option( 'aipn_generosity_level', 'conservative' );
        $counter_strategy = get_option( 'aipn_counter_strategy', 'moderate' );

        // Generosity: what % of the negotiable margin the AI can use.
        $generosity_pcts = array(
            'conservative' => 0.50,
            'moderate'     => 0.70,
            'generous'     => 0.90,
        );
        $generosity_pct  = $generosity_pcts[ $generosity ] ?? 0.50;
        $usable_discount = $margin * $generosity_pct;

        // Diminishing concession steps (40/30/20/10 distribution).
        $concession_weights = array( 0.40, 0.30, 0.20, 0.10 );
        $effective_rounds   = min( $max_turns, count( $concession_weights ) );

        $cumulative_discount = 0.0;
        for ( $i = 0; $i < min( $current_turn, $effective_rounds ); $i++ ) {
            $cumulative_discount += $usable_discount * $concession_weights[ $i ];
        }
        $current_target = max( $floor_total, round( $cart_total - $cumulative_discount, 2 ) );

        // Stock-based pricing adjustments.
        if ( get_option( 'aipn_enable_stock_pricing', 'no' ) === 'yes' ) {
            foreach ( $cart_data['items'] as $item ) {
                if ( $item['stock_qty'] === null || ! $item['is_negotiable'] ) {
                    continue;
                }
                if ( $item['stock_qty'] <= 5 && $item['stock_qty'] > 0 ) {
                    $usable_discount *= 0.50;
                } elseif ( $item['stock_qty'] > 100 ) {
                    $usable_discount *= 1.20;
                }
            }
            $cumulative_adjusted = 0.0;
            for ( $i = 0; $i < min( $current_turn, $effective_rounds ); $i++ ) {
                $cumulative_adjusted += $usable_discount * $concession_weights[ $i ];
            }
            $current_target = max( $floor_total, round( $cart_total - $cumulative_adjusted, 2 ) );
        }

        // Round to clean .00 or .50 increments (never offer $34.98 — use $35 or $34.50).
        $current_target = round( $current_target * 2 ) / 2;

        // Never aim lower than the customer's best offer.
        if ( $best_offer > 0 && $best_offer >= $floor_total ) {
            $current_target = max( $current_target, $best_offer );
        }

        // Build advanced rules.
        $rules = array();

        if ( $remaining_turns <= 1 ) {
            $rules[] = 'This is your LAST chance to make a deal. Give your absolute best price and let them know — warmly, not as an ultimatum — that this is your final offer.';
        } else {
            $rules[] = sprintf( 'You still have %d rounds of conversation left. Don\'t jump to your best price yet — leave room to give a little more each round so the customer feels like they\'re winning.', $remaining_turns );
            $rules[] = sprintf( 'Aim to close at %s%.2f or better. If you can do even better, great.', $currency, $current_target );
        }

        // Detailed 6-tier offer classification.
        if ( $best_offer > 0 ) {
            $rules[] = sprintf( 'Their best offer so far: %s%.2f.', $currency, $best_offer );

            if ( $best_offer >= $cart_total ) {
                $rules[] = 'Their offer is AT or ABOVE full price — accept immediately and enthusiastically!';
            } elseif ( $best_offer >= $cart_total * 0.95 ) {
                $rules[] = 'Their offer is within 5%% of full price — easy win! Accept with enthusiasm and suggest an add-on.';
            } elseif ( $best_offer >= $current_target ) {
                $rules[] = sprintf( 'Their offer (%s%.2f) is at or above your target — ACCEPT THE DEAL! Say yes enthusiastically and lock it in.', $currency, $best_offer );
            } elseif ( $best_offer >= $floor_total ) {
                $rules[] = 'This offer is within your acceptable range! You can accept it, or gently see if they\'ll go a bit higher — but don\'t push too hard.';
            } elseif ( $cost_total > 0 && $best_offer < $cost_total ) {
                $rules[] = 'Their offer is BELOW your cost. Decline politely and suggest alternatives. Never go below cost.';
            } elseif ( $best_offer < $cart_total * 0.30 ) {
                $rules[] = 'Their offer is absurdly low (below 30%%). Redirect with humor — "Ha, I like your style! Let me give you something realistic..."';
            } else {
                $gap = $floor_total - $best_offer;
                $rules[] = sprintf( 'They still need to come up by about %s%.2f to reach your minimum. Guide them with encouragement.', $currency, $gap );
            }
        }

        // First counter-offer rule (based on concession formula).
        if ( $current_turn === 1 && $best_offer > 0 && $margin > 0 ) {
            $first_counter_min = round( ( $cart_total - ( $usable_discount * 0.40 ) ) * 2 ) / 2;
            $rules[] = sprintf(
                'FIRST COUNTER RULE: Your first counter-offer should be no lower than %s%.2f. Always leave room to give more in later rounds.',
                $currency, $first_counter_min
            );
        }

        // Turn-specific strategies.
        if ( $current_turn === 0 ) {
            $rules[] = 'This is the very first message. Do NOT make a counter-offer or mention any prices. Just welcome them and invite them to tell you what price they had in mind.';
        } elseif ( $current_turn === 1 && $best_offer > 0 ) {
            $rules[] = 'They just opened with their first offer. Acknowledge it positively, explain why the items are worth more, and suggest a number closer to your target.';
        }

        // Frame discounts as absolute amounts, never percentages.
        $rules[] = 'Always frame discounts as absolute savings (e.g. "That saves you ' . $currency . '500") — NEVER say the percentage off. Percentages invite the customer to ask for more.';

        // Never accept first offer.
        $rules[] = 'NEVER accept the customer\'s first offer without countering at least once — make the negotiation feel real.';

        // Counter-offer discipline with full history.
        $counter_offers = $session['counter_offers'] ?? array();
        if ( ! empty( $counter_offers ) ) {
            $last_counter = end( $counter_offers );
            $rules[] = sprintf(
                'CRITICAL PRICE RULE: Your last counter-offer was %s%.2f. Your next counter-offer MUST be equal to or LOWER than %s%.2f. NEVER increase your price.',
                $currency, $last_counter, $currency, $last_counter
            );
            if ( count( $counter_offers ) >= 2 ) {
                $history_str = implode( ' → ', array_map( function( $c ) use ( $currency ) {
                    return $currency . number_format( $c, 2 );
                }, $counter_offers ) );
                $rules[] = sprintf( 'Your counter-offer history: %s. Continue this downward trend.', $history_str );
            }
        }

        // Replace basic negotiation context with advanced enhanced version.
        $context['negotiation'] = array(
            'max_turns'           => $max_turns,
            'current_turn'        => $current_turn,
            'remaining_turns'     => $remaining_turns,
            'floor_total'         => $floor_total,
            'cost_total'          => $cost_total,
            'cart_total'          => $cart_total,
            'margin'              => round( $margin, 2 ),
            'usable_discount'     => round( $usable_discount, 2 ),
            'generosity_level'    => $generosity,
            'current_target'      => round( $current_target, 2 ),
            'best_customer_offer' => $best_offer,
            'counter_strategy'    => $counter_strategy,
            'is_final_turn'       => $remaining_turns <= 1,
            'rules'               => $rules,
        );

        return $context;
    }

    /**
     * Enhance Free's basic behavioral rules with customer profiling and returning customer detection.
     */
    public function enhance_behavioral( array $context, array $cart_data, array $session ): array {
        $offers      = $session['offers'] ?? array();
        $offer_count = count( $offers );
        $cart_total  = $session['cart_total'] ?? $cart_data['cart_total'];
        $floor_total = $session['floor_total'] ?? $cart_data['floor_total'];
        $rules       = $context['behavioral']['rules'] ?? array();

        // Customer type profiling (5 types).
        $customer_type = 'unknown';

        if ( $offer_count >= 1 && $cart_total > 0 ) {
            $first_offer_pct = $offers[0] / $cart_total;

            if ( $first_offer_pct >= 0.90 ) {
                $customer_type = 'easy_win';
                $rules[] = 'CUSTOMER PROFILE: Easy win — first offer over 90% of full price. Accept enthusiastically and suggest add-ons.';
            } elseif ( $first_offer_pct >= 0.75 ) {
                $customer_type = 'reasonable';
                $rules[] = 'CUSTOMER PROFILE: Reasonable buyer — fair range. Negotiate normally, close quickly.';
            } elseif ( $first_offer_pct >= 0.40 ) {
                $customer_type = 'aggressive';
                $rules[] = 'CUSTOMER PROFILE: Aggressive bargainer — opened low. Stand firm, give smaller concessions, use value anchoring.';
            } else {
                $customer_type = 'unreasonable';
                $rules[] = 'CUSTOMER PROFILE: Unreasonable or testing — below 40% of cart value. Redirect with humor, then present a serious counter.';
            }
        }

        // Returning customer detection.
        $is_returning = false;
        if ( is_user_logged_in() ) {
            $order_count = wc_get_customer_order_count( get_current_user_id() );
            if ( $order_count > 0 ) {
                $is_returning  = true;
                $customer_type = 'repeat';
                $rules[] = sprintf(
                    'CUSTOMER PROFILE: Repeat customer with %d previous order(s). Be extra warm and a touch more generous — loyalty matters.',
                    $order_count
                );
            }
        }

        $context['behavioral'] = array(
            'offer_count'   => $offer_count,
            'customer_type' => $customer_type,
            'is_returning'  => $is_returning,
            'rules'         => $rules,
        );

        return $context;
    }

    /**
     * Add volume-based discount rules to the context.
     */
    public function add_volume_rules( array $context, array $cart_data, array $session ): array {
        $rules = array();

        // Quantity-based: extra discount for buying multiples.
        foreach ( $cart_data['items'] as $item ) {
            if ( $item['quantity'] >= 2 ) {
                $extra = min( 5, $item['quantity'] - 1 ); // 1% per extra item, max 5%.
                $rules[] = sprintf(
                    'Customer has %dx "%s". You can offer an extra %d%% off as a volume bonus for buying multiples.',
                    $item['quantity'],
                    $item['name'],
                    $extra
                );
            }
        }

        // Cart value thresholds.
        $thresholds = array(
            100 => 5,
            250 => 8,
            500 => 12,
        );

        $cart_total    = $cart_data['cart_total'];
        $best_extra    = 0;
        $best_threshold = 0;

        foreach ( $thresholds as $threshold => $extra_pct ) {
            if ( $cart_total >= $threshold && $extra_pct > $best_extra ) {
                $best_extra     = $extra_pct;
                $best_threshold = $threshold;
            }
        }

        if ( $best_extra > 0 ) {
            $rules[] = sprintf(
                'This is a high-value cart ($%.2f, over $%d). You have up to %d%% additional negotiation room as a volume incentive.',
                $cart_total,
                $best_threshold,
                $best_extra
            );
        }

        // Check for items that are close to a quantity threshold.
        foreach ( $cart_data['items'] as $item ) {
            if ( $item['quantity'] === 1 ) {
                $rules[] = sprintf(
                    'Tip: If the customer adds one more "%s", you could offer a better deal as a volume incentive. Mention this if negotiation stalls.',
                    $item['name']
                );
                break; // Only suggest for one item.
            }
        }

        if ( ! empty( $rules ) ) {
            $context['volume'] = array( 'rules' => $rules );
        }

        return $context;
    }

    /**
     * Add urgency-based rules to the context.
     */
    public function add_urgency_rules( array $context, array $cart_data, array $session ): array {
        if ( get_option( 'aipn_enable_urgency', 'no' ) !== 'yes' ) {
            return $context;
        }

        $rules = array();

        // Low stock alerts.
        foreach ( $cart_data['items'] as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product || ! $product->managing_stock() ) {
                continue;
            }

            $stock = $product->get_stock_quantity();
            if ( $stock !== null && $stock > 0 && $stock <= 5 ) {
                $rules[] = sprintf(
                    '"%s" has only %d left in stock. You can naturally mention this to create urgency: "Just so you know, we only have %d of these left..."',
                    $item['name'],
                    $stock,
                    $stock
                );
            }
        }

        // Session time pressure.
        $rules[] = 'You can mention that the negotiated price is only valid for the current session. Something like: "I should mention, this special pricing is just for right now..."';

        // Sale items.
        foreach ( $cart_data['items'] as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( $product && $product->is_on_sale() ) {
                $rules[] = sprintf(
                    '"%s" is already on sale. Emphasize that any additional discount on top of the sale price is truly special.',
                    $item['name']
                );
            }
        }

        if ( ! empty( $rules ) ) {
            $context['urgency'] = array( 'rules' => $rules );
        }

        return $context;
    }

    /**
     * Add advanced anti-manipulation rules.
     *
     * Extends the free anti-manipulation rules with competitor claim rejection,
     * bulk discount gaming detection, and cooldown awareness.
     */
    public function add_pro_anti_manipulation_rules( array $context, array $cart_data, array $session ): array {
        $rules = $context['anti_manipulation'] ?? array();

        // Reject fake competitor price claims.
        $rules[] = 'If the customer claims they found a lower price on another website, do NOT match it blindly. Respond confidently: "We focus on the value we offer — quality, support, and warranty included. Let me find the best deal I can for you here." Stay within your pricing rules.';

        // Detect bulk discount gaming.
        $rules[] = 'If the customer claims they will "buy more later" to get a per-unit discount now, require the actual cart quantity first. Do not give bulk pricing on a single item based on future promises. Say: "I\'d love to offer a volume deal — add the items to your cart and I can work out something special."';

        // Cooldown awareness — if this is a returning negotiator.
        $cooldown_hours = (int) get_option( 'aipn_cooldown_hours', 0 );
        if ( $cooldown_hours > 0 && ! empty( $session['is_returning_negotiator'] ) ) {
            $previous_offer = $session['previous_final_offer'] ?? 0;
            if ( $previous_offer > 0 ) {
                $currency = $context['currency'] ?? '$';
                $rules[] = sprintf(
                    'This customer recently negotiated and was offered %s%.2f. Present this directly: "Welcome back! I believe we had a great offer on the table — %s%.2f. Would you like to proceed with that?" Only negotiate further if they decline.',
                    $currency, $previous_offer, $currency, $previous_offer
                );
            }
        }

        // Allow during sales check.
        if ( get_option( 'aipn_allow_during_sales', 'no' ) === 'yes' ) {
            foreach ( $cart_data['items'] as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( $product && $product->is_on_sale() ) {
                    $rules[] = sprintf(
                        '"%s" is already on sale. The negotiation price is ON TOP of the sale price. Make sure the customer knows they\'re getting an extra deal: "You\'re already getting a sale price, and I can sweeten it even more!"',
                        $item['name']
                    );
                    break; // Only mention once.
                }
            }
        }

        $context['anti_manipulation'] = $rules;

        return $context;
    }

    /**
     * Add anchoring rules.
     *
     * Makes the AI reference the original price and value in every counter-offer.
     */
    public function add_anchoring_rules( array $context, array $cart_data, array $session ): array {
        $currency = $context['currency'] ?? '$';
        $rules    = array();

        $rules[] = sprintf(
            'ANCHORING: Always reference the original price when making counter-offers. Remind the customer of the full value they\'re getting. Example: "This %s cart includes [key benefits]. At %s%.2f, you\'re already saving — and I can do even better."',
            $currency . number_format( $cart_data['cart_total'], 2 ),
            $currency,
            $cart_data['cart_total']
        );

        // Build product value highlights.
        $highlights = array();
        foreach ( array_slice( $cart_data['items'], 0, 3 ) as $item ) {
            $highlights[] = $item['name'];
        }
        if ( ! empty( $highlights ) ) {
            $rules[] = sprintf(
                'When countering, mention specific products by name to reinforce value: %s. Frame the discount as savings: "That\'s %s[amount] in savings!" rather than just stating the new price.',
                implode( ', ', $highlights ),
                $currency
            );
        }

        $context['anchoring'] = array( 'rules' => $rules );

        return $context;
    }

    /**
     * Add smart closing rules.
     *
     * Configurable urgency time windows and alternative suggestions.
     */
    public function add_smart_closing_rules( array $context, array $cart_data, array $session ): array {
        $rules          = array();
        $current_turn   = $session['turn_count'] ?? 0;
        $max_turns      = (int) get_option( 'aipn_max_turns', 5 );
        $remaining      = max( 0, $max_turns - $current_turn );

        // Offer expiry time window — create urgency on final offers.
        $offer_expiry_mins = (int) get_option( 'aipn_offer_expiry_mins', 30 );
        if ( $offer_expiry_mins > 0 && $remaining <= 2 ) {
            $rules[] = sprintf(
                'When making your final or near-final offer, create urgency: "I can hold this price for you for the next %d minutes. After that, it resets to the listed price." This encourages quick decisions without being pushy.',
                $offer_expiry_mins
            );
        }

        // Offer alternatives instead of flat rejection.
        $rules[] = 'When the customer\'s offer is too low, NEVER just say "no". Instead, suggest alternatives: make a slightly higher counter-offer, or mention they could adjust their cart to fit their budget. Frame it helpfully: "I can\'t quite do that, but here\'s what I CAN do..."';

        // Cross-sell as alternative (if cross-sells are available).
        if ( ! empty( $context['cross_sells']['available'] ) ) {
            $rules[] = 'If negotiation stalls and a deal seems unlikely, you can suggest adding a complementary product to "unlock" a better overall deal, making the total package more valuable.';
        }

        if ( ! empty( $rules ) ) {
            $context['smart_closing'] = array( 'rules' => $rules );
        }

        return $context;
    }
}
