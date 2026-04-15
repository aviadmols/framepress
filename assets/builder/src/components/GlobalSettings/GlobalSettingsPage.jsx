import { useState }    from 'react';
import { useBuilder }  from '../../context/BuilderContext';
import FieldRenderer   from '../fields/FieldRenderer';
import SaveBar         from '../SaveBar';

export default function GlobalSettingsPage() {
    const { state, dispatch } = useBuilder();
    const schema     = state.globalSchema;
    const settings   = state.globalSettings;
    const [ activeGroup, setActiveGroup ] = useState( null );

    if ( ! schema ) {
        return (
            <div className="fp-gs-page">
                <div className="fp-gs-loading">Loading global settings…</div>
            </div>
        );
    }

    const groups   = Object.entries( schema.groups || {} );
    const firstId  = groups[0]?.[0] ?? null;
    const groupId  = activeGroup ?? firstId;

    const groupFields = schema.groups?.[ groupId ]?.settings ?? [];

    return (
        <div className="fp-gs-page">
            <aside className="fp-gs-sidebar">
                <div className="fp-gs-sidebar__header">Global Settings</div>
                <nav className="fp-gs-nav">
                    { groups.map( ( [ id, group ] ) => (
                        <button
                            key={ id }
                            className={ `fp-gs-nav__item ${ groupId === id ? 'active' : '' }` }
                            onClick={ () => setActiveGroup( id ) }
                        >
                            { group.label }
                        </button>
                    ) ) }
                </nav>
            </aside>

            <div className="fp-gs-content">
                <div className="fp-gs-content__header">
                    <h2 className="fp-gs-content__title">{ schema.groups?.[ groupId ]?.label }</h2>
                </div>
                <div className="fp-gs-content__body">
                    { groupFields.length === 0 && (
                        <p className="fp-settings-empty">No settings in this group.</p>
                    ) }
                    { groupFields.map( field => (
                        <FieldRenderer
                            key={ field.id }
                            field={ field }
                            value={ settings[ field.id ] ?? field.default ?? '' }
                            onChange={ value => dispatch( {
                                type:  'UPDATE_GLOBAL_SETTING',
                                key:   field.id,
                                value,
                            } ) }
                        />
                    ) ) }
                </div>
            </div>

            <SaveBar />
        </div>
    );
}
