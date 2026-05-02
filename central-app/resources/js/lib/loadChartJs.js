const CHART_JS_CDN_URL = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js';

let loaderPromise = null;

export async function loadChartJs() {
    if (typeof window === 'undefined') {
        return null;
    }

    if (window.Chart) {
        return window.Chart;
    }

    if (!loaderPromise) {
        loaderPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector(`script[src="${CHART_JS_CDN_URL}"]`);

            if (existingScript) {
                existingScript.addEventListener('load', () => resolve(window.Chart), { once: true });
                existingScript.addEventListener('error', () => reject(new Error('Unable to load Chart.js from CDN.')), {
                    once: true,
                });
                return;
            }

            const script = document.createElement('script');
            script.src = CHART_JS_CDN_URL;
            script.async = true;
            script.onload = () => resolve(window.Chart);
            script.onerror = () => reject(new Error('Unable to load Chart.js from CDN.'));
            document.head.appendChild(script);
        });
    }

    return loaderPromise;
}

