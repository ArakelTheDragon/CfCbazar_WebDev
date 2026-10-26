<?php
// /diy/opamp/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/opamp/index.php";
$parent_url = "/diy/opamp";
$title      = "Understanding Op-Amps — The 3 Basic Configurations";
$template   = "Generic";
$meta_title = "Understanding Op-Amps — The 3 Basic Configurations";
$meta_desc  = "Learn the three simplest op-amp configurations: negative feedback, positive feedback, and no feedback. Beginner-friendly explanation with examples.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <h1>Understanding Op-Amps — The 3 Basic Configurations</h1>

        <div class="card">
            <p class="muted">
                Op-amps (operational amplifiers) are extremely simple once you understand one key idea:
                <br><br>
                <strong>The behavior of an op-amp depends entirely on its feedback.</strong>
                <br><br>
                There are only three basic ways to connect an op-amp:
            </p>

            <ul>
                <li><strong>Negative feedback</strong> — stable, controlled, predictable</li>
                <li><strong>Positive feedback</strong> — switching, latching, oscillating</li>
                <li><strong>No feedback</strong> — open-loop, acts like a comparator</li>
            </ul>

            <p class="muted">
                Once you understand these three, every op-amp circuit becomes easy.
            </p>
        </div>

        <div class="card">
            <h2>1. Negative Feedback</h2>
            <p class="muted">The most important and most common configuration</p>

            <p>
                Negative feedback means the output is fed back to the <strong>inverting input (−)</strong>.
                This forces the op-amp to automatically adjust its output so that the two inputs become almost equal.
            </p>

            <p><strong>What it does:</strong></p>
            <ul>
                <li>Makes the circuit stable</li>
                <li>Controls the gain (using resistors)</li>
                <li>Makes the output follow the input in a predictable way</li>
            </ul>

            <p><strong>Examples:</strong></p>
            <ul>
                <li>Voltage follower (gain = 1)</li>
                <li>Inverting amplifier</li>
                <li>Non-inverting amplifier</li>
                <li>Active filters</li>
            </ul>

            <p class="muted">
                If you see an op-amp with a resistor from output → (−), it is almost always a negative-feedback amplifier.
            </p>
        </div>

        <div class="card">
            <h2>2. Positive Feedback</h2>
            <p class="muted">Used for switching, not amplifying</p>

            <p>
                Positive feedback means the output is fed back to the <strong>non-inverting input (+)</strong>.
                This makes the op-amp reinforce its own output instead of stabilizing it.
            </p>

            <p><strong>What it does:</strong></p>
            <ul>
                <li>Makes the output jump to one extreme or the other</li>
                <li>Creates hysteresis (memory)</li>
                <li>Turns the op-amp into a fast switch</li>
            </ul>

            <p><strong>Examples:</strong></p>
            <ul>
                <li>Schmitt trigger</li>
                <li>Oscillators</li>
                <li>Square-wave generators</li>
                <li>Comparators with hysteresis</li>
            </ul>

            <p class="muted">
                Positive feedback is how you make an op-amp behave digitally — ON or OFF.
            </p>
        </div>

        <div class="card">
            <h2>3. No Feedback (Open-Loop)</h2>
            <p class="muted">The op-amp acts like a comparator</p>

            <p>
                With no feedback at all, the op-amp uses its full internal gain (often 100,000× or more).
                Even a tiny difference between the inputs makes the output slam to the positive or negative rail.
            </p>

            <p><strong>What it does:</strong></p>
            <ul>
                <li>Acts like a comparator</li>
                <li>Output is either HIGH or LOW</li>
                <li>Not stable for analog signals</li>
            </ul>

            <p><strong>Examples:</strong></p>
            <ul>
                <li>Zero-cross detectors</li>
                <li>Simple threshold detectors</li>
                <li>Basic logic-level converters</li>
            </ul>

            <p class="muted">
                Open-loop mode is rarely used for analog circuits because the gain is too high to control.
            </p>
        </div>

        <div class="card">
            <h2>Summary</h2>
            <p class="muted">The entire op-amp world in three simple ideas</p>

            <ul>
                <li><strong>Negative feedback</strong> → stable amplifier</li>
                <li><strong>Positive feedback</strong> → switch or oscillator</li>
                <li><strong>No feedback</strong> → comparator</li>
            </ul>

            <p>
                Once you recognize which type of feedback is used, you can instantly understand what the op-amp is doing.
            </p>
        </div>

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/opamp",
    "Op-Amp Tutorial Source Code"
);
include_footer();
close_database();
?>
