import { useState, useRef } from 'react';

export default function ImageField( { field, value, onChange } ) {
    const [ showUrlInput, setShowUrlInput ] = useState( false );
    const [ urlDraft,     setUrlDraft     ] = useState( '' );
    const urlInputRef = useRef( null );

    const hasMedia = typeof window.wp !== 'undefined' && window.wp?.media;

    const openPicker = () => {
        if ( hasMedia ) {
            const frame = window.wp.media( {
                title:    field.label || 'Select Image',
                multiple: false,
                library:  { type: 'image' },
            } );
            frame.on( 'select', () => {
                const attachment = frame.state().get( 'selection' ).first().toJSON();
                onChange( attachment.url );
            } );
            frame.open();
        } else {
            setUrlDraft( value || '' );
            setShowUrlInput( true );
            setTimeout( () => urlInputRef.current?.focus(), 50 );
        }
    };

    const commitUrl = () => {
        onChange( urlDraft.trim() );
        setShowUrlInput( false );
    };

    return (
        <div className="fp-field fp-field--image">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }

            { value && ! showUrlInput ? (
                <div className="fp-field-image__card">
                    <img src={ value } alt="" />
                    <div className="fp-field-image__card-overlay">
                        <button type="button" className="fp-field-image__card-btn" onClick={ openPicker }>
                            Change
                        </button>
                        <button type="button" className="fp-field-image__card-btn fp-field-image__card-btn--danger" onClick={ () => onChange( '' ) }>
                            Remove
                        </button>
                    </div>
                </div>
            ) : ! showUrlInput ? (
                <div className="fp-field-image__actions">
                    <button type="button" className="fp-btn-secondary" onClick={ openPicker }>
                        Select Image
                    </button>
                </div>
            ) : null }

            { showUrlInput && (
                <div className="fp-field-image__url-input">
                    <input
                        ref={ urlInputRef }
                        type="url"
                        className="fp-field-image__url-text"
                        placeholder="https://example.com/image.jpg"
                        value={ urlDraft }
                        onChange={ e => setUrlDraft( e.target.value ) }
                        onKeyDown={ e => {
                            if ( e.key === 'Enter' ) commitUrl();
                            if ( e.key === 'Escape' ) setShowUrlInput( false );
                        } }
                    />
                    <button type="button" className="fp-btn-primary" onClick={ commitUrl }>OK</button>
                    <button type="button" className="fp-btn-secondary" onClick={ () => setShowUrlInput( false ) }>Cancel</button>
                </div>
            ) }

            { ! hasMedia && ! showUrlInput && ! value && (
                <button type="button" className="fp-field-image__url-link" onClick={ () => { setUrlDraft( '' ); setShowUrlInput( true ); } }>
                    or paste a URL
                </button>
            ) }
        </div>
    );
}
