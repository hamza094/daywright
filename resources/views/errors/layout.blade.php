<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? __('Something went wrong') }} | {{ config('app.name') }}</title>

        <style>
            /* latin – self-hosted Inter (replaces Bunny Fonts CDN) */
            @font-face {
                font-family: 'Inter';
                font-style: normal;
                font-weight: 400;
                font-display: swap;
                src: url('/fonts/inter-latin-400.woff2') format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6,
                    U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122,
                    U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
            @font-face {
                font-family: 'Inter';
                font-style: normal;
                font-weight: 500;
                font-display: swap;
                src: url('/fonts/inter-latin-500.woff2') format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6,
                    U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122,
                    U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
            @font-face {
                font-family: 'Inter';
                font-style: normal;
                font-weight: 600;
                font-display: swap;
                src: url('/fonts/inter-latin-600.woff2') format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6,
                    U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122,
                    U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
            :root {
                color-scheme: light dark;
            }
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: radial-gradient(circle at 20% 20%, #1c2333, #111827);
                color: #f9fafb;
            }
            .card {
                width: min(480px, 90vw);
                padding: 2.5rem;
                border-radius: 1.5rem;
                background: rgba(15, 23, 42, 0.8);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
                backdrop-filter: blur(10px);
                text-align: center;
            }
            .status {
                font-size: 0.9rem;
                font-weight: 600;
                letter-spacing: 0.35rem;
                text-transform: uppercase;
                color: #60a5fa;
            }
            h1 {
                font-size: clamp(2rem, 5vw, 2.75rem);
                margin: 1rem 0 0.75rem;
            }
            p {
                margin: 0 auto 1.5rem;
                color: #cbd5f5;
                line-height: 1.7;
            }
            a.button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.85rem 1.75rem;
                border-radius: 999px;
                font-weight: 600;
                text-decoration: none;
                background: #2563eb;
                color: #fff;
                box-shadow: 0 10px 30px rgba(37, 99, 235, 0.35);
                transition: transform 150ms ease, box-shadow 150ms ease;
            }
            a.button:hover {
                transform: translateY(-1px);
                box-shadow: 0 15px 40px rgba(37, 99, 235, 0.45);
            }
            footer {
                margin-top: 1.5rem;
                font-size: 0.85rem;
                color: #94a3b8;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <span class="status">{{ $code ?? 'Error' }}</span>
            <h1>{{ $heading ?? __('Unexpected Error') }}</h1>
            <p>{{ $description ?? __('An unexpected error occurred. Please try again or head back to the dashboard.') }}</p>
            <a class="button" href="{{ url('/') }}">{{ __('Back to Home') }}</a>
            <footer>
                {{ config('app.name') }} &mdash; {{ now()->format('Y') }}
            </footer>
        </main>
    </body>
</html>
