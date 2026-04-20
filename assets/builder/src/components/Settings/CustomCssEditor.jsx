import { useBuilder } from '../../context/BuilderContext';

export default function CustomCssEditor( { section } ) {
    const { dispatch } = useBuilder();

    return (
        <div className="fp-custom-css-editor">
            <p className="fp-custom-css-editor__hint">
                CSS entered here is scoped to{' '}
                <code>#hero-section-{ section.id }</code> automatically.
            </p>
            <textarea
                className="fp-custom-css-editor__textarea"
                value={ section.custom_css || '' }
                onChange={ e => dispatch( {
                    type:      'UPDATE_SECTION_CSS',
                    sectionId: section.id,
                    css:       e.target.value,
                } ) }
                placeholder=".my-class { color: red; }"
                rows={ 10 }
                spellCheck={ false }
            />
        </div>
    );
}
