import { useState } from 'react';
import {
    DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors,
} from '@dnd-kit/core';
import {
    SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy, arrayMove, useSortable,
} from '@dnd-kit/sortable';
import { CSS }         from '@dnd-kit/utilities';
import { useBuilder }  from '../../context/BuilderContext';
import FieldRenderer   from '../fields/FieldRenderer';

function BlockItem( { block, blockSchema, sectionId, isOpen, onToggle } ) {
    const { dispatch } = useBuilder();
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable( { id: block.id } );

    const style = { transform: CSS.Transform.toString( transform ), transition, opacity: isDragging ? 0.5 : 1 };

    return (
        <div ref={ setNodeRef } style={ style } className={ `fp-block-item ${ isOpen ? 'fp-block-item--open' : '' }` }>
            <div className="fp-block-item__header">
                <span { ...attributes } { ...listeners } className="fp-block-item__handle" onClick={ e => e.stopPropagation() }>⠿</span>
                <button className="fp-block-item__title" onClick={ onToggle }>
                    { blockSchema?.label || block.type }
                </button>
                <button
                    className="fp-icon-btn fp-icon-btn--danger"
                    onClick={ e => { e.stopPropagation(); dispatch( { type: 'REMOVE_BLOCK', sectionId, blockId: block.id } ); } }
                    title="Remove"
                >✕</button>
            </div>
            { isOpen && blockSchema?.settings?.length > 0 && (
                <div className="fp-block-item__settings">
                    { blockSchema.settings.map( field => (
                        <FieldRenderer
                            key={ field.id }
                            field={ field }
                            value={ block.settings[ field.id ] }
                            onChange={ value => dispatch( {
                                type: 'UPDATE_BLOCK_SETTING', sectionId, blockId: block.id, key: field.id, value,
                            } ) }
                        />
                    ) ) }
                </div>
            ) }
        </div>
    );
}

export default function BlocksEditor( { section, schema } ) {
    const { state, dispatch } = useBuilder();
    const [ openBlockId, setOpenBlockId ] = useState( null );

    const sensors = useSensors(
        useSensor( PointerSensor ),
        useSensor( KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates } )
    );

    const allowed    = schema?.blocks?.allowed || [];
    const max        = schema?.blocks?.max ?? 99;
    const canAddMore = section.blocks.length < max;

    // Resolve block schema from inline block_types or global blockSchemas.
    const getBlockSchema = ( type ) => {
        if ( schema?.block_types?.[ type ] ) return { ...schema.block_types[ type ], type };
        return state.blockSchemas.find( b => b.type === type );
    };

    function handleDragEnd( { active, over } ) {
        if ( ! over || active.id === over.id ) return;
        const oldIndex = section.blocks.findIndex( b => b.id === active.id );
        const newIndex = section.blocks.findIndex( b => b.id === over.id );
        dispatch( { type: 'REORDER_BLOCKS', sectionId: section.id, blocks: arrayMove( section.blocks, oldIndex, newIndex ) } );
    }

    if ( allowed.length === 0 ) {
        return <p className="fp-settings-empty">This section has no blocks.</p>;
    }

    return (
        <div className="fp-blocks-editor">
            <DndContext sensors={ sensors } collisionDetection={ closestCenter } onDragEnd={ handleDragEnd }>
                <SortableContext items={ section.blocks.map( b => b.id ) } strategy={ verticalListSortingStrategy }>
                    { section.blocks.map( block => (
                        <BlockItem
                            key={ block.id }
                            block={ block }
                            blockSchema={ getBlockSchema( block.type ) }
                            sectionId={ section.id }
                            isOpen={ openBlockId === block.id }
                            onToggle={ () => setOpenBlockId( prev => prev === block.id ? null : block.id ) }
                        />
                    ) ) }
                </SortableContext>
            </DndContext>

            { canAddMore && (
                <div className="fp-blocks-editor__add">
                    { allowed.map( type => (
                        <button
                            key={ type }
                            className="fp-btn-secondary"
                            onClick={ () => dispatch( { type: 'ADD_BLOCK', sectionId: section.id, blockType: type } ) }
                        >
                            + { getBlockSchema( type )?.label || type }
                        </button>
                    ) ) }
                </div>
            ) }
        </div>
    );
}
