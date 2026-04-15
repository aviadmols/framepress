export default function TextareaField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--textarea">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <textarea
                className="fp-field__textarea"
                value={ value }
                onChange={ e => onChange( e.target.value ) }
                rows={ field.rows || 5 }
                placeholder={ field.placeholder || '' }
            />
        </div>
    );
}
