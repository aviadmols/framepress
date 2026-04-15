import { useRef, useEffect, useCallback } from 'react';
import { useBuilder } from '../../context/BuilderContext';

const DEBOUNCE_MS = 400;

export default function PreviewIframe() {
    const { state, dispatch } = useBuilder();
    const iframeRef           = useRef( null );
    const timerRef            = useRef( null );

    // Notify parent when preview is ready.
    useEffect( () => {
        const onMessage = ( event ) => {
            if ( event.origin !== window.location.origin ) return;
            if ( event.data?.type === 'FRAMEPRESS_PREVIEW_READY' ) {
                dispatch( { type: 'SET_PREVIEW_READY', ready: true } );
            }
        };
        window.addEventListener( 'message', onMessage );
        return () => window.removeEventListener( 'message', onMessage );
    }, [] );

    // Push state changes to the iframe — debounced.
    const sendUpdate = useCallback( () => {
        if ( ! iframeRef.current?.contentWindow ) return;
        iframeRef.current.contentWindow.postMessage( {
            type:           'FRAMEPRESS_UPDATE',
            context:        state.context,
            sections:       state.sections,
            globalSettings: state.globalSettings,
        }, window.location.origin );
    }, [ state.sections, state.globalSettings, state.context ] );

    useEffect( () => {
        if ( ! state.previewReady ) return;
        clearTimeout( timerRef.current );
        timerRef.current = setTimeout( sendUpdate, DEBOUNCE_MS );
        return () => clearTimeout( timerRef.current );
    }, [ state.sections, state.globalSettings, state.previewReady ] );

    const previewUrl = window.framepressData?.previewUrl || '';

    const isMobile  = state.previewMode === 'mobile';
    const frameStyle = isMobile
        ? { width: '375px', margin: '0 auto', border: '8px solid #333', borderRadius: '24px', height: 'calc(100% - 40px)', marginTop: '20px' }
        : { width: '100%', height: '100%', border: 'none' };

    return (
        <div className={ `fp-preview-frame ${ isMobile ? 'fp-preview-frame--mobile' : '' }` }>
            <iframe
                ref={ iframeRef }
                src={ previewUrl }
                style={ frameStyle }
                title="FramePress Preview"
                sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
            />
        </div>
    );
}
