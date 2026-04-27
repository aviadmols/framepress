import Sidebar              from './components/Sidebar/Sidebar';
import PreviewArea          from './components/Preview/PreviewArea';
import SettingsPanel        from './components/Settings/SettingsPanel';
import SaveBar              from './components/SaveBar';
import AISettingsPage       from './components/AISettings/AISettingsPage';
import SectionsManager      from './components/SectionsManager/SectionsManager';
import GlobalSettingsPage   from './components/GlobalSettings/GlobalSettingsPage';
import CodeEditorDrawer     from './components/CodeEditor/CodeEditorDrawer';
import { useBuilder }       from './context/BuilderContext';

export default function App() {
    const { state } = useBuilder();
    const context   = window.heroData?.context || state.context;

    // Dedicated full-page UIs for non-builder contexts.
    if ( context === 'ai-settings' ) {
        return <AISettingsPage />;
    }

    if ( context === 'sections-manager' ) {
        return <SectionsManager />;
    }

    if ( context === 'global' ) {
        return <GlobalSettingsPage />;
    }

    return (
        <div className={ `fp-builder${ state.editingCodeSectionType ? ' fp-builder--drawer-open' : '' }` }>
            <Sidebar />
            <main className="fp-builder__main">
                <PreviewArea />
            </main>
            { state.selectedSectionId && <SettingsPanel /> }
            <SaveBar />
            <CodeEditorDrawer />
        </div>
    );
}
