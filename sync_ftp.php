<?php
/**
 * CfCbazar Database Sync
 *
 * Exports the current MySQL database and sends it to
 * the AwardSpace receiver using an HTTP POST request.
 *
 * This file can:
 *   1. Be opened directly in a browser.
 *   2. Be included from another PHP page.
 *
 * No FTP or SFTP is used.
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

/*
 * AwardSpace receiver.
 *
 * IMPORTANT:
 * This must point to the receive.php file on AwardSpace.
 *
 * HTTP is being used for the initial test because the current
 * AwardSpace HTTPS certificate is not being accepted by cURL.
 */
$receiverUrl = 'http://cfcbazar.atwebpages.com/receive.php';


/*
 * Shared authentication secret.
 *
 * This MUST be exactly the same in receive.php.
 *
 * Do NOT use your FTP password here.
 */
$syncSecret = 'CHANGE_THIS_TO_YOUR_SYNC_SECRET';


/*
 * Maximum time for the HTTP request.
 */
$requestTimeout = 120;


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
|
| config.php already creates $conn.
|--------------------------------------------------------------------------
*/

$configFile = __DIR__ . '/config.php';

if (!is_readable($configFile)) {
    throw new RuntimeException(
        'Could not find config.php.'
    );
}

require_once $configFile;

if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new RuntimeException(
        'Database connection was not available.'
    );
}

if ($conn->connect_errno) {
    throw new RuntimeException(
        'Database connection failed: ' .
        $conn->connect_error
    );
}


/*
|--------------------------------------------------------------------------
| EXPORT DATABASE
|--------------------------------------------------------------------------
*/

function exportDatabase(mysqli $conn): array
{
    $database = [];

    /*
     * Get all tables/views.
     */
    $result = $conn->query(
        'SHOW FULL TABLES'
    );

    if (!$result) {
        throw new RuntimeException(
            'Could not retrieve database tables: ' .
            $conn->error
        );
    }

    while ($row = $result->fetch_array(MYSQLI_NUM)) {

        $tableName = (string)$row[0];

        $tableType = isset($row[1])
            ? (string)$row[1]
            : 'BASE TABLE';

        /*
         * Escape backticks in table names.
         */
        $safeTableName = str_replace(
            '`',
            '``',
            $tableName
        );

        $tableResult = $conn->query(
            "SELECT * FROM `{$safeTableName}`"
        );

        if (!$tableResult) {
            throw new RuntimeException(
                'Could not export table "' .
                $tableName .
                '": ' .
                $conn->error
            );
        }

        $rows = [];

        while ($tableRow = $tableResult->fetch_assoc()) {
            $rows[] = $tableRow;
        }

        $database[$tableName] = [
            'type' => $tableType,
            'rows' => $rows,
        ];

        $tableResult->free();
    }

    $result->free();

    return $database;
}


/*
|--------------------------------------------------------------------------
| SEND JSON TO AWARDSPACE
|--------------------------------------------------------------------------
*/

function sendToReceiver(
    string $url,
    string $json,
    string $secret,
    int $timeout
): array {

    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is not available.'
        );
    }

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException(
            'Could not initialize cURL.'
        );
    }

    curl_setopt_array($ch, [

        /*
         * POST request.
         */
        CURLOPT_POST => true,

        /*
         * Send raw JSON.
         */
        CURLOPT_POSTFIELDS => $json,

        /*
         * Return the remote response.
         */
        CURLOPT_RETURNTRANSFER => true,

        /*
         * Do not output remote headers.
         */
        CURLOPT_HEADER => false,

        /*
         * Do not automatically follow redirects.
         */
        CURLOPT_FOLLOWLOCATION => false,

        /*
         * Connection timeout.
         */
        CURLOPT_CONNECTTIMEOUT => 30,

        /*
         * Overall request timeout.
         */
        CURLOPT_TIMEOUT => $timeout,

        /*
         * HTTP headers.
         */
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
            'X-CfCbazar-Sync-Token: ' . $secret,
            'X-CfCbazar-Sync-File: database_sync.json',
        ],
    ]);

    $response = curl_exec($ch);

    $curlErrorNumber = curl_errno($ch);
    $curlError = curl_error($ch);

    $httpCode = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $effectiveUrl = (string)curl_getinfo(
        $ch,
        CURLINFO_EFFECTIVE_URL
    );

    curl_close($ch);

    if ($response === false || $curlErrorNumber !== 0) {
        throw new RuntimeException(
            'cURL request failed [' .
            $curlErrorNumber .
            ']: ' .
            $curlError
        );
    }

    return [
        'http_code' => $httpCode,
        'response' => (string)$response,
        'effective_url' => $effectiveUrl,
    ];
}


/*
|--------------------------------------------------------------------------
| PERFORM SYNC
|--------------------------------------------------------------------------
*/

