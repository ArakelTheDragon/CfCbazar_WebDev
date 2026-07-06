# ⚡ Power Consumption Calculator  
A lightweight, browser‑based electricity usage calculator that helps users estimate **monthly energy consumption** and **costs** based on device wattage, quantity, and daily usage.

This tool is part of the **CfCbazar DIY Utilities** collection.

---

## 📌 Features

- ✔ Add multiple devices  
- ✔ Auto‑filled common household appliances  
- ✔ Calculates:
  - Daily kWh  
  - Monthly kWh  
  - Monthly cost  
- ✔ Adjustable price per kWh  
- ✔ Error handling with clear messages  
- ✔ Mobile‑friendly responsive layout  
- ✔ No backend required — runs entirely in the browser  
- ✔ SEO‑optimized metadata  
- ✔ Optional visit tracking via CfCbazar reusable module

---

## 🖼️ Screenshot

*(Add your own screenshot here)*

```
![Power Calculator Screenshot](assets/power-preview.png)
```

---

## 🚀 Live Demo

```
https://cfcbazar.42web.io/diy/power.php
```

---

## 📂 File Structure

```
/diy/power.php
/includes/reusable.php   (optional visit tracking)
/assets/power-preview.png
```

---

## 🧮 How It Works

The calculator uses the formula:

```
Monthly kWh = (Power(W) / 1000) × Hours per day × 30 × Quantity
Monthly Cost = Monthly kWh × Price per kWh
```

It also includes a **device efficiency factor (DF)** for more realistic estimates:

| Device            | DF  |
|-------------------|-----|
| LED Bulb          | 1.0 |
| LCD TV            | 0.8 |
| Refrigerator       | 0.1 |
| Air Conditioner    | 0.6 |
| Computer           | 0.7 |
| Laptop             | 0.5 |
| Microwave          | 1.0 |
| Fan               | 0.9 |
| Heater             | 0.7 |
| Other              | 1.0 |

---

## 📱 Mobile Friendly

The layout automatically adapts to small screens:

- Inputs stack vertically  
- Table scrolls horizontally if needed  
- Buttons remain large and touch‑friendly  

---

## 🛠️ Installation

### 1. Clone the repository

```bash
git clone https://github.com/yourusername/power-calculator.git
```

### 2. Upload to your server

Place the files inside your web root:

```
/public_html/diy/power.php
```

### 3. (Optional) Enable visit tracking

If you use CfCbazar’s reusable module:

```php
require_once __DIR__ . '/../../includes/reusable.php';
trackVisit("diy-power");
```

---

## 🧪 Example Usage

1. Enter your electricity price (e.g., `0.15` per kWh).  
2. Adjust device wattage, quantity, and hours per day.  
3. Click **Calculate Consumption & Cost**.  
4. View detailed results in a table.

---

## 🐛 Error Handling

If invalid input is detected:

- Missing or negative values  
- Zero hours  
- Non‑numeric entries  

The calculator displays a red error console with a helpful message.

---

## 🔒 No Backend Required

All calculations run in the browser using JavaScript.  
Backend is only used if you enable visit tracking.

---

## 📜 License

MIT License — free to use, modify, and distribute.

---

## 🤝 Contributing

Pull requests are welcome!  
If you want to add features (charts, export to CSV, dark mode), feel free to open an issue.

---

## ⭐ Acknowledgements

Part of the **CfCbazar DIY Tools** suite.  
Created by **Arak**.
