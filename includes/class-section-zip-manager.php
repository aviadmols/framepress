<?php
/**
 * HERO Section ZIP Manager
 *
 * Handles lifecycle of user-uploaded custom sections stored in
 * wp-content/uploads/hero/sections/{slug}/
 *
 * Security rules:
 * - schema.php must return a PHP array (no executable statements caught on load test)
 * - section.php is the only file allowed to contain PHP
 * - The uploads directory has .htaccess blocking direct PHP execution
 * - Upload via REST uses editor_permission; delete remains admin-only on its route
 */

defined( 'ABSPATH' ) || exit;

class Hero_Section_Zip_Manager {

    private string $base_dir;
    private string $base_url;

    public function __construct() {
        $upload         = wp_upload_dir();
        $this->base_dir = trailingslashit( $upload['basedir'] ) . 'hero/sections/';
        $this->base_url = trailingslashit( $upload['baseurl'] ) . 'hero/sections/';
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    /**
     * Validate and extract a ZIP file into the uploads sections directory.
     *
     * @param string $tmp_file  Absolute path to the uploaded temporary file.
     * @param string $slug      Desired section slug (sanitised internally).
     * @return true|\WP_Error
     */
    public function install_from_zip( string $tmp_file, string $slug ): true|\WP_Error {
        $slug = sanitize_title( $slug );
        if ( ! $slug ) {
            return new \WP_Error( 'invalid_slug', __( 'Invalid section slug.', 'hero' ) );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'no_zip', __( 'ZipArchive PHP extension is required.', 'hero' ) );
        }

        $zip = new \ZipArchive();
        $res = $zip->open( $tmp_file );
        if ( $res !== true ) {
            return new \WP_Error( 'zip_open', __( 'Could not open ZIP file.', 'hero' ) );
        }

        // Validate contents before extraction.
        $validation = $this->validate_zip_contents( $zip );
        if ( is_wp_error( $validation ) ) {
            $zip->close();
            return $validation;
        }

        $target = $this->base_dir . $slug . '/';

        // If section already exists, remove it first.
        if ( is_dir( $target ) ) {
            $this->delete_directory( $target );
        }

        wp_mkdir_p( $target );
        $zip->extractTo( $target );
        $zip->close();

        // Post-extraction: validate that schema.php actually loads as an array and type matches slug.
        $schema_test = $this->test_schema_file( $target . 'schema.php', $slug );
        if ( is_wp_error( $schema_test ) ) {
            $this->delete_directory( $target );
            return $schema_test;
        }

        // Import any image files from the ZIP into WordPress Media Library (non-fatal).
        $zip2 = new \ZipArchive();
        if ( $zip2->open( $tmp_file ) === true ) {
            $this->import_images_from_zip( $zip2 );
            $zip2->close();
        }

        // Bust registry cache so the new section appears immediately.
        Hero_Section_Registry::get_instance()->bust_cache();

        return true;
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    /**
     * Delete an uploaded section.
     *
     * @param string $type         Section type slug.
     * @param bool   $force_delete Delete even if in use.
     * @return true|\WP_Error
     */
    public function delete_section( string $type, bool $force_delete = false ): true|\WP_Error {
        $registry = Hero_Section_Registry::get_instance();
        $schema   = $registry->get_section( $type );

        if ( ! $schema ) {
            return new \WP_Error( 'not_found', __( 'Section not found.', 'hero' ) );
        }

        if ( ( $schema['_source'] ?? '' ) !== 'uploads' ) {
            return new \WP_Error( 'not_uploaded', __( 'Only uploaded sections can be deleted via this manager.', 'hero' ) );
        }

        if ( ! $force_delete ) {
            $in_use = $this->find_usage( $type );
            if ( ! empty( $in_use ) ) {
                return new \WP_Error(
                    'section_in_use',
                    sprintf(
                        __( 'Section "%s" is used on %d page(s). Use force_delete to override.', 'hero' ),
                        $type,
                        count( $in_use )
                    ),
                    [ 'pages' => $in_use ]
                );
            }
        }

        $target = $this->base_dir . $type . '/';
        if ( ! is_dir( $target ) ) {
            return new \WP_Error( 'dir_not_found', __( 'Section directory not found.', 'hero' ) );
        }

        $this->delete_directory( $target );
        $registry->bust_cache();

        return true;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Validate that a ZIP archive contains the required files.
     */
    private function validate_zip_contents( \ZipArchive $zip ): true|\WP_Error {
        $has_section = false;
        $has_schema  = false;

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );

            // Strip leading directory if the ZIP wraps everything in a folder.
            $parts    = explode( '/', $name );
            $basename = end( $parts );

            if ( $basename === 'section.php' ) {
                $has_section = true;
            }
            if ( $basename === 'schema.php' ) {
                $has_schema = true;
            }

            // Reject any .php file that isn't section.php or schema.php.
            if ( pathinfo( $name, PATHINFO_EXTENSION ) === 'php' ) {
                if ( $basename !== 'section.php' && $basename !== 'schema.php' ) {
                    return new \WP_Error(
                        'disallowed_php',
                        sprintf( __( 'ZIP contains disallowed PHP file: %s', 'hero' ), esc_html( $name ) )
                    );
                }
            }
        }

        if ( ! $has_section ) {
            return new \WP_Error( 'missing_section', __( 'ZIP must contain section.php.', 'hero' ) );
        }
        if ( ! $has_schema ) {
            return new \WP_Error( 'missing_schema', __( 'ZIP must contain schema.php.', 'hero' ) );
        }

        return true;
    }

