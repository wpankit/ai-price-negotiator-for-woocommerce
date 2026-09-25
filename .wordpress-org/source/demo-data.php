<?php
/**
 * Demo negotiations for the Analytics & Chat Logs screenshots, used by build-assets.mjs.
 *
 *   wp eval-file demo-data.php "Jacket,Shoes,Shirt - Cream,Shirt - Green,Shirt,Hat"   add the demo
 *   wp eval-file demo-data.php clean                                                   remove it
 *
 * The first argument names the store's products to use (at least three). Only demo rows are
 * touched: seeded rows have session IDs starting "aipn_demo_", and the checkout shots
 * negotiate as emma.johnson@example.com, whose rows and one-time coupons are removed too.
 *
 * @package AI_Price_Negotiator
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

global $wpdb;
$aipn_table      = esc_sql( $wpdb->prefix . 'aipn_negotiations' );
$aipn_demo_email = 'emma.johnson@example.com';

// Remove the previous demo: seeded rows, the checkout shots' rows and their coupons.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$aipn_table} WHERE session_id LIKE %s OR customer_email = %s", $wpdb->esc_like( 'aipn_demo_' ) . '%', $aipn_demo_email ) );
foreach ( get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_aipn_session_id', 'fields' => 'ids' ) ) as $aipn_coupon ) {
	if ( in_array( $aipn_demo_email, (array) get_post_meta( $aipn_coupon, 'customer_email', true ), true ) ) {
		wp_delete_post( $aipn_coupon, true );
	}
}
if ( isset( $args[0] ) && 'clean' === $args[0] ) {
	echo "Removed the demo negotiations.\n";
	return;
}

mt_srand( 7 );

$aipn_products = array();
foreach ( array_map( 'trim', explode( ',', (string) ( $args[0] ?? '' ) ) ) as $aipn_title ) {
	$aipn_found = wc_get_products( array( 'name' => $aipn_title, 'type' => 'simple', 'limit' => 1 ) );
	if ( $aipn_found && (float) $aipn_found[0]->get_price() > 0 ) {
		$aipn_products[] = array( $aipn_found[0]->get_id(), $aipn_found[0]->get_name(), (float) $aipn_found[0]->get_price() );
	}
}
if ( count( $aipn_products ) < 3 ) {
	WP_CLI::error( 'Name at least three simple products with prices, separated by commas.' );
}
$aipn_people   = array( 'Liam Chen', 'Sophia Patel', 'Noah Williams', 'Ava Rodriguez', 'Ethan Kim', 'Mia Thompson', 'Lucas Garcia', 'Isabella Nguyen', 'Mason Brown', 'Amelia Davis', 'James Wilson', 'Charlotte Evans', 'Daniel Park', 'Grace Turner', 'Zoe Mitchell', 'Ryan Cooper', 'Nora Bennett', 'Leo Fischer', 'Chloe Martin', 'Aarav Shah', 'Hannah Scott', 'Mateo Silva' );
$aipn_greeting = "Hi, I'm Alex from the Pinewood deals team! Nice picks. What would you like to pay for the whole cart?";
$aipn_expired  = "Thanks for negotiating with us! Unfortunately we couldn't reach a deal this time. You're welcome to complete your purchase at the current price, or come back and try again later!";

/** Format a price. */
function aipn_demo_usd( $amount ) {
	return '$' . ( floor( $amount ) == $amount ? number_format( $amount ) : number_format( $amount, 2 ) ); // phpcs:ignore Universal.Operators.StrictComparisons
}

/** A cart of one to three products. */
function aipn_demo_cart( $products ) {
	$keys  = (array) array_rand( $products, mt_rand( 1, 3 ) );
	$items = array();
	foreach ( $keys as $key ) {
		list( $id, $name, $price ) = $products[ $key ];
		$qty     = mt_rand( 1, 10 ) > 8 ? 2 : 1;
		$items[] = array(
			'product_id'  => $id,
			'name'        => $name,
			'price'       => (float) $price,
			'quantity'    => $qty,
			'line_total'  => (float) ( $price * $qty ),
			'floor_price' => round( $price * 0.9, 2 ),
		);
	}
	return $items;
}

