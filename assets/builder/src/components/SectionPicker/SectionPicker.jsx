import { useState }   from 'react';
import { useBuilder }  from '../../context/BuilderContext';

const SOURCE_LABELS = { plugin: 'Core', theme: 'Theme', uploads: 'Custom' };

export default function SectionPicker( { onClose } ) {
    const { state, dispatch }       = useBuilder();
    const [ search, setSearch ]     = useState( '' );
    const [ category, setCategory ] = useState( 'all' );

    // Get unique categories.
    const categories = [ 'all', ...new Set( state.schemas.map( s => s.category || 'content' ) ) ];

    const filtered = state.schemas.filter( schema => {
        const matchSearch   = ! search || schema.label.toLowerCase().includes( search.toLowerCase() );
        const matchCategory = category === 'all' || schema.category === category;
        return matchSearch && matchCategory;
    } );

    const addSection = ( schema ) => {
        dispatch( { type: 'ADD_SECTION', sectionType: schema.type } );
        onClose();
    };

    return (
        <div className="fp-picker-overlay" onClick={ onClose }>
            <div className="fp-picker" onClick={ e => e.stopPropagation() }>
                <div className="fp-picker__header">
                    <h2 className="fp-picker__title">Add Section</h2>
                    <button className="fp-icon-btn" onClick={ onClose }>✕</button>
                </div>

                <div className="fp-picker__search">
                    <input
                        type="search"
                        placeholder="Search sections…"
                        value={ search }
                        onChange={ e => setSearch( e.target.value ) }
                        autoFocus
                    />
                </div>

                <div className="fp-picker__categories">
                    { categories.map( cat => (
                        <button
                            key={ cat }
                            className={ `fp-picker__cat-btn ${ category === cat ? 'active' : '' }` }
                            onClick={ () => setCategory( cat ) }
                        >
                            { cat.charAt( 0 ).toUpperCase() + cat.slice( 1 ) }
                        </button>
                    ) ) }
                </div>

                <div className="fp-picker__grid">
                    { filtered.length === 0 && (
                        <p className="fp-picker__empty">No sections found.</p>
                    ) }
                    { filtered.map( schema => (
                        <button
                            key={ schema.type }
                            className="fp-picker__card"
                            onClick={ () => addSection( schema ) }
                        >
                            <div className="fp-picker__card-thumb">
                                <span>{ schema.label.charAt( 0 ) }</span>
                            </div>
                            <div className="fp-picker__card-info">
                                <strong>{ schema.label }</strong>
                                { schema.source && schema.source !== 'plugin' && (
                                    <span className="fp-picker__source-badge">
                                        { SOURCE_LABELS[ schema.source ] || schema.source }
                                    </span>
                                ) }
                            </div>
                        </button>
                    ) ) }
                </div>
            </div>
        </div>
    );
}