    /**
     * Include schema.php and verify it returns an array with required keys.
     * When $expected_slug is set, schema['type'] must match (same as install target folder / UI slug).
     *
     * @param string $schema_file   Absolute path to schema.php.
     * @param string $expected_slug Sanitized slug; empty skips type match check.
     */
    private function test_schema_file( string $schema_file, string $expected_slug = '' ): true|\WP_Error {
        if ( ! file_exists( $schema_file ) ) {
            return new \WP_Error( 'schema_missing', __( 'schema.php not found after extraction.', 'hero' ) );
        }

        try {
            $loader = static function ( string $f ): mixed { return include $f; };
            $result = $loader( $schema_file );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'schema_error', $e->getMessage() );
        }

        if ( ! is_array( $result ) ) {
            return new \WP_Error( 'schema_not_array', __( 'schema.php must return a PHP array.', 'hero' ) );
        }

        foreach ( [ 'type', 'label', 'settings' ] as $key ) {
            if ( empty( $result[ $key ] ) ) {
                return new \WP_Error( 'schema_missing_key', sprintf( __( 'schema.php is missing required key: %s', 'hero' ), $key ) );
            }
        }

        if ( $expected_slug !== '' && (string) ( $result['type'] ?? '' ) !== $expected_slug ) {
            return new \WP_Error(
                'schema_type_mismatch',
                sprintf(
                    /* translators: %s: section slug user entered */
                    __( 'schema.php "type" must be "%s" to match the section slug.', 'hero' ),
                    $expected_slug
                )
            );
        }

        return true;
    }

    /**
     * Find all posts / options that use a given section type.
     * Returns array of ['id' => post_id|'header'|'footer', 'title' => label].
     */
    private function find_usage( string $type ): array {
        $found = [];

        // Search page posts.
        global $wpdb;
        $like  = '%"type":"' . esc_sql( $type ) . '"%';
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_hero_sections'
                 AND pm.meta_value LIKE %s",
                $like
            )
        );
        foreach ( $posts as $post ) {
            $found[] = [ 'id' => $post->ID, 'title' => $post->post_title ];
        }

        // Check header option.
        $header_raw = get_option( 'hero_header', '' );
        if ( $header_raw && str_contains( $header_raw, '"type":"' . $type . '"' ) ) {
            $found[] = [ 'id' => 'header', 'title' => 'Header' ];
        }

        // Check footer option.
        $footer_raw = get_option( 'hero_footer', '' );
        if ( $footer_raw && str_contains( $footer_raw, '"type":"' . $type . '"' ) ) {
            $found[] = [ 'id' => 'footer', 'title' => 'Footer' ];
        }

        return $found;
    }

    /**
     * Import image files found inside a ZIP into the WordPress Media Library.
     * Non-fatal: errors are silently skipped so section install is not blocked.
     */
    private function import_images_from_zip( \ZipArchive $zip ): void {
        static $loaded = false;
        if ( ! $loaded ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $loaded = true;
        }

        $image_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ];

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

            if ( ! in_array( $ext, $image_exts, true ) ) {
                continue;
            }

            $basename = basename( $name );
            $tmp_path = sys_get_temp_dir() . '/' . wp_unique_filename( sys_get_temp_dir(), $basename );

            $content = $zip->getFromIndex( $i );
            if ( $content === false ) {
                continue;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            if ( file_put_contents( $tmp_path, $content ) === false ) {
                continue;
            }

            $file_array = [
                'name'     => $basename,
                'tmp_name' => $tmp_path,
            ];

            $attachment_id = media_handle_sideload( $file_array, 0 );

            // Clean up temp file if sideload didn't move it.
            if ( file_exists( $tmp_path ) ) {
                @unlink( $tmp_path );
            }
        }
    }

    /**
     * Recursively delete a directory.
     */
    private function delete_directory( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        $items = scandir( $dir );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }
            $path = $dir . $item;
            if ( is_dir( $path ) ) {
                $this->delete_directory( trailingslashit( $path ) );
            } else {
                unlink( $path );
            }
        }
        rmdir( $dir );
    }
}
