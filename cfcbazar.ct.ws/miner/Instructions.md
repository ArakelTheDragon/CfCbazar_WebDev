⚡ CfCbazar Miner Integration Guide

Welcome to the official guide for CfCbazar’s new browser-based mining feature! This update lets users earn platform credits (WorkToken or WorkTHR) simply by keeping a CfCbazar page open and letting their device contribute hashpower.

---

🛠️ What’s New

- ✅ CoinIMP JavaScript miner integration
- ✅ Real-time hashrate display
- ✅ CPU throttle slider (10%–100%)
- ✅ Auto-claiming rewards every second
- ✅ Option to earn either WorkToken or WorkTHR
- ✅ Mining stats tracked per user

---

🚀 How to Start Mining

1. Log in to your CfCbazar account  
   Visit cfcbazar.ct.ws and log in with your registered email.

2. Go to the mining page  
   Navigate to /index.php or any page with mining enabled.

3. Choose your reward type  
   Use the dropdown to select:
   - WorkToken — spendable platform credit
   - WorkTHR — mining throughput credit

4. Adjust CPU usage  
   Use the slider to control how much CPU the miner uses.  
   - 100% = full power  
   - 50% = half power  
   - 10% = minimal background mining

5. Watch your hashrate  
   The page shows:
   - Current hashrate (H/s)
   - Total hashes
   - Accepted hashes

6. Earn automatically  
   Rewards are claimed every second based on your accepted hashes.  
   - Example: 100 H/s for 1 hour ≈ 0.208 WorkTokens

---

📊 How Rewards Work

- The miner tracks accepted hashes.
- Every 3600 accepted hashes earns ~0.208 WorkTokens.
- Rewards are added to your account in real time.
- You can view your balance on the dashboard (/d.php).

---

🔐 Privacy & Security

- Mining only starts when you’re logged in.
- You can stop mining anytime by closing the page.
- No downloads or installations required.
- All mining is done in-browser using CoinIMP.

---

🧠 Tips

- Use a dedicated browser tab for mining.
- Lower CPU usage if you’re multitasking.
- Try mining overnight or during idle time.
- Switch between WorkToken and WorkTHR based on your goals.

---

🧩 Developer Notes

- Miner script: https://www.hostingcloud.racing/gODX.js
- Site key: hidden
- Backend logic uses accepted hashes to calculate rewards.
- Rewards are stored in the workers table:
  - tokens_earned for WorkToken
  - mintme for WorkTHR

---

📣 Join the Ecosystem

CfCbazar is more than mining — explore:

- 🎮 Games: /games.php
- 🔧 DIY tools: /features.php
- 💰 Withdrawals: /w.php
- 📡 Speed tests: /speed.php
- 📖 Help Center: /help/

---

🛠️ Contribute

Want to improve the miner or dashboard?  
Check out the repo: CfCbazar-WebDev on GitHub
