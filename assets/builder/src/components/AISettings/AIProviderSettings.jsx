import { useState, useEffect } from 'react';

const REST  = () => window.heroData?.restUrl || '';
const NONCE = () => window.heroData?.nonce   || '';

export default function AIProviderSettings() {
    const [ provider, setProvider ] = useState( 'anthropic' );
    const [ apiKey,   setApiKey   ] = useState( '' );
    const [ model,    setModel    ] = useState( '' );
    const [ enabled,  setEnabled  ] = useState( false );
    const [ saving,   setSaving   ] = useState( false );
    const [ saved,    setSaved    ] = useState( false );
    const [ error,    setError    ] = useState( '' );

    useEffect( () => {
        fetch( REST() + '/ai/settings', { headers: { 'X-WP-Nonce': NONCE() } } )
            .then( r => r.json() )
            .then( data => {
                if ( data.provider ) setProvider( data.provider );
                if ( data.model )    setModel( data.model );
                setEnabled( !! data.enabled );
                if ( data.has_key )  setApiKey( '••••••••' ); // masked placeholder
            } )
            .catch( () => {} );
    }, [] );

    async function save() {
        setSaving( true );
        setError( '' );
        setSaved( false );

        try {
            const res  = await fetch( REST() + '/ai/settings', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
                body:    JSON.stringify( { provider, api_key: apiKey.includes('•') ? '' : apiKey, model, enabled } ),
            } );
            const data = await res.json();
            if ( data.error ) { setError( data.error ); return; }
            setSaved( true );
        } catch ( e ) {
            setError( 'Network error: ' + e.message );
        } finally {
            setSaving( false );
        }
    }

    const modelPlaceholder = provider === 'anthropic' ? 'claude-sonnet-4-6' : 'gpt-4o';

    return (
        <div className="fp-ai-settings-form">
            <div className="fp-field">
                <label className="fp-field__label">Enable AI features</label>
                <label className="fp-field__checkbox-label">
                    <input type="checkbox" checked={ enabled } onChange={ e => setEnabled( e.target.checked ) } />
                    Enable AI section generation in the builder
                </label>
            </div>

            <div className="fp-field">
                <label className="fp-field__label">AI Provider</label>
                <select className="fp-field__select" value={ provider } onChange={ e => setProvider( e.target.value ) }>
                    <option value="anthropic">Anthropic (Claude)</option>
                    <option value="openai">OpenAI (GPT)</option>
                </select>
            </div>

            <div className="fp-field">
                <label className="fp-field__label">API Key</label>
                <input
                    className="fp-field__input"
                    type="password"
                    placeholder="Paste your API key…"
                    value={ apiKey }
                    onChange={ e => setApiKey( e.target.value ) }
                    autoComplete="off"
                />
                <p className="fp-field__description">Stored encrypted on the server. Leave blank to keep existing key.</p>
            </div>

            <div className="fp-field">
                <label className="fp-field__label">Model</label>
                <input
                    className="fp-field__input"
                    type="text"
                    placeholder={ modelPlaceholder }
                    value={ model }
                    onChange={ e => setModel( e.target.value ) }
                />
            </div>

            { error && <p className="fp-sg__error">{ error }</p> }

            <button className="fp-btn-save" style={ { marginTop: 8 } } onClick={ save } disabled={ saving }>
                { saving ? 'Saving…' : saved ? '✓ Saved' : 'Save Settings' }
            </button>
        </div>
    );
}
