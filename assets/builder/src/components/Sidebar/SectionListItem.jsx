import { useSortable } from '@dnd-kit/sortable';
import { CSS }         from '@dnd-kit/utilities';
import { useBuilder }  from '../../context/BuilderContext';

export default function SectionListItem( { section, schema } ) {
    const { state, dispatch } = useBuilder();
    const isSelected          = state.selectedSectionId === section.id;

    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable( { id: section.id } );

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
            <span
                className="fp-section-item__handle"
                { ...attributes }
                { ...listeners }
                onClick={ e => e.stopPropagation() }
                title="Drag to reorder"
            >
                ⠿
            </span>

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

                {/* Delete */}
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
            </div>
        </div>
    );
}