/** Insert one negotiation. */
function aipn_demo_row( $table, $row ) {
	global $wpdb;
	$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB
}

$aipn_rows = array();

// The most recent negotiation, shown expanded in the transcript screenshot.
$aipn_rows[] = array(
	'name'   => 'Olivia Martin',
	'items'  => array(
		array( 'product_id' => $aipn_products[0][0], 'name' => $aipn_products[0][1], 'price' => $aipn_products[0][2], 'quantity' => 1, 'line_total' => $aipn_products[0][2], 'floor_price' => round( $aipn_products[0][2] * 0.9, 2 ) ),
		array( 'product_id' => $aipn_products[3][0] ?? $aipn_products[1][0], 'name' => $aipn_products[3][1] ?? $aipn_products[1][1], 'price' => $aipn_products[3][2] ?? $aipn_products[1][2], 'quantity' => 1, 'line_total' => $aipn_products[3][2] ?? $aipn_products[1][2], 'floor_price' => round( ( $aipn_products[3][2] ?? $aipn_products[1][2] ) * 0.9, 2 ) ),
	),
	'status' => 'accepted',
	'final'  => 42.0,
	'hours'  => 2.3,
	'chat'   => array(
		array( 'assistant', $aipn_greeting ),
		array( 'user', 'Would you do $35 for both?' ),
		array( 'assistant', "I hear you! $35 is a little low for a jacket and a shirt, but I can do $44 for the pair." ),
		array( 'user', "How about $41? It's a birthday present for my brother." ),
		array( 'assistant', 'Lucky brother! Make it $42 and you have a deal.' ),
		array( 'user', 'Deal!' ),
		array( 'assistant', 'Done! Your discount is applied to the cart. Happy birthday to your brother!' ),
	),
);

// The rest of the month.
$aipn_statuses = array_merge( array_fill( 0, 27, 'accepted' ), array_fill( 0, 10, 'expired' ), array_fill( 0, 7, 'abandoned' ) );
shuffle( $aipn_statuses );
foreach ( $aipn_statuses as $aipn_i => $aipn_status ) {
	$aipn_items = aipn_demo_cart( $aipn_products );
	$aipn_total = array_sum( wp_list_pluck( $aipn_items, 'line_total' ) );
	$aipn_floor = round( $aipn_total * 0.9, 2 );
	$aipn_low   = floor( $aipn_total * mt_rand( 62, 80 ) / 100 );
	$aipn_first = ceil( $aipn_total * mt_rand( 95, 98 ) / 100 );
	$aipn_chat  = array( array( 'assistant', $aipn_greeting ) );
	$aipn_final = 0.0;

	$aipn_chat[] = array( 'user', array( 'Would you take ' . aipn_demo_usd( $aipn_low ) . '?', 'Can you do ' . aipn_demo_usd( $aipn_low ) . ' for everything?', 'I was hoping to pay around ' . aipn_demo_usd( $aipn_low ) . '.', aipn_demo_usd( $aipn_low ) . '?' )[ mt_rand( 0, 3 ) ] );
	$aipn_chat[] = array( 'assistant', array( 'That one is a little low for me. I can do ' . aipn_demo_usd( $aipn_first ) . ' for the whole cart.', 'I like where your head is at! How about ' . aipn_demo_usd( $aipn_first ) . '?', 'I can\'t go that low, but ' . aipn_demo_usd( $aipn_first ) . ' works for me.' )[ mt_rand( 0, 2 ) ] );

	if ( 'accepted' === $aipn_status ) {
		$aipn_final  = max( ceil( $aipn_floor ), floor( $aipn_total * mt_rand( 90, 95 ) / 100 ) );
		$aipn_final  = min( $aipn_final, $aipn_total - 1 );
		$aipn_middle = floor( ( $aipn_low + $aipn_final ) / 2 );
		$aipn_chat[] = array( 'user', array( 'Meet me at ' . aipn_demo_usd( $aipn_middle ) . '?', 'How about ' . aipn_demo_usd( $aipn_middle ) . '? It\'s my first order.', aipn_demo_usd( $aipn_middle ) . ' and I\'ll check out now.' )[ mt_rand( 0, 2 ) ] );
		$aipn_chat[] = array( 'assistant', 'Let\'s meet in the middle: ' . aipn_demo_usd( $aipn_final ) . ' and it\'s yours.' );
		$aipn_chat[] = array( 'user', array( 'Deal!', 'OK, deal.', 'Works for me!' )[ mt_rand( 0, 2 ) ] );
		$aipn_chat[] = array( 'assistant', array( 'Deal! Your discount is applied to the cart.', 'You\'ve got yourself a deal! Enjoy your order.', 'Done! The new total is in your cart.' )[ mt_rand( 0, 2 ) ] );
	} elseif ( 'expired' === $aipn_status ) {
		for ( $aipn_t = 0; $aipn_t < 4; $aipn_t++ ) {
			$aipn_chat[] = array( 'user', array( 'Still too much. ' . aipn_demo_usd( $aipn_low + $aipn_t ) . '?', 'Come on, ' . aipn_demo_usd( $aipn_low + $aipn_t ) . '.', aipn_demo_usd( $aipn_low + $aipn_t ) . ' is my limit.' )[ mt_rand( 0, 2 ) ] );
			if ( $aipn_t < 3 ) {
				$aipn_chat[] = array( 'assistant', 'I can\'t go below ' . aipn_demo_usd( ceil( $aipn_floor ) ) . ' for this cart, sorry!' );
			}
		}
		$aipn_chat[] = array( 'assistant', $aipn_expired );
	}

	$aipn_rows[] = array(
		'name'   => mt_rand( 1, 10 ) > 8 ? '' : $aipn_people[ $aipn_i % count( $aipn_people ) ],
		'items'  => $aipn_items,
		'status' => $aipn_status,
		'final'  => $aipn_final,
		'hours'  => 5 + pow( mt_rand( 0, 1000 ) / 1000, 1.4 ) * 29 * 24,
		'chat'   => $aipn_chat,
	);
}

