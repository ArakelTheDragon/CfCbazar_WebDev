<?php
// ============================================================================
// CfCbazar CAN Diagnostic Emulator
// File: /diy/autodiag/index.php
// ============================================================================

$reusablePath = __DIR__ . '/../../includes/reusable.php';

if (!file_exists($reusablePath)) {
    http_response_code(500);
    exit('Required system files are missing.');
}

require_once $reusablePath;


// ============================================================================
// CfCbazar system
// ============================================================================

if (function_exists('trackVisit')) {
    trackVisit('diy/autodiag');
}

if (function_exists('enforce_https')) {
    enforce_https();
}

if (function_exists('checkSystemFlags')) {
    checkSystemFlags();
}


// ============================================================================
// Page title
// ============================================================================

$title = 'CfCbazar CAN Diagnostic Emulator — UDS & OBD-II Simulator';


// ============================================================================
// Standard CfCbazar layout
// ============================================================================

include_header($title);
include_menu();
showAdvertPopup();
render_top_userbar();

?>

<style>

/* ==========================================================================
   CAN Diagnostic Emulator
   ========================================================================== */

.autodiag-intro {
    text-align: center;
}

.autodiag-intro p:last-child {
    margin-bottom: 0;
}


/* ==========================================================================
   Command input
   ========================================================================== */

.autodiag-command-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.autodiag-command-input {
    flex: 1 1 auto;
    width: auto !important;
    min-width: 0;
    margin: 0;
}

.autodiag-command-button {
    flex: 0 0 120px;
    width: 120px !important;
    margin: 0;
}


/* ==========================================================================
   Console
   ========================================================================== */

.autodiag-console {
    background: #000;
    color: #00ff00;
    min-height: 220px;
    max-height: 360px;
    padding: 16px;
    overflow-y: auto;
    overflow-x: auto;

    font-family:
        Consolas,
        "Courier New",
        monospace;

    font-size: .95rem;
    line-height: 1.5;

    border-radius: var(--radius-small);

    white-space: pre-wrap;
    word-break: break-word;
}

.autodiag-console-line {
    margin-bottom: 4px;
}


/* ==========================================================================
   ECU status
   ========================================================================== */

.autodiag-status-row {
    display: flex;
    justify-content: center;
    align-items: stretch;
    gap: 20px;
    flex-wrap: wrap;
}

.autodiag-status {
    min-width: 180px;
    padding: 14px 20px;

    text-align: center;
    font-weight: 700;

    border-radius: var(--radius-small);
}

.autodiag-status.off {
    background: #f1f3f5;
    color: var(--text-light);
}

.autodiag-status.on {
    background: var(--success-bg);
    color: var(--success);
}


/* ==========================================================================
   Dashboard
   ========================================================================== */

.autodiag-dashboard {
    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(180px, 1fr)
        );

    gap: 24px;
}

.autodiag-control {
    min-width: 0;
}

.autodiag-control label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.autodiag-control input[type="range"] {
    width: 100%;
}

.autodiag-value {
    color: var(--primary-dark);
    font-weight: 700;
}


/* ==========================================================================
   Protocol descriptions
   ========================================================================== */

.autodiag-command-list p {
    margin-bottom: 14px;
}

.autodiag-command-list p:last-child {
    margin-bottom: 0;
}

.autodiag-code {
    display: inline-block;
    padding: 2px 6px;

    background: #f3f5f7;

    border-radius: 5px;

    font-family:
        Consolas,
        "Courier New",
        monospace;
}


/* ==========================================================================
   Responsive
   ========================================================================== */

@media (max-width: 992px) {

    .autodiag-dashboard {
        grid-template-columns:
            repeat(
                2,
                minmax(180px, 1fr)
            );
    }

}


@media (max-width: 600px) {

    .autodiag-command-row {
        flex-direction: column;
        align-items: stretch;
    }

    .autodiag-command-input {
        width: 100% !important;
    }

    .autodiag-command-button {
        width: 100% !important;
        flex-basis: auto;
    }

    .autodiag-dashboard {
        grid-template-columns: 1fr;
    }

    .autodiag-status-row {
        flex-direction: column;
    }

    .autodiag-status {
        width: 100%;
    }

}

</style>


<!-- ==========================================================================
     MAIN CONTENT
     ========================================================================== -->

