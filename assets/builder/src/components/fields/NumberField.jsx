export default function NumberField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--number">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <input
                type="number"
                className="fp-field__input"
                value={ value }
                min={ field.min }
                max={ field.max }
                step={ field.step || 1 }
                onChange={ e => onChange( parseFloat( e.target.value ) ) }
            />
        </div>
    );
}