function performSync(
    mysqli $conn,
    string $receiverUrl,
    string $syncSecret,
    int $requestTimeout
): array {

    $startTime = microtime(true);

    /*
     * Export database.
     */
    $tables = exportDatabase($conn);

    /*
     * Build payload.
     */
    $payload = [
        'format' => 'cfcbazar_database_sync_v1',

        'created_at' => date('c'),

        'source_host' =>
            $_SERVER['HTTP_HOST'] ??
            'unknown',

        'database_name' =>
            defined('DB_NAME')
                ? DB_NAME
                : 'unknown',

        'table_count' => count($tables),

        'tables' => $tables,
    ];

    /*
     * Convert to JSON.
     */
    $json = json_encode(
        $payload,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        throw new RuntimeException(
            'JSON encoding failed: ' .
            json_last_error_msg()
        );
    }

    /*
     * Send to AwardSpace.
     */
    $transfer = sendToReceiver(
        $receiverUrl,
        $json,
        $syncSecret,
        $requestTimeout
    );

    $elapsed = microtime(true) - $startTime;

    /*
     * Decode receiver response when possible.
     */
    $remoteData = json_decode(
        $transfer['response'],
        true
    );

    return [
        'success' =>
            $transfer['http_code'] >= 200 &&
            $transfer['http_code'] < 300,

        'http_code' =>
            $transfer['http_code'],

        'json_size' =>
            strlen($json),

        'table_count' =>
            count($tables),

        'elapsed' =>
            round($elapsed, 2),

        'remote_response' =>
            $transfer['response'],

        'remote_data' =>
            is_array($remoteData)
                ? $remoteData
                : null,

        'effective_url' =>
            $transfer['effective_url'],
    ];
}


/*
|--------------------------------------------------------------------------
| RUN
|--------------------------------------------------------------------------
*/

try {

    $databaseSyncResult = performSync(
        $conn,
        $receiverUrl,
        $syncSecret,
        $requestTimeout
    );

} catch (Throwable $e) {

    error_log(
        'CfCbazar database sync failed: ' .
        $e->getMessage()
    );

    $databaseSyncResult = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}


/*
|--------------------------------------------------------------------------
| DIRECT BROWSER OUTPUT
|--------------------------------------------------------------------------
*/

$isDirectRequest =
    isset($_SERVER['SCRIPT_FILENAME']) &&
    realpath((string)$_SERVER['SCRIPT_FILENAME']) ===
    realpath(__FILE__);


if ($isDirectRequest) {

    header(
        'Content-Type: text/html; charset=UTF-8'
    );

    $success =
        !empty($databaseSyncResult['success']);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>CfCbazar Database Sync</title>';

    echo '<style>';
    echo 'body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;padding:20px;}';
    echo '.box{padding:20px;border-radius:8px;}';
    echo '.success{background:#e9f7ef;border:1px solid #198754;}';
    echo '.error{background:#fcebea;border:1px solid #dc3545;}';
    echo 'pre{white-space:pre-wrap;word-break:break-word;background:#f5f5f5;padding:15px;border-radius:6px;}';
    echo '</style>';

    echo '</head>';
    echo '<body>';

    echo '<h1>CfCbazar Database Sync</h1>';

    if ($success) {

        echo '<div class="box success">';
        echo '<h2>Sync successful</h2>';

        echo '<p><strong>HTTP status:</strong> ' .
            htmlspecialchars(
                (string)$databaseSyncResult['http_code']
            ) .
            '</p>';

        echo '<p><strong>Tables:</strong> ' .
            htmlspecialchars(
                (string)$databaseSyncResult['table_count']
            ) .
            '</p>';

        echo '<p><strong>JSON size:</strong> ' .
            number_format(
                (int)$databaseSyncResult['json_size']
            ) .
            ' bytes</p>';

        echo '<p><strong>Time:</strong> ' .
            htmlspecialchars(
                (string)$databaseSyncResult['elapsed']
            ) .
            ' seconds</p>';

        echo '<h3>Receiver response</h3>';

        echo '<pre>' .
            htmlspecialchars(
                (string)$databaseSyncResult['remote_response'],
                ENT_QUOTES,
                'UTF-8'
            ) .
            '</pre>';

        echo '</div>';

    } else {

        echo '<div class="box error">';
        echo '<h2>Sync failed</h2>';

        echo '<pre>' .
            htmlspecialchars(
                (string)(
                    $databaseSyncResult['error'] ??
                    'Unknown error'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) .
            '</pre>';

        echo '</div>';
    }

    echo '</body>';
    echo '</html>';

    exit;
}

/*
|--------------------------------------------------------------------------
| INCLUDED MODE
|--------------------------------------------------------------------------
|
| The including page can inspect:
|
| $databaseSyncResult['success']
|
|--------------------------------------------------------------------------
*/