<div class="container">


    <!-- ======================================================================
         INTRO
         ====================================================================== -->

    <div class="card autodiag-intro">

        <h1>CAN Diagnostic Emulator</h1>

        <p>
            Simulate UDS and OBD-II style diagnostic commands,
            ECU power states, engine status and vehicle sensor values.
        </p>

        <p class="text-muted">
            This is an educational software simulator.
            It does not communicate with a real vehicle,
            ECU, OBD-II adapter or CAN bus.
        </p>

    </div>


    <!-- ======================================================================
         COMMAND INPUT
         ====================================================================== -->

    <div class="card">

        <h2>Send AT Command</h2>

        <div class="autodiag-command-row">

            <input
                type="text"
                id="commandInput"
                class="autodiag-command-input"
                placeholder="Enter AT command, e.g. AT+RDI=0123"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                aria-label="CAN diagnostic AT command"
            >

            <button
                type="button"
                id="sendCommandButton"
                class="autodiag-command-button"
            >
                Send
            </button>

        </div>

        <p class="text-muted mt-15">

            Try
            <code>AT+PWRON</code>,
            then
            <code>AT+ENGINEON</code>,
            followed by
            <code>AT+RDI=0123</code>.

        </p>

    </div>


    <!-- ======================================================================
         CONSOLE
         ====================================================================== -->

    <div class="card">

        <h2>Console Output</h2>

        <div
            id="consoleLog"
            class="autodiag-console"
            role="log"
            aria-live="polite"
            aria-label="Diagnostic console output"
        ></div>

        <div class="text-right mt-10">

            <button
                type="button"
                id="clearConsoleButton"
                style="width:auto;"
            >
                Clear Console
            </button>

        </div>

    </div>


    <!-- ======================================================================
         ECU STATUS
         ====================================================================== -->

    <div class="card">

        <h2>ECU &amp; Engine Status</h2>

        <div class="autodiag-status-row">

            <div
                id="ignitionStatus"
                class="autodiag-status off"
                aria-live="polite"
            >
                Ignition: OFF
            </div>

            <div
                id="engineStatus"
                class="autodiag-status off"
                aria-live="polite"
            >
                Engine: OFF
            </div>

        </div>

    </div>


    <!-- ======================================================================
         VEHICLE DASHBOARD
         ====================================================================== -->

    <div class="card">

        <h2>Vehicle Dashboard</h2>

        <div class="autodiag-dashboard">


            <!-- Speed -->

            <div class="autodiag-control">

                <label for="speed">

                    Speed:
                    <span
                        id="speedValue"
                        class="autodiag-value"
                    >0</span>

                    km/h

                </label>

                <input
                    type="range"
                    id="speed"
                    min="0"
                    max="200"
                    value="0"
                    step="1"
                    aria-label="Vehicle speed"
                >

            </div>


            <!-- RPM -->

            <div class="autodiag-control">

                <label for="rpm">

                    RPM:
                    <span
                        id="rpmValue"
                        class="autodiag-value"
                    >0</span>

                </label>

                <input
                    type="range"
                    id="rpm"
                    min="0"
                    max="8000"
                    value="0"
                    step="100"
                    aria-label="Engine RPM"
                >

            </div>


            <!-- Brake -->

            <div class="autodiag-control">

                <label for="brake">

                    Brake:
                    <span
                        id="brakeValue"
                        class="autodiag-value"
                    >0</span>

                    %

                </label>

                <input
                    type="range"
                    id="brake"
                    min="0"
                    max="100"
                    value="0"
                    step="1"
                    aria-label="Brake pedal position"
                >

            </div>


            <!-- Gas -->

            <div class="autodiag-control">

                <label for="gas">

                    Gas Pedal:
                    <span
                        id="gasValue"
                        class="autodiag-value"
                    >0</span>

                    %

                </label>

                <input
                    type="range"
                    id="gas"
                    min="0"
                    max="100"
                    value="0"
                    step="1"
                    aria-label="Gas pedal position"
                >

            </div>

        </div>

    </div>


    <!-- ======================================================================
         COMMAND DESCRIPTIONS
         ====================================================================== -->

    <div class="card autodiag-command-list">

        <h2>Command Descriptions</h2>

        <p>

            <strong>AT+PWRON</strong>:
            Powers on the ECU.

            CAN:
            <code class="autodiag-code">
                0x10 01
            </code>

            →

            Response:
            <code class="autodiag-code">
                0x50 01
            </code>

        </p>


        <p>

            <strong>AT+ENGINEON</strong>:
            Starts the engine.

            CAN:
            <code class="autodiag-code">
                0x10 03
            </code>

            →

            Response:
            <code class="autodiag-code">
                0x50 03
            </code>

        </p>


        <p>

            <strong>AT+RDI=0123</strong>:
            Read speed and RPM.

            Request:
            <code class="autodiag-code">
                0x22 0123
            </code>

            →

            Response:
            <code class="autodiag-code">
                0x62 0123 [Speed] [RPM]
            </code>

        </p>


        <p>

            <strong>AT+RDI=0456</strong>:
            Read brake and gas pedal values.

            Request:
            <code class="autodiag-code">
                0x22 0456
            </code>

            →

            Response:
            <code class="autodiag-code">
                0x62 0456 [Brake] [Gas]
            </code>

        </p>


        <p>

            <strong>AT+RDI=0789</strong>:
            Read ignition and engine state.

            Request:
            <code class="autodiag-code">
                0x22 0789
            </code>

            →

            Response:
            <code class="autodiag-code">
                0x62 0789 [Ign] [Eng]
            </code>

        </p>


        <p class="text-muted">

            Negative response examples:

            <code class="autodiag-code">
                0x7F 22 11
            </code>

            ECU unavailable

            and

            <code class="autodiag-code">
                0x7F 22 33
            </code>

            engine unavailable.

        </p>

    </div>


    <!-- ======================================================================
         ABOUT
         ====================================================================== -->

    <div class="card">

        <h2>About This Emulator</h2>

        <p>

            The CfCbazar CAN Diagnostic Emulator is designed
            for experimenting with simplified UDS and OBD-II
            diagnostic concepts.

        </p>

        <p>

            All commands and responses are simulated locally
            in your browser. No diagnostic command is transmitted
            to an actual vehicle.

        </p>

    </div>


