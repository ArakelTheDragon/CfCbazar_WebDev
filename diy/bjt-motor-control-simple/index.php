<?php
/**
 * CfCbazar DIY Section - 12V DC Motor Control with Darlington BJT
 * File: /diy/bjt-motor-control-simple/index.php
 */

declare(strict_types=1);

// Include master reusable function registry
require_once __DIR__ . '/../../includes/reusable.php';

// Log page view hit for analytics
if (function_exists('trackVisit')) {
    trackVisit('diy_bjt_motor');
}

// Render site header & menu
if (function_exists('include_header')) {
    include_header('DIY: Simple 12V DC Motor Control with Darlington BJT');
}
if (function_exists('include_menu')) {
    include_menu();
}
?>

<main class="container my-4">
    <article class="card shadow-sm p-4">
        <h1 class="mb-3">DIY: Simple 12V DC Motor Control with Darlington BJT</h1>
        <p class="text-muted">Learn how to safely drive a 12V DC motor using a high-gain Darlington NPN transistor with pull-up resistor logic.</p>
        
        <hr class="my-4">

        <h2>1. Circuit Schematic</h2>
        <p>Below is the complete schematic diagram for the low-side Darlington motor switch:</p>
        
        <div class="text-center my-4">
            <img src="schematic.png" 
                 alt="12V DC Motor Low-Side Darlington Switch Driver Circuit Schematic" 
                 class="img-fluid rounded border shadow-sm" 
                 style="max-width: 100%; height: auto;">
            <caption class="d-block mt-2 text-muted">Figure 1: 12V DC Motor Driver Schematic with Darlington BJT (TIP120) and 10kΩ Pull-Up Resistor.</caption>
        </div>

        <h2>2. Component List</h2>
        <ul class="list-group list-group-flush mb-4">
            <li class="list-group-item"><strong>Transistor (Q1):</strong> TIP120 Darlington NPN BJT (or equivalent $h_{FE} > 1000$)</li>
            <li class="list-group-item"><strong>Motor (M1):</strong> 12V DC Motor (~100mA nominal load)</li>
            <li class="list-group-item"><strong>Flyback Diode (D1):</strong> 1N4001 General-Purpose Rectifier Diode</li>
            <li class="list-group-item"><strong>Pull-Up Resistor (R2):</strong> 10kΩ, 1/8W</li>
            <li class="list-group-item"><strong>Base Series Resistor (R1):</strong> 1kΩ, 1/4W</li>
            <li class="list-group-item"><strong>Power Supply:</strong> +12V DC Main Rail & GND Common Return</li>
        </ul>

        <h2>3. How the Circuit Works</h2>
        <ol class="lh-lg mb-4">
            <li><strong>High Current Gain:</strong> The <strong>TIP120 Darlington BJT</strong> combines two internal transistors, providing an extremely high current gain ($h_{FE} \ge 1000$). This allows tiny base signals to easily switch higher motor currents without overloading control circuitry.</li>
            <li><strong>Pull-Up Active ON State:</strong> The 10kΩ pull-up resistor ($R_2$) holds the transistor base high when the input is floating, ensuring the transistor remains <strong>ON (Saturated)</strong> and the motor runs continuously by default.</li>
            <li><strong>Safe Inductive Clamping:</strong> The antiparallel 1N4001 flyback diode ($D_1$) placed across the motor terminals safely redirects reverse inductive voltage spikes generated when the motor is switched off, protecting $Q_1$ from voltage breakdown.</li>
        </ol>

        <h2>4. Control Logic Reference</h2>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Base Input Signal</th>
                        <th>Transistor State</th>
                        <th>Motor Operation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>HIGH (5V - 12V) / High-Impedance (Floating)</strong></td>
                        <td>ON (Saturated)</td>
                        <td><span class="badge bg-success">Motor Running</span></td>
                    </tr>
                    <tr>
                        <td><strong>LOW (Pulled to 0V / GND)</strong></td>
                        <td>OFF (Cut-off)</td>
                        <td><span class="badge bg-danger">Motor Stopped</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>
</main>

<?php
// Render site footer markup
if (function_exists('include_footer')) {
    include_footer();
}
?>
