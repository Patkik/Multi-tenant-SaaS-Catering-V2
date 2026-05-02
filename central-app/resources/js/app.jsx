import { StrictMode, lazy, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './ui/App';
import { TenantProvider } from './providers/TenantProvider';

const ReactQueryDevtools = lazy(() =>
    import('@tanstack/react-query-devtools').then((module) => ({ default: module.ReactQueryDevtools })),
);

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60,
            gcTime: 1000 * 60 * 30,
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

const mountNode = document.getElementById('app');

if (mountNode) {
    createRoot(mountNode).render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <BrowserRouter>
                    <TenantProvider>
                        <App />
                    </TenantProvider>
                </BrowserRouter>
                {import.meta.env.DEV ? (
                    <Suspense fallback={null}>
                        <ReactQueryDevtools initialIsOpen={false} />
                    </Suspense>
                ) : null}
            </QueryClientProvider>
        </StrictMode>,
    );
}
