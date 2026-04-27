import { useSortable } from '@dnd-kit/sortable';
import { CSS }         from '@dnd-kit/utilities';
import { useBuilder }  from '../../context/BuilderContext';

const CodeIcon = () => (
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
        <path d="M3.5 4L1 6l2.5 2M8.5 4L11 6l-2.5 2M7 2.5l-2 7" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
);

export default function SectionListItem( { section, schema, isElementorEmbed = false } ) {
    const { state, dispatch } = useBuilder();
    const isCodeEditing = state.editingCodeSectionType === section.type;
    const isSelected          = state.selectedSectionId === section.id;

    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable( {
        id:       section.id,
        disabled: isElementorEmbed,
    } );

    const style = {
        transform:  CSS.Transform.toString( transform ),
        transition,
        opacity:    isDragging ? 0.5 : 1,
    };

    const label = schema?.label || section.type;

    return (
        <div
            ref={ setNodeRef }
            style={ style }
            className={ `fp-section-item ${ isSelected ? 'fp-section-item--selected' : '' } ${ ! section.enabled ? 'fp-section-item--disabled' : '' }` }
            onClick={ () => dispatch( { type: 'SELECT_SECTION', id: section.id } ) }
        >
            {/* Drag handle */}
            { ! isElementorEmbed && (
                <span
                    className="fp-section-item__handle"
                    { ...attributes }
                    { ...listeners }
                    onClick={ e => e.stopPropagation() }
                    title="Drag to reorder"
                >
                    ⠿
                </span>
            ) }

            <span className="fp-section-item__label">{ label }</span>

            <div className="fp-section-item__actions" onClick={ e => e.stopPropagation() }>
                {/* Enable / disable toggle */}
                <button
                    className={ `fp-toggle ${ section.enabled ? 'fp-toggle--on' : '' }` }
                    onClick={ () => dispatch( { type: 'TOGGLE_SECTION', id: section.id } ) }
                    title={ section.enabled ? 'Hide section' : 'Show section' }
                >
                    { section.enabled ? '●' : '○' }
                </button>

                {/* Edit Code button */}
                <button
                    className={ `fp-section-item__code-btn${ isCodeEditing ? ' fp-section-item__code-btn--active' : '' }` }
                    onClick={ () => dispatch( isCodeEditing
                        ? { type: 'CLOSE_CODE_EDITOR' }
                        : { type: 'OPEN_CODE_EDITOR', sectionType: section.type }
                    ) }
                    title="Edit section code"
                >
                    <CodeIcon />
                </button>

                {/* Delete — not for Elementor embed (single fixed instance) */}
                { ! isElementorEmbed && (
                    <button
                        className="fp-icon-btn fp-icon-btn--danger"
                        onClick={ () => {
                            if ( window.confirm( `Remove "${ label }"?` ) ) {
                                dispatch( { type: 'REMOVE_SECTION', id: section.id } );
                            }
                        } }
                        title="Remove section"
                    >
                        ✕
                    </button>
                ) }
            </div>
        </div>
    );
}
