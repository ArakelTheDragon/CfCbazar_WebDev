<?php
// /diy/lm317-basic/index.php — Simple LM317 Tutorial

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    if (function_exists('trackVisit')) {
        trackVisit("diy-lm317-basic");
    }
}

include_header();
include_menu();
render_top_userbar();
?>

<main class="container">

    <h1 class="page-title">🔧 LM317 — Basic Adjustable Linear Regulator</h1>

    <div class="card">
        <h2>📘 What is the LM317?</h2>
        <p>
            The <strong>LM317</strong> is a very simple <strong>linear voltage regulator</strong>.
            It takes a higher DC voltage (like 9–24V) and outputs a lower, adjustable voltage.
        </p>

        <p>
            It is called <strong>linear</strong> because it works by dropping excess voltage
            as heat — not by switching like modern buck converters.
        </p>

        <p>
            Output range: <strong>1.25V → ~37V</strong> (depending on input voltage).
        </p>
    </div>

    <div class="card">
        <h2>🔌 LM317 Pinout</h2>

        <p>The LM317 has three pins:</p>

        <p><strong>IN</strong> — This is where you feed the input voltage.</p>
        <p><strong>OUT</strong> — This is the regulated output voltage.</p>
        <p><strong>ADJ</strong> — This pin adjusts the output using resistors.</p>

        <img src="/diy/lm317-basic/img1.png"
             alt="LM317 Basic Circuit"
             style="max-width:420px; width:100%; margin:15px auto; display:block; border-radius:12px;">
    </div>

    <div class="card">
        <h2>⚙️ Basic Adjustable LM317 Circuit</h2>

        <p>This is the simplest LM317 adjustable regulator circuit:</p>

        <p>
            The output voltage is set using two resistors:
        </p>

        <p><strong>R1</strong> — A fixed resistor (usually 240Ω)</p>
        <p><strong>R2</strong> — A variable resistor (potentiometer)</p>

        <p>
            Turning the potentiometer changes the output voltage.
        </p>
    </div>

    <div class="card">
        <h2>📐 How to Calculate Output Voltage</h2>

        <p>The LM317 always keeps <strong>1.25V</strong> between OUT and ADJ.</p>

        <p>The formula is:</p>

        <blockquote>
            <strong>Vout = 1.25 × (1 + R2 / R1)</strong>
        </blockquote>

        <p>Example:</p>

        <p>R1 = 240Ω</p>
        <p>R2 = 720Ω</p>

        <p>
            <strong>Vout = 1.25 × (1 + 720/240) = 5V</strong>
        </p>

        <p>
            This is why the LM317 is popular — the math is extremely simple.
        </p>
    </div>

    <div class="card">
        <h2>🔻 Voltage Drop (Dropout Voltage)</h2>

        <p>
            The LM317 needs the input voltage to be at least
            <strong>3V higher</strong> than the output.
        </p>

        <blockquote>
            <strong>Vin ≥ Vout + 3V</strong>
        </blockquote>

        <p>Example:</p>

        <p>Desired output: 5V</p>
        <p>Minimum input: 8V</p>

        <p>
            If Vin is too low, the LM317 cannot regulate and the output will drop.
        </p>
    </div>

    <div class="card">
        <h2>🔥 When Does the LM317 Need a Heatsink?</h2>

        <p>
            Because the LM317 is a <strong>linear regulator</strong>, it burns off extra voltage
            as heat.
        </p>

        <p>The heat is:</p>

        <blockquote>
            <strong>Power Dissipation = (Vin − Vout) × Load Current</strong>
        </blockquote>

        <p>Example:</p>

        <p>Vin = 12V</p>
        <p>Vout = 5V</p>
        <p>Load = 0.5A</p>

        <p>
            <strong>Heat = (12 − 5) × 0.5 = 3.5W</strong>
        </p>

        <p>
            Anything above <strong>2–3W</strong> usually requires a heatsink.
        </p>

        <p>
            If the LM317 becomes too hot to touch → you need a heatsink.
        </p>
    </div>

    <div class="card">
        <h2>🛠️ Quick Summary</h2>

        <p>LM317 is a <strong>linear regulator</strong></p>
        <p>Adjustable from <strong>1.25V to ~37V</strong></p>
        <p>Needs <strong>Vin ≥ Vout + 3V</strong></p>
        <p>Output voltage set by <strong>R1 and R2</strong></p>
        <p>Heatsink needed if <strong>(Vin − Vout) × Iload > 2W</strong></p>
    </div>

</main>

    <?php
    cfc_footer(
        "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/lm317-basic",
        "LM317 basic GitHub Source Code"
    );
    ?>
   
<?php include_footer(); ?>

