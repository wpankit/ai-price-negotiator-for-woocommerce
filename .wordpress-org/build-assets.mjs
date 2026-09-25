/**
 * Builds the wordpress.org listing assets for AI Price Negotiator for WooCommerce.
 *
 *   node .wordpress-org/build-assets.mjs                 icon PNGs and banners
 *   node .wordpress-org/build-assets.mjs --screenshots   also the screenshots
 *
 * The icon PNGs come from icon.svg and the banners from source/banner.html, rendered
 * in headless Chrome at the exact sizes wordpress.org expects.
 *
 * Screenshots are taken on a local WooCommerce site with the plugin active. While the
 * script runs, a must-use plugin (source/demo-shot.php) turns the site into a demo US
 * store for the script's own browser only, and scripts the negotiator's replies, so no
 * OpenAI calls are made. source/demo-data.php adds demo negotiations for the Analytics
 * screens. Both are removed when the script ends. Checkout shots are taken as a guest;
 * admin shots as an administrator signed in through a short-lived WP-CLI session.
 *
 * Needs Google Chrome, plus puppeteer-core and the Inter and Manrope fonts from the
 * WPAnkit Product theme's QA tools (set AIPN_QA_DIR if they live elsewhere).
 * Screenshots also need WP-CLI and the site: set AIPN_SITE_PATH, AIPN_SITE_URL,
 * AIPN_DB_SOCKET and AIPN_ADMIN, and name the demo products in AIPN_PRODUCTS (simple
 * products with prices: the cart uses the first three, the suggestion uses "Hat").
 */

import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, copyFileSync, existsSync, mkdirSync, unlinkSync, readdirSync, rmdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const QA = process.env.AIPN_QA_DIR || join( homedir(), 'Local Sites/pushrow-lp/app/public/wp-content/themes/wpankit-product/tools/qa' );
const SITE_PATH = process.env.AIPN_SITE_PATH || join( homedir(), 'Local Sites/other-plugin/app/public' );
const SITE_URL = process.env.AIPN_SITE_URL || 'http://other-plugin.local';
const DB_SOCKET = process.env.AIPN_DB_SOCKET || join( homedir(), 'Library/Application Support/Local/run/kdrgZVwMM/mysql/mysqld.sock' );
const ADMIN = process.env.AIPN_ADMIN || 'admin';
const PRODUCTS = process.env.AIPN_PRODUCTS || 'Jacket,Shoes,Shirt - Cream,Shirt - Green,Shirt,Hat';
const DOMAIN = new URL( SITE_URL ).hostname;

const puppeteer = createRequire( join( QA, 'package.json' ) )( 'puppeteer-core' );
const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

const font = ( family, pkg, weight ) =>
	`@font-face{font-family:${ family };font-weight:${ weight };src:url(data:font/woff2;base64,${ readFileSync( join( QA, 'node_modules/@fontsource', pkg, 'files', `${ pkg }-latin-${ weight }-normal.woff2` ) ).toString( 'base64' ) }) format("woff2")}`;
const FONTS = [ font( 'Inter', 'inter', 500 ), font( 'Inter', 'inter', 600 ), font( 'Inter', 'inter', 700 ), font( 'Manrope', 'manrope', 700 ), font( 'Manrope', 'manrope', 800 ) ].join( '\n' );
const iconUri = 'data:image/svg+xml;base64,' + readFileSync( join( HERE, 'icon.svg' ) ).toString( 'base64' );

/** Fails loudly when a PNG is not the size wordpress.org expects. */
function check( name, width, height ) {
	const png = readFileSync( join( HERE, name ) );
	const [ w, h ] = [ png.readUInt32BE( 16 ), png.readUInt32BE( 20 ) ];
	if ( w !== width || h !== height ) {
		throw new Error( `${ name } is ${ w }x${ h }, expected ${ width }x${ height }` );
	}
	console.log( `${ name }  ${ w }x${ h }  ${ Math.round( png.length / 1024 ) } KB` );
}

/* Screenshots ---------------------------------------------------------------- */

const MU_DIR = join( SITE_PATH, 'wp-content/mu-plugins' );
const MU_FILE = join( MU_DIR, 'aipn-demo-shot.php' );
const hadMuDir = existsSync( MU_DIR );
let wroteMu = false;
const sessions = [];

const wp = ( ...args ) => execFileSync( 'php', [ '-d', 'error_reporting=0', '-d', 'display_errors=0', '-d', `mysqli.default_socket=${ DB_SOCKET }`, '/usr/local/bin/wp', `--path=${ SITE_PATH }`, ...args ], { encoding: 'utf8' } );

