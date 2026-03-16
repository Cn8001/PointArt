<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */


// General http response helpers

const HTTP_MESSAGES = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    408 => 'Request Timeout',
    409 => 'Conflict',
    422 => 'Unprocessable Entity',
    429 => 'Too Many Requests',
    500 => 'Internal Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
];

function httpError(int $code, string $detail = ''): void {
    http_response_code($code);
    $message = HTTP_MESSAGES[$code] ?? 'Error';
    $body    = $detail !== '' ? '<p>' . htmlspecialchars($detail) . '</p>' : '';
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>{$code} {$message}</title>
        <style>
            body { font-family: sans-serif; max-width: 500px; margin: 100px auto; padding: 0 20px; color: #333; }
            h1 { font-size: 2em; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
            .code { font-size: 4em; font-weight: bold; color: #ccc; margin: 0; }
        </style>
    </head>
    <body>
        <p class="code">{$code}</p>
        <h1>{$message}</h1>
        {$body}
    </body>
    </html>
    HTML;
}

function return404(): void { httpError(404); }
function return401(): void { httpError(401); }
function return403(): void { httpError(403); }
function return405(): void { httpError(405); }
?>