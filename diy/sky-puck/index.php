<?php
// Sky Stream Puck & TV Datasheet Simulator - Full Layout with Expanded Troubleshooting
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sky Stream Puck & TV - Technical Datasheet Simulator</title>
    <style>
        :root {
            --ic-bg: #1c1c1c;
            --ic-text: #ffffff;
            --pin-lead: #c0c0c0;
            --pin-pad: #d4af37;
            --sky-blue: #0288d1;
            --power-red: #d32f2f;
            --grid-color: #e8e8e8;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f0f0f0;
            color: #111;
            padding: 20px;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        h1 {
            margin: 0 0 5px 0;
            font-family: Arial, sans-serif;
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .subtitle {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 25px;
        }

        /* Expanded Diagram Canvas */
        .diagram-container {
            position: relative;
            width: 1200px;
            height: 560px;
            background: #fff;
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 20px 20px;
            border: 2px solid #000;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            padding: 20px;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* IC Package Design */
        .chip-box {
            position: absolute;
            background: linear-gradient(145deg, #222, #111);
            color: var(--ic-text);
            border-radius: 4px;
            box-shadow: 3px 5px 12px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            letter-spacing: 2px;
            z-index: 2;
        }

        /* Repositioned Component Boxes */
        .puck-chip {
            top: 140px;
            left: 360px;
            width: 150px;
            height: 250px;
            border: 1px solid #444;
        }

        .tv-chip {
            top: 70px;
            left: 880px;
            width: 200px;
            height: 400px;
            border: 1px solid #444;
        }

        /* IC Orientation Notch & Dot */
        .notch {
            width: 20px;
            height: 10px;
            background: #fff;
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
        }

        .pin1-dot {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 6px;
            height: 6px;
            background: #666;
            border-radius: 50%;
        }

        .chip-label {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-family: Arial, sans-serif;
            text-shadow: 0 1px 2px #000;
        }

        /* High-Detail IC Pins */
        .pin-wrapper {
            position: absolute;
            display: flex;
            align-items: center;
            z-index: 3;
        }

        .pin-lead {
            width: 18px;
            height: 8px;
            background: linear-gradient(to bottom, #e6e6e6, #999, #888);
            border: 1px solid #555;
            box-shadow: 0 2px 3px rgba(0,0,0,0.2);
            position: relative;
        }

        .pin-num {
            position: absolute;
            font-size: 9px;
            font-weight: bold;
            color: #222;
            background: var(--pin-pad);
            border: 1px solid #997a15;
            border-radius: 2px;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Opaque Label Badges to Avoid Line Overlap */
        .signal-label {
            position: absolute;
            font-size: 0.8rem;
            font-weight: bold;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
            z-index: 4;
            background: rgba(255, 255, 255, 0.92);
            padding: 2px 6px;
            border-radius: 3px;
        }

        .opt-tag {
            color: #666;
            font-size: 0.68rem;
            font-style: italic;
            font-weight: normal;
        }

        /* SVG Wire Canvas */
        svg.wire-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        /* UK 3-Pin Mains Plug Box */
        .plug-box {
            position: absolute;
            top: 180px;
            left: 20px;
            width: 110px;
            height: 70px;
            border: 2px solid #333;
            background: #fff;
            box-shadow: 2px 2px 6px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.75rem;
            font-weight: bold;
            z-index: 2;
        }

        /* Controls Panel */
        .controls {
            margin-top: 20px;
            padding: 15px 25px;
            background: #fff;
            border: 2px solid #222;
            width: 1200px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 3px;
            color: #fff;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .status-on { background-color: #2e7d32; }
        .status-off { background-color: #c62828; }

        button {
            padding: 8px 14px;
            cursor: pointer;
            font-family: inherit;
            font-weight: bold;
            background: #222;
            color: #fff;
            border: none;
            border-radius: 3px;
        }

        button:hover { background: #444; }

        /* Troubleshooting Section */
        .troubleshooting-panel {
            margin-top: 20px;
            width: 1200px;
            background: #fff;
            border: 2px solid #222;
            padding: 20px 25px;
            box-sizing: border-box;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .troubleshooting-panel h2 {
            margin-top: 0;
            font-family: Arial, sans-serif;
            font-size: 1.1rem;
            color: #d32f2f;
            border-bottom: 2px solid #222;
            padding-bottom: 8px;
            letter-spacing: 1px;
        }

        .error-item {
            margin-bottom: 18px;
        }

        .error-item:last-child {
            margin-bottom: 0;
        }

        .error-title {
            font-weight: bold;
            font-size: 0.95rem;
            color: #111;
            margin-bottom: 6px;
        }

        .error-fix {
            font-size: 0.85rem;
            color: #333;
            line-height: 1.5;
            background: #f9f9f9;
            padding: 10px 14px;
            border-left: 4px solid var(--sky-blue);
        }
    </style>
</head>
<body>

    <h1>SKY STREAM PUCK & SMART TV</h1>
    <div class="subtitle">IC Datasheet Pinout & System Interconnect Diagram</div>

    <div class="diagram-container">
        <!-- SVG Wire Canvas -->
        <svg class="wire-canvas">
            <!-- Power Cable Wire (Plug to Puck Pin 1) -->
            <path id="wire-power" d="M 130 215 L 210 215 L 210 200 L 342 200" 
                  fill="none" stroke="#d32f2f" stroke-width="3" stroke-dasharray="6,3" />

            <!-- HDMI Cable Wire (Puck Pin 4 HDMI Out to TV Pin 1 HDMI 1 IN) -->
            <path id="wire-hdmi" d="M 528 200 L 700 200 L 700 120 L 862 120" 
                  fill="none" stroke="#0288d1" stroke-width="4" />
        </svg>

        <!-- UK 3-PIN PLUG BOX -->
        <div class="plug-box">
            <span>230V AC</span>
            <span style="color:#d32f2f;">UK 3-PIN PLUG</span>
        </div>

        <!-- SKY PUCK CHIP -->
        <div class="puck-chip chip-box">
            <div class="notch"></div>
            <div class="pin1-dot"></div>
            <div class="chip-label">SKY PUCK</div>
        </div>

        <!-- PUCK PINS (LEFT SIDE) -->
        <!-- Pin 1: Power Input -->
        <div class="pin-wrapper" style="top: 196px; left: 342px;">
            <div class="pin-num" style="left: -16px;">1</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 172px; left: 200px;">
            Power <span style="color:#d32f2f;">&#x27F6;</span>
        </div>

        <!-- Pin 2: Ethernet (Optional) -->
        <div class="pin-wrapper" style="top: 266px; left: 342px;">
            <div class="pin-num" style="left: -16px;">2</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 262px; left: 160px;">
            Ethernet <span class="opt-tag">(OPTIONAL)</span> <span style="color:#333;">&#x27F7;</span>
        </div>

        <!-- PUCK PINS (RIGHT SIDE) -->
        <!-- Pin 3: RF Aerial Input (Always Unused) -->
        <div class="pin-wrapper" style="top: 266px; left: 510px;">
            <div class="pin-lead"></div>
            <div class="pin-num" style="right: -16px;">3</div>
        </div>
        <div class="signal-label" style="top: 262px; left: 545px;">
            <span style="color:#888;">&#x27F6;</span> RF_AERIAL <span class="opt-tag">(UNUSED)</span>
        </div>

        <!-- Pin 4: HDMI Out -->
        <div class="pin-wrapper" style="top: 196px; left: 510px;">
            <div class="pin-lead"></div>
            <div class="pin-num" style="right: -16px;">4</div>
        </div>
        <div class="signal-label" style="top: 172px; left: 545px;">
            <span style="color:#0288d1;">&#x27F6;</span> HDMI
        </div>


        <!-- SMART TV CHIP -->
        <div class="tv-chip chip-box">
            <div class="notch"></div>
            <div class="pin1-dot"></div>
            <div class="chip-label">SMART TV PANEL</div>
        </div>

        <!-- TV PINS (LEFT SIDE) -->
        <!-- Pin 1: HDMI 1 IN (Connected to Puck) -->
        <div class="pin-wrapper" style="top: 116px; left: 862px;">
            <div class="pin-num" style="left: -16px;">1</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 112px; left: 765px;">
            HDMI1 <span style="color:#0288d1;">&#x27F6;</span>
        </div>

        <!-- Pin 2: HDMI 2 IN -->
        <div class="pin-wrapper" style="top: 176px; left: 862px;">
            <div class="pin-num" style="left: -16px;">2</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 172px; left: 720px;">
            HDMI2_IN <span class="opt-tag">(AUX)</span> <span style="color:#333;">&#x27F6;</span>
        </div>

        <!-- Pin 3: HDMI 3 IN -->
        <div class="pin-wrapper" style="top: 236px; left: 862px;">
            <div class="pin-num" style="left: -16px;">3</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 232px; left: 720px;">
            HDMI3_IN <span class="opt-tag">(AUX)</span> <span style="color:#333;">&#x27F6;</span>
        </div>

        <!-- Pin 4: Optical Audio Out -->
        <div class="pin-wrapper" style="top: 296px; left: 862px;">
            <div class="pin-num" style="left: -16px;">4</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 292px; left: 690px;">
            OPTICAL_OUT <span class="opt-tag">(SOUNDBAR)</span> <span style="color:#333;">&#x27F6;</span>
        </div>

        <!-- Pin 5: Aerial In -->
        <div class="pin-wrapper" style="top: 356px; left: 862px;">
            <div class="pin-num" style="left: -16px;">5</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 352px; left: 710px;">
            RF_AERIAL <span class="opt-tag">(OPTIONAL)</span> <span style="color:#333;">&#x27F6;</span>
        </div>

        <!-- Pin 6: TV Mains AC Input -->
        <div class="pin-wrapper" style="top: 416px; left: 862px;">
            <div class="pin-num" style="left: -16px;">6</div>
            <div class="pin-lead"></div>
        </div>
        <div class="signal-label" style="top: 412px; left: 760px;">
            AC_MAINS <span style="color:#d32f2f;">&#x27F6;</span>
        </div>
    </div>

    <!-- SYSTEM CONTROL PANEL -->
    <div class="controls">
        <div>
            <strong>3-Pin Power Cable:</strong> 
            <span id="plug-status" class="status-badge status-on">CONNECTED</span>
        </div>
        <div>
            <strong>HDMI Cable (Puck &#x27F6; TV HDMI 1):</strong> 
            <span id="hdmi-status" class="status-badge status-on">CONNECTED</span>
        </div>
        <div>
            <button onclick="toggleCable('power')">Toggle Power Cable</button>
            <button onclick="toggleCable('hdmi')">Toggle HDMI Cable</button>
        </div>
    </div>

    <!-- TROUBLESHOOTING & ERRORS SECTION -->
    <div class="troubleshooting-panel">
        <h2>ERRORS & TROUBLESHOOTING GUIDE</h2>
        
        <div class="error-item">
            <div class="error-title">1. No signal on the TV</div>
            <div class="error-fix">
                Switch the TV to <strong>HDMI1</strong> where Sky is connected by using the source/input button on the TV remote (not the Sky remote).
            </div>
        </div>

        <div class="error-item">
            <div class="error-title">2. The puck is stuck on software update</div>
            <div class="error-fix">
                Connect an ethernet cable to the puck, unplug the power and plug it back in, wait 1 hour, switch on the puck and do the setup, go to <strong>Settings &#x2192; Network &#x2192; Status &#x2192; Reset</strong>, connect to Wi-Fi, then remove the ethernet cable.<br><br>
                <em>If the router is far from the TV:</em> Unplug the HDMI cable from the puck, take the puck to the router, connect it with an ethernet cable, press the power button on the puck, plug in the power cable to the puck, hold the power button on the puck for 30 seconds, and leave it there with the power and ethernet plugged in for 1 hour.
            </div>
        </div>

        <div class="error-item">
            <div class="error-title">3. The puck gets stuck on enter your PIN or enter your new PIN</div>
            <div class="error-fix">
                Take out the power cable, put it back in, and leave the puck like this for 15 minutes.
            </div>
        </div>
    </div>

    <script>
        let powerConnected = true;
        let hdmiConnected = true;

        function toggleCable(cable) {
            if (cable === 'power') {
                powerConnected = !powerConnected;
                const wire = document.getElementById('wire-power');
                const status = document.getElementById('plug-status');
                
                wire.style.display = powerConnected ? 'block' : 'none';
                status.textContent = powerConnected ? 'CONNECTED' : 'DISCONNECTED';
                status.className = 'status-badge ' + (powerConnected ? 'status-on' : 'status-off');
            } else if (cable === 'hdmi') {
                hdmiConnected = !hdmiConnected;
                const wire = document.getElementById('wire-hdmi');
                const status = document.getElementById('hdmi-status');
                
                wire.style.display = hdmiConnected ? 'block' : 'none';
                status.textContent = hdmiConnected ? 'CONNECTED' : 'DISCONNECTED';
                status.className = 'status-badge ' + (hdmiConnected ? 'status-on' : 'status-off');
            }
        }
    </script>
</body>
</html>
