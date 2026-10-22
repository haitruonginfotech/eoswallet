<?php
/**
 * Classic navigation menus.
 *
 * @package WPMCPTools
 */

defined( 'ABSPATH' ) || exit;

class WPMCP_Menus {

	public static function register(): void {
		$h = 'WPMCP_Helpers';

		WPMCP_Registry::add_group(
			'menus',
			array(
				'label'       => 'Menus',
				'description' => 'Classic nav menus: list, items, add/update/delete items, assign locations',
			)
		);

		$item_fields = array(
			'title'     => $h::s_str( 'Label shown in the menu.' ),
			'type'      => $h::s_str( 'custom | post_type | taxonomy.', array( 'enum' => array( 'custom', 'post_type', 'taxonomy' ) ) ),
			'object'    => $h::s_str( 'For post_type/taxonomy: the post type or taxonomy (page, post, category...).' ),
			'object_id' => $h::s_int( 'For post_type/taxonomy: the post or term ID.' ),
			'url'       => $h::s_str( 'For custom links.' ),
			'parent'    => $h::s_int( 'Parent menu item ID (0 = top level).' ),
			'position'  => $h::s_int( 'Menu order (1-based).' ),
			'target'    => $h::s_str( '"_blank" or "".' ),
			'classes'   => $h::s_str( 'CSS classes, space separated.' ),
		);

		WPMCP_Registry::add(
			'menus',
			'list-menus',
			array(
				'label'       => 'List menus',
				'description' => 'List nav menus, registered theme locations and which menu is assigned to each.',
				'capability'  => 'edit_theme_options',
				'readonly'    => true,
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'list_menus' ),
			)
		);

