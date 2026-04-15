export default function CheckboxField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--checkbox">
            <label className="fp-field__checkbox-label">
                <input
                    type="checkbox"
                    checked={ !! value }
                    onChange={ e => onChange( e.target.checked ) }
                />
                <span>{ field.label }</span>
            </label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
        </div>
    );
}