/** Auth cookies for a short-lived session, destroyed when the script ends. */
function login( user ) {
	const s = JSON.parse( wp( 'eval', `$u = get_user_by( "login", "${ user }" ); $exp = time() + 1800; $t = WP_Session_Tokens::get_instance( $u->ID )->create( $exp ); echo json_encode( array( "uid" => $u->ID, "token" => $t, "cookies" => array( array( "name" => AUTH_COOKIE, "value" => wp_generate_auth_cookie( $u->ID, $exp, "auth", $t ) ), array( "name" => LOGGED_IN_COOKIE, "value" => wp_generate_auth_cookie( $u->ID, $exp, "logged_in", $t ) ) ) ) );` ) );
	sessions.push( s );
	return s.cookies.map( ( c ) => ( { ...c, domain: DOMAIN, path: '/' } ) );
}

const productId = ( name ) => Number( wp( 'eval', `$p = wc_get_products( array( "name" => ${ JSON.stringify( name ) }, "type" => "simple", "limit" => 1 ) ); echo $p ? $p[0]->get_id() : 0;` ) );

async function screenshots( browser ) {
	const names = PRODUCTS.split( ',' ).map( ( n ) => n.trim() );
	const cart = names.slice( 0, 3 ).map( productId );
	const hat = productId( 'Hat' );
	if ( cart.includes( 0 ) || ! hat ) {
		throw new Error( `Missing demo products: ${ PRODUCTS } and Hat` );
	}
	console.log( wp( 'eval-file', join( HERE, 'source/demo-data.php' ), PRODUCTS ).trim() );
	const shots = {};
	const errors = [];
	const demoCookies = ( script ) => [ [ 'aipn_demo_shot', '1' ], [ 'aipn_demo_script', script ], [ 'aipn_demo_hat', String( hat ) ] ].map( ( [ name, value ] ) => ( { name, value, domain: DOMAIN, path: '/' } ) );
	const watch = ( page, label ) => {
		page.on( 'pageerror', ( e ) => errors.push( `${ label }: ${ e.message }` ) );
		page.on( 'response', ( r ) => { if ( r.status() >= 400 ) errors.push( `${ label }: HTTP ${ r.status() } ${ r.url() }` ); } );
	};

	// Checkout, as a guest with the demo cart and the billing details filled in.
	async function checkout( script ) {
		const ctx = await browser.createBrowserContext();
		const page = await ctx.newPage();
		watch( page, `checkout ${ script }` );
		await page.setCookie( ...demoCookies( script ) );
		await page.setViewport( { width: 1440, height: 1190, deviceScaleFactor: 1.5 } );
		for ( const id of cart ) {
			await page.goto( `${ SITE_URL }/?add-to-cart=${ id }`, { waitUntil: 'domcontentloaded' } );
		}
		await page.goto( `${ SITE_URL }/checkout/`, { waitUntil: 'networkidle0' } );
		await page.goto( `${ SITE_URL }/checkout/`, { waitUntil: 'networkidle0' } ); // Without the "added to cart" notices.
		// Product descriptions and other plugins' buttons stay out of the shot.
		await page.addStyleTag( { content: '.wc-block-components-product-metadata,.ldfw{display:none!important} *{caret-color:transparent!important}' } );
		for ( const [ field, value ] of [ [ '#email', 'emma.johnson@example.com' ], [ '#billing-first_name', 'Emma' ], [ '#billing-last_name', 'Johnson' ], [ '#billing-address_1', '1204 Maple Street' ], [ '#billing-city', 'Sacramento' ], [ '#billing-postcode', '95814' ], [ '#billing-phone', '(916) 555-0142' ] ] ) {
			await page.click( field, { clickCount: 3 } );
			await page.type( field, value );
		}
		await page.evaluate( () => document.activeElement.blur() );
		await sleep( 2000 );
		const reply = () => page.waitForResponse( ( r ) => r.url().includes( 'aipn/v1/negotiate' ) );
		page.open = async () => {
			await Promise.all( [ reply(), page.click( '#aipn-open-btn' ) ] );
			await sleep( 1200 );
		};
		page.say = async ( text ) => {
			await page.type( '#aipn-message', text );
			await Promise.all( [ reply(), page.click( '#aipn-submit' ) ] );
			await sleep( 1600 );
		};
		page.shoot = async ( name ) => {
			await page.evaluate( () => window.scrollTo( 0, 0 ) );
			await page.mouse.move( 1430, 1180 );
			await sleep( 400 );
			shots[ name ] = await page.screenshot();
		};
		return page;
	}

	let page = await checkout( 'deal' );
	await page.shoot( 'invite' );
	await page.open();
	await page.say( 'Would you take $50 for everything?' );
	await page.say( "How about $60? It's my first order." );
	await page.shoot( 'chat' );
	// Keep the deal on screen: the widget folds itself away three seconds after a deal.
	await page.evaluate( () => { const later = window.setTimeout; window.setTimeout = ( fn, ms, ...rest ) => ( 3000 === ms || 2000 === ms ? 0 : later( fn, ms, ...rest ) ); } );
	await page.say( 'Deal!' );
	await page.waitForFunction( () => /62\.00/.test( document.querySelector( '.wc-block-components-totals-footer-item' )?.innerText || '' ), { timeout: 10000 } );
	await sleep( 1200 );
	await page.evaluate( () => document.querySelectorAll( '.aipn-fireworks' ).forEach( ( e ) => e.remove() ) );
	await page.shoot( 'deal' );
	await page.browserContext().close();

	page = await checkout( 'suggest' );
	await page.open();
	await page.say( 'Can you do $55 for these?' );
	await page.waitForSelector( '#aipn-suggestions:not([hidden])' );
	await sleep( 600 );
	await page.shoot( 'suggest' );
	await page.browserContext().close();

	// Admin screens.
	const ctx = await browser.createBrowserContext();
	page = await ctx.newPage();
	watch( page, 'admin' );
	await page.setCookie( ...login( ADMIN ), ...demoCookies( 'deal' ) );
	const HIDE = '#toplevel_page_noteflow-notes,#wp-admin-bar-noteflow-quick,#wp-admin-bar-noteflow-bell{display:none!important} *{caret-color:transparent!important}';
	const admin = async ( path, height = 900 ) => {
		await page.setViewport( { width: 1440, height, deviceScaleFactor: 1.5 } );
		await page.goto( `${ SITE_URL }/wp-admin/${ path }`, { waitUntil: 'networkidle2' } );
		// Other plugins' menus stay out of the shot.
		await page.addStyleTag( { content: HIDE } );
		await page.evaluate( () => document.querySelectorAll( '#adminmenu > li' ).forEach( ( li ) => { if ( /^Like Dislike/.test( li.querySelector( '.wp-menu-name' )?.textContent || '' ) ) li.style.display = 'none'; } ) );
		await page.mouse.move( 1435, height - 5 );
		await sleep( 500 );
	};
	const shoot = async ( name, options = {} ) => {
		await page.evaluate( () => document.activeElement && document.activeElement.blur() );
		await sleep( 300 );
		shots[ name ] = await page.screenshot( options );
	};

	await admin( 'admin.php?page=aipn-analytics', 1180 );
	await shoot( 'analytics' );
	await admin( 'admin.php?page=aipn-analytics', 1000 );
	await page.click( '.aipn-view-chat[data-chat-index="0"]' );
	await sleep( 600 );
	await page.evaluate( () => {
		document.querySelectorAll( '#aipn-chat-row-0 .aipn-chat-log__messages' ).forEach( ( e ) => { e.scrollTop = e.scrollHeight; } );
		const heading = [ ...document.querySelectorAll( '.aipn-analytics-section__header h2' ) ].find( ( e ) => /Recent/.test( e.textContent ) );
		window.scrollTo( 0, heading.getBoundingClientRect().top + window.scrollY - 60 );
	} );
	await sleep( 400 );
	await shoot( 'transcript' );

	await admin( 'admin.php?page=aipn-settings' );
	await page.click( '.aipn-test-key-btn' );
	await page.waitForFunction( () => ( document.querySelector( '.aipn-test-key-result' )?.textContent || '' ).length > 3, { timeout: 10000 } );
	await page.mouse.move( 1435, 895 );
	await shoot( 'setup' );

	await admin( `post.php?post=${ cart[ 0 ] }&action=edit`, 1000 );
	await page.$eval( '#_aipn_cost_price', ( e ) => { e.value = '14.00'; } );
	await page.$eval( '#_aipn_floor_price', ( e ) => { e.value = '21.00'; } );
	await page.evaluate( () => window.scrollTo( 0, document.getElementById( 'woocommerce-product-data' ).getBoundingClientRect().top + window.scrollY - 150 ) );
	await sleep( 400 );
	await shoot( 'product' );

	await admin( 'admin.php?page=aipn-settings&tab=pro' );
	await shoot( 'behavior', { fullPage: true } );

	await admin( 'admin.php?page=aipn-settings&tab=pro&section=custom_rules' );
	await page.$eval( '#aipn_custom_rules', ( e ) => { e.value = 'Never offer free shipping as part of a deal.\nIf the customer hesitates, mention our free 30-day returns.\nKeep the tone relaxed and never pushy.'; } );
	await shoot( 'rules' );

	await admin( 'admin.php?page=aipn-settings&tab=pro&section=sales' );
	await shoot( 'sales' );
	await admin( 'admin.php?page=aipn-settings&tab=pro&section=visibility', 1180 );
	await shoot( 'visibility' );
	await admin( 'admin.php?page=aipn-settings&tab=appearance' );
	await shoot( 'colors', { fullPage: true } );
	await admin( 'admin.php?page=aipn-settings&tab=text' );
	await shoot( 'text', { fullPage: true } );
	await ctx.close();

	if ( errors.length ) {
		throw new Error( 'Errors while taking screenshots:\n' + errors.join( '\n' ) );
	}
	// The order matches the Screenshots section of readme.txt.
	const order = [ 'chat', 'deal', 'suggest', 'invite', 'analytics', 'transcript', 'setup', 'product', 'behavior', 'rules', 'sales', 'visibility', 'colors', 'text' ];
	order.forEach( ( key, i ) => {
		const name = `screenshot-${ i + 1 }.png`;
		writeFileSync( join( HERE, name ), shots[ key ] );
		console.log( `${ name }  ${ key }  ${ shots[ key ].readUInt32BE( 16 ) }x${ shots[ key ].readUInt32BE( 20 ) }  ${ Math.round( shots[ key ].length / 1024 ) } KB` );
	} );
}

