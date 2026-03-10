<?php
/**
 * Chat Handler — REST API endpoints for the negotiation system.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Chat_Handler {

    private const REST_NAMESPACE = 'aipn/v1';

    /** @var AIPN_Session_Manager */
    private $session_manager;

    /** @var AIPN_Rules_Engine */
    private $rules_engine;

    /** @var AIPN_Prompt_Builder */
    private $prompt_builder;

    /** @var AIPN_Coupon_Manager */
    private $coupon_manager;

    /** @var AIPN_Cart_Analyzer */
    private $cart_analyzer;

    /** @var AIPN_Logger */
    private $logger;

    public function __construct(
        AIPN_Session_Manager $session_manager,
        AIPN_Rules_Engine $rules_engine,
        AIPN_Prompt_Builder $prompt_builder,
        AIPN_Coupon_Manager $coupon_manager,
        AIPN_Cart_Analyzer $cart_analyzer,
        AIPN_Logger $logger
    ) {
        $this->session_manager = $session_manager;
        $this->rules_engine    = $rules_engine;
        $this->prompt_builder  = $prompt_builder;
        $this->coupon_manager  = $coupon_manager;
        $this->cart_analyzer   = $cart_analyzer;
        $this->logger          = $logger;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void {
        // Main negotiation endpoint.
        register_rest_route( self::REST_NAMESPACE, '/negotiate', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_negotiate' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'message' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'offer' => array(
                    'type'              => 'number',
                    'required'          => false,
                    'default'           => 0,
                    'sanitize_callback' => function ( $value ) {
                        return is_numeric( $value ) ? (float) $value : 0.0;
                    },
                ),
                'customer_email' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'customer_name' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Get current session state (for restoring conversation on page reload).
        register_rest_route( self::REST_NAMESPACE, '/session', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'handle_get_session' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        // Add a suggested cross-sell product at special price.
        register_rest_route( self::REST_NAMESPACE, '/add-suggestion', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_add_suggestion' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'product_id' => array(
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
                'special_price' => array(
                    'type'              => 'number',
                    'required'          => true,
                    'sanitize_callback' => function ( $value ) {
                        return is_numeric( $value ) ? (float) $value : 0.0;
                    },
                ),
            ),
        ) );
    }

    /**
     * Permission callback — verify nonce.
     */
    public function check_permission( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'x-wp-nonce' ) ?: $request->get_header( 'X-WP-Nonce' );
        return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
    }

    /**
     * Handle the main negotiation endpoint.
     */
    public function handle_negotiate( WP_REST_Request $request ) {
        // Preflight checks.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'no_woocommerce', __( 'WooCommerce is required.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        if ( get_option( 'aipn_enabled', 'yes' ) !== 'yes' ) {
            return new WP_Error( 'disabled', __( 'Negotiation is currently disabled.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 403 ) );
        }

        $api_key = trim( (string) get_option( 'aipn_openai_key', '' ) );
        if ( $api_key === '' ) {
            return new WP_Error( 'missing_key', __( 'OpenAI API key is not configured.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        // Ensure cart is loaded (needed for REST context).
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return new WP_Error( 'empty_cart', __( 'Your cart is empty.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        // Get or create session.
        $session = $this->session_manager->get_or_create( $this->cart_analyzer );

        if ( $session['status'] !== 'active' ) {
            return new WP_Error( 'session_closed', __( 'This negotiation has ended. Please refresh the page.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        // Stock check — verify products are still available mid-negotiation (Part 8.1).
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ( $product && $product->managing_stock() && ! $product->is_in_stock() ) {
                return new WP_REST_Response( array(
                    'reply'       => sprintf(
                        /* translators: %s: product name that went out of stock */
                        __( 'I\'m sorry — %s just went out of stock! Please update your cart and we can continue.', 'ai-price-negotiator-for-woocommerce' ),
                        $product->get_name()
                    ),
                    'accepted'    => false,
                    'coupon_code' => '',
                    'new_total'   => 0.0,
                    'suggestions' => array(),
                    'cart_actions' => array(),
                    'session_id'  => $session['session_id'],
                    'turn'        => $session['turn_count'] ?? 0,
                    'ended'       => true,
                ), 200 );
            }
        }

        // Cooldown check — detect returning negotiators.
        $cooldown_hours = (int) get_option( 'aipn_cooldown_hours', 0 );
        if ( $cooldown_hours > 0 && empty( $session['cooldown_checked'] ) ) {
            $customer_email = $session['customer_email'] ?? '';
            $user_id        = get_current_user_id();

            $previous = $this->logger->get_recent_negotiation( $customer_email, $user_id, $cooldown_hours );

            if ( $previous ) {
                $session['is_returning_negotiator'] = true;
                $session['previous_final_offer']    = ( $previous['status'] === 'accepted' )
                    ? (float) $previous['final_price']
                    : 0.0;

                // If accepted, recall the accepted price; if expired, recall the last counter-offer or cart total.
                if ( $session['previous_final_offer'] <= 0 && ! empty( $previous['cart_total'] ) ) {
                    // No accepted price — use floor as fallback (don't reveal exact previous offers).
                    $session['previous_final_offer'] = (float) $previous['floor_total'];
                }
            }

            $session['cooldown_checked'] = true;
            $this->session_manager->update( $session );
        }

        // Process email capture if provided.
        $incoming_email = (string) $request->get_param( 'customer_email' );
        $incoming_name  = (string) $request->get_param( 'customer_name' );

        if ( $incoming_email !== '' && is_email( $incoming_email ) ) {
            $session['customer_email'] = $incoming_email;
            $session['customer_name']  = $incoming_name;
            $session['email_captured'] = true;
            $this->session_manager->update( $session );
        }

        // Extract customer input.
        $message = (string) $request->get_param( 'message' );
        $offer   = (float) $request->get_param( 'offer' );

        // Try to extract a numeric offer from the message text too.
        if ( $offer <= 0 && $message !== '' ) {
            $offer = $this->extract_offer_from_message( $message );
        }

        // Allow empty input on the very first turn — this triggers the AI greeting.
        $is_greeting = ( $message === '' && $offer <= 0 && empty( $session['conversation'] ) );

        // Require at least a message or an offer (unless greeting).
        if ( ! $is_greeting && $message === '' && $offer <= 0 ) {
            return new WP_Error( 'empty_input', __( 'Please enter a message or an offer amount.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        // Record customer message in session (skip for greeting — no user message).
        if ( ! $is_greeting ) {
            $currency_symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
            $user_message = $message;
            if ( $offer > 0 && strpos( $message, (string) $offer ) === false ) {
                $user_message .= ( $user_message ? ' ' : '' ) . sprintf( '(My offer: %s%.2f)', $currency_symbol, $offer );
            }

            $this->session_manager->add_message( $session, 'user', $user_message );
            $this->session_manager->increment_turn( $session );
            if ( $offer > 0 ) {
                $this->session_manager->record_offer( $session, $offer );
            }
        }

        // Enforce max turns — hard stop if limit exceeded.
        $max_turns = (int) get_option( 'aipn_max_turns', 5 );
        if ( ! $is_greeting && $session['turn_count'] > $max_turns ) {
            $final_msg = __( 'Thanks for negotiating with us! Unfortunately we couldn\'t reach a deal this time. You\'re welcome to complete your purchase at the current price, or come back and try again later!', 'ai-price-negotiator-for-woocommerce' );
            $this->session_manager->add_message( $session, 'assistant', $final_msg );
            $this->session_manager->end_negotiation( $session, 'expired' );

            return new WP_REST_Response( array(
                'reply'       => $final_msg,
                'accepted'    => false,
                'coupon_code' => '',
                'new_total'   => 0.0,
                'suggestions' => array(),
                'session_id'  => $session['session_id'],
                'turn'        => $session['turn_count'],
                'ended'       => true,
            ), 200 );
        }

        // Run the rules engine.
        $context = $this->rules_engine->evaluate( $session );

        // Build OpenAI messages.
        $messages = $this->prompt_builder->build( $context, $session['conversation'] );

        // Call OpenAI.
        $ai_raw = $this->call_openai( $api_key, $messages );
        if ( is_wp_error( $ai_raw ) ) {
            return $ai_raw;
        }

        // Parse the AI response for action tags.
        $parsed = $this->prompt_builder->parse_response( $ai_raw );

        // Add AI response to conversation (clean text, no tags).
        $this->session_manager->add_message( $session, 'assistant', $parsed['text'] );

        // Track AI counter-offer: extract any price the AI mentioned.
        if ( ! $parsed['deal_accepted'] ) {
            $counter_price = $this->extract_counter_offer_from_response( $parsed['text'] );
            if ( $counter_price > 0 ) {
                $this->session_manager->record_counter_offer( $session, $counter_price );
            }
        }

        // Track cross-sell suggestion (one-shot: first suggestion locks it).
        if ( ! empty( $parsed['suggested_products'] ) ) {
            $session['cross_sell_suggested'] = true;
            $this->session_manager->update( $session );
        }

        // Execute cart actions requested by the AI (add, remove, update qty).
        $cart_action_results = array();
        if ( ! empty( $parsed['cart_actions'] ) ) {
            $cart_action_results = $this->execute_cart_actions( $parsed['cart_actions'] );

            // Re-analyze cart after modifications — update session totals so deal
            // acceptance validation uses the correct floor/cart values.
            $updated_cart = $this->cart_analyzer->analyze();
            $session['cart_total']  = $updated_cart['cart_total'];
            $session['floor_total'] = $updated_cart['floor_total'];
            $this->session_manager->update( $session );
        }

        // Prepare the API response.
        $response_data = array(
            'reply'        => $parsed['text'],
            'accepted'     => false,
            'coupon_code'  => '',
            'new_total'    => 0.0,
            'suggestions'  => $parsed['suggested_products'],
            'cart_actions'  => $cart_action_results,
            'session_id'   => $session['session_id'],
            'turn'         => $session['turn_count'],
        );

        $email_captured = ! empty( $session['email_captured'] );

        // Handle deal acceptance.
        if ( $parsed['deal_accepted'] && $parsed['accepted_price'] > 0 ) {

            // If email not captured yet, hold the deal and request email first.
            if ( ! $email_captured ) {
                $session['held_accepted_price'] = $parsed['accepted_price'];
                $response_data['request_email'] = true;
                $response_data['hold_deal']     = true;

                $this->session_manager->update( $session );
                return new WP_REST_Response( $response_data, 200 );
            }

            $accepted_price = $parsed['accepted_price'];
            $floor_total    = $session['floor_total'];
            $cart_total     = $session['cart_total'];

            // Validate: AI should not accept below floor.
            if ( $accepted_price < $floor_total ) {
                $accepted_price = $floor_total;
            }

            // Validate: AI should not accept above the cart total.
            if ( $accepted_price > $cart_total ) {
                $accepted_price = $cart_total;
            }

            // Validate: If the customer has made offers, the accepted price should be
            // at least as high as their best offer (AI shouldn't give a bigger discount
            // than what the customer actually asked for).
            $best_offer = $session['best_customer_offer'] ?? 0;
            if ( $best_offer > 0 && $accepted_price < $best_offer && $best_offer >= $floor_total ) {
                $accepted_price = $best_offer;
            }
            $discount = max( 0, $cart_total - $accepted_price );

            if ( $discount > 0 ) {
                $coupon_code = $this->coupon_manager->create_cart_coupon(
                    $discount,
                    $session['session_id'],
                    $session['cart_items'],
                    $session['customer_email']
                );

                if ( is_wp_error( $coupon_code ) ) {
                    $response_data['reply'] .= ' ' . __( 'There was an issue applying the discount. Please try again.', 'ai-price-negotiator-for-woocommerce' );
                } else {
                    $applied = $this->coupon_manager->apply_to_cart( $coupon_code, $session['customer_email'] ?? '' );

                    if ( $applied ) {
                        $this->session_manager->accept( $session, $accepted_price, $coupon_code );

                        $response_data['accepted']    = true;
                        $response_data['coupon_code'] = $coupon_code;
                        $response_data['new_total']   = $accepted_price;
                    } else {
                        $response_data['reply'] .= ' ' . __( 'Could not apply the coupon. Please try again.', 'ai-price-negotiator-for-woocommerce' );
                    }
                }
            } else {
                // No discount needed (offer = full price).
                $this->session_manager->accept( $session, $accepted_price, '' );
                $response_data['accepted']  = true;
                $response_data['new_total'] = $accepted_price;
            }
        }

        // Check if email was just captured and there's a held deal — finalize it.
        if ( $email_captured && ! empty( $session['held_accepted_price'] ) && ! $response_data['accepted'] ) {
            $held_price = (float) $session['held_accepted_price'];
            unset( $session['held_accepted_price'] );

            $floor_total = $session['floor_total'];
            $cart_total  = $session['cart_total'];

            if ( $held_price < $floor_total ) {
                $held_price = $floor_total;
            }
            if ( $held_price > $cart_total ) {
                $held_price = $cart_total;
            }

            $best_offer = $session['best_customer_offer'] ?? 0;
            if ( $best_offer > 0 && $held_price < $best_offer && $best_offer >= $floor_total ) {
                $held_price = $best_offer;
            }

            $discount = max( 0, $cart_total - $held_price );

            if ( $discount > 0 ) {
                $coupon_code = $this->coupon_manager->create_cart_coupon(
                    $discount,
                    $session['session_id'],
                    $session['cart_items'],
                    $session['customer_email']
                );

                if ( ! is_wp_error( $coupon_code ) ) {
                    $applied = $this->coupon_manager->apply_to_cart( $coupon_code, $session['customer_email'] ?? '' );
                    if ( $applied ) {
                        $this->session_manager->accept( $session, $held_price, $coupon_code );
                        $response_data['accepted']    = true;
                        $response_data['coupon_code'] = $coupon_code;
                        $response_data['new_total']   = $held_price;
                    }
                }
            } else {
                $this->session_manager->accept( $session, $held_price, '' );
                $response_data['accepted']  = true;
                $response_data['new_total'] = $held_price;
            }
        }

        // Request email capture for guests after engagement (turn >= 2).
        if ( ! $email_captured && ! $is_greeting && $session['turn_count'] >= 2 ) {
            $response_data['request_email'] = true;
        }

        // Save session state.
        $this->session_manager->update( $session );

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * Handle GET session — restore conversation on page reload.
     */
    public function handle_get_session( WP_REST_Request $request ) {
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        $session = $this->session_manager->get_existing();

        if ( ! $session ) {
            return new WP_REST_Response( array( 'active' => false ), 200 );
        }

        return new WP_REST_Response( array(
            'active'       => $session['status'] === 'active',
            'status'       => $session['status'],
            'session_id'   => $session['session_id'],
            'conversation' => $this->sanitize_conversation_for_frontend( $session['conversation'] ?? array() ),
            'turn_count'   => $session['turn_count'] ?? 0,
            'cart_total'      => $session['cart_total'] ?? 0,
            'accepted'        => $session['status'] === 'accepted',
            'coupon_code'     => $session['coupon_code'] ?? '',
            'email_captured'  => ! empty( $session['email_captured'] ),
        ), 200 );
    }

    /**
     * Handle adding a cross-sell suggestion at the special negotiated price.
     */
    public function handle_add_suggestion( WP_REST_Request $request ) {
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        // Ensure WC session is initialized (critical for REST context).
        if ( ! WC()->session ) {
            return new WP_Error( 'no_session', __( 'Session not available. Please refresh the page.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        if ( ! WC()->cart ) {
            return new WP_Error( 'no_cart', __( 'Cart not available.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        $product_id    = (int) $request->get_param( 'product_id' );
        $special_price = (float) $request->get_param( 'special_price' );

        if ( $product_id <= 0 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid product.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', __( 'Product not found.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 404 ) );
        }

        if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            return new WP_Error( 'unavailable', __( 'This product is not available.', 'ai-price-negotiator-for-woocommerce' ), array( 'status' => 400 ) );
        }

        // Validate: special price must be between floor and regular price.
        $regular_price    = (float) $product->get_price();
        $global_floor_pct = (float) get_option( 'aipn_global_floor_pct', 70 );
        $floor_meta       = get_post_meta( $product_id, '_aipn_floor_price', true );
        $floor_price      = ( $floor_meta !== '' && $floor_meta !== false )
            ? (float) $floor_meta
            : round( $regular_price * ( $global_floor_pct / 100 ), 2 );

        if ( $special_price <= 0 || $special_price < $floor_price || $special_price > $regular_price ) {
            $special_price = max( $floor_price, min( $special_price, $regular_price ) );
        }

        // Clear WC notices so we can check for add-to-cart errors.
        wc_clear_notices();

        // Add to cart with custom meta to track the special price.
        $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array(
            'aipn_special_price' => $special_price,
        ) );

        if ( ! $cart_item_key ) {
            // Capture any WC notices as the error message.
            $notices = wc_get_notices( 'error' );
            wc_clear_notices();
            $error_msg = ! empty( $notices )
                ? wp_strip_all_tags( $notices[0]['notice'] ?? $notices[0] )
                : __( 'Could not add product to cart.', 'ai-price-negotiator-for-woocommerce' );
            return new WP_Error( 'add_failed', $error_msg, array( 'status' => 400 ) );
        }

        WC()->cart->calculate_totals();

        // Persist cart to session (REST context may not auto-save).
        $this->persist_cart_session();

        return new WP_REST_Response( array(
            'success'    => true,
            'cart_total' => (float) WC()->cart->get_total( 'edit' ),
            'item_key'   => $cart_item_key,
        ), 200 );
    }

    /**
     * Execute cart actions from AI response tags.
     *
     * @param array $actions Parsed cart actions from the prompt builder.
     * @return array Results of each action for the frontend.
     */
    private function execute_cart_actions( array $actions ): array {
        if ( ! WC()->cart ) {
            return array();
        }

        $results = array();

        foreach ( $actions as $action ) {
            switch ( $action['action'] ) {
                case 'add':
                    $results[] = $this->cart_action_add( $action );
                    break;

                case 'update_qty':
                    $results[] = $this->cart_action_update_qty( $action );
                    break;

                case 'remove':
                    $results[] = $this->cart_action_remove( $action );
                    break;
            }
        }

        // Recalculate totals after all actions.
        WC()->cart->calculate_totals();

        // Persist cart to session (REST context may not auto-save).
        $this->persist_cart_session();

        return $results;
    }

    /**
     * Explicitly save cart data to the WC session.
     * WooCommerce's shutdown-based session save may not fire reliably in REST context.
     */
    private function persist_cart_session(): void {
        if ( ! WC()->session || ! WC()->cart ) {
            return;
        }

        $cart_for_session = array();
        foreach ( WC()->cart->get_cart() as $key => $values ) {
            $cart_for_session[ $key ] = $values;
            unset( $cart_for_session[ $key ]['data'] );
        }
        WC()->session->set( 'cart', $cart_for_session );
        WC()->session->set( 'cart_totals', WC()->cart->get_totals() );
        WC()->session->save_data();
    }

    /**
     * Cart action: add a product.
     */
    private function cart_action_add( array $action ): array {
        $product_id = $action['product_id'];
        $quantity   = $action['quantity'];
        $price      = $action['price'] ?? 0;

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            return array( 'action' => 'add', 'product_id' => $product_id, 'success' => false );
        }

        // Validate price: must be above zero and not exceed regular price.
        $regular_price = (float) $product->get_price();
        if ( $price <= 0 ) {
            $price = $regular_price;
        }
        if ( $price > $regular_price ) {
            $price = $regular_price;
        }

        // Validate against floor price.
        $global_floor_pct = (float) get_option( 'aipn_global_floor_pct', 70 );
        $floor_meta       = get_post_meta( $product_id, '_aipn_floor_price', true );
        $floor_price      = ( $floor_meta !== '' && $floor_meta !== false )
            ? (float) $floor_meta
            : round( $regular_price * ( $global_floor_pct / 100 ), 2 );

        if ( $price < $floor_price ) {
            $price = $floor_price;
        }

        $cart_item_data = array();
        if ( $price < $regular_price ) {
            $cart_item_data['aipn_special_price'] = $price;
        }

        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );

        return array(
            'action'     => 'add',
            'product_id' => $product_id,
            'name'       => $product->get_name(),
            'quantity'   => $quantity,
            'price'      => $price,
            'success'    => (bool) $cart_item_key,
        );
    }

    /**
     * Cart action: update quantity of a product already in the cart.
     */
    private function cart_action_update_qty( array $action ): array {
        $product_id  = $action['product_id'];
        $new_qty     = $action['quantity'];

        // Find the cart item key for this product.
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( (int) $cart_item['product_id'] === $product_id ) {
                WC()->cart->set_quantity( $cart_item_key, $new_qty );
                return array(
                    'action'     => 'update_qty',
                    'product_id' => $product_id,
                    'quantity'   => $new_qty,
                    'success'    => true,
                );
            }
        }

        return array( 'action' => 'update_qty', 'product_id' => $product_id, 'success' => false );
    }

    /**
     * Cart action: remove a product from the cart.
     */
    private function cart_action_remove( array $action ): array {
        $product_id = $action['product_id'];

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( (int) $cart_item['product_id'] === $product_id ) {
                WC()->cart->remove_cart_item( $cart_item_key );
                return array(
                    'action'     => 'remove',
                    'product_id' => $product_id,
                    'success'    => true,
                );
            }
        }

        return array( 'action' => 'remove', 'product_id' => $product_id, 'success' => false );
    }

    /**
     * Call OpenAI Chat Completions API.
     *
     * @param string $api_key  OpenAI API key.
     * @param array  $messages Messages array for the API.
     * @return string|WP_Error AI response text or error.
     */
    private function call_openai( string $api_key, array $messages ) {
        $body = wp_json_encode( array(
            'model'       => 'gpt-4o-mini',
            'messages'    => $messages,
            'max_tokens'  => 200,
            'temperature' => 0.8,
        ) );

        $request_args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => $body,
            'timeout' => 25,
        );

        // Retry once on connection failure (Part 8.3 of rulebook).
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $request_args );

        if ( is_wp_error( $response ) ) {
            // One retry.
            $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $request_args );
            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'openai_connection',
                    __( 'Could not connect to AI service. Please try again in a few moments.', 'ai-price-negotiator-for-woocommerce' ),
                    array( 'status' => 502 )
                );
            }
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'openai_error',
                __( 'AI service returned an error. Please try again.', 'ai-price-negotiator-for-woocommerce' ),
                array( 'status' => $code )
            );
        }

        $parsed = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $parsed['choices'][0]['message']['content'] ) ) {
            return new WP_Error(
                'openai_response',
                __( 'Unexpected AI response format.', 'ai-price-negotiator-for-woocommerce' ),
                array( 'status' => 500 )
            );
        }

        return trim( $parsed['choices'][0]['message']['content'] );
    }

    /**
     * Extract a numeric offer from a customer message.
     *
     * Uses a two-pass approach:
     * 1. First, try to match a price with an explicit currency symbol (highest confidence).
     * 2. Fallback: match a bare number, but only if the message looks like a price offer
     *    (contains price-intent words or is mostly just a number) AND the value is plausible
     *    relative to the cart total.
     */
    private function extract_offer_from_message( string $message ): float {
        $floor_total = 0.0;
        $cart_total  = 0.0;

        if ( WC()->cart ) {
            $cart_total = (float) WC()->cart->get_total( 'edit' );
        }

        // Minimum plausible offer: at least 10% of cart total (or $1 if cart is tiny).
        $min_plausible = max( 1, $cart_total * 0.10 );

        // Pass 1: Match amounts with an explicit currency symbol — high confidence.
        if ( preg_match_all( '/(?:[₹$€£¥₩₦₫₴₪₨₱¢]|Rs\.?)\s*([\d,]+(?:\.\d{1,2})?)/', $message, $matches ) && ! empty( $matches[1] ) ) {
            // Take the last currency-prefixed number (most likely the offer).
            $raw   = str_replace( ',', '', end( $matches[1] ) );
            $value = (float) $raw;
            if ( $value >= $min_plausible && $value <= 10000000 ) {
                return $value;
            }
        }

        // Skip bare-number extraction if message contains a percentage sign (e.g. "10% off").
        if ( strpos( $message, '%' ) !== false ) {
            return 0.0;
        }

        // Pass 2: Bare number — only accept if the message signals price intent.
        $price_intent = preg_match( '/\b(offer|pay|do|afford|spend|budget|deal|price|how\s+about|what\s+about|counter|accept|settle|willing|go)\b/i', $message );
        $is_mostly_number = preg_match( '/^\s*[\d,]+(?:\.\d{1,2})?\s*$/', $message );

        if ( $price_intent || $is_mostly_number ) {
            if ( preg_match_all( '/([\d,]+(?:\.\d{1,2})?)/', $message, $matches ) && ! empty( $matches[1] ) ) {
                $raw   = str_replace( ',', '', end( $matches[1] ) );
                $value = (float) $raw;
                if ( $value >= $min_plausible && $value <= 10000000 ) {
                    return $value;
                }
            }
        }

        return 0.0;
    }

    /**
     * Extract a counter-offer price from the AI response text.
     *
     * The counter-offer is the LOWEST price the AI mentions (the discount price),
     * not the cart total or original price the AI might reference for context.
     * We also filter out values below the floor or above the cart total.
     */
    private function extract_counter_offer_from_response( string $text ): float {
        $currency_symbol = preg_quote( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ), '/' );

        $candidates = array();

        // Match prices with the store's currency symbol, e.g. ₹450, $175.00
        if ( preg_match_all( '/' . $currency_symbol . '\s*([\d,]+(?:\.\d{1,2})?)/', $text, $matches ) && ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $raw ) {
                $value = (float) str_replace( ',', '', $raw );
                if ( $value >= 1 && $value <= 10000000 ) {
                    $candidates[] = $value;
                }
            }
        }

        // Fallback: match any currency symbol followed by a number.
        if ( empty( $candidates ) && preg_match_all( '/[₹$€£¥₩₦₫₴₪₨₱]\s*([\d,]+(?:\.\d{1,2})?)/', $text, $matches ) && ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $raw ) {
                $value = (float) str_replace( ',', '', $raw );
                if ( $value >= 1 && $value <= 10000000 ) {
                    $candidates[] = $value;
                }
            }
        }

        if ( empty( $candidates ) ) {
            return 0.0;
        }

        // The counter-offer is the lowest price mentioned (the discounted amount).
        // If the AI says "Your $300 cart... how about $250?" we want $250, not $300.
        return min( $candidates );
    }

    /**
     * Sanitize conversation history for frontend display.
     */
    private function sanitize_conversation_for_frontend( array $conversation ): array {
        return array_map( function ( $msg ) {
            return array(
                'role'    => $msg['role'] ?? 'assistant',
                'content' => wp_kses_post( $msg['content'] ?? '' ),
            );
        }, $conversation );
    }
}
