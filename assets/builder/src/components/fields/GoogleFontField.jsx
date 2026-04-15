import { useMemo, useState } from 'react';

function buildPresetUrl( family ) {
    const q = family.replace( / /g, '+' );
    return `https://fonts.googleapis.com/css2?family=${ q }:wght@400;600;700&display=swap`;
}

export default function GoogleFontField( { field, value, globalSettings = {}, googleFonts = [], onBatchChange } ) {
    const pick           = value ?? '';
    const [ search, setSearch ] = useState( '' );
    const customUrl      = globalSettings.google_fonts_url || '';

    const filtered = useMemo( () => {
        if ( ! search.trim() ) {
            return googleFonts;
        }
        const t = search.toLowerCase();
        return googleFonts.filter( f =>
            ( f.label || '' ).toLowerCase().includes( t ) ||
            ( f.family || '' ).toLowerCase().includes( t )
        );
    }, [ googleFonts, search ] );

    const onPickChange = ( e ) => {
        const slug = e.target.value;
        if ( slug === '' ) {
            onBatchChange( {
                google_font_pick: '',
                google_fonts_url: '',
                font_body:        'sans-serif',
                font_heading:     'sans-serif',
            } );
            return;
        }
        if ( slug === '__custom__' ) {
            onBatchChange( {
                google_font_pick: '__custom__',
                google_fonts_url: customUrl,
            } );
            return;
        }
        const font = googleFonts.find( f => f.slug === slug );
        if ( ! font ) {
            return;
        }
        const url   = buildPresetUrl( font.family );
        const stack = `${ font.family }, sans-serif`;
        onBatchChange( {
            google_font_pick: slug,
            google_fonts_url: url,
            font_body:        stack,
            font_heading:     stack,
        } );
    };

    const onCustomUrlChange = ( e ) => {
        onBatchChange( { google_fonts_url: e.target.value.trim() } );
    };

    return (
        <div className="fp-field fp-field--google-font">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }

            <input
                type="search"
                className="fp-field__input fp-field-google-font__search"
                placeholder="Search fonts…"
                value={ search }
                onChange={ e => setSearch( e.target.value ) }
                aria-label="Search fonts"
            />

            <select
                className="fp-field__select"
                value={ pick }
                onChange={ onPickChange }
            >
                <option value="">None (system fonts)</option>
                { filtered.map( f => (
                    <option key={ f.slug } value={ f.slug }>{ f.label }</option>
                ) ) }
                <option value="__custom__">Custom URL…</option>
            </select>

            { pick === '__custom__' && (
                <div className="fp-field-google-font__custom">
                    <label className="fp-field__label fp-field-google-font__custom-label">Google Fonts CSS URL</label>
                    <input
                        type="url"
                        className="fp-field__input"
                        placeholder="https://fonts.googleapis.com/css2?family=…"
                        value={ customUrl }
                        onChange={ onCustomUrlChange }
                    />
                </div>
            ) }
        </div>
    );
}
