import FieldRenderer from '../fields/FieldRenderer';
import { useBuilder } from '../../context/BuilderContext';

export default function SettingsForm( { section, schema } ) {
    const { dispatch } = useBuilder();

    if ( ! schema?.settings?.length ) {
        return <p className="fp-settings-empty">No settings for this section.</p>;
    }

    return (
        <div className="fp-settings-form">
            { schema.settings.map( field => (
                <FieldRenderer
                    key={ field.id }
                    field={ field }
                    value={ section.settings[ field.id ] }
                    onChange={ ( value ) => dispatch( {
                        type:      'UPDATE_SECTION_SETTING',
                        sectionId: section.id,
                        key:       field.id,
                        value,
                    } ) }
                />
            ) ) }
        </div>
    );
}
