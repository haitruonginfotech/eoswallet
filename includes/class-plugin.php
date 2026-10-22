<?php
/**
 * Bootstrap: wires modules, Abilities API hooks and the MCP server.
 *
 * @package WPMCPTools
 */

defined( 'ABSPATH' ) || exit;

class WPMCP_Plugin {

	/** @var string[] Module classes. Each has a static register() that declares its group + tools. */
	const MODULES = array(
		'WPMCP_Site',
		'WPMCP_Posts',
		'WPMCP_Taxonomies',
		'WPMCP_Fields',
		'WPMCP_Media',
		'WPMCP_Uploads',
		'WPMCP_Menus',
		'WPMCP_Theme',
	);

	public static function init(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_no_abilities' ) );
			return;
		}

		// OAuth tables are created on activation; this covers copied-in updates.
		WPMCP_OAuth_Store::maybe_install();
		WPMCP_OAuth_Server::init();

		foreach ( self::MODULES as $module ) {
			$module::register();
		}

		/**
		 * Let other plugins / the theme add their own tools:
		 * add_action( 'wpmcp_register_tools', fn() => WPMCP_Registry::add( 'content', 'my-tool', [...] ) );
		 */
		// Fired after the theme is loaded so functions.php can hook it too.
		add_action(
			'after_setup_theme',
			static function () {
				do_action( 'wpmcp_register_tools' );
			},
			99
		);

		// Master switch (MCP Tools → Connection, or the admin-bar "MCP" menu).
		if ( WPMCP_Settings::mcp_enabled() ) {
			add_action( 'wp_abilities_api_categories_init', array( 'WPMCP_Registry', 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( 'WPMCP_Registry', 'register_abilities' ) );
			add_action( 'mcp_adapter_init', array( __CLASS__, 'create_server' ) );
		}

		WPMCP_Admin_Bar::init();

		if ( is_admin() ) {
			WPMCP_Admin::init();
			add_action( 'admin_notices', array( __CLASS__, 'notice_no_adapter' ) );
		}
	}

	/**
	 * Hook: mcp_adapter_init.
	 *
	 * @param \WP\MCP\Core\McpAdapter $adapter Adapter instance.
	 */
	public static function create_server( $adapter ): void {
		// Make sure abilities are registered (wp_get_abilities() triggers the init hooks lazily).
		if ( function_exists( 'wp_get_abilities' ) ) {
			wp_get_abilities();
		}

		$tools = WPMCP_Registry::registered_abilities();

		$result = $adapter->create_server(
			WPMCP_SERVER_ID,
			WPMCP_ROUTE_NAMESPACE,
			WPMCP_ROUTE,
			'WP MCP Tools — ' . get_bloginfo( 'name' ),
			'Content, SCF/ACF, media, menus, options and theme-file tools for ' . home_url(),
			WPMCP_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$tools,
			array(),
			array(),
			static function () {
				if ( ! is_user_logged_in() ) {
					// Plain false (not WP_Error) keeps the adapter from logging every OAuth discovery probe;
					// WPMCP_OAuth_Server turns the response into 401 + WWW-Authenticate.
					return false;
				}
				$cap = (string) WPMCP_Settings::get( 'endpoint_capability' );
				return current_user_can( $cap ?: 'edit_posts' );
			}
		);

		if ( is_wp_error( $result ) ) {
			error_log( '[wp-mcp-tools] Failed to create MCP server: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	public static function notice_no_abilities(): void {
		echo '<div class="notice notice-error"><p><strong>WP MCP Tools</strong> requires WordPress 6.9+ (Abilities API).</p></div>';
	}

	public static function notice_no_adapter(): void {
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>WP MCP Tools</strong>: activate the <code>MCP Adapter</code> plugin to expose the MCP server.</p></div>';
	}
}
