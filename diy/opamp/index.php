<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Understanding Op-Amps — The 3 Basic Configurations</title>

    <meta name="description" content="Learn the three simplest op-amp configurations: negative feedback, positive feedback, and no feedback. Beginner-friendly explanation with examples.">
    <meta name="keywords" content="op-amp basics, negative feedback, positive feedback, open loop, electronics DIY, CfCbazar DIY">
    <meta name="author" content="CfCbazar">
    <link rel="canonical" href="https://cfcbazar.42web.io/diy/opamp/">

    <meta property="og:title" content="Understanding Op-Amps — The 3 Basic Configurations">
    <meta property="og:description" content="A simple explanation of the three fundamental op-amp circuits: negative feedback, positive feedback, and no feedback.">
    <meta property="og:image" content="/diy/opamp/images/opamp-basic.png">
    <meta property="og:url" content="https://cfcbazar.42web.io/diy/opamp/">
    <meta property="og:type" content="article">

    <link rel="stylesheet" href="/css/styles.css">
    <script src="/js/scripts.js" defer></script>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Understanding Op-Amps — The 3 Basic Configurations</h1>
    </div>

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

    <!-- NEGATIVE FEEDBACK -->
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

    <!-- POSITIVE FEEDBACK -->
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

    <!-- NO FEEDBACK -->
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

    <!-- SUMMARY -->
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

<footer class="footer">
    © CfCbazar — DIY Electronics
</footer>

</body>
</html>