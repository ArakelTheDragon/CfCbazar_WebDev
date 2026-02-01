let questionsList = [];
let currentQuestion = null;

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("fetchTokensBtn").addEventListener("click", fetchTokens);
  fetchQuestions();
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

function fetchTokens() {
  const email = getEmail();
  if (!email) return;

  fetch(`https://cfcbazar.atwebpages.com/get_tokens.php?email=${encodeURIComponent(email)}`)
    .then(res => res.json())
    .then(data => {
      const display = data.error ? "Error" : parseFloat(data.earned_tokens).toFixed(2);
      document.getElementById("workTokens").innerText = display;
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
    <h3>${currentQuestion}</h3>
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
    updateTokens(0.1);
    document.getElementById("answerSection").style.display = "block";
  } else {
    result += " ❌ Failed! Token lost.";
    updateTokens(-0.2);
    setTimeout(() => startRound(), 1000);
  }

  document.getElementById("diceResult").innerText = result;
}

function submitAnswer() {
  const answer = document.getElementById("answerInput").value.trim();
  if (!answer) {
    alert("Please enter your answer.");
    return;
  }

  const li = document.createElement("li");
  li.textContent = `${currentQuestion} – ${answer}`;
  document.getElementById("answersList").appendChild(li);

  startRound();
}

