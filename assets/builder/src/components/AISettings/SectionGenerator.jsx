import { useState } from 'react';

const REST   = () => window.framepressData?.restUrl || '';
const NONCE  = () => window.framepressData?.nonce   || '';

const MODES = [
    { id: 'description', label: '✦ Describe a section' },
    { id: 'html',        label: '< / > Paste HTML' },
];

const FILE_TABS = [
    { id: 'schema_php',  label: 'schema.php' },
    { id: 'section_php', label: 'section.php' },
    { id: 'style_css',   label: 'style.css' },
    { id: 'script_js',   label: 'script.js' },
];

export default function SectionGenerator() {
    const [ mode,        setMode        ] = useState( 'description' );
    const [ description, setDescription ] = useState( '' );
    const [ html,        setHtml        ] = useState( '' );
    const [ slug,        setSlug        ] = useState( '' );
    const [ loading,     setLoading     ] = useState( false );
    const [ result,      setResult      ] = useState( null );   // { slug, label, schema_php, section_php, style_css }
    const [ fileTab,     setFileTab     ] = useState( 'schema_php' );
    const [ error,       setError       ] = useState( '' );
    const [ installed,   setInstalled   ] = useState( false );
    const [ installing,  setInstalling  ] = useState( false );
    const [ fixing,       setFixing       ] = useState( false );
    const [ installError, setInstallError ] = useState( '' );
    const [ showPrompt,   setShowPrompt   ] = useState( false );
    const [ prompt,       setPrompt       ] = useState( '' );
    const [ promptLoading, setPromptLoading ] = useState( false );
    const [ copied,       setCopied       ] = useState( false );

    async function generate() {
        setLoading( true );
        setError( '' );
        setResult( null );
        setInstalled( false );
        setInstallError( '' );

        const body = mode === 'html'
            ? { mode: 'html', html, slug }
            : { mode: 'description', description };

        try {
            const res  = await fetch( REST() + '/ai/generate-section-files', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
                body:    JSON.stringify( body ),
            } );
            const data = await res.json();

            if ( data.error ) { setError( data.error ); return; }
            setResult( data );
            setFileTab( 'schema_php' );
        } catch ( e ) {
            setError( 'Network error: ' + e.message );
        } finally {
            setLoading( false );
        }
    }

    async function install() {
        if ( ! result ) return;
        setInstalling( true );
        setInstallError( '' );

        try {
            const res  = await fetch( REST() + '/ai/install-section', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
                body:    JSON.stringify( result ),
            } );
            const data = await res.json();

            if ( data.error ) { setInstallError( data.error ); return; }
            setInstalled( true );
        } catch ( e ) {
            setInstallError( 'Network error: ' + e.message );
        } finally {
            setInstalling( false );
        }
    }

    async function fixWithAI() {
        if ( ! result || ! installError ) return;
        setFixing( true );
        setInstallError( '' );

        try {
            const res  = await fetch( REST() + '/ai/fix-section-files', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
                body:    JSON.stringify( { ...result, error: installError } ),
            } );
            const data = await res.json();

            if ( data.error ) { setInstallError( data.error ); return; }
            setResult( data );
            setFileTab( 'section_php' );
        } catch ( e ) {
            setInstallError( 'Network error: ' + e.message );
        } finally {
            setFixing( false );
        }
    }

    async function togglePrompt() {
        if ( showPrompt ) { setShowPrompt( false ); return; }
        setShowPrompt( true );
        if ( prompt ) return;
        setPromptLoading( true );
        try {
            const res  = await fetch( REST() + '/ai/section-files-prompt', { headers: { 'X-WP-Nonce': NONCE() } } );
            const data = await res.json();
            setPrompt( data.prompt || '' );
        } catch ( e ) {
            setPrompt( '// Could not load prompt: ' + e.message );
        } finally {
            setPromptLoading( false );
        }
    }

    function copyPrompt() {
        navigator.clipboard.writeText( prompt ).then( () => {
            setCopied( true );
            setTimeout( () => setCopied( false ), 2000 );
        } );
    }

    const inputReady = mode === 'html' ? html.trim().length > 0 : description.trim().length > 10;

    return (
        <div className="fp-sg">
            {/* Mode toggle */}
            <div className="fp-sg__mode-row">
                { MODES.map( m => (
                    <button
                        key={ m.id }
                        className={ `fp-sg__mode-btn ${ mode === m.id ? 'active' : '' }` }
                        onClick={ () => { setMode( m.id ); setResult( null ); setError( '' ); setInstalled( false ); } }
                    >
                        { m.label }
                    </button>
                ) ) }
            </div>

            {/* Input area */}
            <div className="fp-sg__input-area">
                { mode === 'description' ? (
                    <>
                        <label className="fp-sg__label">Describe the section you want to create</label>
                        <textarea
                            className="fp-sg__textarea"
                            rows={ 5 }
                            placeholder={ 'e.g. A testimonials grid with 3 columns. Each card has a quote, author name, author title, and avatar image. Section has a heading and subtitle above the grid.' }
                            value={ description }
                            onChange={ e => setDescription( e.target.value ) }
                        />
                    </>
                ) : (
                    <>
                        <label className="fp-sg__label">Paste your HTML — the AI will extract all variable parts into schema fields</label>
                        <div className="fp-sg__slug-row">
                            <input
                                className="fp-sg__slug-input"
                                type="text"
                                placeholder="section-slug (optional)"
                                value={ slug }
                                onChange={ e => setSlug( e.target.value ) }
                            />
                        </div>
                        <textarea
                            className="fp-sg__textarea fp-sg__textarea--code"
                            rows={ 12 }
                            placeholder={ '<section class="hero">\n  <h1>Big Headline</h1>\n  <p>Supporting text...</p>\n  <a href="/contact" class="btn btn-primary">Get Started</a>\n</section>' }
                            value={ html }
                            onChange={ e => setHtml( e.target.value ) }
                        />
                    </>
                ) }

                <button
                    className="fp-sg__generate-btn"
                    onClick={ generate }
                    disabled={ loading || ! inputReady }
                >
                    { loading ? 'Generating…' : '✦ Generate Section' }
                </button>
            </div>

            { error && <p className="fp-sg__error">{ error }</p> }

            {/* System prompt panel */}
            <div className="fp-sg__prompt-section">
                <button className="fp-sg__prompt-toggle" onClick={ togglePrompt }>
                    { showPrompt ? '▲ Hide system prompt' : '▼ View system prompt for external AI' }
                </button>
                { showPrompt && (
                    <div className="fp-sg__prompt-panel">
                        <div className="fp-sg__prompt-toolbar">
                            <span className="fp-sg__prompt-hint">Copy this prompt into ChatGPT, Claude, Gemini, etc. Then add your description or HTML at the end.</span>
                            <button className="fp-sg__copy-btn" onClick={ copyPrompt } disabled={ ! prompt }>
                                { copied ? '✓ Copied!' : 'Copy' }
                            </button>
                        </div>
                        { promptLoading
                            ? <p className="fp-sg__prompt-loading">Loading…</p>
                            : <textarea className="fp-sg__prompt-text" readOnly value={ prompt } spellCheck={ false } />
                        }
                    </div>
                ) }
            </div>

            {/* Result preview */}
            { result && (
                <div className="fp-sg__result">
                    <div className="fp-sg__result-header">
                        <div>
                            <span className="fp-sg__result-label">Generated:</span>
                            <strong className="fp-sg__result-name">{ result.label }</strong>
                            <code className="fp-sg__result-slug">{ result.slug }</code>
                        </div>
                        { installed ? (
                            <span className="fp-sg__installed-badge">✓ Installed — refresh the builder to use it</span>
                        ) : (
                            <button
                                className="fp-sg__install-btn"
                                onClick={ install }
                                disabled={ installing || fixing }
                            >
                                { installing ? 'Installing…' : '↓ Install Section' }
                            </button>
                        ) }
                    </div>

                    { installError && (
                        <div className="fp-sg__install-error">
                            <span className="fp-sg__install-error-msg">{ installError }</span>
                            <button
                                className="fp-sg__fix-btn"
                                onClick={ fixWithAI }
                                disabled={ fixing }
                            >
                                { fixing ? 'Fixing…' : '✦ Fix with AI' }
                            </button>
                        </div>
                    ) }

                    {/* File tabs */}
                    <div className="fp-sg__file-tabs">
                        { FILE_TABS.map( t => (
                            <button
                                key={ t.id }
                                className={ `fp-sg__file-tab ${ fileTab === t.id ? 'active' : '' }` }
                                onClick={ () => setFileTab( t.id ) }
                            >
                                { t.label }
                            </button>
                        ) ) }
                    </div>
                    <pre className="fp-sg__code">{ result[ fileTab ] || '/* empty */' }</pre>
                </div>
            ) }
        </div>
    );
}
