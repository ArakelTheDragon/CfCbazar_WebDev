<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CfCbazar CAN Diagnostic Emulator — UDS & OBD‑II Simulator</title>

    <meta name="description" content="Use the CfCbazar CAN Diagnostic Emulator to simulate UDS and OBD‑II commands, ECU power states, engine status, speed, RPM, brake and gas readings.">
    <meta name="keywords" content="CAN emulator, UDS simulator, OBD-II emulator, automotive diagnostics, CfCbazar DIY, CAN bus tool, ECU simulator, AT commands">
    <meta name="author" content="CfCbazar">
    <link rel="canonical" href="https://CfCbazar.42web.io/diy/autodiag/">

    <meta property="og:title" content="CfCbazar CAN Diagnostic Emulator — UDS & OBD‑II Simulator">
    <meta property="og:description" content="Simulate CAN bus diagnostics with UDS/OBD‑II commands. Free automotive diagnostic emulator from CfCbazar DIY.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://CfCbazar.42web.io/diy/autodiag/">
    <meta property="og:image" content="/assets/cfcbazar-preview.png">

    <link rel="stylesheet" href="/css/styles.css">
</head>

<body>

<div class="container">

    <div class="header">
        <h1>CAN Diagnostic Emulator</h1>
    </div>

    <!-- COMMAND INPUT -->
    <div class="card">
        <h3>Send AT Command</h3>
        <input type="text" id="commandInput" placeholder="Enter AT command (e.g., AT+RDI=0123)" onkeydown="if(event.key==='Enter') sendCommand()">
        <button onclick="sendCommand()">Send</button>
    </div>

    <!-- CONSOLE -->
    <div class="card">
        <h3>Console Output</h3>
        <div id="consoleLog" style="
            background:black;
            color:lime;
            padding:10px;
            height:200px;
            overflow-y:auto;
            font-family:monospace;
            border-radius:8px;">
        </div>
    </div>

    <!-- ECU STATUS -->
    <div class="card">
        <h3>ECU & Engine Status</h3>
        <div style="display:flex; justify-content:space-around;">
            <div id="ignitionStatus" class="off">Ignition: OFF</div>
            <div id="engineStatus" class="off">Engine: OFF</div>
        </div>
    </div>

    <!-- DASHBOARD -->
    <div class="card">
        <h3>Vehicle Dashboard</h3>

        <div style="display:flex; justify-content:center; flex-wrap:wrap; gap:20px;">

            <div class="control">
                <label>Speed (km/h): <span id="speedValue">0</span></label>
                <input type="range" id="speed" min="0" max="200" value="0" oninput="updateValue('speed')">
            </div>

            <div class="control">
                <label>RPM: <span id="rpmValue">0</span></label>
                <input type="range" id="rpm" min="0" max="8000" value="0" oninput="updateValue('rpm')">
            </div>

            <div class="control">
                <label>Brake: <span id="brakeValue">0</span></label>
                <input type="range" id="brake" min="0" max="100" value="0" oninput="updateValue('brake')">
            </div>

            <div class="control">
                <label>Gas Pedal: <span id="gasValue">0</span></label>
                <input type="range" id="gas" min="0" max="100" value="0" oninput="updateValue('gas')">
            </div>

        </div>
    </div>

    <!-- COMMAND DESCRIPTIONS -->
    <div class="card">
        <h3>Command Descriptions</h3>

        <p><strong>AT+PWRON</strong>: Powers on the ECU → CAN: <code>0x10 01</code> → Response: <code>0x50 01</code></p>
        <p><strong>AT+ENGINEON</strong>: Starts engine → CAN: <code>0x10 03</code> → Response: <code>0x50 03</code></p>

        <p><strong>AT+RDI=0123</strong>: Read Speed & RPM → <code>0x22 0123</code> → <code>0x62 0123 [Speed] [RPM]</code></p>
        <p><strong>AT+RDI=0456</strong>: Read Brake & Gas → <code>0x22 0456</code> → <code>0x62 0456 [Brake] [Gas]</code></p>
        <p><strong>AT+RDI=0789</strong>: Read Ignition & Engine → <code>0x22 0789</code> → <code>0x62 0789 [Ign] [Eng]</code></p>

        <p class="muted">Negative responses: <code>0x7F 22 [Error]</code> if ECU/Engine is OFF.</p>
    </div>

</div>

<footer class="footer">
    © CfCbazar — Automotive DIY Tools
</footer>

<!-- ========================= -->
<!-- INLINE JAVASCRIPT SECTION -->
<!-- ========================= -->
<script>
let ecuPoweredOn = false;
let engineOn = false;

function logMessage(message) {
    let log = document.getElementById("consoleLog");
    log.innerHTML += message + "<br>";
    log.scrollTop = log.scrollHeight;
}

function updateValue(id) {
    document.getElementById(id + "Value").innerText =
        document.getElementById(id).value;
}

function sendCommand() {
    let input = document.getElementById("commandInput").value.trim();
    if (!input) return;

    logMessage("> " + input);
    let response = processATCommand(input);
    logMessage(response);
    document.getElementById("commandInput").value = "";
}

function processATCommand(command) {

    if (command === "AT+PWRON") {
        ecuPoweredOn = true;
        let ign = document.getElementById("ignitionStatus");
        ign.classList.remove("off");
        ign.classList.add("on");
        ign.innerText = "Ignition: ON";
        return "CAN [0x10 01] → Response: 0x50 01";
    }

    if (command === "AT+ENGINEON") {
        if (!ecuPoweredOn) return "ERROR: ECU must be powered on first!";
        engineOn = true;
        let eng = document.getElementById("engineStatus");
        eng.classList.remove("off");
        eng.classList.add("on");
        eng.innerText = "Engine: ON";
        return "CAN [0x10 03] → Response: 0x50 03";
    }

    if (command.startsWith("AT+RDI=")) {
        if (!ecuPoweredOn) return "ERROR: ECU is OFF! Response: 0x7F 22 11";
        if (!engineOn) return "ERROR: Engine is OFF! Response: 0x7F 22 33";

        let id = command.split("=")[1];

        switch (id) {
            case "0123":
                return `CAN [0x22 0123] → Response: 0x62 0123 ${hex("speed")} ${hex("rpm")}`;
            case "0456":
                return `CAN [0x22 0456] → Response: 0x62 0456 ${hex("brake")} ${hex("gas")}`;
            case "0789":
                return `CAN [0x22 0789] → Response: 0x62 0789 ${ecuPoweredOn ? "01" : "00"} ${engineOn ? "01" : "00"}`;
            default:
                return "ERROR: Unknown RDI Identifier! Response: 0x7F 22 12";
        }
    }

    return "ERROR: Unknown command!";
}

function hex(id) {
    return Number(document.getElementById(id).value)
        .toString(16)
        .padStart(2, "0")
        .toUpperCase();
}
</script>

</body>
</html>