		WPMCP_Registry::add(
			'menus',
			'get-menu',
			array(
				'label'       => 'Get menu items',
				'description' => 'Items of a menu (id, title, url, type, object, object_id, parent, position).',
				'properties'  => array( 'menu' => array( 'type' => array( 'integer', 'string' ), 'description' => 'Menu ID, slug or name.' ) ),
				'required'    => array( 'menu' ),
				'capability'  => 'edit_theme_options',
				'readonly'    => true,
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'get_menu' ),
			)
		);

		WPMCP_Registry::add(
			'menus',
			'create-menu',
			array(
				'label'       => 'Create menu',
				'description' => 'Create a nav menu, optionally assigning it to a theme location.',
				'properties'  => array(
					'name'     => $h::s_str( 'Menu name.' ),
					'location' => $h::s_str( 'Theme location slug to assign.' ),
				),
				'required'    => array( 'name' ),
				'capability'  => 'edit_theme_options',
				'callback'    => array( __CLASS__, 'create_menu' ),
			)
		);

		WPMCP_Registry::add(
			'menus',
			'add-menu-item',
			array(
				'label'       => 'Add menu item',
				'description' => 'Add an item to a menu. Page link: {type:"post_type", object:"page", object_id:12}. Custom: {type:"custom", url:"https://...", title:"..."}.',
				'properties'  => array_merge( array( 'menu' => array( 'type' => array( 'integer', 'string' ), 'description' => 'Menu ID, slug or name.' ) ), $item_fields ),
				'required'    => array( 'menu' ),
				'capability'  => 'edit_theme_options',
				'callback'    => array( __CLASS__, 'add_item' ),
			)
		);

		WPMCP_Registry::add(
			'menus',
			'update-menu-item',
			array(
				'label'       => 'Update menu item',
				'description' => 'Update an existing menu item; unspecified fields keep their current values.',
				'properties'  => array_merge( array( 'id' => $h::s_int( 'Menu item ID.' ) ), $item_fields ),
				'required'    => array( 'id' ),
				'capability'  => 'edit_theme_options',
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'update_item' ),
			)
		);

		WPMCP_Registry::add(
			'menus',
			'delete-menu-item',
			array(
				'label'       => 'Delete menu item',
				'description' => 'Delete a menu item.',
				'properties'  => array( 'id' => $h::s_int( 'Menu item ID.' ) ),
				'required'    => array( 'id' ),
				'capability'  => 'edit_theme_options',
				'destructive' => true,
				'callback'    => array( __CLASS__, 'delete_item' ),
			)
		);

		WPMCP_Registry::add(
			'menus',
			'assign-menu-location',
			array(
				'label'       => 'Assign menu location',
				'description' => 'Assign a menu to a theme location (menu 0 = unassign).',
				'properties'  => array(
					'location' => $h::s_str( 'Theme location slug.' ),
					'menu'     => array( 'type' => array( 'integer', 'string' ), 'description' => 'Menu ID, slug or name. 0 to unassign.' ),
				),
				'required'    => array( 'location', 'menu' ),
				'capability'  => 'edit_theme_options',
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'assign_location' ),
			)
		);
	}

	/**
	 * @param mixed $menu ID, slug or name.
	 * @return WP_Term|WP_Error
	 */
	private static function menu( $menu ) {
		$obj = wp_get_nav_menu_object( $menu );
		if ( ! $obj ) {
			return WPMCP_Helpers::error( 'not_found', 'Menu not found: ' . $menu, 404 );
		}
		return $obj;
	}

	private static function format_item( $item ): array {
		return array(
			'id'        => (int) $item->ID,
			'title'     => $item->title,
			'url'       => $item->url,
			'type'      => $item->type,
			'object'    => $item->object,
			'object_id' => (int) $item->object_id,
			'parent'    => (int) $item->menu_item_parent,
			'position'  => (int) $item->menu_order,
			'target'    => $item->target,
			'classes'   => trim( implode( ' ', (array) $item->classes ) ),
		);
	}

	public static function list_menus(): array {
		$assigned = get_nav_menu_locations();
		$menus    = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$menus[] = array(
				'id'        => $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'count'     => $menu->count,
				'locations' => array_keys( array_filter( $assigned, static fn( $id ) => (int) $id === (int) $menu->term_id ) ),
			);
		}
		$locations = array();
		foreach ( get_registered_nav_menus() as $slug => $label ) {
			$locations[] = array(
				'location' => $slug,
				'label'    => $label,
				'menu_id'  => (int) ( $assigned[ $slug ] ?? 0 ),
			);
		}
		return array(
			'menus'     => $menus,
			'locations' => $locations,
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public static function get_menu( array $input ) {
		$menu = self::menu( $input['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$items = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) );
		return array(
			'id'    => $menu->term_id,
			'name'  => $menu->name,
			'items' => array_map( array( __CLASS__, 'format_item' ), (array) $items ),
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public static function create_menu( array $input ) {
		$id = wp_create_nav_menu( (string) $input['name'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! empty( $input['location'] ) ) {
			$res = self::assign_location( array( 'location' => $input['location'], 'menu' => $id ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		return array(
			'id'   => $id,
			'name' => (string) $input['name'],
		);
	}

	/**
	 * Build wp_update_nav_menu_item() args from input, falling back to an existing item.
	 *
	 * @param object|null $current Existing item.
	 */
	private static function item_args( array $input, $current = null ): array {
		$pick = static function ( $key, $fallback ) use ( $input ) {
			return array_key_exists( $key, $input ) && null !== $input[ $key ] ? $input[ $key ] : $fallback;
		};

		$type = $pick( 'type', $current ? $current->type : ( ! empty( $input['object_id'] ) ? 'post_type' : 'custom' ) );

		return array(
			'menu-item-title'     => (string) $pick( 'title', $current ? $current->title : '' ),
			'menu-item-type'      => $type,
			'menu-item-object'    => (string) $pick( 'object', $current ? $current->object : ( 'custom' === $type ? 'custom' : 'page' ) ),
			'menu-item-object-id' => (int) $pick( 'object_id', $current ? $current->object_id : 0 ),
			'menu-item-url'       => (string) $pick( 'url', $current ? $current->url : '' ),
			'menu-item-parent-id' => (int) $pick( 'parent', $current ? $current->menu_item_parent : 0 ),
			'menu-item-position'  => (int) $pick( 'position', $current ? $current->menu_order : 0 ),
			'menu-item-target'    => (string) $pick( 'target', $current ? $current->target : '' ),
			'menu-item-classes'   => (string) $pick( 'classes', $current ? implode( ' ', (array) $current->classes ) : '' ),
			'menu-item-attr-title' => $current ? $current->attr_title : '',
			'menu-item-description' => $current ? $current->description : '',
			'menu-item-xfn'       => $current ? $current->xfn : '',
			'menu-item-status'    => 'publish',
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public static function add_item( array $input ) {
		$menu = self::menu( $input['menu'] );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$id = wp_update_nav_menu_item( $menu->term_id, 0, self::item_args( $input ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return self::format_item( wp_setup_nav_menu_item( get_post( $id ) ) );
	}

	/**
	 * @return array|WP_Error
	 */
	public static function update_item( array $input ) {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
			return WPMCP_Helpers::error( 'not_found', 'Menu item not found.', 404 );
		}
		$current = wp_setup_nav_menu_item( $post );
		$menus   = wp_get_object_terms( $post->ID, 'nav_menu', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $menus ) || ! $menus ) {
			return WPMCP_Helpers::error( 'orphan', 'Menu item is not attached to a menu.' );
		}
		$id = wp_update_nav_menu_item( (int) $menus[0], $post->ID, self::item_args( $input, $current ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return self::format_item( wp_setup_nav_menu_item( get_post( $id ) ) );
	}

	/**
	 * @return array|WP_Error
	 */
	public static function delete_item( array $input ) {
		$post = get_post( absint( $input['id'] ) );
		if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
			return WPMCP_Helpers::error( 'not_found', 'Menu item not found.', 404 );
		}
		wp_delete_post( $post->ID, true );
		return array( 'deleted' => $post->ID );
	}

	/**
	 * @return array|WP_Error
	 */
	public static function assign_location( array $input ) {
		$location   = (string) $input['location'];
		$registered = get_registered_nav_menus();
		if ( ! isset( $registered[ $location ] ) ) {
			return WPMCP_Helpers::error( 'invalid_location', 'Unknown location. Registered: ' . implode( ', ', array_keys( $registered ) ) );
		}
		$menu_id = 0;
		if ( ! empty( $input['menu'] ) ) {
			$menu = self::menu( $input['menu'] );
			if ( is_wp_error( $menu ) ) {
				return $menu;
			}
			$menu_id = (int) $menu->term_id;
		}
		$locations              = get_nav_menu_locations();
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return array(
			'location' => $location,
			'menu_id'  => $menu_id,
		);
	}
}
