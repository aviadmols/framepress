import Sidebar      from './components/Sidebar/Sidebar';
import PreviewArea  from './components/Preview/PreviewArea';
import SettingsPanel from './components/Settings/SettingsPanel';
import SaveBar       from './components/SaveBar';
import { useBuilder } from './context/BuilderContext';

export default function App() {
    const { state } = useBuilder();

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
