import { useBuilder } from '../../context/BuilderContext';
import SectionList    from './SectionList';

export default function Sidebar() {
    const { state, dispatch, save } = useBuilder();
    const wpData = window.framepressData || {};
    const isElementorEmbed = wpData.context === 'elementor-section';

    const contexts = [
        { id: 'page',   label: 'Page',   disabled: ! wpData.postId },
        { id: 'header', label: 'Header', disabled: false },
        { id: 'footer', label: 'Footer', disabled: false },
    ];

    return (
        <aside className="fp-sidebar">
            {/* Header */}
            <div className="fp-sidebar__header">
                <span className="fp-sidebar__logo">
                    { isElementorEmbed ? 'FramePress · Elementor' : 'FramePress' }
                </span>
                <div className="fp-sidebar__header-actions">
                    <a
                        href={ wpData.adminUrl + 'admin.php?page=framepress-global' }
                        className="fp-icon-btn"
                        title="Global Settings"
                    >
                        ⚙
                    </a>
                    { state.isDirty && (
                        <button
                            className="fp-btn-save"
                            onClick={ save }
                            disabled={ state.isSaving }
                        >
                            { state.isSaving ? 'Saving…' : 'Save' }
                        </button>
                    ) }
                </div>
            </div>

            {/* Context tabs — hidden when editing a section embedded in Elementor */}
            { ! isElementorEmbed && (
                <div className="fp-sidebar__tabs">
                    { contexts.map( ctx => (
                        <button
                            key={ ctx.id }
                            className={ `fp-sidebar__tab ${ state.context === ctx.id ? 'active' : '' }` }
                            disabled={ ctx.disabled }
                            onClick={ () => dispatch( { type: 'SET_CONTEXT', context: ctx.id, postId: wpData.postId } ) }
                        >
                            { ctx.label }
                        </button>
                    ) ) }
                </div>
            ) }

            {/* Section list */}
            <div className="fp-sidebar__sections">
                <SectionList />
            </div>

        </aside>
    );
}
