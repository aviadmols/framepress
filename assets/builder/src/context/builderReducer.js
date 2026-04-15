import { generateId } from '../utils/generateId';

export const initialState = {
    sections:          [],
    selectedSectionId: null,
    selectedBlockId:   null,
    schemas:           [],
    blockSchemas:      [],
    globalSettings:    {},
    globalSchema:      null,
    isDirty:           false,
    isSaving:          false,
    saveError:         null,
    previewMode:       'desktop',   // 'desktop' | 'mobile'
    context:           'page',      // 'page' | 'header' | 'footer'
    postId:            null,
    previewReady:      false,
};

export function builderReducer( state, action ) {
    switch ( action.type ) {

        case 'LOAD_SCHEMAS':
            return { ...state, schemas: action.schemas, blockSchemas: action.blockSchemas || [] };

        case 'LOAD_SECTIONS':
            return { ...state, sections: action.sections, isDirty: false };

        case 'LOAD_GLOBAL_SETTINGS':
            return { ...state, globalSettings: action.settings, globalSchema: action.schema };

        case 'SET_CONTEXT':
            return { ...state, context: action.context, postId: action.postId ?? state.postId, selectedSectionId: null, selectedBlockId: null };

        case 'SET_PREVIEW_MODE':
            return { ...state, previewMode: action.mode };

        case 'SET_PREVIEW_READY':
            return { ...state, previewReady: action.ready };

        case 'SELECT_SECTION':
            return { ...state, selectedSectionId: action.id, selectedBlockId: null };

        case 'DESELECT':
            return { ...state, selectedSectionId: null, selectedBlockId: null };

        case 'SELECT_BLOCK':
            return { ...state, selectedBlockId: action.id };

        // ── Section CRUD ──────────────────────────────────────────────────────

        case 'ADD_SECTION': {
            const newSection = {
                id:         generateId(),
                type:       action.sectionType,
                settings:   action.settings || {},
                blocks:     action.blocks   || [],
                custom_css: '',
                enabled:    true,
            };
            const insertAt  = action.insertAfter
                ? state.sections.findIndex( s => s.id === action.insertAfter ) + 1
                : state.sections.length;
            const sections  = [
                ...state.sections.slice( 0, insertAt ),
                newSection,
                ...state.sections.slice( insertAt ),
            ];
            return { ...state, sections, isDirty: true, selectedSectionId: newSection.id };
        }

        case 'REMOVE_SECTION': {
            const sections = state.sections.filter( s => s.id !== action.id );
            return {
                ...state,
                sections,
                isDirty:           true,
                selectedSectionId: state.selectedSectionId === action.id ? null : state.selectedSectionId,
            };
        }

        case 'TOGGLE_SECTION': {
            const sections = state.sections.map( s =>
                s.id === action.id ? { ...s, enabled: ! s.enabled } : s
            );
            return { ...state, sections, isDirty: true };
        }

        case 'REORDER_SECTIONS': {
            return { ...state, sections: action.sections, isDirty: true };
        }

        case 'UPDATE_SECTION_SETTING': {
            const sections = state.sections.map( s => {
                if ( s.id !== action.sectionId ) return s;
                return { ...s, settings: { ...s.settings, [ action.key ]: action.value } };
            } );
            return { ...state, sections, isDirty: true };
        }

        case 'UPDATE_SECTION_SETTINGS': {
            // Batch update (e.g. from AI generation)
            const sections = state.sections.map( s => {
                if ( s.id !== action.sectionId ) return s;
                return {
                    ...s,
                    settings: { ...s.settings, ...action.settings },
                    blocks:   action.blocks !== undefined ? action.blocks : s.blocks,
                };
            } );
            return { ...state, sections, isDirty: true };
        }

        case 'UPDATE_SECTION_CSS': {
            const sections = state.sections.map( s =>
                s.id === action.sectionId ? { ...s, custom_css: action.css } : s
            );
            return { ...state, sections, isDirty: true };
        }

        // ── Block CRUD ────────────────────────────────────────────────────────

        case 'ADD_BLOCK': {
            const newBlock = {
                id:       generateId(),
                type:     action.blockType,
                settings: action.settings || {},
            };
            const sections = state.sections.map( s => {
                if ( s.id !== action.sectionId ) return s;
                return { ...s, blocks: [ ...s.blocks, newBlock ] };
            } );
            return { ...state, sections, isDirty: true, selectedBlockId: newBlock.id };
        }

        case 'REMOVE_BLOCK': {
            const sections = state.sections.map( s => {
                if ( s.id !== action.sectionId ) return s;
                return { ...s, blocks: s.blocks.filter( b => b.id !== action.blockId ) };
            } );
            return {
                ...state,
                sections,
                isDirty:        true,
                selectedBlockId: state.selectedBlockId === action.blockId ? null : state.selectedBlockId,
            };
        }

        case 'REORDER_BLOCKS': {
            const sections = state.sections.map( s => {
                if ( s.id !== action.sectionId ) return s;
                return { ...s, blocks: action.blocks };
            } );
            return { ...state, sections, isDirty: true };
        }

        case 'UPDATE_BLOCK_SETTING': {
            const sections = state.sections.map( s => {
                if ( s.id !== action.sectionId ) return s;
                const blocks = s.blocks.map( b => {
                    if ( b.id !== action.blockId ) return b;
                    return { ...b, settings: { ...b.settings, [ action.key ]: action.value } };
                } );
                return { ...s, blocks };
            } );
            return { ...state, sections, isDirty: true };
        }

        // ── Global settings ───────────────────────────────────────────────────

        case 'UPDATE_GLOBAL_SETTING': {
            return {
                ...state,
                globalSettings: { ...state.globalSettings, [ action.key ]: action.value },
                isDirty: true,
            };
        }

        case 'UPDATE_GLOBAL_SETTINGS': {
            return { ...state, globalSettings: { ...state.globalSettings, ...action.settings }, isDirty: true };
        }

        // ── Save lifecycle ────────────────────────────────────────────────────

        case 'SAVE_START':   return { ...state, isSaving: true, saveError: null };
        case 'SAVE_SUCCESS': return { ...state, isSaving: false, isDirty: false, saveError: null };
        case 'SAVE_ERROR':   return { ...state, isSaving: false, saveError: action.message || 'Save failed. Check your connection and try again.' };

        default:
            return state;
    }
}
