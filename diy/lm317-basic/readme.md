# 🔧 LM317 Basic Adjustable Linear Regulator  
A simple, beginner‑friendly explanation of how the LM317 works, how to calculate its output voltage, and when it requires a heatsink. This page is part of the CfCbazar DIY Electronics series.

---

## 📘 Overview  
The LM317 is a **linear adjustable voltage regulator**.  
It takes a higher DC input voltage and outputs a lower, adjustable voltage between **1.25V and ~37V**, depending on the input.

This tutorial explains:  
- What the LM317 is  
- How its three pins work  
- How to build the basic adjustable circuit  
- How to calculate the output voltage  
- How to determine dropout voltage  
- When a heatsink is required  

---

## 🔌 LM317 Pinout  
The LM317 has three pins:

**IN** — Input voltage  
**OUT** — Regulated output  
**ADJ** — Adjustment pin used with resistors to set the output voltage

An example schematic is included in the project folder:

```
/diy/lm317-basic/img1.png
```

---

## ⚙️ Basic Adjustable Circuit  
The LM317 uses two resistors:

**R1** — Fixed resistor (typically 240Ω)  
**R2** — Adjustable resistor (potentiometer)

Changing R2 adjusts the output voltage.

---

## 📐 Output Voltage Formula  
The LM317 maintains **1.25V** between OUT and ADJ.

```
Vout = 1.25 × (1 + R2 / R1)
```

Example:  
R1 = 240Ω  
R2 = 720Ω  
Vout = 5V

---

## 🔻 Dropout Voltage  
The LM317 requires the input voltage to be at least **3V higher** than the output.

```
Vin ≥ Vout + 3V
```

Example:  
To get 5V out, you need at least 8V in.

---

## 🔥 Heatsink Requirements  
Because the LM317 is a **linear regulator**, it burns excess voltage as heat.

```
Power Dissipation = (Vin − Vout) × Load Current
```

If the LM317 dissipates more than **2–3W**, a heatsink is recommended.

---

## 📂 Project Structure  
```
/diy/lm317-basic/index.php
/diy/lm317-basic/img1.png
```

---

## 🧩 CfCbazar Template  
This page follows the standard CfCbazar template:

- Error reporting  
- Reusable include block  
- Header, menu, top userbar  
- Page content  
- Source code footer  
- GitHub link  
- include_footer()

---

## 🔗 Source Code  
The full source code is available here:

**GitHub:**  
https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/lm317-basic

---

## 📜 License  
MIT License — free to use and modify.

