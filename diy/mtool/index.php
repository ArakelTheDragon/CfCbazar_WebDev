<?php
// /diy/mtool/index.php
// Message tool using global CfCbazar styles.
// Stores a 32-char message, returns a 6-digit reference, expires in 1 hour.

header("Content-Type: text/html; charset=UTF-8");

// Storage file
$storageFile = __DIR__ . "/messages.json";

// Ensure storage file exists
if (!file_exists($storageFile)) {
    file_put_contents($storageFile, "{}", LOCK_EX);
}

// Load JSON safely
$dataRaw = file_get_contents($storageFile);
$data = json_decode($dataRaw, true);
if (!is_array($data)) {
    $data = [];
}

// Cleanup expired messages
$changed = false;
foreach ($data as $ref => $entry) {
    if (time() > $entry['expires']) {
        unset($data[$ref]);
        $changed = true;
    }
}
if ($changed) {
    file_put_contents(
        $storageFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

$storeResult = null;
$getResult   = null;

/* ============================
   STORE MESSAGE
   ============================ */
if (isset($_POST['store_message'])) {

    $msg = trim($_POST['message'] ?? "");

    if ($msg === "" || mb_strlen($msg, 'UTF-8') > 32) {
        $storeResult = ["error" => "Message must be 1–32 characters."];
    } else {

        // Generate unique 6-digit reference
        do {
            $ref = str_pad(rand(0, 999999), 6, "0", STR_PAD_LEFT);
        } while (isset($data[$ref]));

        // Save message
        $data[$ref] = [
            "message" => $msg,
            "expires" => time() + 3600
        ];

        file_put_contents(
            $storageFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        $storeResult = ["ref" => $ref];
    }
}

/* ============================
   RETRIEVE MESSAGE
   ============================ */
if (isset($_POST['get_message'])) {

    $ref = preg_replace('/\D/', '', $_POST['ref'] ?? '');

    if (strlen($ref) !== 6) {
        $getResult = ["error" => "Invalid reference number."];
    } elseif (!isset($data[$ref])) {
        $getResult = ["error" => "Message not found or expired."];
    } else {
        $seconds = $data[$ref]['expires'] - time();
        if ($seconds < 0) {
            $getResult = ["error" => "Message not found or expired."];
        } else {
            $minutes = floor($seconds / 60);
            $getResult = [
                "ref"        => $ref,
                "message"    => $data[$ref]['message'],
                "expires_in" => $minutes . " minute" . ($minutes === 1 ? "" : "s") . " remaining"
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Message Tool</title>
<link rel="stylesheet" href="/css/styles.css">
</head>
<body>

<div class="container">

    <h1 class="page-title" style="font-size:3rem; margin-bottom:2rem;">
        Message Storage Tool
    </h1>

    <!-- STORE MESSAGE -->
    <div class="card" style="padding:30px;">
        <form method="post">
            <h3 style="font-size:1.8rem; margin-bottom:20px;">Store a Message</h3>

            <label style="font-size:1.2rem;">Message (max 32 characters):</label>
            <input type="text"
                   name="message"
                   id="messageInput"
                   maxlength="32"
                   required
                   autocomplete="off"
                   style="font-size:1.3rem; padding:18px;">

            <div id="messageCounter"
                 style="text-align:right; font-size:1.1rem; margin-top:6px; color:#555;">
                0 / 32
            </div>

            <button type="submit"
                    name="store_message"
                    style="font-size:1.4rem; padding:20px; margin-top:20px;">
                Store Message
            </button>

            <?php if ($storeResult): ?>
                <?php if (isset($storeResult['error'])): ?>
                    <div class="error" style="font-size:1.3rem; padding:20px;">
                        <?= htmlspecialchars($storeResult['error']) ?>
                    </div>
                <?php else: ?>
                    <div class="success" style="font-size:1.5rem; padding:25px;">
                        <strong>Reference Number:</strong><br>
                        <span id="refText"
                              style="font-size:2.8rem; font-weight:bold; display:block; margin:15px 0;">
                            <?= htmlspecialchars($storeResult['ref']) ?>
                        </span>

                        <button type="button"
                                onclick="copyText('refText')"
                                style="font-size:1.4rem; padding:18px; margin-top:10px;">
                            Copy Reference
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>

    <!-- RETRIEVE MESSAGE -->
    <div class="card" style="padding:30px;">
        <form method="post">
            <h3 style="font-size:1.8rem; margin-bottom:20px;">Retrieve a Message</h3>

            <label style="font-size:1.2rem;">Reference Number:</label>
            <input type="text"
                   name="ref"
                   maxlength="6"
                   required
                   autocomplete="off"
                   inputmode="numeric"
                   pattern="[0-9]{6}"
                   style="font-size:1.3rem; padding:18px;">

            <button type="submit"
                    name="get_message"
                    style="font-size:1.4rem; padding:20px; margin-top:20px;">
                Get Message
            </button>

            <?php if ($getResult): ?>
                <?php if (isset($getResult['error'])): ?>
                    <div class="error" style="font-size:1.3rem; padding:20px;">
                        <?= htmlspecialchars($getResult['error']) ?>
                    </div>
                <?php else: ?>
                    <div class="success" style="font-size:1.5rem; padding:25px;">
                        <strong>Message:</strong><br>
                        <span id="msgText"
                              style="font-size:2.4rem; font-weight:bold; display:block; margin:15px 0;">
                            <?= htmlspecialchars($getResult['message']) ?>
                        </span>

                        <strong style="font-size:1.3rem;">
                            Expiration: <?= htmlspecialchars($getResult['expires_in']) ?>
                        </strong>

                        <button type="button"
                                onclick="copyText('msgText')"
                                style="font-size:1.4rem; padding:18px; margin-top:15px;">
                            Copy Message
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>

    <!-- DISCLAIMER -->
    <div class="card" style="padding:30px; font-size:1.2rem; text-align:left;">
        <h3 style="font-size:1.8rem; margin-bottom:15px;">Legal Disclaimer</h3>
        <p>
            This tool is provided for convenience only. All messages submitted are
            <strong>user-generated content</strong>. We do not review, monitor, or verify any
            message stored through this system. By using this tool, you agree that:
        </p>
        <ul style="margin-left:25px; line-height:1.8;">
            <li>You are solely responsible for the content you submit.</li>
            <li>We are not liable for offensive, harmful, illegal, or misleading messages.</li>
            <li>Messages are temporary and may be deleted at any time.</li>
            <li>No guarantee of data security, privacy, or protection against unauthorized access.</li>
            <li>We are not responsible for data loss, leaks, or misuse.</li>
            <li>This service is provided “as is” without warranties.</li>
        </ul>
    </div>

</div>

<script>
    const msgInput   = document.getElementById('messageInput');
    const msgCounter = document.getElementById('messageCounter');

    if (msgInput && msgCounter) {
        msgInput.addEventListener('input', function () {
            const len = [...msgInput.value].length;
            msgCounter.textContent = len + " / 32";
        });
    }

    function copyText(id){
        const el = document.getElementById(id);
        if (!el) return;
        const text = el.textContent || el.innerText;
        navigator.clipboard.writeText(text).then(function(){
            alert('Copied: ' + text);
        });
    }
</script>

</body>
</html>
