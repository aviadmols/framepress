export default function UrlField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--url">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <input
                type="url"
                className="fp-field__input"
                value={ value }
                onChange={ e => onChange( e.target.value ) }
                placeholder="https://"
            />
        </div>
    );
}
