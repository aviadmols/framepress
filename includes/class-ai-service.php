<?php
/**
 * HERO AI Service
 *
 * Provider-abstracted LLM integration.
 * Generates section settings JSON and page structures from natural language prompts.
 *
 * Rules:
 * - AI never generates PHP code — only JSON values.
 * - Every AI response is validated against section schemas before use.
 * - Rate limited: 10 requests per user per minute (via WP transients).
 * - API key stored encrypted in wp_options.
 */

defined( 'ABSPATH' ) || exit;

class Hero_AI_Service {

    private string $provider;
    private string $api_key;
    private string $model;
    private bool   $enabled;

    public function __construct() {
        $this->provider = get_option( 'hero_ai_provider', 'anthropic' );
        $this->api_key  = $this->decrypt_api_key( get_option( 'hero_ai_key', '' ) );
        $this->model    = get_option( 'hero_ai_model', 'claude-sonnet-4-6' );
        $this->enabled  = (bool) get_option( 'hero_ai_enabled', false );
    }

    // ─── Public generation API ────────────────────────────────────────────────

    /**
     * Generate settings + blocks values for a single section type.
     *
     * @param string $section_type  Section type slug.
     * @param string $prompt        User's natural language description.
     * @param array  $schema        Section schema array (from registry).
     * @return array|\WP_Error      { settings: {}, blocks: [] }
     */
    public function generate_section_settings( string $section_type, string $prompt, array $schema ): array|\WP_Error {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured. Please add an API key in HERO → AI Settings.', 'hero' ) );
        }

        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) {
            return $rate_error;
        }

        $system_prompt = $this->build_section_system_prompt( $schema );
        $user_message  = sprintf(
            "Generate content for a \"%s\" section. User's description: %s\n\nReturn ONLY valid JSON with keys: settings (object) and blocks (array). No other text.",
            esc_html( $section_type ),
            esc_html( $prompt )
        );

        $result = $this->call_api( $system_prompt, $user_message );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->validate_section_response( $result, $schema );
    }

    /**
     * Generate a full page structure (array of section instances) from a prompt.
     *
     * @param string $prompt           Natural language page description.
     * @param array  $available_schemas All registered section schemas.
     * @return array|\WP_Error         { sections: [ { type, settings, blocks }, ... ] }
     */
    public function generate_page_structure( string $prompt, array $available_schemas ): array|\WP_Error {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured.', 'hero' ) );
        }

        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) {
            return $rate_error;
        }

        $system_prompt = $this->build_page_system_prompt( $available_schemas );
        $user_message  = sprintf(
            "Create a page structure for: %s\n\nReturn ONLY valid JSON with key: sections (array of section objects with type, settings, blocks). Use only the section types listed above.",
            esc_html( $prompt )
        );

        $result = $this->call_api( $system_prompt, $user_message );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return is_array( $result ) ? $result : [ 'sections' => [] ];
    }

    // ─── API abstraction ──────────────────────────────────────────────────────

    /**
     * @param string      $system_prompt
     * @param string      $user_message
     * @param string|null $image_base64  Optional base64-encoded image (data URI or raw base64).
     * @param string      $image_mime    MIME type, e.g. 'image/png'
     */
    private function call_api( string $system_prompt, string $user_message, ?string $image_base64 = null, string $image_mime = 'image/png' ): array|\WP_Error {
        switch ( $this->provider ) {
            case 'anthropic':
                return $this->call_anthropic( $system_prompt, $user_message, $image_base64, $image_mime );
            case 'openai':
                return $this->call_openai( $system_prompt, $user_message, $image_base64, $image_mime );
            default:
                return new \WP_Error( 'unknown_provider', sprintf( __( 'Unknown AI provider: %s', 'hero' ), $this->provider ) );
        }
    }

    private function call_anthropic( string $system_prompt, string $user_message, ?string $image_base64 = null, string $image_mime = 'image/png' ): array|\WP_Error {
        // Build content array — optionally prepend an image block.
        $content = [];
        if ( $image_base64 ) {
            // Strip data URI prefix if present.
            $raw = preg_replace( '/^data:[^;]+;base64,/', '', $image_base64 );
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $image_mime,
                    'data'       => $raw,
                ],
            ];
        }
        $content[] = [ 'type' => 'text', 'text' => $user_message ];

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 120,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode( [
                'model'      => $this->model,
                'max_tokens' => 2048,
                'system'     => $system_prompt,
                'messages'   => [
                    [ 'role' => 'user', 'content' => $content ],
                ],
            ] ),
        ] );

        return $this->parse_anthropic_response( $response );
    }

    private function call_openai( string $system_prompt, string $user_message, ?string $image_base64 = null, string $image_mime = 'image/png' ): array|\WP_Error {
        // Build user content array — optionally include an image.
        if ( $image_base64 ) {
            $raw = preg_replace( '/^data:[^;]+;base64,/', '', $image_base64 );
            $user_content = [
                [ 'type' => 'text',       'text'      => $user_message ],
                [ 'type' => 'image_url',  'image_url' => [ 'url' => 'data:' . $image_mime . ';base64,' . $raw ] ],
            ];
        } else {
            $user_content = $user_message;
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode( [
                'model'    => $this->model ?: 'gpt-4o',
                'messages' => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user',   'content' => $user_content ],
                ],
                'response_format' => [ 'type' => 'json_object' ],
            ] ),
        ] );

        return $this->parse_openai_response( $response );
    }

    // ─── Response parsers ─────────────────────────────────────────────────────

    private function parse_anthropic_response( $response ): array|\WP_Error {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? __( 'API request failed', 'hero' );
            return new \WP_Error( 'api_error', $msg );
        }

        $text = $body['content'][0]['text'] ?? '';
        return $this->parse_json_from_text( $text );
    }

    private function parse_openai_response( $response ): array|\WP_Error {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? __( 'API request failed', 'hero' );
            return new \WP_Error( 'api_error', $msg );
        }

        $text = $body['choices'][0]['message']['content'] ?? '';
        return $this->parse_json_from_text( $text );
    }

    private function parse_json_from_text( string $text ): array|\WP_Error {
        // Strip markdown code fences if present.
        $text = preg_replace( '/^```(?:json)?\n?/m', '', $text );
        $text = preg_replace( '/```\s*$/m', '', $text );
        $text = trim( $text );

        $decoded = json_decode( $text, true );
        if ( ! is_array( $decoded ) ) {
            return new \WP_Error( 'json_parse', __( 'AI returned invalid JSON.', 'hero' ) );
        }

        return $decoded;
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    /**
     * Validate and coerce AI-returned values against the section schema.
     * Unknown fields are stripped; wrong types are cast or defaulted.
     */
    private function validate_section_response( array $response, array $schema ): array {
        $clean_settings = [];

        foreach ( $schema['settings'] ?? [] as $field ) {
            $id    = $field['id'];
            $value = $response['settings'][ $id ] ?? $field['default'] ?? '';

            $clean_settings[ $id ] = $this->coerce_value( $value, $field );
        }

        $clean_blocks = [];
        $allowed      = $schema['blocks']['allowed'] ?? [];
        foreach ( $response['blocks'] ?? [] as $block ) {
            $block_type = $block['type'] ?? '';
            if ( ! in_array( $block_type, $allowed, true ) ) {
                continue;
            }
            $clean_blocks[] = [
                'id'       => wp_generate_uuid4(),
                'type'     => $block_type,
                'settings' => (array) ( $block['settings'] ?? [] ),
            ];
        }

        return [
            'settings' => $clean_settings,
            'blocks'   => $clean_blocks,
        ];
    }

    private function coerce_value( mixed $value, array $field ): mixed {
        switch ( $field['type'] ) {
            case 'number':
            case 'range':
                return is_numeric( $value ) ? (float) $value : ( $field['default'] ?? 0 );
            case 'checkbox':
                return (bool) $value;
            case 'select':
                $allowed = wp_list_pluck( $field['options'] ?? [], 'value' );
                return in_array( $value, $allowed, true ) ? $value : ( $field['default'] ?? '' );
            case 'url':
                return esc_url_raw( (string) $value );
            case 'color':
                return sanitize_hex_color( (string) $value ) ?? ( $field['default'] ?? '' );
            default:
                return wp_kses_post( (string) $value );
        }
    }

    // ─── Section file generation (AI → PHP files) ────────────────────────────

    /**
     * Generate HERO section files (schema.php, section.php, style.css)
     * from a natural language description.
     *
     * @param string $description  What the section should do/look like.
     * @return array|\WP_Error     { slug, label, schema_php, section_php, style_css }
     */
    public function generate_section_from_description( string $description, string $image_data = '' ): array|\WP_Error {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured.', 'hero' ) );
        }
        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) return $rate_error;

        $system = $this->build_section_files_system_prompt();
        $user   = "Create a HERO section for: {$description}";
        if ( $image_data ) {
            $user .= "\n\nA reference image is attached — use it to match the visual design.";
        }
        $user .= "\n\nReturn ONLY valid JSON with keys: slug, label, schema_php, section_php, style_css.";

        $mime = $this->detect_image_mime( $image_data );
        $result = $this->call_api( $system, $user, $image_data ?: null, $mime );
        if ( is_wp_error( $result ) ) return $result;

        return $this->validate_section_files_response( $result );
    }

    /**
     * Convert raw HTML into HERO section files.
     *
     * @param string $html   The raw HTML to analyse and convert.
     * @param string $slug   Optional suggested slug (user-supplied).
     * @return array|\WP_Error  { slug, label, schema_php, section_php, style_css }
     */
    public function generate_section_from_html( string $html, string $slug = '', string $image_data = '' ): array|\WP_Error {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured.', 'hero' ) );
        }
        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) return $rate_error;

        $system = $this->build_section_files_system_prompt();
        $slug_hint = $slug ? "Suggested slug: {$slug}\n\n" : '';
        $user = "Convert this HTML into a HERO section.\n\n"
              . $slug_hint
              . "HTML:\n```html\n{$html}\n```\n\n"
              . "Identify all variable parts (text, images, colours, links) and turn them into schema settings. "
              . "Hard-code structural HTML; parameterise content.";
        if ( $image_data ) {
            $user .= "\n\nA reference image is attached — use it to understand the visual design.";
        }
        $user .= "\n\nReturn ONLY valid JSON with keys: slug, label, schema_php, section_php, style_css.";

        $mime   = $this->detect_image_mime( $image_data );
        $result = $this->call_api( $system, $user, $image_data ?: null, $mime );
        if ( is_wp_error( $result ) ) return $result;

        return $this->validate_section_files_response( $result );
    }

    /**
     * Fix broken section files using AI.
     * Sends the broken files + error message back to the LLM and returns corrected files.
     *
     * @param array  $files   { schema_php, section_php, style_css, script_js }
     * @param string $error   The PHP syntax error message returned by install_section_files.
     * @return array|\WP_Error  { slug, label, schema_php, section_php, style_css, script_js }
     */
    public function fix_section_files( array $files, string $error ): array|\WP_Error {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured.', 'hero' ) );
        }
        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) return $rate_error;

        $system = $this->build_section_files_system_prompt();

        $schema_php  = $files['schema_php']  ?? '';
        $section_php = $files['section_php'] ?? '';
        $style_css   = $files['style_css']   ?? '';
        $script_js   = $files['script_js']   ?? '';
        $slug        = $files['slug']        ?? '';
        $label       = $files['label']       ?? '';

        $user = <<<MSG
