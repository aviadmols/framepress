import { useState }    from 'react';
import { useBuilder }   from '../../context/BuilderContext';
import SettingsForm     from './SettingsForm';
import BlocksEditor     from './BlocksEditor';
import CustomCssEditor  from './CustomCssEditor';

const TABS = [
    { id: 'settings', label: 'Settings' },
    { id: 'style',    label: 'Style' },
    { id: 'blocks',   label: 'Blocks' },
    { id: 'css',      label: 'CSS' },
];

export default function SettingsPanel() {
    const { state, dispatch } = useBuilder();
    const [ activeTab, setActiveTab ] = useState( 'settings' );

    const section = state.sections.find( s => s.id === state.selectedSectionId );
    const schema  = state.schemas.find( s => s.type === section?.type );

    if ( ! section ) {
        return (
            <aside className="fp-settings-panel fp-settings-panel--empty">
                <p>Select a section to edit its settings.</p>
            </aside>
        );
    }

    return (
        <aside className="fp-settings-panel">
            <div className="fp-settings-panel__header">
                <span className="fp-settings-panel__title">{ schema?.label || section.type }</span>
                <button
                    className="fp-icon-btn"
                    onClick={ () => dispatch( { type: 'DESELECT' } ) }
                    title="Close"
                >✕</button>
            </div>

            <div className="fp-settings-panel__tabs">
                { TABS.map( tab => (
                    <button
                        key={ tab.id }
                        className={ `fp-settings-panel__tab ${ activeTab === tab.id ? 'active' : '' }` }
                        onClick={ () => setActiveTab( tab.id ) }
                    >
                        { tab.label }
                    </button>
                ) ) }
            </div>

            <div className="fp-settings-panel__body">
                { activeTab === 'settings' && <SettingsForm section={ section } schema={ schema } tab="settings" /> }
                { activeTab === 'style'    && <SettingsForm section={ section } schema={ schema } tab="style" /> }
                { activeTab === 'blocks'   && <BlocksEditor section={ section } schema={ schema } /> }
                { activeTab === 'css'      && <CustomCssEditor section={ section } /> }
            </div>
        </aside>
    );
}
