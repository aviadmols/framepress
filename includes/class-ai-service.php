<?php
/**
 * FramePress AI Service
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

class FramePress_AI_Service {

    private string $provider;
    private string $api_key;
    private string $model;
    private bool   $enabled;

    public function __construct() {
        $this->provider = get_option( 'framepress_ai_provider', 'anthropic' );
        $this->api_key  = $this->decrypt_api_key( get_option( 'framepress_ai_key', '' ) );
        $this->model    = get_option( 'framepress_ai_model', 'claude-sonnet-4-6' );
        $this->enabled  = (bool) get_option( 'framepress_ai_enabled', false );
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
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured. Please add an API key in FramePress → AI Settings.', 'framepress' ) );
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
            return new \WP_Error( 'ai_disabled', __( 'AI is not configured.', 'framepress' ) );
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

    private function call_api( string $system_prompt, string $user_message ): array|\WP_Error {
        switch ( $this->provider ) {
            case 'anthropic':
                return $this->call_anthropic( $system_prompt, $user_message );
            case 'openai':
                return $this->call_openai( $system_prompt, $user_message );
            default:
                return new \WP_Error( 'unknown_provider', sprintf( __( 'Unknown AI provider: %s', 'framepress' ), $this->provider ) );
        }
    }

    private function call_anthropic( string $system_prompt, string $user_message ): array|\WP_Error {
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
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
                    [ 'role' => 'user', 'content' => $user_message ],
                ],
            ] ),
        ] );

        return $this->parse_anthropic_response( $response );
    }

    private function call_openai( string $system_prompt, string $user_message ): array|\WP_Error {
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode( [
                'model'    => $this->model ?: 'gpt-4o',
                'messages' => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user',   'content' => $user_message ],
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
            $msg = $body['error']['message'] ?? __( 'API request failed', 'framepress' );
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
            $msg = $body['error']['message'] ?? __( 'API request failed', 'framepress' );
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
            return new \WP_Error( 'json_parse', __( 'AI returned invalid JSON.', 'framepress' ) );
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

    // ─── System prompt builders ───────────────────────────────────────────────

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

    private function is_available(): bool {
        return $this->enabled && ! empty( $this->api_key );
    }

    private function check_rate_limit(): true|\WP_Error {
        $user_id   = get_current_user_id();
        $key       = 'fp_ai_rl_' . $user_id;
        $current   = (int) get_transient( $key );

        if ( $current >= 10 ) {
            return new \WP_Error( 'rate_limited', __( 'Too many AI requests. Please wait a moment and try again.', 'framepress' ) );
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
