import { useState } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
    arrayMove,
} from '@dnd-kit/sortable';
import { useBuilder }       from '../../context/BuilderContext';
import SectionListItem      from './SectionListItem';
import SectionPicker        from '../SectionPicker/SectionPicker';

export default function SectionList() {
    const { state, dispatch } = useBuilder();
    const [ pickerOpen, setPickerOpen ] = useState( false );

    const sensors = useSensors(
        useSensor( PointerSensor ),
        useSensor( KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates } )
    );

    const schemaMap = Object.fromEntries( state.schemas.map( s => [ s.type, s ] ) );

    function handleDragEnd( event ) {
        const { active, over } = event;
        if ( ! over || active.id === over.id ) return;

        const oldIndex = state.sections.findIndex( s => s.id === active.id );
        const newIndex = state.sections.findIndex( s => s.id === over.id );
        dispatch( { type: 'REORDER_SECTIONS', sections: arrayMove( state.sections, oldIndex, newIndex ) } );
    }

    if ( state.sections.length === 0 ) {
        return (
            <div className="fp-section-list fp-section-list--empty">
                <p className="fp-section-list__empty-msg">No sections yet.</p>
                <button className="fp-btn-add-section" onClick={ () => setPickerOpen( true ) }>
                    + Add Section
                </button>
                { pickerOpen && <SectionPicker onClose={ () => setPickerOpen( false ) } /> }
            </div>
        );
    }

    return (
        <div className="fp-section-list">
            <DndContext sensors={ sensors } collisionDetection={ closestCenter } onDragEnd={ handleDragEnd }>
                <SortableContext items={ state.sections.map( s => s.id ) } strategy={ verticalListSortingStrategy }>
                    { state.sections.map( section => (
                        <SectionListItem
                            key={ section.id }
                            section={ section }
                            schema={ schemaMap[ section.type ] }
                        />
                    ) ) }
                </SortableContext>
            </DndContext>

            <button className="fp-btn-add-section" onClick={ () => setPickerOpen( true ) }>
                + Add Section
            </button>

            { pickerOpen && <SectionPicker onClose={ () => setPickerOpen( false ) } /> }
        </div>
    );
}
