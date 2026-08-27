<!DOCTYPE html>
<html lang="it" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Codice di verifica · Matches</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .wrapper {
            background-color: transparent;
            padding: 48px 16px 64px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }

        .container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #09090b; /* zinc-950 */
            border: 1px solid #27272a; /* zinc-800 */
        }

        .corner-tape {
            height: 3px;
            background-color: #0ea5e9; /* sky-500 */
        }

        .header {
            padding: 28px 40px;
            border-bottom: 1px solid #27272a; /* zinc-800 */
        }

        .app-name {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: #a1a1aa; /* zinc-400 */
            text-decoration: none;
        }

        .body {
            padding: 40px 40px 36px;
        }

        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #f4f4f5; /* zinc-100 */
            margin-bottom: 14px;
            letter-spacing: -0.02em;
            line-height: 1.3;
        }

        .body-text {
            font-size: 14px;
            line-height: 1.75;
            color: #a1a1aa; /* zinc-400 */
            margin-bottom: 36px;
        }

        .code-wrapper {
            margin-bottom: 36px;
        }

        .code {
            display: inline-block;
            background-color: #18181b; /* zinc-900 */
            border: 1px solid #27272a; /* zinc-800 */
            color: #f4f4f5; /* zinc-100 */
            font-size: 30px;
            font-weight: 600;
            letter-spacing: 0.32em;
            padding: 18px 28px 18px 36px;
            line-height: 1;
        }

        .divider {
            height: 1px;
            background-color: #18181b; /* zinc-900 */
            margin-bottom: 24px;
        }

        .meta {
            font-size: 12px;
            line-height: 1.7;
            color: #71717a; /* zinc-500 */
            margin-bottom: 6px;
        }

        .footer {
            padding: 20px 40px;
            border-top: 1px solid #18181b; /* zinc-900 */
        }

        .footer-text {
            font-size: 11px;
            letter-spacing: 0.04em;
            color: #52525b; /* zinc-600 */
        }

        @media only screen and (max-width: 600px) {
            .header,
            .body,
            .footer {
                padding-left: 24px;
                padding-right: 24px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="corner-tape"></div>

        <div class="header">
            <span class="app-name">Matches</span>
        </div>

        <div class="body">
            <p class="greeting">Codice di verifica</p>

            <p class="body-text">
                Hai ricevuto questa email perché è stata avviata un'iscrizione a un torneo con questo indirizzo.
                Inserisci il codice qui sotto per confermarla.
            </p>

            <div class="code-wrapper">
                <span class="code">{{ $code }}</span>
            </div>

            <div class="divider"></div>

            <p class="meta">Il codice scade tra {{ $ttlMinutes }} minuti e può essere usato una sola volta.</p>
            <p class="meta">Se non hai richiesto tu l'iscrizione, ignora questa email: senza il codice non verrà completata.</p>
        </div>

        <div class="footer">
            <p class="footer-text">© {{ date('Y') }} Matches</p>
        </div>
    </div>
</div>
</body>
</html>
