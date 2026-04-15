export default function TextField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--text">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <input
                type="text"
                className="fp-field__input"
                value={ value }
                onChange={ e => onChange( e.target.value ) }
                placeholder={ field.placeholder || '' }
            />
        </div>
    );
}
