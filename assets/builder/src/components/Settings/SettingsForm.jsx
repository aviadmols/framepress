import FieldRenderer from '../fields/FieldRenderer';
import { useBuilder } from '../../context/BuilderContext';

export default function SettingsForm( { section, schema, tab = 'settings' } ) {
    const { dispatch } = useBuilder();

    if ( ! schema?.settings?.length ) {
        return <p className="fp-settings-empty">No settings for this section.</p>;
    }

    const fields = schema.settings.filter( field => ( field.tab || 'settings' ) === tab );

    if ( ! fields.length ) {
        return <p className="fp-settings-empty">No { tab } settings for this section.</p>;
    }

    return (
        <div className="fp-settings-form">
            { fields.map( field => (
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