/* Build ---------------------------------------------------------------------- */

const browser = await puppeteer.launch( { executablePath: CHROME, headless: 'new' } );
try {
	if ( process.argv.includes( '--screenshots' ) ) {
		mkdirSync( MU_DIR, { recursive: true } );
		copyFileSync( join( HERE, 'source/demo-shot.php' ), MU_FILE );
		wroteMu = true;
		await screenshots( browser );
	}

	const page = await browser.newPage();
	for ( const size of [ 128, 256 ] ) {
		await page.setViewport( { width: size, height: size, deviceScaleFactor: 1 } );
		await page.setContent( `<html><body style="margin:0;background:transparent"><img src="${ iconUri }" width="${ size }" height="${ size }" style="display:block"></body></html>` );
		const name = `icon-${ size }x${ size }.png`;
		await page.screenshot( { path: join( HERE, name ), omitBackground: true, clip: { x: 0, y: 0, width: size, height: size } } );
		check( name, size, size );
	}

	const banner = readFileSync( join( HERE, 'source/banner.html' ), 'utf8' )
		.replace( '/* FONTS: build-assets.mjs injects the Inter and Manrope @font-face rules here. */', FONTS )
		.replaceAll( 'ICON_URI', iconUri );
	for ( const [ width, height, scale ] of [ [ 772, 250, 1 ], [ 1544, 500, 2 ] ] ) {
		await page.setViewport( { width: 772, height: 250, deviceScaleFactor: scale } );
		await page.setContent( banner, { waitUntil: 'load' } );
		await page.evaluate( () => document.fonts.ready );
		const name = `banner-${ width }x${ height }.png`;
		await page.screenshot( { path: join( HERE, name ), clip: { x: 0, y: 0, width: 772, height: 250 } } );
		check( name, width, height );
	}
} finally {
	await browser.close();
	if ( wroteMu ) {
		try {
			console.log( wp( 'eval-file', join( HERE, 'source/demo-data.php' ), 'clean' ).trim() );
		} catch ( e ) {
			console.error( 'Could not remove the demo negotiations:', e.message );
		}
	}
	for ( const s of sessions ) {
		wp( 'eval', `WP_Session_Tokens::get_instance( ${ s.uid } )->destroy( "${ s.token }" );` );
	}
	if ( wroteMu && existsSync( MU_FILE ) ) {
		unlinkSync( MU_FILE );
		if ( ! hadMuDir && ! readdirSync( MU_DIR ).length ) {
			rmdirSync( MU_DIR );
		}
	}
}
