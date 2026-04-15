import TextField      from './TextField';
import TextareaField  from './TextareaField';
import RichTextField  from './RichTextField';
import ImageField     from './ImageField';
import SelectField    from './SelectField';
import CheckboxField  from './CheckboxField';
import ColorField     from './ColorField';
import NumberField    from './NumberField';
import RangeField     from './RangeField';
import UrlField       from './UrlField';
import GoogleFontField from './GoogleFontField';

const FIELD_COMPONENTS = {
    text:      TextField,
    textarea:  TextareaField,
    richtext:  RichTextField,
    image:     ImageField,
    select:    SelectField,
    checkbox:  CheckboxField,
    color:     ColorField,
    number:    NumberField,
    range:     RangeField,
    url:       UrlField,
};

export default function FieldRenderer( { field, value, onChange, globalSettings, googleFonts, onGlobalBatchChange } ) {
    if ( field.hidden || field.type === 'hidden' ) {
        return null;
    }
    if ( field.type === 'google_font' ) {
        return (
            <GoogleFontField
                field={ field }
                value={ value !== undefined ? value : ( field.default ?? '' ) }
                globalSettings={ globalSettings }
                googleFonts={ googleFonts || [] }
                onBatchChange={ onGlobalBatchChange || ( () => {} ) }
            />
        );
    }
    const Component = FIELD_COMPONENTS[ field.type ];
    if ( ! Component ) {
        return <div className="fp-field fp-field--unknown">Unknown field type: { field.type }</div>;
    }
    const resolvedValue = value !== undefined ? value : ( field.default ?? '' );
    return <Component field={ field } value={ resolvedValue } onChange={ onChange } />;
}
