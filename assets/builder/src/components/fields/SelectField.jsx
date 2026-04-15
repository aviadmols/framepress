export default function SelectField( { field, value, onChange } ) {
    return (
        <div className="fp-field fp-field--select">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <select className="fp-field__select" value={ value } onChange={ e => onChange( e.target.value ) }>
                { ( field.options || [] ).map( opt => (
                    <option key={ opt.value } value={ opt.value }>{ opt.label }</option>
                ) ) }
            </select>
        </div>
    );
}
