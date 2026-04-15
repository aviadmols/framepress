export default function RangeField( { field, value, onChange } ) {
    const min  = field.min  ?? 0;
    const max  = field.max  ?? 100;
    const step = field.step ?? 1;

    return (
        <div className="fp-field fp-field--range">
            <label className="fp-field__label">
                { field.label }
                <span className="fp-field-range__value">{ value }</span>
            </label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            <div className="fp-field-range__row">
                <input
                    type="range"
                    className="fp-field-range__slider"
                    min={ min }
                    max={ max }
                    step={ step }
                    value={ value }
                    onChange={ e => onChange( parseFloat( e.target.value ) ) }
                />
                <input
                    type="number"
                    className="fp-field-range__number"
                    min={ min }
                    max={ max }
                    step={ step }
                    value={ value }
                    onChange={ e => onChange( parseFloat( e.target.value ) ) }
                />
            </div>
        </div>
    );
}
