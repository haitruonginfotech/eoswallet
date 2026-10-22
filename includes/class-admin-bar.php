<?php
/**
 * Admin bar "MCP" menu: status at a glance + on/off switch (front end and admin).
 *
 * @package WPMCPTools
 */

defined( 'ABSPATH' ) || exit;

class WPMCP_Admin_Bar {

	const NODE = 'wpmcp';

	public static function init(): void {
		add_action( 'admin_bar_menu', array( __CLASS__, 'menu' ), 100 );
		add_action( 'admin_head', array( __CLASS__, 'styles' ) );
		add_action( 'wp_head', array( __CLASS__, 'styles' ) );
		add_action( 'admin_footer', array( __CLASS__, 'script' ) );
		add_action( 'wp_footer', array( __CLASS__, 'script' ) );
		add_action( 'admin_post_wpmcp_toggle', array( __CLASS__, 'handle_toggle' ) );
	}

	private static function visible(): bool {
		return is_admin_bar_showing() && current_user_can( 'manage_options' );
	}

	public static function menu( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$on        = WPMCP_Settings::mcp_enabled();
		$abilities = function_exists( 'wp_register_ability' );
		$adapter   = class_exists( '\WP\MCP\Core\McpAdapter' );
		$tools     = count( WPMCP_Registry::active_tool_slugs() );
		$ok        = $on && $abilities && $adapter;
		$state     = $ok ? 'on' : ( $on ? 'warn' : 'off' );

		$bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => WPMCP_Admin::icon( 'wpmcp-ab-ico' ) . '<span class="ab-label">MCP</span><span class="wpmcp-ab-dot wpmcp-ab-' . $state . '" aria-hidden="true"></span>',
				'href'  => WPMCP_Admin::url( 'connection' ),
				'meta'  => array(
					'title' => $ok ? 'MCP is on' : ( $on ? 'MCP is on but a dependency is missing' : 'MCP is off' ),
				),
			)
		);

		$items = array(
			'abilities' => 'Abilities API: ' . ( $abilities ? 'Available' : 'Unavailable (WP 6.9+)' ),
			'adapter'   => 'MCP Adapter: ' . ( $adapter ? 'Active' : 'Not active' ),
			'exposure'  => 'MCP exposure: ' . ( $on ? 'Enabled' : 'Disabled' ),
			'tools'     => 'Active tools: ' . $tools,
		);
		if ( $on ) {
			$items['oauth'] = 'OAuth: ' . ( WPMCP_OAuth_Server::enabled() ? 'Enabled' : 'Disabled' );
		}
		foreach ( $items as $id => $title ) {
			$bar->add_node(
				array(
					'parent' => self::NODE,
					'id'     => self::NODE . '-' . $id,
					'title'  => esc_html( $title ),
					'meta'   => array( 'class' => 'wpmcp-ab-info' ),
				)
			);
		}

		$bar->add_node(
			array(
				'parent' => self::NODE,
				'id'     => self::NODE . '-toggle',
				'title'  => $on ? 'Turn MCP off' : 'Turn MCP on',
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=wpmcp_toggle&to=' . ( $on ? 'off' : 'on' ) ), 'wpmcp_toggle' ),
				'meta'   => array( 'class' => 'wpmcp-ab-toggle' . ( $on ? ' wpmcp-ab-confirm' : '' ) ),
			)
		);
		$bar->add_node(
			array(
				'parent' => self::NODE,
				'id'     => self::NODE . '-settings',
				'title'  => 'Connection settings →',
				'href'   => WPMCP_Admin::url( 'connection' ),
			)
		);
	}

	public static function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'wpmcp_toggle' );
		$to = isset( $_GET['to'] ) && 'on' === $_GET['to'];
		WPMCP_Settings::set_enabled( $to );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : WPMCP_Admin::url( 'connection' ) );
		exit;
	}

	public static function styles(): void {
		if ( ! self::visible() ) {
			return;
		}
		?>
		<style id="wpmcp-admin-bar">
		#wpadminbar #wp-admin-bar-wpmcp > .ab-item{display:flex;align-items:center;gap:5px}
		#wpadminbar .wpmcp-ab-ico{width:18px;height:18px;flex:none;fill:currentColor;color:#a7aaad;transition:color .1s}
		#wpadminbar #wp-admin-bar-wpmcp:hover .wpmcp-ab-ico,#wpadminbar #wp-admin-bar-wpmcp.hover .wpmcp-ab-ico{color:#72aee6}
		#wpadminbar .wpmcp-ab-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin:0 0 0 1px}
		#wpadminbar .wpmcp-ab-on{background:#00ba37;box-shadow:0 0 0 2px rgba(0,186,55,.25)}
		#wpadminbar .wpmcp-ab-warn{background:#dba617}
		#wpadminbar .wpmcp-ab-off{background:#8c8f94}
		#wpadminbar .wpmcp-ab-info > .ab-item{cursor:default}
		#wpadminbar .wpmcp-ab-toggle > .ab-item{color:#72aee6!important}
		@media screen and (max-width:782px){#wpadminbar #wp-admin-bar-wpmcp{display:block}#wpadminbar #wp-admin-bar-wpmcp > .ab-item{padding:0 10px;font-size:14px}#wpadminbar .wpmcp-ab-ico{width:26px;height:26px}#wpadminbar #wp-admin-bar-wpmcp .ab-label{display:none}}
		</style>
		<?php
	}

	/** Confirm before turning MCP off. */
	public static function script(): void {
		if ( ! self::visible() ) {
			return;
		}
		?>
		<script>
		(function(){
			var a=document.querySelector('#wp-admin-bar-wpmcp-toggle.wpmcp-ab-confirm > a');
			if(a){ a.addEventListener('click',function(e){ if(!window.confirm('Turn MCP off? Connected AI clients will lose access until you turn it back on.')){ e.preventDefault(); } }); }
		})();
		</script>
		<?php
	}
}
