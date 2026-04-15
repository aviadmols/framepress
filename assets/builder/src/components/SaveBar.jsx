import { useBuilder } from '../context/BuilderContext';

export default function SaveBar() {
    const { state, dispatch, save } = useBuilder();

    if ( ! state.isDirty && ! state.saveError ) return null;

    return (
        <div className="fp-save-bar">
            { state.saveError
                ? <span className="fp-save-bar__error">⚠ { state.saveError }</span>
                : <span className="fp-save-bar__msg">You have unsaved changes.</span>
            }
            <div className="fp-save-bar__actions">
                <button
                    className="fp-btn-secondary"
                    onClick={ () => {
                        // Reload sections from server to discard changes.
                        dispatch( { type: 'SET_CONTEXT', context: state.context, postId: state.postId } );
                    } }
                    disabled={ state.isSaving }
                >
                    Discard
                </button>
                <button
                    className="fp-btn-save"
                    onClick={ save }
                    disabled={ state.isSaving }
                >
                    { state.isSaving ? 'Saving…' : 'Save Changes' }
                </button>
            </div>
        </div>
    );
}
