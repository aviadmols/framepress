/**
 * Generate a short unique ID for section and block instances.
 * Uses crypto.randomUUID() when available (all modern browsers + Node 15+).
 */
export function generateId() {
    if ( typeof crypto !== 'undefined' && crypto.randomUUID ) {
        return crypto.randomUUID();
    }
    // Fallback for older environments.
    return 'fp-' + Math.random().toString( 36 ).slice( 2, 11 );
}
