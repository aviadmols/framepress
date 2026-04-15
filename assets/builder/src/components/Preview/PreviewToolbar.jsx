import { useBuilder } from '../../context/BuilderContext';

export default function PreviewToolbar() {
    const { state, dispatch } = useBuilder();

    return (
        <div className="fp-preview-toolbar">
            <div className="fp-preview-toolbar__modes">
                <button
                    className={ `fp-preview-mode-btn ${ state.previewMode === 'desktop' ? 'active' : '' }` }
                    onClick={ () => dispatch( { type: 'SET_PREVIEW_MODE', mode: 'desktop' } ) }
                    title="Desktop preview"
                >
                    🖥
                </button>
                <button
                    className={ `fp-preview-mode-btn ${ state.previewMode === 'mobile' ? 'active' : '' }` }
                    onClick={ () => dispatch( { type: 'SET_PREVIEW_MODE', mode: 'mobile' } ) }
                    title="Mobile preview"
                >
                    📱
                </button>
            </div>
            <a
                href={ window.framepressData?.previewUrl }
                target="_blank"
                rel="noopener noreferrer"
                className="fp-preview-toolbar__open"
                title="Open preview in new tab"
            >
                ↗
            </a>
        </div>
    );
}
