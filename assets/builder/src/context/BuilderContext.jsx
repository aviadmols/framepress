import { createContext, useContext, useReducer, useEffect } from 'react';
import { builderReducer, initialState } from './builderReducer';
import { api } from '../api/framepress-api';

const BuilderContext = createContext( null );

export function BuilderProvider( { children } ) {
    const wpData = window.framepressData || {};

    const [ state, dispatch ] = useReducer( builderReducer, {
        ...initialState,
        context: wpData.context || 'page',
        postId:  wpData.postId  || null,
    } );

    // ── Initial data load ─────────────────────────────────────────────────────

    useEffect( () => {
        const load = async () => {
            try {
                const fd            = window.framepressData || {};
                const schemaContext = fd.context === 'elementor-section' ? 'page' : ( fd.context || 'page' );
                const [ schemasData, blocksData, globalData ] = await Promise.all( [
                    api.getSchemas( schemaContext ),
                    api.getBlocks(),
                    api.getGlobalSettings(),
                ] );
                dispatch( { type: 'LOAD_SCHEMAS', schemas: schemasData, blockSchemas: blocksData } );
                dispatch( {
                    type:        'LOAD_GLOBAL_SETTINGS',
                    settings:    globalData.settings,
                    schema:      globalData.schema,
                    googleFonts: globalData.googleFonts || [],
                } );
            } catch ( e ) {
                console.error( '[FramePress] Failed to load schemas', e );
            }
        };
        load();
    }, [] );

    useEffect( () => {
        const loadSections = async () => {
            try {
                let sections = [];
                if ( state.context === 'page' && state.postId ) {
                    sections = await api.getPageSections( state.postId );
                } else if ( state.context === 'header' ) {
                    sections = await api.getHeader();
                } else if ( state.context === 'footer' ) {
                    sections = await api.getFooter();
                } else if ( state.context === 'elementor-section' ) {
                    const fd = window.framepressData || {};
                    if ( fd.elementorSectionKey && fd.postId && fd.sectionType ) {
                        const data = await api.getElementorSection(
                            fd.elementorSectionKey,
                            fd.postId,
                            fd.sectionType
                        );
                        sections = data.sections || [];
                    }
                }
                dispatch( { type: 'LOAD_SECTIONS', sections } );
            } catch ( e ) {
                console.error( '[FramePress] Failed to load sections', e );
            }
        };
        loadSections();
    }, [ state.context, state.postId ] );

    // ── Save ──────────────────────────────────────────────────────────────────

    const save = async () => {
        dispatch( { type: 'SAVE_START' } );
        try {
            if ( state.context === 'page' && state.postId ) {
                await api.savePageSections( state.postId, state.sections );
            } else if ( state.context === 'header' ) {
                await api.saveHeader( state.sections );
            } else if ( state.context === 'footer' ) {
                await api.saveFooter( state.sections );
            } else if ( state.context === 'global' ) {
                await api.saveGlobalSettings( state.globalSettings );
            } else if ( state.context === 'elementor-section' ) {
                const fd = window.framepressData || {};
                await api.saveElementorSection(
                    fd.elementorSectionKey,
                    fd.postId,
                    fd.sectionType,
                    state.sections
                );
            }
            dispatch( { type: 'SAVE_SUCCESS' } );
        } catch ( e ) {
            console.error( '[FramePress] Save failed', e );
            dispatch( { type: 'SAVE_ERROR', message: e.message } );
        }
    };

    const saveGlobal = async () => {
        dispatch( { type: 'SAVE_START' } );
        try {
            await api.saveGlobalSettings( state.globalSettings );
            dispatch( { type: 'SAVE_SUCCESS' } );
        } catch ( e ) {
            dispatch( { type: 'SAVE_ERROR' } );
        }
    };

    // Keyboard shortcut: Ctrl+S / Cmd+S
    useEffect( () => {
        const onKey = ( e ) => {
            if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
                e.preventDefault();
                if ( state.isDirty ) save();
            }
            if ( e.key === 'Escape' ) {
                dispatch( { type: 'DESELECT' } );
            }
        };
        window.addEventListener( 'keydown', onKey );
        return () => window.removeEventListener( 'keydown', onKey );
    }, [ state.isDirty, state.sections, state.globalSettings ] );

    const value = { state, dispatch, save, saveGlobal };

    return (
        <BuilderContext.Provider value={ value }>
            { children }
        </BuilderContext.Provider>
    );
}

export function useBuilder() {
    const ctx = useContext( BuilderContext );
    if ( ! ctx ) throw new Error( 'useBuilder must be used inside BuilderProvider' );
    return ctx;
}
