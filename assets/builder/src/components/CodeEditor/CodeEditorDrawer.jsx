import { useState, useEffect, useRef, useCallback } from 'react';
import { useBuilder } from '../../context/BuilderContext';
import { api }        from '../../api/hero-api';

const FILE_TABS = [
    { id: 'style_css',   label: 'style.css'   },
    { id: 'section_php', label: 'section.php' },
    { id: 'script_js',   label: 'script.js'   },
];

const STORAGE_KEY = 'hero_drawer_height';
const DEFAULT_H   = 340;

export default function CodeEditorDrawer() {
    const { state, dispatch } = useBuilder();
    const sectionType = state.editingCodeSectionType;

    const [ files,      setFiles      ] = useState( {} );
    const [ activeTab,  setActiveTab  ] = useState( 'style_css' );
    const [ status,     setStatus     ] = useState( '' ); // '' | 'saving' | 'saved' | 'error'
    const [ editable,   setEditable   ] = useState( true );
    const [ drawerH,    setDrawerH    ] = useState( () => parseInt( localStorage.getItem( STORAGE_KEY ) ) || DEFAULT_H );

    const debounceRef  = useRef( null );
    const resizeRef    = useRef( null );
    const startRef     = useRef( null );

    // Load files when sectionType changes
    useEffect( () => {
        if ( ! sectionType ) return;
        setFiles( {} );
        setStatus( '' );
        api.getSectionFiles( sectionType )
            .then( data => {
                setFiles( {
                    style_css:   data.files?.['style.css']   ?? '',
                    section_php: data.files?.['section.php'] ?? '',
                    script_js:   data.files?.['script.js']   ?? '',
                } );
                setEditable( data.editable !== false );
            } )
            .catch( () => setStatus( 'error' ) );
    }, [ sectionType ] );

    // Apply drawer height to CSS variable
    useEffect( () => {
        document.documentElement.style.setProperty( '--fp-drawer-h', drawerH + 'px' );
    }, [ drawerH ] );

    const sendDraftPreview = useCallback( ( currentFiles ) => {
        if ( ! sectionType ) return;
        api.draftPreview( sectionType, {
            'style.css':   currentFiles.style_css   ?? '',
            'section.php': currentFiles.section_php ?? '',
        } ).then( data => {
            if ( data.html !== undefined ) {
                window.dispatchEvent( new CustomEvent( 'hero:draft-preview', {
                    detail: { sectionType, html: data.html },
                } ) );
            }
        } ).catch( () => {} );
    }, [ sectionType ] );

    const handleChange = ( tabId, value ) => {
        const next = { ...files, [ tabId ]: value };
        setFiles( next );
        clearTimeout( debounceRef.current );
        debounceRef.current = setTimeout( () => sendDraftPreview( next ), 800 );
    };

    const save = useCallback( async () => {
        if ( ! sectionType || ! editable ) return;
        setStatus( 'saving' );
        try {
            await api.saveSectionFiles( sectionType, {
                'style.css':   files.style_css   ?? '',
                'section.php': files.section_php ?? '',
                'script.js':   files.script_js   ?? '',
            } );
            setStatus( 'saved' );
            setTimeout( () => setStatus( '' ), 3000 );
        } catch ( e ) {
            setStatus( 'error' );
        }
    }, [ sectionType, editable, files ] );

    // Ctrl+S to save
    useEffect( () => {
        const onKey = ( e ) => {
            if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' && sectionType ) {
                e.preventDefault();
                save();
            }
        };
        window.addEventListener( 'keydown', onKey );
        return () => window.removeEventListener( 'keydown', onKey );
    }, [ save, sectionType ] );

    // Resize handle
    const onResizeMouseDown = ( e ) => {
        e.preventDefault();
        startRef.current = { y: e.clientY, h: drawerH };
        const onMove = ( ev ) => {
            const delta = startRef.current.y - ev.clientY;
            const newH  = Math.max( 120, Math.min( window.innerHeight - 80, startRef.current.h + delta ) );
            setDrawerH( newH );
            localStorage.setItem( STORAGE_KEY, newH );
        };
        const onUp = () => {
            window.removeEventListener( 'mousemove', onMove );
            window.removeEventListener( 'mouseup', onUp );
        };
        window.addEventListener( 'mousemove', onMove );
        window.addEventListener( 'mouseup', onUp );
    };

    if ( ! sectionType ) return null;

    const statusClass = status ? `fp-code-drawer__status--${ status }` : '';
    const statusText  = status === 'saving' ? 'Saving…' : status === 'saved' ? 'Saved ✓' : status === 'error' ? 'Error saving' : editable ? 'Ctrl+S to save' : 'Read-only';

    return (
        <div className="fp-code-drawer" style={ { height: drawerH + 'px' } }>
            <div className="fp-code-drawer__resize" ref={ resizeRef } onMouseDown={ onResizeMouseDown } />

            <div className="fp-code-drawer__header">
                <div className="fp-code-drawer__section-label">
                    Edit
                    <span className="fp-code-drawer__section-type">{ sectionType }</span>
                </div>

                <div className="fp-code-drawer__tabs">
                    { FILE_TABS.map( tab => (
                        <button
                            key={ tab.id }
                            className={ `fp-code-drawer__tab${ activeTab === tab.id ? ' active' : '' }` }
                            onClick={ () => setActiveTab( tab.id ) }
                        >
                            { tab.label }
                        </button>
                    ) ) }
                </div>

                <div className="fp-code-drawer__actions">
                    <span className={ `fp-code-drawer__status ${ statusClass }` }>{ statusText }</span>
                    { editable && (
                        <button
                            className="fp-code-drawer__save-btn"
                            onClick={ save }
                            disabled={ status === 'saving' }
                        >
                            Save
                        </button>
                    ) }
                    <button
                        className="fp-code-drawer__close-btn"
                        onClick={ () => dispatch( { type: 'CLOSE_CODE_EDITOR' } ) }
                        title="Close"
                    >
                        ✕
                    </button>
                </div>
            </div>

            { ! editable && (
                <p className="fp-code-drawer__readonly-note">
                    This section is built-in (plugin or theme) and is read-only. Upload a custom version to edit it.
                </p>
            ) }

            <div className="fp-code-drawer__body">
                { Object.keys( files ).length === 0 ? (
                    <p style={ { color: '#6d7175', padding: '16px', fontSize: '13px' } }>Loading…</p>
                ) : (
                    <textarea
                        className="fp-code-drawer__editor"
                        value={ files[ activeTab ] ?? '' }
                        onChange={ e => handleChange( activeTab, e.target.value ) }
                        readOnly={ ! editable }
                        spellCheck={ false }
                        onKeyDown={ e => {
                            if ( e.key === 'Tab' ) {
                                e.preventDefault();
                                const ta    = e.target;
                                const start = ta.selectionStart;
                                const end   = ta.selectionEnd;
                                const val   = ta.value;
                                const next  = val.substring( 0, start ) + '    ' + val.substring( end );
                                handleChange( activeTab, next );
                                requestAnimationFrame( () => {
                                    ta.selectionStart = ta.selectionEnd = start + 4;
                                } );
                            }
                        } }
                    />
                ) }
            </div>
        </div>
    );
}
