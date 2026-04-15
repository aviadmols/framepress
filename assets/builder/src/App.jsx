import Sidebar          from './components/Sidebar/Sidebar';
import PreviewArea      from './components/Preview/PreviewArea';
import SettingsPanel    from './components/Settings/SettingsPanel';
import SaveBar          from './components/SaveBar';
import AISettingsPage   from './components/AISettings/AISettingsPage';
import SectionsManager  from './components/SectionsManager/SectionsManager';
import { useBuilder }   from './context/BuilderContext';

export default function App() {
    const { state } = useBuilder();
    const context   = window.framepressData?.context || state.context;

    // Dedicated full-page UIs for non-builder contexts.
    if ( context === 'ai-settings' ) {
        return <AISettingsPage />;
    }

    if ( context === 'sections-manager' ) {
        return <SectionsManager />;
    }

    return (
        <div className="fp-builder">
            <Sidebar />
            <main className="fp-builder__main">
                <PreviewArea />
            </main>
            { state.selectedSectionId && <SettingsPanel /> }
            <SaveBar />
        </div>
    );
}
