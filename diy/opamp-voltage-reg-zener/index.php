<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Op-Amp Voltage Regulator with Zener Reference — CfCbazar DIY</title>

    <meta name="description" content="Learn how an op-amp, Zener diode, and NPN transistor form a stable adjustable voltage regulator.">
    <meta name="keywords" content="Op-Amp, Zener, Voltage Regulator, Electronics DIY, NPN transistor, CfCbazar DIY">
    <meta name="author" content="CfCbazar">
    <link rel="canonical" href="https://CfCbazar.42web.io/diy/opamp-voltage-reg-zener/">

    <meta property="og:title" content="Op-Amp Voltage Regulator Using Zener Reference — CfCbazar DIY">
    <meta property="og:description" content="A simple and effective adjustable voltage regulator using an op-amp, Zener diode, and NPN transistor.">
    <meta property="og:image" content="/diy/opamp-voltage-reg-zener/images/img.jpg">
    <meta property="og:url" content="https://CfCbazar.42web.io/diy/opamp-voltage-reg-zener/">
    <meta property="og:type" content="article">

    <link rel="stylesheet" href="/css/styles.css">
    <script src="/js/scripts.js" defer></script>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Op-Amp Voltage Regulator Using a Zener Reference & NPN Transistor</h1>
    </div>

    <div class="card">
        <img src="/diy/opamp-voltage-reg-zener/images/img.jpg" alt="Op-Amp Zener Voltage Regulator" style="width:100%; border-radius:12px;">
    </div>

    <div class="card">
        <h2>Circuit Diagram</h2>
        <p class="muted">Op-amp + Zener + NPN regulator</p>

        <img src="/diy/opamp-voltage-reg-zener/images/img.jpg" alt="Op-Amp Zener Regulator Circuit" style="width:100%; border-radius:12px; margin-top:10px;">

        <p class="muted" style="margin-top:10px;">
            This schematic shows the full regulator: Zener reference, potentiometer divider, op-amp control loop,
            and NPN pass transistor.
        </p>
    </div>

    <div class="card">
        <p class="muted">
            This circuit is a compact and effective way to generate a stable DC voltage using an op-amp, a Zener diode,
            and an NPN transistor. It behaves as a closed-loop voltage regulator, keeping the output voltage steady even
            when the load resistance changes.
        </p>
    </div>

    <!-- SECTION 1 -->
    <div class="card">
        <h2>1. Zener Reference Network</h2>
        <p class="muted">Stable voltage reference</p>

        <p>A Zener diode (≈11 V) is reverse‑biased through a 70 Ω resistor. This creates a stable reference voltage:</p>

        <ul>
            <li>≈11 V across the Zener</li>
            <li>≈1 V across the 70 Ω resistor</li>
            <li>≈14 mA Zener current</li>
        </ul>

        <p>
            This keeps the Zener in its proper regulation region, ensuring a clean and predictable reference voltage
            for the op-amp.
        </p>
    </div>

    <!-- SECTION 2 -->
    <div class="card">
        <h2>2. Adjustable Voltage Divider (R9)</h2>
        <p class="muted">Sets the output voltage</p>

        <p>
            A potentiometer (R9) samples the Zener voltage and feeds an adjustable fraction of it into the op-amp’s
            input. Turning the pot changes the desired output voltage.
        </p>

        <p>R9 is the main voltage control element of the regulator.</p>
    </div>

    <!-- SECTION 3 -->
    <div class="card">
        <h2>3. Op-Amp Control Loop</h2>
        <p class="muted">Closed-loop regulation</p>

        <p>The op-amp compares:</p>

        <ul>
            <li>The reference voltage (Zener + R9)</li>
            <li>The actual output voltage (from the transistor emitter)</li>
        </ul>

        <p>
            It then drives the base of the NPN transistor. The op-amp continuously adjusts its output so that the
            emitter voltage matches the reference voltage.
        </p>

        <p>This closed-loop action is what stabilizes the output.</p>
    </div>

    <!-- SECTION 4 -->
    <div class="card">
        <h2>4. NPN Transistor as a Pass Element</h2>
        <p class="muted">Supplies the load</p>

        <p>The NPN transistor (Q2) acts as a series pass transistor:</p>

        <ul>
            <li>The op-amp controls its base</li>
            <li>The emitter follows the base voltage minus V<sub>BE</sub></li>
            <li>The load is powered from the emitter</li>
        </ul>

        <p>
            Because the op-amp compensates for the V<sub>BE</sub> drop, the emitter voltage remains extremely stable.
        </p>
    </div>

    <!-- SECTION 5 -->
    <div class="card">
        <h2>5. Load Behavior</h2>
        <p class="muted">Voltage stays constant</p>

        <p>When the load resistance changes:</p>

        <ul>
            <li>The output voltage stays constant (regulated)</li>
            <li>The current changes according to Ohm’s law</li>
        </ul>

        <p>This is the defining behavior of a voltage regulator.</p>
    </div>

    <!-- SECTION 6 -->
    <div class="card">
        <h2>6. LED and Series Resistor</h2>
        <p class="muted">Part of the load</p>

        <p>
            The LED and its series resistor (R20) are simply part of the load. They receive the regulated voltage
            from the emitter and behave normally regardless of load changes elsewhere.
        </p>
    </div>

    <!-- SUMMARY -->
    <div class="card">
        <h2>Summary</h2>
        <p class="muted">Why this regulator works so well</p>

        <p>This circuit is a straightforward and reliable DC voltage regulator:</p>

        <ul>
            <li>The Zener diode provides a stable reference.</li>
            <li>The potentiometer sets the desired output voltage.</li>
            <li>The op-amp compares the reference with the output.</li>
            <li>The NPN transistor supplies the load with a regulated voltage.</li>
            <li>The output voltage remains stable even as the load current varies.</li>
        </ul>

        <p>
            It’s a clean example of how an op-amp can be used to build a precise, adjustable linear regulator with
            only a handful of components.
        </p>
    </div>

</div>

<footer class="footer">
    © CfCbazar — DIY Electronics
</footer>

</body>
</html>