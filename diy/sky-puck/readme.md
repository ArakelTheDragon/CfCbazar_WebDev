# Sky Stream Puck & Smart TV Datasheet Simulator

An interactive, single-file PHP schematic simulator that visualizes hardware interconnects between a Sky Stream Puck and a Smart TV panel using an IC (Integrated Circuit) datasheet aesthetic.

---

## Features

* **Datasheet Layout**: Built with a technical schematic grid background, component notch indicators, and IC pinouts.
* **Interactive Wiring**: Toggle 3-Pin AC Mains and HDMI cables with dynamic status indicators.
* **High-Detail Components**: Dual-lead metallic IC pins, gold solder pads, and non-overlapping signal labels.
* **Troubleshooting Guide**: Integrated step-by-step panel for common setup and recovery issues.

---

## Interactive Pinout Overview

| Component | Pin | Label | Function / Destination |
| --- | --- | --- | --- |
| **Sky Puck** | Pin 1 | `Power` | Power Supply Connection |
|  | Pin 2 | `Ethernet` | Ethernet Port (Optional) |
|  | Pin 3 | `RF_AERIAL` | Unused |
|  | Pin 4 | `HDMI` | Video Output → **TV HDMI 1** |
| **Smart TV** | Pin 1 | `HDMI1` | Primary Input (Sky Puck) |
|  | Pin 2 | `HDMI2_IN` | Auxiliary Input |
|  | Pin 3 | `HDMI3_IN` | Auxiliary Input |
|  | Pin 4 | `OPTICAL_OUT` | Digital Audio Output (Soundbar) |
|  | Pin 5 | `RF_AERIAL` | Terrestrial TV Input (Optional) |
|  | Pin 6 | `AC_MAINS` | AC Power Input |

---

## Common Errors & Fixes

**1. No signal on the TV**

* Switch the TV input to **HDMI1** using the source button on your TV remote (not the Sky remote).

**2. Puck stuck on software update**

* Connect an ethernet cable to the puck, power-cycle it, and wait 1 hour.
* Complete setup, navigate to **Settings → Network → Status → Reset**, reconnect to Wi-Fi, and remove the ethernet cable.
* *Router far from TV*: Move the puck next to the router, connect ethernet and power, hold the power button for 30 seconds, and leave it connected for 1 hour.

**3. Stuck on PIN creation/entry**

* Unplug the power cable, reconnect it, and leave the puck powered on for 15 minutes.

---

## Quick Start

1. Save the code as `sky_puck.php`.
2. Serve via local server (e.g., PHP Built-in Server):
```bash
php -S localhost:8000

```


3. Open `http://localhost:8000/sky_puck.php` in any modern web browser.
