import { StrictMode } from 'react';
import { createRoot }  from 'react-dom/client';
import { BuilderProvider } from './context/BuilderContext';
import App from './App';
import './builder.css';

const root = document.getElementById( 'hero-builder-root' );

if ( root ) {
    createRoot( root ).render(
        <StrictMode>
            <BuilderProvider>
                <App />
            </BuilderProvider>
        </StrictMode>
    );
}
