import { useMemo, useState } from 'react';
import { useBuilder }  from '../../context/BuilderContext';
import FieldRenderer   from '../fields/FieldRenderer';
import SaveBar         from '../SaveBar';

export default function GlobalSettingsPage() {
    const { state, dispatch } = useBuilder();
    const schema     = state.globalSchema;
    const settings   = state.globalSettings;
    const googleFonts = state.globalGoogleFonts || [];
    const [ activeGroup, setActiveGroup ] = useState( null );
    const [ fieldSearch, setFieldSearch ] = useState( '' );

    const groups = useMemo( () => Object.entries( schema?.groups || {} ), [ schema ] );

    const firstId = groups[0]?.[0] ?? null;
    const groupId = activeGroup ?? firstId;

    const groupFields = schema?.groups?.[ groupId ]?.settings ?? [];
    const groupLabel  = schema?.groups?.[ groupId ]?.label ?? '';

    const visibleFields = useMemo( () => {
        const q = fieldSearch.trim().toLowerCase();
        if ( ! q ) {
            return groupFields.filter( f => ! f.hidden && f.type !== 'hidden' );
        }
        return groupFields.filter( f => {
            if ( f.hidden || f.type === 'hidden' ) {
                return false;
            }
            const label = ( f.label || '' ).toLowerCase();
            const id    = ( f.id || '' ).toLowerCase();
            const desc  = ( f.description || '' ).toLowerCase();
            return label.includes( q ) || id.includes( q ) || desc.includes( q );
        } );
    }, [ groupFields, fieldSearch ] );

    if ( ! schema ) {
        return (
            <div className="fp-gs-page">
                <div className="fp-gs-loading">Loading global settings…</div>
            </div>
        );
    }

    const batchGlobal = ( patch ) => {
        dispatch( { type: 'UPDATE_GLOBAL_SETTINGS', settings: patch } );
    };

    return (
        <div className="fp-gs-page">
            <aside className="fp-gs-sidebar">
                <div className="fp-gs-sidebar__header">
                    <span className="fp-gs-sidebar__title">Global Settings</span>
                </div>
                <nav className="fp-gs-nav" aria-label="Settings groups">
                    { groups.map( ( [ id, group ] ) => (
                        <button
                            key={ id }
                            type="button"
                            className={ `fp-gs-nav__item ${ groupId === id ? 'active' : '' }` }
                            onClick={ () => {
                                setActiveGroup( id );
                                setFieldSearch( '' );
                            } }
                        >
                            <span className="fp-gs-nav__item-label">{ group.label }</span>
                            <span className="fp-gs-nav__item-count">{ group.settings?.length ?? 0 }</span>
                        </button>
                    ) ) }
                </nav>
            </aside>

            <div className="fp-gs-main">
                <header className="fp-gs-topbar">
                    <div className="fp-gs-topbar__titles">
                        <h1 className="fp-gs-topbar__title">{ groupLabel }</h1>
                        <p className="fp-gs-topbar__subtitle">Site-wide design tokens — colors, typography, spacing, and custom CSS.</p>
                    </div>
                    <div className="fp-gs-topbar__search-wrap">
                        <input
                            type="search"
                            className="fp-gs-field-search"
                            placeholder="Search fields in this group…"
                            value={ fieldSearch }
                            onChange={ e => setFieldSearch( e.target.value ) }
                            aria-label="Search fields"
                        />
                    </div>
                </header>

                <div className="fp-gs-scroll">
                    <section className="fp-gs-card">
                        <div className="fp-gs-card__body">
                            { visibleFields.length === 0 && (
                                <p className="fp-gs-empty">No fields match your search.</p>
                            ) }
                            { visibleFields.map( field => (
                                <FieldRenderer
                                    key={ field.id }
                                    field={ field }
                                    value={ settings[ field.id ] ?? field.default ?? '' }
                                    onChange={ value => dispatch( {
                                        type:  'UPDATE_GLOBAL_SETTING',
                                        key:   field.id,
                                        value,
                                    } ) }
                                    globalSettings={ settings }
                                    googleFonts={ googleFonts }
                                    onGlobalBatchChange={ batchGlobal }
                                />
                            ) ) }
                        </div>
                    </section>
                </div>
            </div>

            <SaveBar />
        </div>
    );
}