The following HERO section files failed PHP syntax validation with this error:

ERROR: {$error}

Please fix ONLY the syntax error and return the corrected files. Do not change the section's structure, fields, or logic — only fix the PHP syntax.

Current files:

### schema.php
```php
{$schema_php}
```

### section.php
```php
{$section_php}
```

### style.css
```css
{$style_css}
```

### script.js
```js
{$script_js}
```

Return ONLY valid JSON with keys: slug, label, schema_php, section_php, style_css, script_js.
Keep slug="{$slug}" and label="{$label}" unless they need fixing.
MSG;

        $result = $this->call_api( $system, $user );
        if ( is_wp_error( $result ) ) return $result;

        return $this->validate_section_files_response( $result );
    }

    /**
     * Edit section files from a user instruction, scoped to one section. Returns full merged file map.
     *
     * @param string $section_type  Section type slug (prompt scope only; registry path is not taken from AI).
     * @param string $instruction   User request (e.g. change spacing, add field).
     * @param array  $current_files File name (schema.php, …) => file contents. Only these keys are considered.
     * @return array{ files: array<string, string>, brief: string }|\WP_Error
     */
    public function assist_edit_section_files( string $section_type, string $instruction, array $current_files ): array|\WP_Error {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured. Please add an API key in HERO → AI Settings.', 'hero' ) );
        }
        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) {
            return $rate_error;
        }

        $section_type = sanitize_title( $section_type );
        $allowed      = [ 'schema.php', 'section.php', 'style.css', 'script.js' ];
        $baseline     = [];
        foreach ( $allowed as $f ) {
            if ( array_key_exists( $f, $current_files ) && is_string( $current_files[ $f ] ) ) {
                $baseline[ $f ] = $current_files[ $f ];
            }
        }
        if ( $baseline === [] ) {
            return new \WP_Error( 'no_files', __( 'No section files to edit.', 'hero' ) );
        }

        $system = $this->build_section_files_system_prompt()
            . "\n\n--- SECTION FILE EDIT (IN-PLACE) ---\n"
            . "The user is editing ONE HERO section only: type slug `{$section_type}`.\n"
            . "You MUST NOT reference other section types, paths on disk, or any files outside: schema.php, section.php, style.css, script.js for this section.\n"
            . "Return ONLY valid JSON with:\n"
            . '  "files" — object whose keys are only: "schema.php", "section.php", "style.css", "script.js" (include only files you changed, or all four if needed).' . "\n"
            . '  "brief" — one short sentence describing what you changed (optional).' . "\n"
            . "Respect the same HERO rules as in the system prompt. Keep slug/type inside schema in sync with this section. Do not output markdown — JSON only.";

        $file_blocks = '';
        foreach ( $baseline as $name => $content ) {
            $lines = ( $name === 'section.php' || $name === 'schema.php' ) ? 'php' : ( $name === 'style.css' ? 'css' : 'js' );
            $file_blocks .= "\n### {$name}\n```{$lines}\n{$content}\n```\n";
        }
        $user = "Section type: `{$section_type}`\n\nUser request:\n{$instruction}\n\nCurrent files:{$file_blocks}\n"
            . 'Return JSON: { "files": { "file.php": "full new content" }, "brief": "…" }';

        $parsed = $this->call_api( $system, $user );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }
        if ( ! isset( $parsed['files'] ) || ! is_array( $parsed['files'] ) ) {
            return new \WP_Error( 'invalid_ai_response', __( 'AI response did not include a "files" object.', 'hero' ) );
        }
        $delta = $this->normalize_assist_file_keys( $parsed['files'] );
        $merged = $baseline;
        $n      = 0;
        foreach ( $allowed as $f ) {
            if ( ! array_key_exists( $f, $delta ) ) {
                continue;
            }
            if ( ! is_string( $delta[ $f ] ) ) {
                return new \WP_Error( 'invalid_ai_response', sprintf( /* translators: %s: file name */ __( 'Invalid content for file: %s', 'hero' ), $f ) );
            }
            $merged[ $f ] = $delta[ $f ];
            $n++;
        }
        if ( $n === 0 ) {
            return new \WP_Error( 'no_changes', __( 'The AI did not return any updated files.', 'hero' ) );
        }

        $valid = $this->validate_assist_merged_files( $merged );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $brief = is_string( $parsed['brief'] ?? null ) ? trim( (string) $parsed['brief'] ) : '';
        if ( $brief !== '' && strlen( $brief ) > 2000 ) {
            $brief = substr( $brief, 0, 2000 ) . '…';
        }

        return [
            'files' => $merged,
            'brief' => $brief,
        ];
    }

    /**
     * @param array<string, mixed> $raw From AI: may use "schema_php" or "schema.php" keys.
     *
     * @return array<string, string>
     */
    private function normalize_assist_file_keys( array $raw ): array {
        $map = [
            'schema_php'  => 'schema.php',
            'section_php' => 'section.php',
            'style_css'   => 'style.css',
            'script_js'   => 'script.js',
        ];
        $out = [];
        foreach ( $raw as $k => $v ) {
            if ( is_string( $k ) && isset( $map[ $k ] ) ) {
                $out[ $map[ $k ] ] = is_string( $v ) ? $v : '';
            } else {
                $out[ (string) $k ] = is_string( $v ) ? $v : ( is_scalar( $v ) ? (string) $v : '' );
            }
        }
        return $out;
    }

    /**
     * @param array<string, string> $files
     */
    private function validate_assist_merged_files( array $files ): true|\WP_Error {
        $allowed = [ 'schema.php', 'section.php', 'style.css', 'script.js' ];
        foreach ( $files as $filename => $content ) {
            if ( ! in_array( $filename, $allowed, true ) ) {
                continue;
            }
            if ( ! is_string( $content ) ) {
                return new \WP_Error( 'invalid_file', sprintf( /* translators: %s: file name */ __( 'Invalid content for: %s', 'hero' ), $filename ) );
            }
            if ( substr( $filename, -4 ) === '.php' ) {
                try {
                    token_get_all( $content, TOKEN_PARSE );
                } catch ( \ParseError $e ) {
                    return new \WP_Error(
                        'php_syntax',
                        sprintf(
                            /* translators: 1: file name, 2: error */
                            __( 'PHP syntax error in %1$s: %2$s', 'hero' ),
                            $filename,
                            $e->getMessage()
                        )
                    );
                }
            }
            if ( $filename === 'schema.php' && function_exists( 'hero_is_schema_php_unsafe' ) && hero_is_schema_php_unsafe( $content ) ) {
                return new \WP_Error( 'unsafe_schema', __( 'schema.php contains unsafe PHP constructs.', 'hero' ) );
            }
        }
        return true;
    }

    /**
     * Write AI-generated section files to the uploads directory and
     * flush the section registry so the new section is immediately available.
     *
     * @param string $slug
     * @param string $schema_php
     * @param string $section_php
     * @param string $style_css
     * @return true|\WP_Error
     */
    public function install_section_files( string $slug, string $schema_php, string $section_php, string $style_css, string $script_js = '' ): true|\WP_Error {
        $slug = sanitize_title( $slug );
        if ( empty( $slug ) ) {
            return new \WP_Error( 'invalid_slug', __( 'Invalid section slug.', 'hero' ) );
        }

        // Safety: PHP files must not be executable from the uploads dir — our
        // .htaccess blocks direct PHP requests, but verify the path is correct.
        $dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'hero/sections/' . $slug . '/';

        if ( ! wp_mkdir_p( $dir ) ) {
            return new \WP_Error( 'mkdir_failed', __( 'Could not create section directory.', 'hero' ) );
        }

        // Strip any <?php opening tag and leading `return` keyword the AI might have included.
        $strip_open = static function ( string $php ): string {
            $php = ltrim( preg_replace( '/^<\?php\s*/i', '', trim( $php ) ) );
            $php = preg_replace( '/^return\s+/i', '', $php );
            return $php;
        };

        $schema_content = "<?php\ndefined( 'ABSPATH' ) || exit;\n\nreturn " . $strip_open( $schema_php );

        // section.php can be either a pure-PHP file or an HTML template with embedded PHP tags.
        // If the stripped content starts with '<' it's HTML-first — we must close the PHP block
        // immediately after the security check so PHP switches back to HTML output mode.
        // If it starts with PHP code (variables, function calls, etc.) we keep PHP mode open.
        $section_raw   = $strip_open( $section_php );
        $first_char    = ltrim( $section_raw )[0] ?? '';
        if ( $first_char === '<' ) {
            // HTML-first template: open PHP, run security check, close PHP tag, then HTML output.
            $section_content = '<?php defined( \'ABSPATH\' ) || exit; ?>' . "\n" . $section_raw;
        } else {
            // PHP-first file: keep PHP block open
            $section_content = "<?php\ndefined( 'ABSPATH' ) || exit;\n\n" . $section_raw;
        }

        // Validate PHP syntax before writing to disk. token_get_all() with TOKEN_PARSE
        // throws a ParseError on syntax errors (PHP 7.0+). This prevents installing
        // broken files that would cause a WordPress fatal error on every page load.
        $syntax_check = static function ( string $content, string $filename ): ?\WP_Error {
            try {
                token_get_all( $content, TOKEN_PARSE );
            } catch ( \ParseError $e ) {
                return new \WP_Error(
                    'syntax_error',
                    sprintf( 'PHP syntax error in %s: %s', $filename, $e->getMessage() )
                );
            }
            return null;
        };

        $err = $syntax_check( $schema_content, 'schema.php' );
        if ( $err ) {
            return $err;
        }

        // Validate schema is safe (no includes / eval / dangerous calls) — tokenizer, not regex (avoids false positives in labels).
        if ( hero_is_schema_php_unsafe( $schema_content ) ) {
            return new \WP_Error( 'unsafe_schema', __( 'AI-generated schema contains unsafe PHP constructs.', 'hero' ) );
        }

        $err = $syntax_check( $section_content, 'section.php' );
        if ( $err ) return $err;

        if ( file_put_contents( $dir . 'schema.php', $schema_content ) === false ) {
            return new \WP_Error( 'write_failed', __( 'Could not write schema.php — check uploads directory permissions.', 'hero' ) );
        }
        if ( file_put_contents( $dir . 'section.php', $section_content ) === false ) {
            return new \WP_Error( 'write_failed', __( 'Could not write section.php — check uploads directory permissions.', 'hero' ) );
        }

        if ( ! empty( $style_css ) ) {
            file_put_contents( $dir . 'style.css', $style_css );
        }

        if ( ! empty( $script_js ) ) {
            file_put_contents( $dir . 'script.js', $script_js );
        }

        // Bust registry cache so the new section shows up immediately.
        delete_transient( 'hero_section_registry' );

        return true;
    }

    // ─── Validation for file generation ──────────────────────────────────────

    private function validate_section_files_response( array $data ): array|\WP_Error {
        $required = [ 'slug', 'schema_php', 'section_php' ];
        foreach ( $required as $key ) {
            if ( empty( $data[ $key ] ) ) {
                return new \WP_Error( 'missing_key', sprintf( __( 'AI response missing required key: %s', 'hero' ), $key ) );
            }
        }
        return [
            'slug'        => sanitize_title( $data['slug'] ),
            'label'       => sanitize_text_field( $data['label'] ?? $data['slug'] ),
            'schema_php'  => (string) $data['schema_php'],
            'section_php' => (string) $data['section_php'],
            'style_css'   => (string) ( $data['style_css'] ?? '' ),
            'script_js'   => (string) ( $data['script_js']  ?? '' ),
        ];
    }

    // ─── System prompt builders ───────────────────────────────────────────────

    /**
     * The master system prompt for section file generation.
     * Embedded directly so every AI call for section creation uses the exact same rules.
     */
    public function get_section_files_system_prompt(): string {
        return $this->build_section_files_system_prompt();
    }

    private function build_section_files_system_prompt(): string {
        return <<<'PROMPT'
You are building PHP section files for a WordPress plugin called HERO.
HERO works exactly like Shopify Themes — each section is a folder with two required files:
  schema.php   — returns a PHP array describing the section's fields and metadata.
  section.php  — receives $settings, $blocks, $section and outputs HTML.

══ RULES ══════════════════════════════════════════════════════════════════════

1. schema.php must `return [...]` — never echo, never execute, pure data only.
2. section.php receives exactly three variables:
     $settings  — merged field values (associative array)
     $blocks    — array of block instances
     $section   — array with keys: id, type, source
3. Every output in section.php must be escaped: esc_html(), esc_url(), wp_kses_post().
4. Use CSS custom properties for theming:
     --fp-color-primary, --fp-color-secondary, --fp-color-text, --fp-color-background
     --fp-section-padding-v, --fp-section-padding-h, --fp-container-width, --fp-gap
     --fp-font-body, --fp-font-heading, --fp-btn-radius
5. Wrap all section HTML in a single root element — the outer #hero-section-{id} wrapper is added automatically, do NOT add it yourself.
6. style.css is optional — include it if the section needs non-trivial CSS. Use the section type as a BEM namespace (e.g. .fp-hero__title). Never use inline <style> tags inside section.php.
7. For repeated UI items (cards, features, pricing tiers, steps, logos, testimonials), ALWAYS model them as blocks — never as numbered settings like item_1_title, item_2_title.
8. If you use blocks, define both:
     - 'blocks' => ['allowed' => ['your-block-type'], 'min' => 0, 'max' => N]
     - 'block_types' => ['your-block-type' => ['label' => '...', 'settings' => [...]]]
9. DO NOT invent legacy migration keys in AI output (like repeatable_migration). Those are manual migration-only config.
10. All sections must be responsive (mobile-first, CSS Grid or Flexbox).
11. DO NOT output <?php opening tags — they will be added automatically.

══ TYPOGRAPHY (mandatory) ════════════════════════════════════════════════════

12. ALWAYS use font-family: var(--fp-font-body) for body text, paragraphs, inputs, and buttons.
13. ALWAYS use font-family: var(--fp-font-heading) for headings (h1–h4).
14. NEVER hardcode any font name (e.g. 'Polin', 'Inter', 'Roboto', 'sans-serif') — always use the CSS variable.
15. If the source HTML has @font-face: move it to style.css, but replace all font-family usage with var(--fp-font-body) or var(--fp-font-heading).

══ JAVASCRIPT & INTERACTIVITY ════════════════════════════════════════════════

16. If the section needs JavaScript, output a <script> block at the BOTTOM of the section.php HTML (after all markup). Scope all logic inside an IIFE keyed to the section ID to avoid conflicts when multiple instances exist:
    <script>
    (function() {
        var root = document.getElementById('<?php echo esc_attr( $section['id'] ); ?>');
        if (!root) return;
        // all your JS here, use root.querySelector(...) not document.querySelector(...)
    })();
    </script>
17. Do NOT use inline onclick/onchange attributes — use addEventListener inside the script block.
18. External CDN scripts (e.g. lottie-player): output a <script src="..."> tag BEFORE the section markup in section.php. Load it once using a flag if needed.

══ RTL & DIRECTION ══════════════════════════════════════════════════════════

19. If the source HTML uses dir="rtl" or lang="he"/"ar", handle direction in style.css only:
    .fp-sectionslug { direction: rtl; text-align: right; }
    Do NOT add dir or lang attributes to HTML elements in section.php.

══ CSS VARIABLES FROM SOURCE HTML ════════════════════════════════════════════

20. If the source HTML defines :root { --custom-var: value; }, move those variables into style.css scoped to the section BEM class: .fp-sectionslug { --custom-var: value; }
21. If a source CSS variable maps logically to a schema color/number field, expose it as a schema setting instead (so the user can edit it). Otherwise keep it as a local variable in style.css.
22. NEVER output a <style> tag inside section.php.

══ SCHEMA FIELD TYPES ═════════════════════════════════════════════════════════

text | textarea | richtext | image (returns URL string)
select  → requires options: [{value, label}]
checkbox | color
number  → supports min, max
range   → requires min, max, step
url

══ SCHEMA STRUCTURE ═══════════════════════════════════════════════════════════

return [
    'type'     => 'section-slug',       // kebab-case, unique
    'label'    => 'Human Label',
    'category' => 'content',            // content | layout | media | header | footer
    'contexts' => ['page'],             // page | header | footer | any
    'settings' => [
        ['id' => 'field_id', 'type' => 'text', 'label' => 'Label', 'default' => 'value'],
    ],
    'blocks' => [
        'allowed' => ['button'],        // block type slugs, or [] for no blocks
        'max'     => 3,
    ],
];

══ BLOCK INSTANCE STRUCTURE (in section.php) ══════════════════════════════════

foreach ($blocks as $block) {
    // $block['type']               — e.g. 'button'
    // $block['settings']['label']  — text
    // $block['settings']['url']    — URL
    // $block['settings']['style']  — 'primary' | 'outline'
}

══ OUTPUT FORMAT ══════════════════════════════════════════════════════════════

Return ONLY a single valid JSON object with these keys:
  slug        — kebab-case section type (e.g. "testimonials-grid")
  label       — human-readable name (e.g. "Testimonials Grid")
  schema_php  — the PHP return [...] array as a string (no <?php tag)
  section_php — the full section.php template as a string (no <?php tag)
  style_css   — CSS string (empty string "" if no styles needed)
  script_js   — JavaScript string (empty string "" if no JS needed)

IMPORTANT: If the section needs JavaScript, put ALL JS in script_js (it becomes script.js).
Do NOT put <script> tags inside section_php. The script_js file is loaded automatically.
In script_js, always scope to the section element using the section ID:
  (function() {
      var root = document.querySelector('[data-fp-id]'); // or use a unique class
      // your code here
  })();

No explanations. No markdown. No code fences. Only the JSON object.
PROMPT;
    }

    private function build_section_system_prompt( array $schema ): string {
        $fields_desc = implode( "\n", array_map( static function ( array $f ): string {
            $line = sprintf( '- %s (id: "%s", type: %s', $f['label'], $f['id'], $f['type'] );
            if ( isset( $f['options'] ) ) {
                $opts  = wp_list_pluck( $f['options'], 'value' );
                $line .= ', options: ' . implode( '|', $opts );
            }
            $line .= ', default: ' . ( is_scalar( $f['default'] ?? '' ) ? $f['default'] ?? '' : 'N/A' ) . ')';
            return $line;
        }, $schema['settings'] ?? [] ) );

        return "You are a content generator for a website section builder.\n"
            . "You generate JSON content values for a \"{$schema['label']}\" section.\n\n"
            . "Available fields:\n{$fields_desc}\n\n"
            . "Return ONLY valid JSON. No explanations. No markdown. No PHP code.\n"
            . "The JSON must have keys: \"settings\" (object with field values) and \"blocks\" (array).";
    }

    private function build_page_system_prompt( array $schemas ): string {
        $section_list = implode( "\n", array_map( static function ( array $s ): string {
            return sprintf( '- %s (type: "%s")', $s['label'], $s['type'] );
        }, $schemas ) );

        return "You are a page structure generator for a website builder.\n"
            . "You create ordered lists of page sections based on user descriptions.\n\n"
            . "Available section types:\n{$section_list}\n\n"
            . "Return ONLY valid JSON with key \"sections\" — an array of objects with:\n"
            . "  - type: one of the types listed above (required)\n"
            . "  - settings: object with any relevant text values\n"
            . "  - blocks: array (empty if the section has no blocks)\n\n"
            . "Use only the types listed above. No PHP code. No markdown. No explanations.";
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Whether AI is enabled and a key is present (e.g. for section file editor).
     */
    public function is_configured(): bool {
        return $this->is_available();
    }

    private function is_available(): bool {
        return $this->enabled && ! empty( $this->api_key );
    }

    private function check_rate_limit(): true|\WP_Error {
        $user_id   = get_current_user_id();
        $key       = 'fp_ai_rl_' . $user_id;
        $current   = (int) get_transient( $key );

        if ( $current >= 10 ) {
            return new \WP_Error( 'rate_limited', __( 'Too many AI requests. Please wait a moment and try again.', 'hero' ) );
        }

        set_transient( $key, $current + 1, MINUTE_IN_SECONDS );
        return true;
    }

    // ─── Encryption helpers ───────────────────────────────────────────────────

    /**
     * Save an API key encrypted using WP's auth salts as the encryption key.
     */
    public static function encrypt_api_key( string $plain ): string {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $plain );
        }
        $key = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY ), 0, 32 );
        $iv  = openssl_random_pseudo_bytes( 16 );
        $enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
        return base64_encode( $iv . '::' . $enc );
    }

    /**
     * Detect MIME type from a base64 data URI or raw base64 string.
     */
    private function detect_image_mime( string $image_data ): string {
        if ( preg_match( '/^data:([^;]+);base64,/', $image_data, $m ) ) {
            return $m[1];
        }
        return 'image/png'; // safe default
    }

    private function decrypt_api_key( string $stored ): string {
        if ( empty( $stored ) ) {
            return '';
        }
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return (string) base64_decode( $stored );
        }
        $decoded = base64_decode( $stored );
        if ( ! str_contains( $decoded, '::' ) ) {
            return $stored; // Legacy plain storage.
        }
        [ $iv, $enc ] = explode( '::', $decoded, 2 );
        $key = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY ), 0, 32 );
        return (string) openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );
    }
}
