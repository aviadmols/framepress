import { useState, useEffect } from 'react';

const REST  = () => window.heroData?.restUrl || '';
const NONCE = () => window.heroData?.nonce   || '';

const SOURCE_LABELS = { plugin: 'Core', theme: 'Theme', uploads: 'Custom' };
const SOURCE_COLORS = { plugin: '#6d7175', theme: '#8a5cf6', uploads: '#2c6ecb' };

const FILE_TABS = [ 'schema.php', 'section.php', 'style.css', 'script.js' ];

export default function SectionsManager() {
    const [ sections,  setSections  ] = useState( [] );
    const [ loading,   setLoading   ] = useState( true );
    const [ expanded,  setExpanded  ] = useState( null );   // type currently open
    const [ files,     setFiles     ] = useState( {} );     // { filename: content }
    const [ fileTab,   setFileTab   ] = useState( 'section.php' );
    const [ editable,  setEditable  ] = useState( false );
    const [ saving,    setSaving    ] = useState( false );
    const [ saveMsg,   setSaveMsg   ] = useState( '' );
    const [ saveError, setSaveError ] = useState( '' );
    const [ filesLoading, setFilesLoading ] = useState( false );

    useEffect( () => {
        fetch( REST() + '/sections-manager/list', { headers: { 'X-WP-Nonce': NONCE() } } )
            .then( r => r.json() )
            .then( data => { setSections( Array.isArray( data ) ? data : [] ); setLoading( false ); } )
            .catch( () => setLoading( false ) );
    }, [] );

    async function openSection( type ) {
        if ( expanded === type ) { setExpanded( null ); return; }
        setExpanded( type );
        setFiles( {} );
        setSaveMsg( '' );
        setSaveError( '' );
        setFilesLoading( true );

        try {
            const res  = await fetch( REST() + '/sections-manager/' + type + '/files', { headers: { 'X-WP-Nonce': NONCE() } } );
            const data = await res.json();
            setFiles( data.files || {} );
            setEditable( !! data.editable );
            // Pick a sensible default tab.
            const available = FILE_TABS.filter( f => data.files?.[ f ] !== undefined );
            setFileTab( available.includes( 'section.php' ) ? 'section.php' : ( available[0] || 'section.php' ) );
        } catch ( e ) {
            setSaveError( 'Could not load files.' );
        } finally {
            setFilesLoading( false );
        }
    }

    async function saveFiles() {
        if ( ! editable || ! expanded ) return;
        setSaving( true );
        setSaveMsg( '' );
        setSaveError( '' );

        try {
            const res  = await fetch( REST() + '/sections-manager/' + expanded + '/files', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
                body:    JSON.stringify( { files } ),
            } );
            const data = await res.json();
            if ( data.error ) { setSaveError( data.error ); return; }
            setSaveMsg( '✓ Saved' );
            setTimeout( () => setSaveMsg( '' ), 2500 );
        } catch ( e ) {
            setSaveError( 'Network error: ' + e.message );
        } finally {
            setSaving( false );
        }
    }

    const section = sections.find( s => s.type === expanded );
    const availableTabs = FILE_TABS.filter( f => files[ f ] !== undefined || ( expanded && editable ) );

    return (
        <div className="fp-sm">
            <div className="fp-sm__header">
                <h1 className="fp-sm__title">Sections</h1>
                <p className="fp-sm__subtitle">All registered section types. Click a section to view or edit its files.</p>
            </div>

            { loading && <p className="fp-sm__loading">Loading…</p> }

            { ! loading && sections.length === 0 && (
                <p className="fp-sm__empty">No sections found.</p>
            ) }

            <div className="fp-sm__list">
                { sections.map( s => (
                    <div key={ s.type } className={ `fp-sm__item ${ expanded === s.type ? 'open' : '' }` }>
                        { /* ── Row ── */ }
                        <div className="fp-sm__row" onClick={ () => openSection( s.type ) }>
                            <div className="fp-sm__row-left">
                                <span className="fp-sm__source-badge" style={ { background: SOURCE_COLORS[ s.source ] } }>
                                    { SOURCE_LABELS[ s.source ] || s.source }
                                </span>
                                <span className="fp-sm__label">{ s.label }</span>
                                <code className="fp-sm__slug">{ s.type }</code>
                            </div>
                            <div className="fp-sm__row-right">
                                { s.usage.length > 0 && (
                                    <span className="fp-sm__usage-count">Used in { s.usage.length } place{ s.usage.length > 1 ? 's' : '' }</span>
                                ) }
                                <span className="fp-sm__chevron">{ expanded === s.type ? '▲' : '▼' }</span>
                            </div>
                        </div>

                        { /* ── Expanded panel ── */ }
                        { expanded === s.type && (
                            <div className="fp-sm__panel">
                                { /* Usage list */ }
                                { section?.usage?.length > 0 && (
                                    <div className="fp-sm__usage">
                                        <span className="fp-sm__usage-label">Used in:</span>
                                        { section.usage.map( ( u, i ) => (
                                            <a key={ i } href={ u.edit_url } target="_blank" className="fp-sm__usage-link">
                                                { u.title }
                                            </a>
                                        ) ) }
                                    </div>
                                ) }

                                { filesLoading && <p style={ { padding: '12px', color: '#6d7175' } }>Loading files…</p> }

                                { ! filesLoading && (
                                    <>
                                        { /* File tabs */ }
                                        <div className="fp-sm__file-tabs">
                                            { FILE_TABS.filter( f => files[ f ] !== undefined ).map( f => (
                                                <button
                                                    key={ f }
                                                    className={ `fp-sm__file-tab ${ fileTab === f ? 'active' : '' }` }
                                                    onClick={ () => setFileTab( f ) }
                                                >
                                                    { f }
                                                </button>
                                            ) ) }
                                        </div>

                                        { /* Editor / viewer */ }
                                        { files[ fileTab ] !== undefined && (
                                            <textarea
                                                className={ `fp-sm__code-editor ${ ! editable ? 'readonly' : '' }` }
                                                value={ files[ fileTab ] ?? '' }
                                                readOnly={ ! editable }
                                                spellCheck={ false }
                                                onChange={ e => setFiles( prev => ( { ...prev, [ fileTab ]: e.target.value } ) ) }
                                            />
                                        ) }

                                        { ! editable && (
                                            <p className="fp-sm__readonly-note">
                                                { section?.source === 'plugin' ? 'Core sections are read-only. Override by placing the section in your child theme.' : 'This section is read-only.' }
                                            </p>
                                        ) }

                                        { saveError && <p className="fp-sm__error">{ saveError }</p> }

                                        { editable && (
                                            <div className="fp-sm__actions">
                                                <button className="fp-btn-save" onClick={ saveFiles } disabled={ saving }>
                                                    { saving ? 'Saving…' : saveMsg || 'Save Changes' }
                                                </button>
                                            </div>
                                        ) }
                                    </>
                                ) }
                            </div>
                        ) }
                    </div>
                ) ) }
            </div>
        </div>
    );
}
