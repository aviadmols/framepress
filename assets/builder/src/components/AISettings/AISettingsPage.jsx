import { useState } from 'react';
import SectionGenerator from './SectionGenerator';
import AIProviderSettings from './AIProviderSettings';

const TABS = [
    { id: 'generator', label: '✦ Section Generator' },
    { id: 'settings',  label: '⚙ API Settings' },
];

export default function AISettingsPage() {
    const [ tab, setTab ] = useState( 'generator' );
    const adminUrl = window.framepressData?.adminUrl || '';

    return (
        <div className="fp-ai-page">
            <div className="fp-ai-page__header">
                <div className="fp-ai-page__header-left">
                    <a href={ adminUrl + 'admin.php?page=framepress' } className="fp-ai-page__back">
                        ← FramePress
                    </a>
                    <h1 className="fp-ai-page__title">AI Settings</h1>
                </div>
            </div>

            <div className="fp-ai-page__tabs">
                { TABS.map( t => (
                    <button
                        key={ t.id }
                        className={ `fp-ai-page__tab ${ tab === t.id ? 'active' : '' }` }
                        onClick={ () => setTab( t.id ) }
                    >
                        { t.label }
                    </button>
                ) ) }
            </div>

            <div className="fp-ai-page__body">
                { tab === 'generator' && <SectionGenerator /> }
                { tab === 'settings'  && <AIProviderSettings /> }
            </div>
        </div>
    );
}
