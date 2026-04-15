/**
 * FramePress REST API client.
 * Thin fetch wrapper — all calls include the WP REST nonce.
 */

const { restUrl, nonce } = window.framepressData || {};

async function request( path, options = {} ) {
    const url = restUrl.replace( /\/$/, '' ) + '/' + path.replace( /^\//, '' );
    const res  = await fetch( url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
            ...( options.headers || {} ),
        },
    } );
    if ( ! res.ok ) {
        const err = await res.json().catch( () => ( { error: res.statusText } ) );
        throw new Error( err.error || err.message || `HTTP ${ res.status }` );
    }
    return res.json();
}

export const api = {
    // ── Schemas ──────────────────────────────────────────────────────────────
    getSchemas:       ( context = 'page' ) => request( `schemas?context=${ context }` ),
    getAIExport:      ()                   => request( 'schemas/ai-export' ),
    getBlocks:        ()                   => request( 'blocks' ),

    // ── Page sections ─────────────────────────────────────────────────────────
    getPageSections:  ( id )               => request( `page/${ id }` ),
    savePageSections: ( id, sections )     => request( `page/${ id }`, { method: 'POST', body: JSON.stringify( { sections } ) } ),

    // ── Header / Footer ───────────────────────────────────────────────────────
    getHeader:        ()                   => request( 'header' ),
    saveHeader:       ( sections )         => request( 'header', { method: 'POST', body: JSON.stringify( { sections } ) } ),
    getFooter:        ()                   => request( 'footer' ),
    saveFooter:       ( sections )         => request( 'footer', { method: 'POST', body: JSON.stringify( { sections } ) } ),

    // ── Global settings ───────────────────────────────────────────────────────
    getGlobalSettings:  ()                 => request( 'global-settings' ),
    saveGlobalSettings: ( settings )       => request( 'global-settings', { method: 'POST', body: JSON.stringify( { settings } ) } ),

    // ── Live preview ──────────────────────────────────────────────────────────
    renderSection:    ( instance )         => request( 'render-section', { method: 'POST', body: JSON.stringify( { instance } ) } ),

    // ── ZIP manager ───────────────────────────────────────────────────────────
    uploadSection: ( formData ) => {
        const url = restUrl.replace( /\/$/, '' ) + '/sections/upload';
        return fetch( url, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            body: formData,
        } ).then( r => r.json() );
    },
    deleteSection: ( type, force = false ) => request( `sections/${ type }${ force ? '?force=1' : '' }`, { method: 'DELETE' } ),

    // ── AI ────────────────────────────────────────────────────────────────────
    aiGenerateSection: ( sectionType, prompt ) =>
        request( 'ai/generate-section', { method: 'POST', body: JSON.stringify( { section_type: sectionType, prompt } ) } ),
    aiGeneratePage: ( prompt ) =>
        request( 'ai/generate-page', { method: 'POST', body: JSON.stringify( { prompt } ) } ),
};
