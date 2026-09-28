<?php
// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// Retrieve error code from query param (?code=404) or Apache redirect environment variable
$code = filter_input(INPUT_GET, 'code', FILTER_VALIDATE_INT);
if (!$code && isset($_SERVER['REDIRECT_STATUS'])) {
    $code = (int)$_SERVER['REDIRECT_STATUS'];
}

// Fallback to 404 if no valid code is passed or out of standard range
if (!$code || $code < 400 || $code > 599) {
    $code = 404;
}

// Emit correct HTTP response header
http_response_code($code);

// Map common error codes to messages
$errorMap = [
    400 => [
        'title'   => 'Bad Request',
        'message' => 'The server could not understand the request due to invalid syntax or missing parameters.'
    ],
    401 => [
        'title'   => 'Unauthorized Access',
        'message' => 'You must be logged in with valid credentials to view this page.'
    ],
    403 => [
        'title'   => 'Access Forbidden',
        'message' => 'You do not have the required permissions to access this directory or resource.'
    ],
    404 => [
        'title'   => 'Page Not Found',
        'message' => 'The requested URL was not found on this server. It may have been moved, renamed, or removed.'
    ],
    500 => [
        'title'   => 'Internal Server Error',
        'message' => 'An unexpected server error occurred. Our team has been notified and is looking into it.'
    ],
    503 => [
        'title'   => 'Service Unavailable',
        'message' => 'The server is currently undergoing maintenance or temporary capacity limits. Please try again shortly.'
    ]
];

$errorDetails = $errorMap[$code] ?? [
    'title'   => 'System Error',
    'message' => 'An unexpected system error occurred while processing your request.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= (int)$code ?> - <?= htmlspecialchars($errorDetails['title']) ?> | CfCbazar</title>
    <meta name="robots" content="noindex, follow" />
    <style>
        :root {
            --bg-color: #f9f9fc;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #666666;
            --primary: #0077cc;
            --primary-hover: #005599;
            --border-color: #e2e8f0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            display: flex;
            min-height: 90vh;
            align-items: center;
            justify-content: center;
        }

        .error-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            max-width: 480px;
            width: 100%;
            padding: 40px 30px;
            text-align: center;
        }

        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: var(--primary);
            margin: 0;
            line-height: 1;
        }

        .error-title {
            font-size: 20px;
            margin: 15px 0 10px;
            color: var(--text-main);
        }

        .error-message {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: #edf2f7;
            color: var(--text-main);
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
        }
    </style>
</head>
<body>

<div class="error-card">
    <h1 class="error-code"><?= (int)$code ?></h1>
    <h2 class="error-title"><?= htmlspecialchars($errorDetails['title']) ?></h2>
    <p class="error-message"><?= htmlspecialchars($errorDetails['message']) ?></p>
    
    <div class="actions">
        <a href="/index.php" class="btn btn-primary">Home</a>
        <a href="/d.php" class="btn btn-secondary">Dashboard</a>
        <a href="/help/" class="btn btn-secondary">Help Center</a>
    </div>
</div>

</body>
</html>
