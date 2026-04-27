import { useRef, useEffect, useCallback } from 'react';
import { useBuilder } from '../../context/BuilderContext';

const DEBOUNCE_MS = 400;

export default function PreviewIframe() {
    const { state, dispatch } = useBuilder();
    const iframeRef           = useRef( null );
    const timerRef            = useRef( null );

    // Send a postMessage to the iframe
    const sendToIframe = useCallback( ( msg ) => {
        if ( iframeRef.current?.contentWindow ) {
            iframeRef.current.contentWindow.postMessage( msg, window.location.origin );
        }
    }, [] );

    // Listen for messages FROM the iframe
    useEffect( () => {
        const onMessage = ( event ) => {
            if ( event.origin !== window.location.origin ) return;
            const msg = event.data;
            if ( ! msg?.type ) return;

            if ( msg.type === 'HERO_PREVIEW_READY' ) {
                dispatch( { type: 'SET_PREVIEW_READY', ready: true } );
            }

            if ( msg.type === 'HERO_SECTION_CLICK' ) {
                dispatch( { type: 'SELECT_SECTION', id: msg.sectionId } );
            }

            if ( msg.type === 'HERO_REORDER' ) {
                const { orderedIds } = msg;
                if ( Array.isArray( orderedIds ) ) {
                    const reordered = orderedIds
                        .map( id => state.sections.find( s => s.id === id ) )
                        .filter( Boolean );
                    if ( reordered.length ) {
                        dispatch( { type: 'REORDER_SECTIONS', sections: reordered } );
                    }
                }
            }
        };
        window.addEventListener( 'message', onMessage );
        return () => window.removeEventListener( 'message', onMessage );
    }, [ state.sections ] );

    // Forward draft preview HTML from CodeEditorDrawer to the iframe
    useEffect( () => {
        const onDraft = ( e ) => {
            sendToIframe( {
                type:        'HERO_DRAFT_PREVIEW',
                sectionType: e.detail.sectionType,
                html:        e.detail.html,
            } );
        };
        window.addEventListener( 'hero:draft-preview', onDraft );
        return () => window.removeEventListener( 'hero:draft-preview', onDraft );
    }, [ sendToIframe ] );

    // Push state changes to the iframe — debounced
    const sendUpdate = useCallback( () => {
        sendToIframe( {
            type:           'HERO_UPDATE',
            context:        state.context,
            sections:       state.sections,
            globalSettings: state.globalSettings,
        } );
    }, [ state.sections, state.globalSettings, state.context, sendToIframe ] );

    useEffect( () => {
        if ( ! state.previewReady ) return;
        clearTimeout( timerRef.current );
        timerRef.current = setTimeout( sendUpdate, DEBOUNCE_MS );
        return () => clearTimeout( timerRef.current );
    }, [ state.sections, state.globalSettings, state.previewReady ] );

    // Sync selected section highlight to preview
    useEffect( () => {
        if ( ! state.previewReady ) return;
        sendToIframe( { type: 'HERO_SELECT_SECTION', sectionId: state.selectedSectionId || null } );
    }, [ state.selectedSectionId, state.previewReady, sendToIframe ] );

    const previewUrl = window.heroData?.previewUrl || '';
    const isMobile   = state.previewMode === 'mobile';

    return (
        <div className={ `fp-preview-frame ${ isMobile ? 'fp-preview-frame--mobile' : '' }` }>
            <iframe
                ref={ iframeRef }
                src={ previewUrl }
                style={ isMobile
                    ? { width: '375px', margin: '0 auto', border: '8px solid #333', borderRadius: '24px', height: 'calc(100% - 40px)', marginTop: '20px' }
                    : { width: '100%', height: '100%', border: 'none' }
                }
                title="HERO Preview"
                sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
            />
        </div>
    );
}
