<?php
/**
 * Media library.
 *
 * @package WPMCPTools
 */

defined( 'ABSPATH' ) || exit;

class WPMCP_Media {

	public static function register(): void {
		$h = 'WPMCP_Helpers';

		WPMCP_Registry::add_group(
			'media',
			array(
				'label'       => 'Media',
				'description' => 'Media library: list, upload (URL, base64, chunked base64 or a one-time upload link), update alt/title/caption, delete',
			)
		);

		WPMCP_Registry::add(
			'media',
			'list-media',
			array(
				'label'       => 'List media',
				'description' => 'List/search media library items (id, url, alt, mime type, size).',
				'properties'  => array_merge(
					array(
						'search'    => $h::s_str( 'Search in title/filename.' ),
						'mime_type' => $h::s_str( 'Filter by mime type, e.g. image, image/png, application/pdf.' ),
						'parent'    => $h::s_int( 'Only items attached to this post ID.' ),
					),
					$h::paging()
				),
				'capability'  => 'upload_files',
				'readonly'    => true,
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'list_media' ),
			)
		);

		WPMCP_Registry::add(
			'media',
			'get-media',
			array(
				'label'       => 'Get media',
				'description' => 'Get one media item by ID, including available image sizes.',
				'properties'  => array( 'id' => $h::s_int( 'Attachment ID.' ) ),
				'required'    => array( 'id' ),
				'capability'  => 'upload_files',
				'readonly'    => true,
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'get_media' ),
			)
		);

		WPMCP_Registry::add(
			'media',
			'upload-media',
			array(
				'label'       => 'Upload media',
				'description' => 'Add a file to the media library from a public URL (url) or base64 data (data + filename). Returns the attachment ID to use in featured_media or image fields. For local files larger than ~200 KB use create-upload-session instead.',
				'properties'  => array(
					'url'         => $h::s_str( 'Remote file URL to download.' ),
					'data'        => $h::s_str( 'Base64 file contents (alternative to url). Data-URI prefix allowed.' ),
					'filename'    => $h::s_str( 'File name incl. extension (required with data; optional with url).' ),
					'title'       => $h::s_str( 'Attachment title.' ),
					'alt'         => $h::s_str( 'Alt text.' ),
					'caption'     => $h::s_str( 'Caption.' ),
					'attach_to'   => $h::s_int( 'Parent post ID.' ),
				),
				'capability'  => 'upload_files',
				'callback'    => array( __CLASS__, 'upload_media' ),
			)
		);

		WPMCP_Registry::add(
			'media',
			'update-media',
			array(
				'label'       => 'Update media',
				'description' => 'Update title, alt text, caption or description of a media item.',
				'properties'  => array(
					'id'          => $h::s_int( 'Attachment ID.' ),
					'title'       => $h::s_str( 'Title.' ),
					'alt'         => $h::s_str( 'Alt text.' ),
					'caption'     => $h::s_str( 'Caption.' ),
					'description' => $h::s_str( 'Description.' ),
				),
				'required'    => array( 'id' ),
				'capability'  => 'upload_files',
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'update_media' ),
			)
		);

		WPMCP_Registry::add(
			'media',
			'delete-media',
			array(
				'label'       => 'Delete media',
				'description' => 'Permanently delete a media item and its files.',
				'properties'  => array( 'id' => $h::s_int( 'Attachment ID.' ) ),
				'required'    => array( 'id' ),
				'capability'  => 'delete_posts',
				'destructive' => true,
				'callback'    => array( __CLASS__, 'delete_media' ),
			)
		);
	}

	/**
	 * @return WP_Post|WP_Error
	 */
	private static function attachment( $id, string $cap = 'edit_post' ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return WPMCP_Helpers::error( 'not_found', 'Attachment not found: ' . $id, 404 );
		}
		if ( ! current_user_can( $cap, $post->ID ) ) {
			return WPMCP_Helpers::error( 'forbidden', 'Not allowed.', 403 );
		}
		return $post;
	}

	public static function list_media( array $input ): array {
		$h    = 'WPMCP_Helpers';
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $h::per_page( $input ),
			'paged'          => $h::page( $input ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = (string) $input['search'];
		}
		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = (string) $input['mime_type'];
		}
		if ( isset( $input['parent'] ) ) {
			$args['post_parent'] = absint( $input['parent'] );
		}
		$q = new WP_Query( $args );
		return array(
			'total' => (int) $q->found_posts,
			'items' => array_map( array( $h, 'format_attachment' ), $q->posts ),
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public static function get_media( array $input ) {
		$post = self::attachment( $input['id'], 'read_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$data  = WPMCP_Helpers::format_attachment( $post );
		$sizes = array();
		if ( wp_attachment_is_image( $post->ID ) ) {
			foreach ( get_intermediate_image_sizes() as $size ) {
				$src = wp_get_attachment_image_src( $post->ID, $size );
				if ( $src ) {
					$sizes[ $size ] = array( 'url' => $src[0], 'width' => $src[1], 'height' => $src[2] );
				}
			}
		}
		$data['sizes'] = $sizes;
		return $data;
	}

	/**
	 * @return array|WP_Error
	 */
	public static function upload_media( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$h         = 'WPMCP_Helpers';
		$parent    = absint( $h::arg( $input, 'attach_to', 0 ) );
		$filename  = sanitize_file_name( (string) $h::arg( $input, 'filename', '' ) );

		if ( ! empty( $input['url'] ) ) {
			$url = esc_url_raw( (string) $input['url'] );
			$tmp = download_url( $url, 60 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			if ( '' === $filename ) {
				$filename = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
			}
		} elseif ( ! empty( $input['data'] ) ) {
			if ( '' === $filename ) {
				return $h::error( 'missing_filename', 'filename is required with data.' );
			}
			$raw = (string) $input['data'];
			if ( preg_match( '/^data:[^;]+;base64,/', $raw ) ) {
				$raw = substr( $raw, strpos( $raw, ',' ) + 1 );
			}
			$bytes = base64_decode( $raw, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $bytes ) {
				return $h::error( 'invalid_data', 'data is not valid base64.' );
			}
			$tmp = wp_tempnam( $filename );
			file_put_contents( $tmp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		} else {
			return $h::error( 'missing_source', 'Pass url or data.' );
		}

		if ( '' === $filename ) {
			$filename = 'upload-' . time();
		}

		$file = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);
		$post_data = array();
		if ( ! empty( $input['title'] ) ) {
			$post_data['post_title'] = (string) $input['title'];
		}
		if ( ! empty( $input['caption'] ) ) {
			$post_data['post_excerpt'] = (string) $input['caption'];
		}

		$id = media_handle_sideload( $file, $parent, null, $post_data );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt'] ) );
		}

		return $h::format_attachment( get_post( $id ) );
	}

	/**
	 * @return array|WP_Error
	 */
	public static function update_media( array $input ) {
		$post = self::attachment( $input['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$arr = array( 'ID' => $post->ID );
		if ( isset( $input['title'] ) ) {
			$arr['post_title'] = (string) $input['title'];
		}
		if ( isset( $input['caption'] ) ) {
			$arr['post_excerpt'] = (string) $input['caption'];
		}
		if ( isset( $input['description'] ) ) {
			$arr['post_content'] = (string) $input['description'];
		}
		if ( count( $arr ) > 1 ) {
			$res = wp_update_post( wp_slash( $arr ), true );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		if ( isset( $input['alt'] ) ) {
			update_post_meta( $post->ID, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt'] ) );
		}
		return WPMCP_Helpers::format_attachment( get_post( $post->ID ) );
	}

	/**
	 * @return array|WP_Error
	 */
	public static function delete_media( array $input ) {
		$post = self::attachment( $input['id'], 'delete_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! wp_delete_attachment( $post->ID, true ) ) {
			return WPMCP_Helpers::error( 'failed', 'Delete failed.', 500 );
		}
		return array( 'deleted' => $post->ID );
	}
}
