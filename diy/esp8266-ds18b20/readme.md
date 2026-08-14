This directory contains the code and documentation for interfacing a **DS18B20 1-Wire Digital Temperature Sensor** with an **ESP8266 (NodeMCU)** development board.

## 📌 Features

* **Single Data Pin:** Uses 1-Wire protocol on NodeMCU pin `D4` (`GPIO2`).
* **High Accuracy:** $\pm 0.5^\circ\text{C}$ accuracy from $-10^\circ\text{C}$ to $+85^\circ\text{C}$.
* **Wide Operating Range:** Operating temperatures from $-55^\circ\text{C}$ to $+125^\circ\text{C}$.
* **3.3V Logic Compatible:** Powered directly from the ESP8266 3.3V rail.

---

## 🔌 Hardware Connections

> **Important:** A **$4.7\text{k}\Omega$ pull-up resistor** is required between the **DQ (Data)** line and **3.3V** for stable 1-Wire communication.

| DS18B20 Pin | Function | ESP8266 (NodeMCU) |
| :--- | :--- | :--- |
| **Pin 1 (GND)** | Ground | `GND` |
| **Pin 2 (DQ)** | Data Line | `D4` (`GPIO2`) *(Pull-up to 3.3V)* |
| **Pin 3 (VDD)** | Power (3.0V – 5.5V) | `3V3` |

---

## 🛠️ Software Requirements

Before compiling, install the following libraries in the **Arduino IDE** via **Tools > Manage Libraries**:

1. **OneWire** by *Paul Stoffregen*
2. **DallasTemperature** by *Miles Burton*

---

## 🚀 Quick Start

1. Wire the DS18B20 to your ESP8266 according to the table above.
2. Open `index.php` or copy the Arduino sketch into your IDE.
3. Select board **NodeMCU 1.0 (ESP-12E Module)** and select your COM port.
4. Upload the code and open the **Serial Monitor** set to **`115200` baud**.

---

## 📁 File Structure

```text
├── index.php      # Main PHP tutorial page & embedded Arduino C++ source code
└── README.md      # Documentation & pinout reference

📄 License
Distributed under the MIT License. See LICENSE in the main repo for details.


