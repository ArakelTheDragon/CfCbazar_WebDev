`/diy/btc-profit-calculator/readme.md`
---

# 📘 BTC Profit Calculator — README

## 🧾 Overview  
The **BTC Mining Profit Calculator** is a simple tool that shows how much Bitcoin you earn from cloud mining or ASIC mining over a selected period.  
It highlights the most important values:

- **Mined BTC for the full period**  
- **Service fee for the full period**  
- **Leftover BTC after fee**  
- **Final profit after subtracting upfront cost**

This makes it easy for beginners to understand mining profitability.

---

## 🔢 Inputs  
The calculator requires a few basic values:

### **Upfront Cost (USD)**  
The amount you paid for the mining contract or ASIC.

### **Total TH Purchased**  
Your mining power in terahashes.

### **Total Days**  
How long the mining runs.

### **Service Fee per TH/day (USD)**  
Daily maintenance fee.  
Default: **0.028 USD per TH/day**

### **BTC Price (USD)**  
Used to convert BTC earnings into USD.  
Default: **63,000 USD**

---

## ⚙️ How It Works  
The calculator uses a fixed mining rate:

**0.00000048 BTC per TH/day**

Then it computes:

1. **Mined BTC**  
2. **Mined USD**  
3. **Service Fee (USD + BTC)**  
4. **Leftover BTC after fee**  
5. **Leftover BTC converted to USD**  
6. **Final Profit (USD + BTC)**  
   - Formula:  
     **Final Profit = (Leftover BTC × BTC Price) – Upfront Cost**

All important values are highlighted for easy reading.

---

## 📦 Output Example  
```
Results (Full Period)
Mined BTC: 0.0072
Mined USD: 453.60

Service Fee (USD): 420.00
Service Fee (BTC): 0.00666666666667

Leftover BTC After Fee: 0.000533333333333
Leftover BTC After Fee (USD): 33.60

Final Profit net BTC - Upfront (USD): -65.40
Final Profit (BTC): -0.0010380952381
```

---

## 📁 File Location  
Place the calculator here:

```
/tools/btc-profit-calculator/index.php
```

This README belongs in:

```
/diy/btc-profit-calculator/readme.md
```
