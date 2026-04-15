import PreviewToolbar from './PreviewToolbar';
import PreviewIframe  from './PreviewIframe';

export default function PreviewArea() {
    return (
        <div className="fp-preview-area">
            <PreviewToolbar />
            <div className="fp-preview-area__content">
                <PreviewIframe />
            </div>
        </div>
    );
}
