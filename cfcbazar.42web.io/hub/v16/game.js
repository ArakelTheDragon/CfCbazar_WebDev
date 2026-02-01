console.log("💾 game.js loaded");

// Player starts at level 0, XP 0 with basic Common gear.
let questionsList = [];
let currentQuestion = null;
let playerXP = 0;
let playerLevel = 0;
let equipment = {
  helmet: { name: "Common Helmet", count: 1 },
  armour: { name: "Common Armour", count: 1 },
  pants: { name: "Common Pants", count: 1 },
  boots: { name: "Common Boots", count: 1 },
  gloves: { name: "Common Gloves", count: 1 },
  baseLocation: { name: "Common Base", count: 1 }
};

document.addEventListener("DOMContentLoaded", () => {
  // Update player's tokens and gear from the database
  document.getElementById("fetchTokensBtn").addEventListener("click", fetchPlayerData);

  const startBtn = document.getElementById("startRoundBtn");
  if (startBtn) startBtn.addEventListener("click", startRound);

  window.startRound = startRound;
  window.rollDice = rollDice;
  window.submitAnswer = submitAnswer;

  fetchQuestions();
  fetchPlayerData(); // initial load from database
  updateDashboard();
});

function fetchQuestions() {
  fetch('questions.json')
    .then(response => response.json())
    .then(data => {
      questionsList = data.questions;
    })
    .catch(() => {
      alert("Failed to load questions.");
    });
}

function fetchPlayerData() {
  const email = getEmail();
  if (!email) return;

  fetch(`/get_tokens.php?email=${encodeURIComponent(email)}`)
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        document.getElementById("workTokens").innerText = "Error";
        alert(data.error);
      } else {
        const tokens = parseFloat(data.tokens_earned || 0);
        document.getElementById("workTokens").innerText = tokens.toFixed(2);

        playerXP = parseInt(data.exp || 0, 10);
        playerLevel = parseInt(data.level || 0, 10);

        // Update gear based on what's coming from the database
        equipment = {
          helmet: { name: data.helmet || "Common Helmet", count: 1 },
          armour: { name: data.armour || "Common Armour", count: 1 },
          pants: { name: data.pants || "Common Pants", count: 1 },
          boots: { name: data.boots || "Common Boots", count: 1 },
          gloves: { name: data.gloves || "Common Gloves", count: 1 },
          baseLocation: { name: data.base_location || "Common Base", count: 1 }
        };

        updateDashboard();

        if (tokens <= 0) {
          alert("You have 0 WorkTokens! Please mine some at https://cc.free.bg/site/miner/");
        }
      }
    })
    .catch(() => {
      document.getElementById("workTokens").innerText = "Network error";
    });
}

function updateTokens(amount) {
  const email = getEmail();
  if (!email) return;

  // Build a payload that includes the tokens delta, xp, level, and gear.
  const payload = new URLSearchParams();
  payload.append('email', email);
  payload.append('tokens', amount);
  payload.append('exp', playerXP);
  payload.append('level', playerLevel);
  payload.append('helmet', equipment.helmet.name);
  payload.append('armour', equipment.armour.name);
  payload.append('pants', equipment.pants.name);
  payload.append('boots', equipment.boots.name);
  payload.append('gloves', equipment.gloves.name);
  payload.append('base_location', equipment.baseLocation.name);

  fetch('/update_tokens.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: payload.toString()
  })
    .then(res => res.json())
    .then(data => {
      if (data.status === "success") {
        // Re-fetch from the database to keep client state in sync.
        fetchPlayerData();
      } else {
        alert("Token update failed.");
      }
    })
    .catch(err => console.error("Token update error:", err));
}

function getEmail() {
  const email = document.getElementById("emailInput").value.trim();
  if (!email) {
    alert("Please enter your email.");
    return null;
  }
  return email;
}

function startRound() {
  if (questionsList.length === 0) {
    alert("Questions not loaded yet.");
    return;
  }

  // Pick a random question and generate a random difficulty value.
  currentQuestion = questionsList[Math.floor(Math.random() * questionsList.length)];
  const difficulty = Math.floor(Math.random() * 39) + 1;

  document.getElementById("gameContent").innerHTML = `
    <h3>${currentQuestion.text}</h3>
    <p>🎲 Required Dice Roll: ${difficulty}</p>
    <button onclick="rollDice(${difficulty})">Roll Dice</button>
    <p id="diceResult"></p>
    <div id="answerSection" style="display:none;">
      <input type="text" id="answerInput" placeholder="Your answer here">
      <button onclick="submitAnswer()">Submit Answer</button>
    </div>
  `;
}

function rollDice(difficulty) {
  const dice = Math.floor(Math.random() * 39) + 1;
  let result = `🎲 You rolled: ${dice}`;

  if (dice >= difficulty) {
    result += " ✅ Success!";
    gainXP(10);
    // Add exactly 1 WorkToken on success.
    updateTokens(1);
    document.getElementById("answerSection").style.display = "block";
  } else {
    result += " ❌ Failed! You lost 2 WorkTokens.";
    updateTokens(-2);
    setTimeout(() => startRound(), 1000);
  }

  document.getElementById("diceResult").innerText = result;
}

/**
 * Returns an equipment slot name based on the question id prefix.
 * Only certain prefixes correspond to gear updates.
 * '1' = helmet, '2' = armour, '5' = pants, '6' = boots, '7' = gloves, '8' = baseLocation.
 * Otherwise, no equipment is updated.
 */
function getEquipmentField(questionId) {
  switch (questionId.charAt(0)) {
    case '1': return "helmet";
    case '2': return "armour";
    case '5': return "pants";
    case '6': return "boots";
    case '7': return "gloves";
    case '8': return "baseLocation";
    default: return null;
  }
}

function submitAnswer() {
  const answer = document.getElementById("answerInput").value.trim();
  if (!answer) {
    alert("Please enter your answer.");
    return;
  }
  if (!currentQuestion || !currentQuestion.id) {
    alert("Invalid question.");
    return;
  }

  // Check if the question has a gear slot associated with it.
  const slot = getEquipmentField(currentQuestion.id);
  if (slot) {
    // Update this particular gear slot with the new answer.
    equipment[slot] = { name: answer, count: 1 };
  }
  // Call updateTokens(0) to persist the gear change (along with xp and level) to the database.
  updateTokens(0);

  const li = document.createElement("li");
  li.textContent = `${currentQuestion.text} – ${answer}`;
  document.getElementById("answersList").prepend(li);

  startRound();
}

function gainXP(amount) {
  playerXP += amount;
  checkLevelUp();
  updateTokens(0);
}

function checkLevelUp() {
  const XP_THRESHOLD = 100;
  while (playerXP >= XP_THRESHOLD) {
    playerXP -= XP_THRESHOLD;
    playerLevel++;
    // Gear is preserved on level up.
    const li = document.createElement("li");
    li.textContent = `🎉 Level Up! You're now level ${playerLevel}`;
    li.style.color = "#ffcc00";
    document.getElementById("answersList").prepend(li);
  }
  // Persist any XP/level changes.
  updateTokens(0);
}

function toTitleCase(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function updateDashboard() {
  document.getElementById("playerXP").textContent = playerXP;
  document.getElementById("playerLevel").textContent = playerLevel;
  for (const slot in equipment) {
    const el = document.getElementById(slot);
    if (el) {
      el.textContent = `${equipment[slot].name} x${equipment[slot].count}`;
    }
  }
}

