export default function ImageField( { field, value, onChange } ) {
    const openPicker = () => {
        if ( typeof window.wp === 'undefined' || ! window.wp.media ) {
            // Fallback: prompt for URL.
            const url = window.prompt( 'Enter image URL', value || '' );
            if ( url !== null ) onChange( url );
            return;
        }

        const frame = window.wp.media( {
            title:    field.label,
            multiple: false,
            library:  { type: 'image' },
        } );

        frame.on( 'select', () => {
            const attachment = frame.state().get( 'selection' ).first().toJSON();
            onChange( attachment.url );
        } );

        frame.open();
    };

    return (
        <div className="fp-field fp-field--image">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            { value && (
                <div className="fp-field-image__preview">
                    <img src={ value } alt="" />
                </div>
            ) }
            <div className="fp-field-image__actions">
                <button type="button" className="fp-btn-secondary" onClick={ openPicker }>
                    { value ? 'Change Image' : 'Select Image' }
                </button>
                { value && (
                    <button type="button" className="fp-btn-danger" onClick={ () => onChange( '' ) }>
                        Remove
                    </button>
                ) }
            </div>
        </div>
    );
}