</div>


<!-- ==========================================================================
     JAVASCRIPT
     ========================================================================== -->

<script>
(function () {

    'use strict';


    // ========================================================================
    // Simulator state
    // ========================================================================

    let ecuPoweredOn = false;
    let engineOn = false;


    // ========================================================================
    // DOM references
    // ========================================================================

    const commandInput =
        document.getElementById('commandInput');

    const sendCommandButton =
        document.getElementById('sendCommandButton');

    const clearConsoleButton =
        document.getElementById('clearConsoleButton');

    const consoleLog =
        document.getElementById('consoleLog');

    const ignitionStatus =
        document.getElementById('ignitionStatus');

    const engineStatus =
        document.getElementById('engineStatus');


    // ========================================================================
    // Console
    // ========================================================================

    function logMessage(message) {

        const line =
            document.createElement('div');

        line.className =
            'autodiag-console-line';

        // Use textContent so commands cannot inject HTML.
        line.textContent = message;

        consoleLog.appendChild(line);

        consoleLog.scrollTop =
            consoleLog.scrollHeight;
    }


    function clearConsole() {

        consoleLog.replaceChildren();

    }


    // ========================================================================
    // Dashboard
    // ========================================================================

    function updateValue(id) {

        const input =
            document.getElementById(id);

        const output =
            document.getElementById(id + 'Value');

        if (!input || !output) {
            return;
        }

        output.textContent =
            input.value;
    }


    function updateAllValues() {

        updateValue('speed');
        updateValue('rpm');
        updateValue('brake');
        updateValue('gas');

    }


    // ========================================================================
    // ECU status
    // ========================================================================

    function updateStatusDisplay() {

        ignitionStatus.textContent =
            'Ignition: ' +
            (ecuPoweredOn ? 'ON' : 'OFF');

        ignitionStatus.classList.toggle(
            'on',
            ecuPoweredOn
        );

        ignitionStatus.classList.toggle(
            'off',
            !ecuPoweredOn
        );


        engineStatus.textContent =
            'Engine: ' +
            (engineOn ? 'ON' : 'OFF');

        engineStatus.classList.toggle(
            'on',
            engineOn
        );

        engineStatus.classList.toggle(
            'off',
            !engineOn
        );

    }


    // ========================================================================
    // Convert a value to one hexadecimal byte
    // ========================================================================

    function byteHex(value) {

        value =
            Number(value);

        if (!Number.isFinite(value)) {
            value = 0;
        }

        value =
            Math.max(
                0,
                Math.min(
                    255,
                    Math.round(value)
                )
            );

        return value
            .toString(16)
            .padStart(2, '0')
            .toUpperCase();
    }


    // ========================================================================
    // Convert RPM to a 16-bit hexadecimal value
    // ========================================================================

    function rpmHex() {

        let value =
            Number(
                document.getElementById('rpm').value
            );

        if (!Number.isFinite(value)) {
            value = 0;
        }

        value =
            Math.max(
                0,
                Math.min(
                    8000,
                    Math.round(value)
                )
            );

        return value
            .toString(16)
            .padStart(4, '0')
            .toUpperCase();
    }


    // ========================================================================
    // Send command
    // ========================================================================

    function sendCommand() {

        const rawInput =
            commandInput.value.trim();

        if (!rawInput) {
            return;
        }

        const command =
            rawInput
                .toUpperCase()
                .replace(/\s+/g, '');

        logMessage(
            '> ' + command
        );

        const response =
            processATCommand(command);

        logMessage(response);

        commandInput.value = '';

        commandInput.focus();
    }


    // ========================================================================
    // Process AT command
    // ========================================================================

    function processATCommand(command) {


        // ====================================================================
        // ECU POWER ON
        // ====================================================================

        if (command === 'AT+PWRON') {

            ecuPoweredOn = true;

            updateStatusDisplay();

            return (
                'CAN [0x10 01] → ' +
                'Response: 0x50 01'
            );
        }


        // ====================================================================
        // ENGINE ON
        // ====================================================================

        if (command === 'AT+ENGINEON') {

            if (!ecuPoweredOn) {

                return (
                    'ERROR: ECU must be powered on first! ' +
                    'Response: 0x7F 10 11'
                );
            }

            engineOn = true;

            updateStatusDisplay();

            return (
                'CAN [0x10 03] → ' +
                'Response: 0x50 03'
            );
        }


        // ====================================================================
        // READ DATA BY IDENTIFIER
        // ====================================================================

        if (command.startsWith('AT+RDI=')) {


            // ----------------------------------------------------------------
            // ECU must be powered
            // ----------------------------------------------------------------

            if (!ecuPoweredOn) {

                return (
                    'ERROR: ECU is OFF! ' +
                    'Response: 0x7F 22 11'
                );
            }


            // ----------------------------------------------------------------
            // Engine must be running
            // ----------------------------------------------------------------

            if (!engineOn) {

                return (
                    'ERROR: Engine is OFF! ' +
                    'Response: 0x7F 22 33'
                );
            }


            const identifier =
                command
                    .substring(7)
                    .trim();


            switch (identifier) {


                // ============================================================
                // Speed + RPM
                // ============================================================

                case '0123': {

                    const speed =
                        document.getElementById('speed').value;

                    const rpm =
                        rpmHex();

                    return (
                        'CAN [0x22 0123] → ' +
                        'Response: 0x62 0123 ' +
                        byteHex(speed) +
                        ' ' +
                        rpm
                    );
                }


                // ============================================================
                // Brake + Gas
                // ============================================================

                case '0456': {

                    const brake =
                        document.getElementById('brake').value;

                    const gas =
                        document.getElementById('gas').value;

                    return (
                        'CAN [0x22 0456] → ' +
                        'Response: 0x62 0456 ' +
                        byteHex(brake) +
                        ' ' +
                        byteHex(gas)
                    );
                }


                // ============================================================
                // Ignition + Engine
                // ============================================================

                case '0789':

                    return (
                        'CAN [0x22 0789] → ' +
                        'Response: 0x62 0789 ' +
                        (ecuPoweredOn ? '01' : '00') +
                        ' ' +
                        (engineOn ? '01' : '00')
                    );


                // ============================================================
                // Unknown identifier
                // ============================================================

                default:

                    return (
                        'ERROR: Unknown RDI Identifier! ' +
                        'Response: 0x7F 22 12'
                    );
            }
        }


        // ====================================================================
        // UNKNOWN COMMAND
        // ====================================================================

        return (
            'ERROR: Unknown command!'
        );

    }


    // ========================================================================
    // Events
    // ========================================================================

    sendCommandButton.addEventListener(
        'click',
        sendCommand
    );


    clearConsoleButton.addEventListener(
        'click',
        clearConsole
    );


    commandInput.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Enter') {

                event.preventDefault();

                sendCommand();
            }

        }
    );


    // ========================================================================
    // Dashboard slider events
    // ========================================================================

    [
        'speed',
        'rpm',
        'brake',
        'gas'
    ].forEach(function (id) {

        const input =
            document.getElementById(id);

        if (input) {

            input.addEventListener(
                'input',
                function () {

                    updateValue(id);

                }
            );

        }

    });


    // ========================================================================
    // Initial state
    // ========================================================================

    updateAllValues();

    updateStatusDisplay();

    logMessage(
        'CfCbazar CAN Diagnostic Emulator ready.'
    );

    logMessage(
        'Start with: AT+PWRON'
    );

})();
</script>


<?php

// ============================================================================
// Source code
// ============================================================================

cfc_footer(
    'https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/autodiag',
    'Tool Source Code'
);


// ============================================================================
// Standard CfCbazar footer
// ============================================================================

include_footer();

?>
