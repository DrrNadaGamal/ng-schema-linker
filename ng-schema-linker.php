<?php
/**
 * Plugin Name: NG Schema & Internal Linker
 * Description: Outputs valid JSON-LD (Article, BreadcrumbList, HowTo, Organization) and adds internal links from keyword rules, safely, without touching headings, existing links or code. Steps aside when another SEO plugin outputs schema. Part of the NG WordPress Suite.
 * Version: 0.1.0
 * Author: Nada Gamal
 * License: GPL-2.0-or-later
 * Text Domain: ng-sl
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NGSL {
	const OPT = 'ngsl_settings';
	static function defaults() { return array( 'schema' => 1, 'force' => 0, 'article_type' => 'BlogPosting', 'org_name' => '', 'org_logo' => '', 'breadcrumbs' => 1, 'link' => 1, 'rules' => '', 'max_per_post' => 5, 'max_per_rule' => 1, 'titles' => 0 ); }
	static function s() { return wp_parse_args( get_option( self::OPT, array() ), self::defaults() ); }
	static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_schema' ), 30 );
		add_filter( 'the_content', array( __CLASS__, 'link_content' ), 30 );
		add_action( 'add_meta_boxes', function () { add_meta_box( 'ngsl', 'NG Schema', array( __CLASS__, 'box' ), 'post', 'side' ); } );
		add_action( 'save_post', array( __CLASS__, 'save_box' ) );
		add_action( 'admin_menu', function () { add_options_page( 'NG Schema & Linker', __( 'Schema & Linker', 'ng-sl' ), 'manage_options', 'ngsl', array( __CLASS__, 'page' ) ); } );
		add_action( 'admin_post_ngsl_save', array( __CLASS__, 'save' ) );
		add_filter( 'gettext_ng-sl', function ( $t ) { $m = self::T(); $l = substr( apply_filters( 'ngsl_locale', determine_locale() ), 0, 2 ); return isset( $m[ $l ][ $t ] ) ? $m[ $l ][ $t ] : $t; } );
	}
	static function T() { return array(
		'ar' => array( 'Schema & Linker' => 'السكيما والروابط الداخلية', 'Save settings' => 'حفظ الإعدادات', 'HowTo steps (one per line)' => 'خطوات الشرح (خطوة في كل سطر)' ),
		'fr' => array( 'Schema & Linker' => 'Schéma et maillage', 'Save settings' => 'Enregistrer', 'HowTo steps (one per line)' => 'Étapes HowTo (une par ligne)' ),
		'de' => array( 'Schema & Linker' => 'Schema und interne Links', 'Save settings' => 'Einstellungen speichern', 'HowTo steps (one per line)' => 'HowTo-Schritte (eine pro Zeile)' ),
	); }
	static function other_seo() { return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || apply_filters( 'ngsl_other_seo', false ); }

	/* ---- schema ---- */
	static function graph( $post ) {
		$s = self::s(); $g = array(); $url = get_permalink( $post ); $site = home_url( '/' );
		$orgname = $s['org_name'] ?: get_bloginfo( 'name' ); $org = array( '@type' => 'Organization', '@id' => $site . '#org', 'name' => $orgname, 'url' => $site );
		if ( $s['org_logo'] ) { $org['logo'] = array( '@type' => 'ImageObject', 'url' => esc_url_raw( $s['org_logo'] ) ); }
		$g[] = $org;
		if ( 'post' === $post->post_type ) {
			$a = array( '@type' => in_array( $s['article_type'], array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ? $s['article_type'] : 'BlogPosting', '@id' => $url . '#article', 'headline' => mb_substr( wp_strip_all_tags( get_the_title( $post ) ), 0, 110 ), 'datePublished' => get_the_date( 'c', $post ), 'dateModified' => get_the_modified_date( 'c', $post ), 'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $url ), 'publisher' => array( '@id' => $site . '#org' ), 'inLanguage' => str_replace( '_', '-', get_locale() ) );
			$au = get_userdata( $post->post_author ); if ( $au ) { $a['author'] = array( '@type' => 'Person', 'name' => $au->display_name, 'url' => get_author_posts_url( $au->ID ) ); }
			if ( has_post_thumbnail( $post ) ) { $a['image'] = array( get_the_post_thumbnail_url( $post, 'full' ) ); }
			$g[] = $a;
		}
		if ( $s['breadcrumbs'] ) { $b = self::crumbs( $post ); if ( count( $b ) > 1 ) { $g[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $b ); } }
		$steps = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) get_post_meta( $post->ID, '_ngsl_howto', true ) ) ) ) );
		if ( count( $steps ) >= 2 ) {
			$h = array( '@type' => 'HowTo', 'name' => wp_strip_all_tags( get_the_title( $post ) ), 'step' => array() );
			foreach ( $steps as $i => $st ) { $h['step'][] = array( '@type' => 'HowToStep', 'position' => $i + 1, 'text' => $st ); }
			$g[] = $h;
		}
		return apply_filters( 'ngsl_graph', array( '@context' => 'https://schema.org', '@graph' => $g ), $post );
	}
	static function crumbs( $post ) {
		$items = array( array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );
		if ( 'page' === $post->post_type ) { foreach ( array_reverse( get_post_ancestors( $post ) ) as $aid ) { $items[] = array( 'name' => get_the_title( $aid ), 'url' => get_permalink( $aid ) ); } }
		else { $c = get_the_category( $post->ID ); if ( $c ) { $items[] = array( 'name' => $c[0]->name, 'url' => get_category_link( $c[0] ) ); } }
		$items[] = array( 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) );
		$out = array(); foreach ( $items as $i => $it ) { $out[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $it['name'] ), 'item' => $it['url'] ); }
		return $out;
	}
	static function print_schema() {
		$s = self::s(); if ( ! $s['schema'] || ! is_singular( array( 'post', 'page' ) ) ) { return; }
		if ( self::other_seo() && ! $s['force'] ) { return; }
		$json = wp_json_encode( self::graph( get_queried_object() ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		echo "\n<script type=\"application/ld+json\" id=\"ngsl-schema\">" . $json . "</script>\n";   // JSON_HEX_TAG makes </script> injection impossible
	}

	/* ---- internal links ---- */
	static function rules() {
		$out = array();
		foreach ( preg_split( '/\R/', (string) self::s()['rules'] ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line, 2 ) ); if ( 2 !== count( $p ) || '' === $p[0] ) { continue; }
			$u = esc_url_raw( $p[1], array( 'http', 'https' ) ); if ( $u ) { $out[] = array( $p[0], $u ); }
		}
		return $out;
	}
	static function link_content( $html ) {
		$s = self::s(); if ( ! $s['link'] || is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() || '' === trim( $html ) ) { return $html; }
		$rules = self::rules();
		if ( $s['titles'] ) { foreach ( get_posts( array( 'post_type' => 'post', 'posts_per_page' => 50, 'post__not_in' => array( get_the_ID() ), 'fields' => 'ids' ) ) as $pid ) { $t = get_the_title( $pid ); if ( mb_strlen( $t ) >= 12 ) { $rules[] = array( $t, get_permalink( $pid ) ); } } }
		if ( ! $rules ) { return $html; }
		return self::apply( $html, $rules, (int) $s['max_per_post'], (int) $s['max_per_rule'], get_permalink() );
	}
	static function apply( $html, $rules, $max_total, $max_rule, $self_url = '' ) {
		if ( ! class_exists( 'DOMDocument' ) ) { return $html; }
		$doc = new DOMDocument(); $prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="ngsl-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors(); libxml_use_internal_errors( $prev );
		$xp = new DOMXPath( $doc ); $skip = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'script', 'style', 'button', 'textarea', 'select', 'figcaption', 'iframe' );
		$total = 0; $counts = array();
		usort( $rules, function ( $a, $b ) { return mb_strlen( $b[0] ) - mb_strlen( $a[0 ] ); } );
		foreach ( iterator_to_array( $xp->query( '//text()' ) ) as $node ) {
			$bad = false; for ( $p = $node->parentNode; $p; $p = $p->parentNode ) { if ( $p instanceof DOMElement && in_array( strtolower( $p->nodeName ), $skip, true ) ) { $bad = true; break; } }
			if ( $bad ) { continue; }
			while ( $node && $total < $max_total && '' !== trim( $node->nodeValue ) ) {
				$text = $node->nodeValue; $best = null;
				foreach ( $rules as $i => $r ) {      // earliest match among rules that still have budget; longer keywords win ties
					if ( ( $counts[ $i ] ?? 0 ) >= $max_rule || untrailingslashit( $r[1] ) === untrailingslashit( $self_url ) ) { continue; }
					$re = '/(?<![\p{L}\p{N}_])(' . preg_quote( $r[0], '/' ) . ')(?![\p{L}\p{N}_])/iu';
					if ( preg_match( $re, $text, $m, PREG_OFFSET_CAPTURE ) && ( null === $best || $m[1][1] < $best[0] ) ) { $best = array( $m[1][1], $m[1][0], $i ); }
				}
				if ( ! $best ) { break; }
				list( $off, $hit, $i ) = $best; $before = substr( $text, 0, $off ); $after = substr( $text, $off + strlen( $hit ) );
				$a = $doc->createElement( 'a' ); $a->setAttribute( 'href', $rules[ $i ][1] ); $a->appendChild( $doc->createTextNode( $hit ) );
				$frag = $doc->createDocumentFragment();
				if ( '' !== $before ) { $frag->appendChild( $doc->createTextNode( $before ) ); }
				$frag->appendChild( $a ); $tail = null;
				if ( '' !== $after ) { $tail = $doc->createTextNode( $after ); $frag->appendChild( $tail ); }
				$node->parentNode->replaceChild( $frag, $node );
				$counts[ $i ] = ( $counts[ $i ] ?? 0 ) + 1; $total++; $node = $tail;
			}
		}
		$root = $doc->getElementById( 'ngsl-root' ); if ( ! $root ) { return $html; }
		$out = ''; foreach ( $root->childNodes as $c ) { $out .= $doc->saveHTML( $c ); }
		return $out;
	}

	/* ---- admin ---- */
	static function box( $post ) { wp_nonce_field( 'ngsl_box', 'ngsl_n' ); printf( '<p>%s</p><textarea name="ngsl_howto" rows="5" style="width:100%%">%s</textarea>', esc_html__( 'HowTo steps (one per line)', 'ng-sl' ), esc_textarea( (string) get_post_meta( $post->ID, '_ngsl_howto', true ) ) ); }
	static function save_box( $id ) {
		if ( ! isset( $_POST['ngsl_n'] ) || ! wp_verify_nonce( $_POST['ngsl_n'], 'ngsl_box' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
		$v = sanitize_textarea_field( wp_unslash( $_POST['ngsl_howto'] ?? '' ) ); $v ? update_post_meta( $id, '_ngsl_howto', $v ) : delete_post_meta( $id, '_ngsl_howto' );
	}
	static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); } check_admin_referer( 'ngsl' ); $in = wp_unslash( $_POST );
		$n = array( 'schema' => empty( $in['schema'] ) ? 0 : 1, 'force' => empty( $in['force'] ) ? 0 : 1, 'article_type' => in_array( $in['article_type'] ?? '', array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ? $in['article_type'] : 'BlogPosting', 'org_name' => sanitize_text_field( $in['org_name'] ?? '' ), 'org_logo' => esc_url_raw( $in['org_logo'] ?? '' ), 'breadcrumbs' => empty( $in['breadcrumbs'] ) ? 0 : 1, 'link' => empty( $in['link'] ) ? 0 : 1, 'rules' => sanitize_textarea_field( $in['rules'] ?? '' ), 'max_per_post' => max( 0, min( 50, (int) ( $in['max_per_post'] ?? 5 ) ) ), 'max_per_rule' => max( 1, min( 5, (int) ( $in['max_per_rule'] ?? 1 ) ) ), 'titles' => empty( $in['titles'] ) ? 0 : 1 );
		update_option( self::OPT, $n ); wp_safe_redirect( admin_url( 'options-general.php?page=ngsl&msg=' . rawurlencode( 'Saved' ) ) ); exit;
	}
	static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = self::s(); $rtl = is_rtl() ? ' dir="rtl"' : '';
		echo '<div class="wrap"' . $rtl . '><h1>NG ' . esc_html__( 'Schema & Linker', 'ng-sl' ) . '</h1>';
		if ( isset( $_GET['msg'] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( wp_unslash( $_GET['msg'] ) ) . '</p></div>'; }
		if ( self::other_seo() ) { echo '<div class="notice notice-warning"><p>Another SEO plugin is active, so schema output is off unless you tick "force".</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'ngsl' ); echo '<input type="hidden" name="action" value="ngsl_save"><table class="form-table">';
		foreach ( array( 'schema' => 'Output JSON-LD schema', 'force' => 'Force schema even if another SEO plugin is active', 'breadcrumbs' => 'Breadcrumb schema', 'link' => 'Internal links', 'titles' => 'Also link to titles of other posts' ) as $k => $l ) { printf( '<tr><th>%s</th><td><input type="checkbox" name="%s" value="1" %s></td></tr>', esc_html( $l ), esc_attr( $k ), checked( $s[ $k ], 1, false ) ); }
		printf( '<tr><th>Article type</th><td><select name="article_type">%s</select></td></tr>', implode( '', array_map( function ( $t ) use ( $s ) { return sprintf( '<option %s>%s</option>', selected( $s['article_type'], $t, false ), esc_html( $t ) ); }, array( 'Article', 'BlogPosting', 'NewsArticle' ) ) ) );
		printf( '<tr><th>Organization name</th><td><input name="org_name" class="regular-text" value="%s"></td></tr><tr><th>Logo URL</th><td><input name="org_logo" class="regular-text" value="%s"></td></tr>', esc_attr( $s['org_name'] ), esc_attr( $s['org_logo'] ) );
		printf( '<tr><th>Link rules<br><small>keyword | https://url (one per line)</small></th><td><textarea name="rules" rows="6" class="large-text">%s</textarea></td></tr>', esc_textarea( $s['rules'] ) );
		printf( '<tr><th>Max links per post</th><td><input type="number" name="max_per_post" value="%d"></td></tr><tr><th>Max per keyword</th><td><input type="number" name="max_per_rule" value="%d"></td></tr>', (int) $s['max_per_post'], (int) $s['max_per_rule'] );
		echo '</table>'; submit_button( __( 'Save settings', 'ng-sl' ) ); echo '</form></div>';
	}
}
NGSL::init();
