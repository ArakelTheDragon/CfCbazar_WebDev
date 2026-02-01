<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Slot Machine – WorkTokens</title>
  <style>
    body { text-align: center; font-family: Arial, sans-serif; margin: 0; padding: 2rem; background: #f4f6f8; }
    .container { max-width: 400px; margin: auto; }
    .slot-container {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 5px;
      margin-top: 1rem;
    }
    .slot {
      width: 100%;
      aspect-ratio: 1;
      font-size: 2em;
      border-radius: 10px;
      background: linear-gradient(120deg, #ffcc00, #ff6600);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    button {
      width: 100%;
      margin-top: 15px;
      padding: 10px;
      font-size: 1.2em;
      background-color: #28a745;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    .balance, .result {
      font-size: 1.1em;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🎰 Slot Machine</h1>
    <div class="balance">Balance: <span id="balance">Loading...</span> WT</div>
    <div class="result">Last Spin Result: <span id="result">--</span></div>

    <div class="slot-container" id="slots">
      <!-- 15 slots -->
      <div class="slot">?</div><div class="slot">?</div><div class="slot">?</div><div class="slot">?</div><div class="slot">?</div>
      <div class="slot">?</div><div class="slot">?</div><div class="slot">?</div><div class="slot">?</div><div class="slot">?</div>
      <div class="slot">?</div><div class="slot">?</div><div class="slot">?</div><div class="slot">?</div><div class="slot">?</div>
    </div>

    <button onclick="spin()">Spin (0.1 WT)</button>
  </div>

  <script>
    const email = "<?php echo $email; ?>";
    let balance = 0.0;
    const symbols = ['🍒', '🍋', '🍊', '🍇', '🔔'];

    async function loadBalance() {
      const res = await fetch("/worker.php?email=" + encodeURIComponent(email));
      const data = await res.json();
      balance = parseFloat(data.tokens_earned);
      document.getElementById("balance").textContent = balance.toFixed(5);
    }

    function calculatePrizes(results) {
      let prize = 0;
      for (let i = 0; i < results.length - 1; i++) {
        let count = 1;
        while (i + count < results.length && results[i] === results[i + count]) count++;
        if (count === 2) prize += 0.001;
        else if (count === 3) prize += 0.01;
        else if (count === 4) prize += 1;
        else if (count >= 5) prize += 5;
        i += count - 1;
      }
      return prize;
    }

    async function spin() {
      if (balance < 0.1) {
        alert("Not enough WorkTokens!");
        return;
      }

      const slots = document.querySelectorAll(".slot");
      const results = [];

      slots.forEach(slot => {
        const sym = symbols[Math.floor(Math.random() * symbols.length)];
        slot.textContent = sym;
        results.push(sym);
      });

      const winnings = calculatePrizes(results);
      const net = winnings - 0.1;
      balance += net;

      document.getElementById("balance").textContent = balance.toFixed(5);
      document.getElementById("result").textContent = net.toFixed(5) + " WT";

      // Save to DB
      fetch("/worker.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `email=${encodeURIComponent(email)}&tokens=${net}`
      });
    }

    window.onload = loadBalance;
  </script>
</body>
</html>