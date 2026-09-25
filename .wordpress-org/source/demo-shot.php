<?php
/**
 * Plugin Name: AI Price Negotiator demo screenshots (temporary)
 * Description: Installed by .wordpress-org/build-assets.mjs while it takes the screenshots, then removed. Only requests carrying the aipn_demo_shot cookie are affected: they see a demo US store, and the negotiator's replies are scripted, so no OpenAI calls are made.
 *
 * @package AI_Price_Negotiator
 */

// phpcs:disable WordPress.Security.NonceVerification

if ( empty( $_COOKIE['aipn_demo_shot'] ) ) {
	return;
}

add_filter(
	'pre_option_aipn_openai_key',
	function () {
		return 'sk-demo-screenshots-not-a-real-key';
	}
);
add_filter(
	'pre_option_blogname',
	function () {
		return 'Pinewood Outfitters';
	}
);

// Open a "coming soon" store, and make it a US store with a payment method.
add_filter(
	'pre_option_woocommerce_coming_soon',
	function () {
		return 'no';
	}
);
add_filter(
	'pre_option_woocommerce_default_country',
	function () {
		return 'US:CA';
	}
);
add_filter(
	'woocommerce_currency',
	function () {
		return 'USD';
	}
);
add_filter(
	'pre_option_woocommerce_bacs_settings',
	function () {
		return array(
			'enabled'     => 'yes',
			'title'       => 'Direct bank transfer',
			'description' => 'Make your payment directly into our bank account. Your order will ship once the funds have cleared.',
		);
	}
);
if ( ! is_admin() ) {
	add_filter( 'show_admin_bar', '__return_false' );
}

/**
 * The scripted negotiator: one reply per customer message, chosen by the aipn_demo_script cookie.
 *
 * @return string[]
 */
function aipn_demo_script() {
	$greeting = "Hi, I'm Alex from the Pinewood deals team! Nice picks. What would you like to pay for the whole cart?";
	$scripts  = array(
		'deal'    => array(
			$greeting,
			'I like your style! $50 is a stretch for a **Jacket** and **Shoes** in one go, though. I can do $65 for everything.',
			'Welcome to Pinewood! Meet me at $62 and the whole cart is yours.',
			"You've got yourself a deal! Enjoy the new gear. [DEAL_ACCEPTED:62.00]",
		),
		'suggest' => array(
			$greeting,
			'Close! I can do $64 for the three. And a **Hat** would finish off that **Jacket** nicely. Want to add one for just $10? [SUGGEST_PRODUCT:' . AIPN_DEMO_HAT . ':10]',
		),
	);
	$key = isset( $_COOKIE['aipn_demo_script'] ) ? sanitize_key( wp_unslash( $_COOKIE['aipn_demo_script'] ) ) : 'deal';
	return $scripts[ $key ] ?? $scripts['deal'];
}

define( 'AIPN_DEMO_HAT', isset( $_COOKIE['aipn_demo_hat'] ) ? absint( $_COOKIE['aipn_demo_hat'] ) : 0 );

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.openai.com/' ) ) {
			return $pre;
		}
		if ( false !== strpos( $url, '/v1/models' ) ) {
			$body = array( 'data' => array( array( 'id' => 'gpt-4o-mini' ) ) );
		} else {
			$request = json_decode( $args['body'], true );
			$turn    = 0;
			foreach ( (array) ( $request['messages'] ?? array() ) as $message ) {
				if ( 'user' === ( $message['role'] ?? '' ) ) {
					++$turn;
				}
			}
			$script = aipn_demo_script();
			$body   = array( 'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => $script[ min( $turn, count( $script ) - 1 ) ] ) ) ) );
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
