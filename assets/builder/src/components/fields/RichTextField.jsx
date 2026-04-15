import { useEffect, useRef } from 'react';

export default function RichTextField( { field, value, onChange } ) {
    const ref     = useRef( null );
    const editorId = useRef( 'fp_rich_' + Math.random().toString( 36 ).slice( 2, 9 ) );

    // Use wp.editor if available (WP 5+). Fall back to simple textarea.
    const hasWpEditor = typeof window.wp !== 'undefined' && window.wp.editor;

    useEffect( () => {
        if ( ! hasWpEditor || ! ref.current ) return;

        window.wp.editor.initialize( editorId.current, {
            tinymce: {
                wpautop:   true,
                plugins:   'lists link paste',
                toolbar1:  'bold italic | bullist numlist | link',
                setup: ( ed ) => {
                    ed.on( 'change keyup', () => {
                        onChange( window.wp.editor.getContent( editorId.current ) );
                    } );
                },
            },
            quicktags: true,
        } );

        return () => {
            if ( window.wp.editor.remove ) {
                window.wp.editor.remove( editorId.current );
            }
        };
    }, [] );

    return (
        <div className="fp-field fp-field--richtext">
            <label className="fp-field__label">{ field.label }</label>
            { field.description && <p className="fp-field__description">{ field.description }</p> }
            { hasWpEditor ? (
                <textarea
                    id={ editorId.current }
                    ref={ ref }
                    defaultValue={ value }
                    className="fp-field__richtext-textarea"
                />
            ) : (
                <textarea
                    className="fp-field__textarea"
                    value={ value }
                    onChange={ e => onChange( e.target.value ) }
                    rows={ 6 }
                />
            ) }
        </div>
    );
}