foreach ( $aipn_rows as $aipn_row ) {
	$aipn_total   = array_sum( wp_list_pluck( $aipn_row['items'], 'line_total' ) );
	$aipn_created = time() - (int) ( $aipn_row['hours'] * HOUR_IN_SECONDS );
	$aipn_turns   = count( array_filter( $aipn_row['chat'], function ( $m ) {
		return 'user' === $m[0];
	} ) );
	$aipn_email   = $aipn_row['name'] ? strtolower( str_replace( ' ', '.', $aipn_row['name'] ) ) . '@example.com' : '';
	aipn_demo_row(
		$aipn_table,
		array(
			'session_id'      => 'aipn_demo_' . wp_generate_password( 12, false ),
			'customer_id'     => 0,
			'customer_name'   => $aipn_row['name'],
			'customer_email'  => $aipn_email,
			'cart_hash'       => md5( wp_json_encode( $aipn_row['items'] ) ),
			'cart_total'      => $aipn_total,
			'floor_total'     => round( $aipn_total * 0.9, 2 ),
			'final_price'     => 'accepted' === $aipn_row['status'] ? $aipn_row['final'] : 0,
			'discount_amount' => 'accepted' === $aipn_row['status'] ? $aipn_total - $aipn_row['final'] : 0,
			'coupon_code'     => 'accepted' === $aipn_row['status'] ? 'NEGO-' . strtoupper( wp_generate_password( 10, false ) ) : '',
			'status'          => $aipn_row['status'],
			'turn_count'      => 'expired' === $aipn_row['status'] ? 6 : $aipn_turns,
			'chat_log'        => wp_json_encode(
				array_map(
					function ( $m ) use ( $aipn_created ) {
						return array( 'role' => $m[0], 'content' => $m[1], 'timestamp' => $aipn_created );
					},
					$aipn_row['chat']
				)
			),
			'cart_items'      => wp_json_encode( $aipn_row['items'] ),
			'created_at'      => gmdate( 'Y-m-d H:i:s', $aipn_created ),
			'updated_at'      => gmdate( 'Y-m-d H:i:s', $aipn_created + 300 ),
		)
	);
}

echo 'seeded ', count( $aipn_rows ), " negotiations\n";
