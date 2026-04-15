<?php
/**
 * FramePress REST API
 *
 * All endpoints under the framepress/v1 namespace.
 * Permission: current_user_can('edit_pages') + X-WP-Nonce header (handled by WP core).
 */

defined( 'ABSPATH' ) || exit;

class FramePress_Rest_API {

    private FramePress_Section_Registry $section_registry;
    private FramePress_Block_Registry   $block_registry;
    private FramePress_Section_Renderer $renderer;
    private FramePress_Global_Settings  $global_settings;

    private const NS = 'framepress/v1';

    public function __construct(
        FramePress_Section_Registry $section_registry,
        FramePress_Block_Registry   $block_registry,
        FramePress_Section_Renderer $renderer,
        FramePress_Global_Settings  $global_settings
    ) {
        $this->section_registry = $section_registry;
        $this->block_registry   = $block_registry;
        $this->renderer         = $renderer;
        $this->global_settings  = $global_settings;
    }

    public function register_routes(): void {
        // ── Schemas ──────────────────────────────────────────────────────────
        register_rest_route( self::NS, '/schemas', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_schemas' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        register_rest_route( self::NS, '/schemas/ai-export', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_schemas_ai_export' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        register_rest_route( self::NS, '/blocks', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_blocks' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        // ── Page sections ─────────────────────────────────────────────────────
        register_rest_route( self::NS, '/page/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_page_sections' ],
                'permission_callback' => [ $this, 'editor_permission' ],
                'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_page_sections' ],
                'permission_callback' => [ $this, 'editor_permission' ],
                'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
            ],
        ] );

        // ── Header ────────────────────────────────────────────────────────────
        register_rest_route( self::NS, '/header', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_header' ],  'permission_callback' => [ $this, 'editor_permission' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'save_header' ], 'permission_callback' => [ $this, 'editor_permission' ] ],
        ] );

        // ── Footer ────────────────────────────────────────────────────────────
        register_rest_route( self::NS, '/footer', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_footer' ],  'permission_callback' => [ $this, 'editor_permission' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'save_footer' ], 'permission_callback' => [ $this, 'editor_permission' ] ],
        ] );

        // ── Global settings ───────────────────────────────────────────────────
        register_rest_route( self::NS, '/global-settings', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_global_settings' ],  'permission_callback' => [ $this, 'editor_permission' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'save_global_settings' ], 'permission_callback' => [ $this, 'editor_permission' ] ],
        ] );

        // CSS-only endpoint used by the preview iframe's live postMessage bridge.
        register_rest_route( self::NS, '/global-settings/css', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'get_global_settings_css' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        // ── Live preview render ───────────────────────────────────────────────
        register_rest_route( self::NS, '/render-section', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'render_section' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        // ── ZIP upload ────────────────────────────────────────────────────────
        register_rest_route( self::NS, '/sections/upload', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'upload_section' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        register_rest_route( self::NS, '/sections/(?P<type>[a-z0-9\-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_section' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [ 'type' => [ 'sanitize_callback' => 'sanitize_title' ] ],
        ] );

        // ── Sections manager ──────────────────────────────────────────────────
        // List all sections with usage info.
        register_rest_route( self::NS, '/sections-manager/list', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'sections_manager_list' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // Read / write section files (uploads sections only for writes).
        register_rest_route( self::NS, '/sections-manager/(?P<type>[a-z0-9\-]+)/files', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_section_files' ],  'permission_callback' => [ $this, 'admin_permission' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'save_section_files' ], 'permission_callback' => [ $this, 'admin_permission' ] ],
        ] );

        // ── AI endpoints ──────────────────────────────────────────────────────
        register_rest_route( self::NS, '/ai/generate-section', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'ai_generate_section' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        register_rest_route( self::NS, '/ai/generate-page', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'ai_generate_page' ],
            'permission_callback' => [ $this, 'editor_permission' ],
        ] );

        // AI provider settings (GET = load, POST = save).
        register_rest_route( self::NS, '/ai/settings', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_ai_settings' ],  'permission_callback' => [ $this, 'admin_permission' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'save_ai_settings' ], 'permission_callback' => [ $this, 'admin_permission' ] ],
        ] );

        // Generate section PHP files from a description or raw HTML.
        register_rest_route( self::NS, '/ai/generate-section-files', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'ai_generate_section_files' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // Fix broken AI-generated section files using AI.
        register_rest_route( self::NS, '/ai/fix-section-files', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'ai_fix_section_files' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // Write AI-generated section files to disk and register them.
        register_rest_route( self::NS, '/ai/install-section', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'ai_install_section' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );
    }

    // ─── Permission checks ────────────────────────────────────────────────────

    public function editor_permission(): bool {
        return current_user_can( 'edit_pages' );
    }

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    // ─── Schema endpoints ─────────────────────────────────────────────────────

    public function get_schemas( \WP_REST_Request $request ): \WP_REST_Response {
        $context = sanitize_text_field( $request->get_param( 'context' ) ?: 'page' );
        $raw     = $this->section_registry->get_sections_for_context( $context );
        $schemas = [];

        foreach ( $raw as $type => $schema ) {
            $schemas[] = $this->prepare_schema_for_api( $schema );
        }

        return rest_ensure_response( $schemas );
    }

    public function get_schemas_ai_export( \WP_REST_Request $request ): \WP_REST_Response {
        $all     = $this->section_registry->get_all_sections();
        $compact = [];

        foreach ( $all as $schema ) {
            $entry = [
                'type'     => $schema['type'],
                'label'    => $schema['label'],
                'category' => $schema['category'] ?? 'content',
                'settings' => array_map( static fn( array $f ) => [
                    'id'          => $f['id'],
                    'type'        => $f['type'],
                    'label'       => $f['label'],
                    'description' => $f['description'] ?? '',
                    'default'     => $f['default'] ?? '',
                ], $schema['settings'] ?? [] ),
                'blocks'   => [
                    'allowed' => $schema['blocks']['allowed'] ?? [],
                    'max'     => $schema['blocks']['max'] ?? 0,
                ],
            ];
            $compact[] = $entry;
        }

        return rest_ensure_response( [ 'sections' => $compact ] );
    }

    public function get_blocks( \WP_REST_Request $request ): \WP_REST_Response {
        $blocks = [];
        foreach ( $this->block_registry->get_all_blocks() as $schema ) {
            $blocks[] = [
                'type'     => $schema['type'],
                'label'    => $schema['label'],
                'settings' => $schema['settings'] ?? [],
                'source'   => $schema['source'] ?? 'plugin',
            ];
        }
        return rest_ensure_response( $blocks );
    }

    // ─── Page sections ────────────────────────────────────────────────────────

    public function get_page_sections( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id  = (int) $request->get_param( 'id' );
        $raw      = get_post_meta( $post_id, '_framepress_sections', true );
        $sections = $raw ? json_decode( $raw, true ) : [];
        return rest_ensure_response( is_array( $sections ) ? $sections : [] );
    }

    public function save_page_sections( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id  = (int) $request->get_param( 'id' );
        $body     = $request->get_json_params();
        $sections = $body['sections'] ?? [];

        if ( ! is_array( $sections ) ) {
            return new \WP_REST_Response( [ 'error' => 'sections must be an array' ], 400 );
        }

        $clean = $this->sanitize_sections( $sections );
        update_post_meta( $post_id, '_framepress_sections', wp_json_encode( $clean ) );

        return rest_ensure_response( [ 'success' => true, 'sections' => $clean ] );
    }

    // ─── Header / Footer ──────────────────────────────────────────────────────

    public function get_header( \WP_REST_Request $request ): \WP_REST_Response {
        $raw      = get_option( 'framepress_header', '[]' );
        $sections = json_decode( $raw, true );
        return rest_ensure_response( is_array( $sections ) ? $sections : [] );
    }

    public function save_header( \WP_REST_Request $request ): \WP_REST_Response {
        $body     = $request->get_json_params();
        $sections = $body['sections'] ?? [];
        if ( ! is_array( $sections ) ) {
            return new \WP_REST_Response( [ 'error' => 'sections must be an array' ], 400 );
        }
        $clean = $this->sanitize_sections( $sections );
        update_option( 'framepress_header', wp_json_encode( $clean ) );
        return rest_ensure_response( [ 'success' => true, 'sections' => $clean ] );
    }

    public function get_footer( \WP_REST_Request $request ): \WP_REST_Response {
        $raw      = get_option( 'framepress_footer', '[]' );
        $sections = json_decode( $raw, true );
        return rest_ensure_response( is_array( $sections ) ? $sections : [] );
    }

    public function save_footer( \WP_REST_Request $request ): \WP_REST_Response {
        $body     = $request->get_json_params();
        $sections = $body['sections'] ?? [];
        if ( ! is_array( $sections ) ) {
            return new \WP_REST_Response( [ 'error' => 'sections must be an array' ], 400 );
        }
        $clean = $this->sanitize_sections( $sections );
        update_option( 'framepress_footer', wp_json_encode( $clean ) );
        return rest_ensure_response( [ 'success' => true, 'sections' => $clean ] );
    }

    // ─── Global settings ──────────────────────────────────────────────────────

    public function get_global_settings( \WP_REST_Request $request ): \WP_REST_Response {
        return rest_ensure_response( [
            'settings' => $this->global_settings->get_settings(),
            'schema'   => $this->global_settings->get_schema(),
        ] );
    }

    public function save_global_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $raw  = $body['settings'] ?? [];
        if ( ! is_array( $raw ) ) {
            return new \WP_REST_Response( [ 'error' => 'settings must be an object' ], 400 );
        }
        $this->global_settings->save_settings( $raw );
        return rest_ensure_response( [ 'success' => true, 'settings' => $this->global_settings->get_settings() ] );
    }

    public function get_global_settings_css( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $raw  = $body['settings'] ?? [];
        if ( is_array( $raw ) ) {
            // Temporarily override settings to build CSS from the draft state.
            $saved = get_option( 'framepress_global_settings', '{}' );
            update_option( 'framepress_global_settings', wp_json_encode( $raw ) );
            $css = $this->global_settings->build_css_output();
            update_option( 'framepress_global_settings', $saved );
        } else {
            $css = $this->global_settings->build_css_output();
        }
        return rest_ensure_response( [ 'css' => $css ] );
    }

    // ─── Live preview render ──────────────────────────────────────────────────

    public function render_section( \WP_REST_Request $request ): \WP_REST_Response {
        $body     = $request->get_json_params();
        $instance = $body['instance'] ?? null;

        if ( ! is_array( $instance ) || empty( $instance['type'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'instance is required' ], 400 );
        }

        // Validate type exists.
        if ( ! $this->section_registry->get_section( $instance['type'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'unknown section type' ], 400 );
        }

        $html = $this->renderer->render_section( $instance );

        return rest_ensure_response( [
            'html'       => $html,
            'section_id' => $instance['id'] ?? '',
        ] );
    }

    // ─── ZIP upload ───────────────────────────────────────────────────────────

    public function upload_section( \WP_REST_Request $request ): \WP_REST_Response {
        $files = $request->get_file_params();
        if ( empty( $files['section_zip']['tmp_name'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'No file uploaded' ], 400 );
        }

        $slug = sanitize_title( $request->get_param( 'slug' ) ?: pathinfo( $files['section_zip']['name'], PATHINFO_FILENAME ) );
        if ( ! $slug ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid slug' ], 400 );
        }

        $manager = new FramePress_Section_Zip_Manager();
        $result  = $manager->install_from_zip( $files['section_zip']['tmp_name'], $slug );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 422 );
        }

        return rest_ensure_response( [ 'success' => true, 'slug' => $slug ] );
    }

    public function delete_section( \WP_REST_Request $request ): \WP_REST_Response {
        $type         = sanitize_title( $request->get_param( 'type' ) );
        $force_delete = (bool) $request->get_param( 'force' );

        $manager = new FramePress_Section_Zip_Manager();
        $result  = $manager->delete_section( $type, $force_delete );

        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            $code = $result->get_error_code() === 'section_in_use' ? 409 : 422;
            return new \WP_REST_Response( [
                'error' => $result->get_error_message(),
                'data'  => $data,
            ], $code );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    // ─── AI endpoints ─────────────────────────────────────────────────────────

    public function ai_generate_section( \WP_REST_Request $request ): \WP_REST_Response {
        $body         = $request->get_json_params();
        $section_type = sanitize_text_field( $body['section_type'] ?? '' );
        $prompt       = sanitize_textarea_field( $body['prompt'] ?? '' );

        if ( ! $section_type || ! $prompt ) {
            return new \WP_REST_Response( [ 'error' => 'section_type and prompt are required' ], 400 );
        }

        $schema = $this->section_registry->get_section( $section_type );
        if ( ! $schema ) {
            return new \WP_REST_Response( [ 'error' => 'Unknown section type' ], 400 );
        }

        $ai     = new FramePress_AI_Service();
        $result = $ai->generate_section_settings( $section_type, $prompt, $schema );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( $result );
    }

    public function ai_generate_page( \WP_REST_Request $request ): \WP_REST_Response {
        $body   = $request->get_json_params();
        $prompt = sanitize_textarea_field( $body['prompt'] ?? '' );

        if ( ! $prompt ) {
            return new \WP_REST_Response( [ 'error' => 'prompt is required' ], 400 );
        }

        $ai      = new FramePress_AI_Service();
        $schemas = $this->section_registry->get_all_sections();
        $result  = $ai->generate_page_structure( $prompt, $schemas );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        // Validate every returned section type.
        $clean_sections = [];
        foreach ( $result['sections'] ?? [] as $inst ) {
            if ( $this->section_registry->get_section( $inst['type'] ?? '' ) ) {
                $clean_sections[] = $this->sanitize_section_instance( $inst );
            }
        }

        return rest_ensure_response( [ 'sections' => $clean_sections ] );
    }

    /** Return current AI settings (key masked). */
    public function get_ai_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $has_key = ! empty( get_option( 'framepress_ai_key', '' ) );
        return rest_ensure_response( [
            'provider' => get_option( 'framepress_ai_provider', 'anthropic' ),
            'model'    => get_option( 'framepress_ai_model', '' ),
            'enabled'  => (bool) get_option( 'framepress_ai_enabled', false ),
            'has_key'  => $has_key,
        ] );
    }

    /** Save AI settings. API key is only updated when a non-empty value is sent. */
    public function save_ai_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();

        update_option( 'framepress_ai_provider', sanitize_key( $body['provider'] ?? 'anthropic' ) );
        update_option( 'framepress_ai_model',    sanitize_text_field( $body['model'] ?? '' ) );
        update_option( 'framepress_ai_enabled',  ! empty( $body['enabled'] ) ? 1 : 0 );

        $plain_key = trim( (string) ( $body['api_key'] ?? '' ) );
        if ( $plain_key !== '' ) {
            update_option( 'framepress_ai_key', FramePress_AI_Service::encrypt_api_key( $plain_key ) );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Generate section PHP files from a description OR raw HTML.
     * Returns the file contents as strings for preview before install.
     */
    public function ai_generate_section_files( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $mode = sanitize_key( $body['mode'] ?? 'description' ); // 'description' | 'html'
        $ai   = new FramePress_AI_Service();

        if ( $mode === 'html' ) {
            $html   = wp_kses_post( $body['html'] ?? '' );
            $slug   = sanitize_title( $body['slug'] ?? '' );
            if ( empty( $html ) ) {
                return new \WP_REST_Response( [ 'error' => 'html is required' ], 400 );
            }
            $result = $ai->generate_section_from_html( $html, $slug );
        } else {
            $description = sanitize_textarea_field( $body['description'] ?? '' );
            if ( empty( $description ) ) {
                return new \WP_REST_Response( [ 'error' => 'description is required' ], 400 );
            }
            $result = $ai->generate_section_from_description( $description );
        }

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Fix broken AI-generated section files and return corrected versions.
     */
    public function ai_fix_section_files( \WP_REST_Request $request ): \WP_REST_Response {
        $body  = $request->get_json_params();
        $error = sanitize_text_field( $body['error'] ?? '' );
        $files = [
            'slug'        => sanitize_title( $body['slug']        ?? '' ),
            'label'       => sanitize_text_field( $body['label']  ?? '' ),
            'schema_php'  => (string) ( $body['schema_php']       ?? '' ),
            'section_php' => (string) ( $body['section_php']      ?? '' ),
            'style_css'   => (string) ( $body['style_css']        ?? '' ),
            'script_js'   => (string) ( $body['script_js']        ?? '' ),
        ];

        if ( empty( $error ) || empty( $files['section_php'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'error message and section_php are required' ], 400 );
        }

        $ai     = new FramePress_AI_Service();
        $result = $ai->fix_section_files( $files, $error );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Install previously-generated section files to the uploads directory.
     */
    public function ai_install_section( \WP_REST_Request $request ): \WP_REST_Response {
        $body        = $request->get_json_params();
        $slug        = sanitize_title( $body['slug'] ?? '' );
        $schema_php  = (string) ( $body['schema_php']  ?? '' );
        $section_php = (string) ( $body['section_php'] ?? '' );
        $style_css   = (string) ( $body['style_css']   ?? '' );
        $script_js   = (string) ( $body['script_js']   ?? '' );

        if ( ! $slug || ! $schema_php || ! $section_php ) {
            return new \WP_REST_Response( [ 'error' => 'slug, schema_php and section_php are required' ], 400 );
        }

        $ai     = new FramePress_AI_Service();
        $result = $ai->install_section_files( $slug, $schema_php, $section_php, $style_css, $script_js );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( [ 'success' => true, 'slug' => $slug ] );
    }

    // ─── Sections Manager ─────────────────────────────────────────────────────

    /**
     * GET /sections-manager/list
     * Returns all registered sections with source, file list, and usage (pages/header/footer).
     */
    public function sections_manager_list(): \WP_REST_Response {
        $schemas = $this->section_registry->get_all_sections();

        // Collect usage: pages
        $page_usage = [];
        $pages = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_key'    => '_framepress_sections',
        ] );
        foreach ( $pages as $post ) {
            $raw = get_post_meta( $post->ID, '_framepress_sections', true );
            if ( ! $raw ) continue;
            $instances = json_decode( $raw, true );
            if ( ! is_array( $instances ) ) continue;
            foreach ( $instances as $inst ) {
                $type = $inst['type'] ?? '';
                if ( $type ) {
                    $page_usage[ $type ][] = [ 'id' => $post->ID, 'title' => get_the_title( $post ), 'edit_url' => admin_url( 'admin.php?page=framepress&post_id=' . $post->ID . '&context=page' ) ];
                }
            }
        }

        // Collect usage: header / footer
        $header_types = [];
        $raw = get_option( 'framepress_header', '[]' );
        foreach ( json_decode( $raw, true ) ?: [] as $inst ) {
            if ( ! empty( $inst['type'] ) ) $header_types[] = $inst['type'];
        }
        $footer_types = [];
        $raw = get_option( 'framepress_footer', '[]' );
        foreach ( json_decode( $raw, true ) ?: [] as $inst ) {
            if ( ! empty( $inst['type'] ) ) $footer_types[] = $inst['type'];
        }

        $result = [];
        foreach ( $schemas as $schema ) {
            $type   = $schema['type'];
            $path   = trailingslashit( $schema['_path'] ?? '' );
            $source = $schema['source'] ?? 'plugin';

            // Detect which files exist.
            $files = [];
            foreach ( [ 'schema.php', 'section.php', 'style.css', 'script.js' ] as $f ) {
                if ( file_exists( $path . $f ) ) $files[] = $f;
            }

            $usage = $page_usage[ $type ] ?? [];
            if ( in_array( $type, $header_types, true ) ) $usage[] = [ 'id' => 0, 'title' => 'Header', 'edit_url' => admin_url( 'admin.php?page=framepress&context=header' ) ];
            if ( in_array( $type, $footer_types, true ) ) $usage[] = [ 'id' => 0, 'title' => 'Footer', 'edit_url' => admin_url( 'admin.php?page=framepress&context=footer' ) ];

            $result[] = [
                'type'     => $type,
                'label'    => $schema['label'] ?? $type,
                'category' => $schema['category'] ?? '',
                'source'   => $source,
                'files'    => $files,
                'usage'    => $usage,
                'editable' => $source === 'uploads',
            ];
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET /sections-manager/{type}/files
     * Returns the raw file contents for a section type.
     */
    public function get_section_files( \WP_REST_Request $request ): \WP_REST_Response {
        $type   = sanitize_title( $request->get_param( 'type' ) );
        $schema = $this->section_registry->get_section( $type );
        if ( ! $schema ) {
            return new \WP_REST_Response( [ 'error' => 'Section not found' ], 404 );
        }

        $path  = trailingslashit( $schema['_path'] );
        $files = [];
        foreach ( [ 'schema.php', 'section.php', 'style.css', 'script.js' ] as $f ) {
            $full = $path . $f;
            if ( file_exists( $full ) ) {
                $files[ $f ] = file_get_contents( $full );
            }
        }

        return rest_ensure_response( [
            'type'     => $type,
            'source'   => $schema['source'] ?? 'plugin',
            'editable' => ( $schema['source'] ?? '' ) === 'uploads',
            'files'    => $files,
        ] );
    }

    /**
     * POST /sections-manager/{type}/files
     * Saves updated file contents — only allowed for uploads sections.
     */
    public function save_section_files( \WP_REST_Request $request ): \WP_REST_Response {
        $type   = sanitize_title( $request->get_param( 'type' ) );
        $schema = $this->section_registry->get_section( $type );
        if ( ! $schema ) {
            return new \WP_REST_Response( [ 'error' => 'Section not found' ], 404 );
        }

        if ( ( $schema['source'] ?? '' ) !== 'uploads' ) {
            return new \WP_REST_Response( [ 'error' => 'Only user-uploaded sections can be edited.' ], 403 );
        }

        $body  = $request->get_json_params();
        $files = $body['files'] ?? [];
        $path  = trailingslashit( $schema['_path'] );

        $allowed = [ 'schema.php', 'section.php', 'style.css', 'script.js' ];
        foreach ( $allowed as $filename ) {
            if ( ! isset( $files[ $filename ] ) ) continue;
            $content = (string) $files[ $filename ];

            // PHP syntax check for PHP files.
            if ( substr( $filename, -4 ) === '.php' ) {
                try {
                    token_get_all( $content, TOKEN_PARSE );
                } catch ( \ParseError $e ) {
                    return new \WP_REST_Response( [ 'error' => "Syntax error in $filename: " . $e->getMessage() ], 400 );
                }
                // Block unsafe constructs in schema.php.
                if ( $filename === 'schema.php' && preg_match( '/\b(include|require|eval|exec|system|passthru|shell_exec)\s*[(\s]/i', $content ) ) {
                    return new \WP_REST_Response( [ 'error' => 'schema.php contains unsafe PHP constructs.' ], 400 );
                }
            }

            if ( file_put_contents( $path . $filename, $content ) === false ) {
                return new \WP_REST_Response( [ 'error' => "Could not write $filename" ], 500 );
            }
        }

        // Bust registry cache if schema.php was updated.
        if ( isset( $files['schema.php'] ) ) {
            delete_transient( 'framepress_section_registry' );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    // ─── Sanitisation helpers ─────────────────────────────────────────────────

    /**
     * Sanitise an array of section instances.
     * - Strips any type not registered in the Section Registry.
     * - Generates new UUIDs for instances that lack an id.
     * - Only allows known keys in each instance.
     */
    private function sanitize_sections( array $sections ): array {
        $clean = [];
        foreach ( $sections as $inst ) {
            $result = $this->sanitize_section_instance( $inst );
            if ( $result ) {
                $clean[] = $result;
            }
        }
        return $clean;
    }

    private function sanitize_section_instance( array $inst ): ?array {
        $type = sanitize_title( $inst['type'] ?? '' );
        if ( ! $type || ! $this->section_registry->get_section( $type ) ) {
            return null;
        }

        return [
            'id'         => sanitize_text_field( $inst['id'] ?? wp_generate_uuid4() ),
            'type'       => $type,
            'settings'   => $this->sanitize_settings_values( $inst['settings'] ?? [] ),
            'blocks'     => $this->sanitize_blocks( $inst['blocks'] ?? [] ),
            'custom_css' => str_ireplace( '</style>', '', wp_unslash( (string) ( $inst['custom_css'] ?? '' ) ) ),
            'enabled'    => isset( $inst['enabled'] ) ? (bool) $inst['enabled'] : true,
        ];
    }

    private function sanitize_settings_values( array $settings ): array {
        $clean = [];
        foreach ( $settings as $key => $value ) {
            $key = sanitize_key( $key );
            if ( is_array( $value ) ) {
                $clean[ $key ] = $value; // e.g. multi-select — don't flatten
            } elseif ( is_bool( $value ) ) {
                $clean[ $key ] = $value;
            } elseif ( is_numeric( $value ) ) {
                $clean[ $key ] = $value;
            } else {
                // Allow rich text HTML; wp_kses_post strips dangerous tags.
                $clean[ $key ] = wp_kses_post( wp_unslash( (string) $value ) );
            }
        }
        return $clean;
    }

    private function sanitize_blocks( array $blocks ): array {
        $clean = [];
        foreach ( $blocks as $block ) {
            $block_type = sanitize_title( $block['type'] ?? '' );
            if ( ! $block_type ) {
                continue;
            }
            $clean[] = [
                'id'       => sanitize_text_field( $block['id'] ?? wp_generate_uuid4() ),
                'type'     => $block_type,
                'settings' => $this->sanitize_settings_values( $block['settings'] ?? [] ),
            ];
        }
        return $clean;
    }

    // ─── Schema preparation ───────────────────────────────────────────────────

    /**
     * Prepare a schema for the API response — strip internal _keys and
     * populate dynamic options (e.g. wp_categories).
     */
    private function prepare_schema_for_api( array $schema ): array {
        // Strip internal filesystem keys.
        unset( $schema['_path'], $schema['_source'] );

        // Populate dynamic options_source fields.
        foreach ( $schema['settings'] as &$field ) {
            if ( isset( $field['options_source'] ) ) {
                $field['options'] = $this->resolve_options_source( $field['options_source'] );
                unset( $field['options_source'] );
            }
        }
        unset( $field );

        return $schema;
    }

    private function resolve_options_source( string $source ): array {
        switch ( $source ) {
            case 'wp_categories':
                return array_map( static function ( $cat ) {
                    return [ 'value' => (string) $cat->term_id, 'label' => $cat->name ];
                }, get_categories( [ 'hide_empty' => false ] ) );

            case 'wp_menus':
                return array_map( static function ( $menu ) {
                    return [ 'value' => (string) $menu->term_id, 'label' => $menu->name ];
                }, wp_get_nav_menus() );

            case 'wp_pages':
                return array_map( static function ( $page ) {
                    return [ 'value' => (string) $page->ID, 'label' => $page->post_title ];
                }, get_pages() );

            default:
                return [];
        }
    }
}
