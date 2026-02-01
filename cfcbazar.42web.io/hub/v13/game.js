let questionsList = [];
let currentQuestion = null;
let playerXP = 0;
let playerLevel = 1;

let equipment = {
  helmet: { name: "Common Helmet", count: 0 },
  armor: { name: "Common Armor", count: 0 },
  weapon: { name: "Common Weapon", count: 0 },
  secondWeapon: { name: "Common 2nd Weapon", count: 0 },
  pants: { name: "Common Pants", count: 0 },
  boots: { name: "Common Boots", count: 0 },
  gloves: { name: "Common Gloves", count: 0 },
  baseLocation: { name: "Common Base", count: 0 }
};

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("fetchTokensBtn").addEventListener("click", fetchTokens);
  loadFromStorage();
  fetchQuestions();
  updateDashboard();
});

// Load XP, level, equipment from localStorage
function loadFromStorage() {
  const savedXP = localStorage.getItem("playerXP");
  const savedLevel = localStorage.getItem("playerLevel");
  const savedEquipment = localStorage.getItem("equipment");

  if (savedXP) playerXP = parseInt(savedXP, 10);
  if (savedLevel) playerLevel = parseInt(savedLevel, 10);
  if (savedEquipment) equipment = JSON.parse(savedEquipment);
}

// Save XP, level, equipment to localStorage
function saveToStorage() {
  localStorage.setItem("playerXP", playerXP);
  localStorage.setItem("playerLevel", playerLevel);
  localStorage.setItem("equipment", JSON.stringify(equipment));
}

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

function fetchTokens() {
  const email = getEmail();
  if (!email) return;

  fetch(`https://cfcbazar.atwebpages.com/get_tokens.php?email=${encodeURIComponent(email)}`)
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        document.getElementById("workTokens").innerText = "Error";
      } else {
        const tokens = parseFloat(data.earned_tokens);
        document.getElementById("workTokens").innerText = tokens.toFixed(2);

        // Check if tokens are zero or less, prompt mining
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

  fetch('https://cfcbazar.atwebpages.com/update_tokens.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ email, tokens: amount })
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === "success") fetchTokens();
    else alert("Token update failed.");
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

  const winTokens = playerLevel * 0.1;
  const loseTokens = playerLevel * 0.2;

  if (dice >= difficulty) {
    result += " ✅ Success!";
    updateTokens(winTokens);
    gainXP(10); // XP on success
    document.getElementById("answerSection").style.display = "block";
  } else {
    result += " ❌ Failed! Token lost.";
    updateTokens(-loseTokens);
    setTimeout(() => startRound(), 1000);
  }

  document.getElementById("diceResult").innerText = result;
}

// Map first digit of question ID to equipment field
function getEquipmentField(questionId) {
  switch (questionId.charAt(0)) {
    case '1': return "helmet";
    case '2': return "armor";
    case '3': return "weapon";
    case '4': return "secondWeapon";
    case '5': return "pants";
    case '6': return "boots";
    case '7': return "gloves";
    case '8': return "baseLocation";
    default: return null;
  }
}

// Submit answer, update equipment and counts
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
  if (!slot) {
    alert("Unknown equipment slot for this question.");
    return;
  }

  if (equipment[slot].name === answer) {
    equipment[slot].count++;
  } else {
    equipment[slot].name = answer;
    equipment[slot].count = 1;
  }

  saveToStorage();
  updateDashboard();

  const li = document.createElement("li");
  li.textContent = `${currentQuestion.text} – ${answer}`;
  document.getElementById("answersList").prepend(li);

  startRound();
}

// XP / Level system
function gainXP(amount) {
  playerXP += amount;
  checkLevelUp();
  saveToStorage();
  updateDashboard();
}

function checkLevelUp() {
  const XP_THRESHOLD = 100;
  while (playerXP >= XP_THRESHOLD) {
    playerXP -= XP_THRESHOLD;
    playerLevel++;
    levelUpGear(playerLevel);

    const li = document.createElement("li");
    li.textContent = `🎉 Level Up! You're now level ${playerLevel}`;
    li.style.color = "#ffcc00";
    document.getElementById("answersList").prepend(li);
  }
  saveToStorage();
  updateDashboard();
}

function levelUpGear(level) {
  const rarityNames = {
    1: "Common",
    2: "Rare",
    3: "Legendary",
    4: "Epic"
  };

  const rarity = rarityNames[level] || "Common";

  equipment.helmet = { name: `${rarity} Helmet`, count: 1 };
  equipment.armor = { name: `${rarity} Armor`, count: 1 };
  equipment.weapon = { name: `${rarity} Weapon`, count: 1 };
  equipment.secondWeapon = { name: `${rarity} 2nd Weapon`, count: 1 };
  equipment.pants = { name: `${rarity} Pants`, count: 1 };
  equipment.boots = { name: `${rarity} Boots`, count: 1 };
  equipment.gloves = { name: `${rarity} Gloves`, count: 1 };
  equipment.baseLocation = { name: `${rarity} Base`, count: 1 };

  saveToStorage();
}

// Update dashboard UI
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

