export default function ColorField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--color">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <div className="fp-field-color__row">
                <input
                    type="color"
                    className="fp-field-color__swatch"
                    value={ value || '#000000' }
                    onChange={ e => onChange( e.target.value ) }
                />
                <input
                    type="text"
                    className="fp-field-color__hex"
                    value={ value || '' }
                    onChange={ e => onChange( e.target.value ) }
                    placeholder="#000000"
                    maxLength={ 7 }
                />
            </div>
        </div>
    );
}
