console.log("💾 game.js loaded");

// Player starts at level 0, XP 0 with basic Common gear.
let questionsList = [];
let currentQuestion = null;
let playerXP = 0;
let playerLevel = 0;
let equipment = {
  helmet: { name: "Common Helmet", count: 1 },
  armour: { name: "Common Armour", count: 1 },
  weapon: { name: "Common Weapon", count: 1 },
  secondWeapon: { name: "Common 2nd Weapon", count: 1 },
  pants: { name: "Common Pants", count: 1 },
  boots: { name: "Common Boots", count: 1 },
  gloves: { name: "Common Gloves", count: 1 },
  baseLocation: { name: "Common Base", count: 1 }
};

// Helper function to parse stored equipment from the database
function parseGearString(gearStr, defaultName) {
  if (!gearStr) return { name: defaultName, count: 1 };

  let parts = gearStr.split(" x");
  if (parts.length === 2) {
    let count = parseInt(parts[1].trim(), 10);
    return { name: parts[0].trim(), count: isNaN(count) ? 1 : count };
  } else {
    return { name: gearStr.trim(), count: 1 };
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("fetchTokensBtn").addEventListener("click", fetchPlayerData);

  const startBtn = document.getElementById("startRoundBtn");
  if (startBtn) startBtn.addEventListener("click", startRound);

  window.startRound = startRound;
  window.rollDice = rollDice;
  window.submitAnswer = submitAnswer;

  fetchQuestions();
  fetchPlayerData();
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

        equipment = {
          helmet: parseGearString(data.helmet, "Common Helmet"),
          armour: parseGearString(data.armour, "Common Armour"),
          weapon: parseGearString(data.weapon, "Common Weapon"),
          secondWeapon: parseGearString(data.second_weapon, "Common 2nd Weapon"),
          pants: parseGearString(data.pants, "Common Pants"),
          boots: parseGearString(data.boots, "Common Boots"),
          gloves: parseGearString(data.gloves, "Common Gloves"),
          baseLocation: parseGearString(data.base_location, "Common Base")
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

  const payload = new URLSearchParams();
  payload.append("email", email);
  payload.append("tokens", amount);
  payload.append("exp", playerXP);
  payload.append("level", playerLevel);
  payload.append("helmet", `${equipment.helmet.name} x${equipment.helmet.count}`);
  payload.append("armour", `${equipment.armour.name} x${equipment.armour.count}`);
  payload.append("weapon", `${equipment.weapon.name} x${equipment.weapon.count}`);
  payload.append("second_weapon", `${equipment.secondWeapon.name} x${equipment.secondWeapon.count}`);
  payload.append("pants", `${equipment.pants.name} x${equipment.pants.count}`);
  payload.append("boots", `${equipment.boots.name} x${equipment.boots.count}`);
  payload.append("gloves", `${equipment.gloves.name} x${equipment.gloves.count}`);
  payload.append("base_location", `${equipment.baseLocation.name} x${equipment.baseLocation.count}`);

  fetch("/update_tokens.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: payload.toString()
  })
    .then(res => res.json())
    .then(data => {
      if (data.status === "success") {
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
    updateTokens(0.1);
    document.getElementById("answerSection").style.display = "block";
  } else {
    result += " ❌ Failed! You lost 0.2 WorkTokens.";
    updateTokens(-0.2);
    setTimeout(() => startRound(), 1000);
  }

  document.getElementById("diceResult").innerText = result;
}

function getEquipmentField(questionId) {
  switch (questionId.charAt(0)) {
    case "1": return "helmet";
    case "2": return "armour";
    case "3": return "weapon";
    case "4": return "secondWeapon";
    case "5": return "pants";
    case "6": return "boots";
    case "7": return "gloves";
    case "8": return "baseLocation";
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

  const slot = getEquipmentField(currentQuestion.id);
  if (slot) {
    let currentItem = equipment[slot];
    if (currentItem.name.toLowerCase() === answer.toLowerCase()) {
      currentItem.count++;
    } else {
      currentItem = { name: answer, count: 1 };
    }
    equipment[slot] = currentItem;
  }

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
    const li = document.createElement("li");
    li.textContent = `🎉 Level Up! You're now level ${playerLevel}`;
    li.style.color = "#ffcc00";
    document.getElementById("answersList").prepend(li);
  }
  updateTokens(0);
}

function updateDashboard() {
  document.getElementById("playerXP").textContent = playerXP;
  document.getElementById("playerLevel").textContent = playerLevel;
  for (const slot in equipment) {
    const el = document.getElementById(slot);
    if (el) el.textContent = `${equipment[slot].name} x${equipment[slot].count}`;
  }
}

