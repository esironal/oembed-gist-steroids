<?php
/*
  Plugin Name: oEmbed Gist on StErOids
  Plugin URI: http://krtnb.ch/plugins/oembed-gist-on-steroids/
  Version: 1.4.0
  Author: Daan Kortenbach
  Author URI: http://krtnb.ch/
  Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=BKUCJXJJ8XJVY
  Description: Embed source from gist.github.com and add the raw source in a noscript tag for SEO.
  Text Domain: oembed-gist
  Domain Path /languages/
*/

/*
Copyright (c) 2010 Takayuki Miyauchi (THETA NETWORKS Co,.Ltd).
Copyright (c) 2012 Daan Kortenbach.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

Forked October 3, 2012 by Daan Kortenbach (krtnb.ch) to add NOSCRIPT > CODE for SEO
Modified August 18, 2011 by Alex King (alexking.org) to add NOSCRIPT and i18n support
Original by Takayuki Miyauchi (firegoby.jp)

*/


/**
 * Class to embed Gists.
 *
 * Embed source from gist.github.com with the Github Gists JS script and add the raw Gist in a noscript tag for SEO
 *
 * @category Plugin
 * @package oEmbed Gist on StErOids
 *
 * @since 1.4.0
 */
class OGS_Gist {

	/**
	 * $noscript declaration.
	 * @var string
	 */
	private $noscript;

	/**
	 * Declare $html and fill with Gist script and noscript for sprintf.
	 * @var string
	 */
	private $html = '<script src="https://gist.github.com/%s.js%s"></script><noscript>%s</noscript>';

	/**
	 * Constructor. Load plugins_loaded.
	 */
	function __construct(){
		add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
	}

	/**
	 * Load text domain. Register embed handler. Add shortcode.
	 * @return void
	 */
	public function plugins_loaded() {

		// Load plugin text domain
		load_plugin_textdomain(
			'oembed-gist',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		// Register embed handler
		wp_embed_register_handler(
			'gist',
			'#https://gist.github.com/([a-zA-Z0-9]+)(\#file_(.+))?$#i',
			array( &$this, 'handler' )
		);

		/* Add shortcode */
		add_shortcode( 'gist', array( &$this, 'shortcode' ) );
	}

	/**
	 * Handler returns shortcode html
	 * @param  array  $m
	 * @param  string $attr
	 * @param  string $url
	 * @param  string $rattr
	 * @return string
	 */
	public function handler( $m, $attr, $url, $rattr ){

		if( !isset( $m[2] ) || !isset( $m[3] ) || !$m[3] ){
			$m[3] = null;
		}
		return '[gist id="'.$m[1].'" file="'.$m[3].'"]';
	}

	/**
	 * Returns the shortcode output
	 * @param  array $p
	 * @return string
	 */
	public function shortcode( $p ){

		if( preg_match( "/^[a-zA-Z0-9]+$/", $p['id'] ) ){

			// Get any existing copy of our transient data
			if ( false === ( $gist_cache = get_transient( 'gist-' . $p['id'] ) ) ) {
				
				// It wasn't there, so regenerate the data
				if( $p['file'] ){

					$gist_cache = wp_remote_fopen( 'https://raw.github.com/gist/' . $p['id'] . '?file=' . $p['file'] );
				}
				else{
					$gist_cache = wp_remote_fopen( 'https://raw.github.com/gist/' . $p['id'] );
				}

				// Save the transient for 1 day
				set_transient( 'gist-' . $p['id'], $gist_cache, 60*60*24 );
			}

			// Fill the $noscript string
			$noscript = sprintf(
				__( '<div class="embed-github-gist-source"><code><pre>%s</pre><code></div><p>View the code on <a href="https://gist.github.com/%s">Gist</a>.</p>', 'oembed-gist' ),
				$gist_cache,
				$p['id']
			);

			
			add_filter( 'comment_text', array( &$this, 'oembed_gist_filter' ), 1 );

			// return the output
			if( $p['file'] ){

				return sprintf( $this->html, $p['id'], '?file=' . $p['file'], $noscript );
			}
			else{
				return sprintf( $this->html, $p['id'], '', $noscript );
			}
		}
	}

	/**
	 * [oembed_gist_filter description]
	 * @param  string $comment_text [description]
	 * @return string               [description]
	 */
	public function oembed_gist_filter( $comment_text ) {
		global $wp_embed;

		add_filter( 'embed_oembed_discover', '__return_false', 999 );
		$comment_text = do_shortcode( $wp_embed->autoembed( $comment_text ) );
		remove_filter( 'embed_oembed_discover', '__return_false', 999 );

		return $comment_text;
	}

}

new OGS_Gist();

