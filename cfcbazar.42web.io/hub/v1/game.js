let users = {}; // Simulated database
let currentUser = null;
let currentQuestion = 0; // Which question we're on

// Load users from localStorage when page loads
window.onload = function() {
  const savedUsers = localStorage.getItem('dnd_users');
  if (savedUsers) {
    users = JSON.parse(savedUsers);
  }
};

function startGame() {
  const username = document.getElementById('username').value.trim();

  if (username === '') {
    alert('Please enter a username.');
    return;
  }

  if (!users[username]) {
    // New user
    users[username] = {
      position: 0,
      wins: 0,
      losses: 0,
      experience: 10000,
      chosenPath: null
    };
  }

  currentUser = username;
  currentQuestion = users[currentUser].position;

  document.getElementById('login').style.display = 'none';
  document.getElementById('game').style.display = 'block';
  document.getElementById('welcome').innerText = `Welcome, ${currentUser}!`;
  updateExperience();
  loadQuestion();
}

function updateExperience() {
  document.getElementById('experience').innerText = users[currentUser].experience;
}

function loadQuestion() {
  const form = document.getElementById('questionForm');
  form.innerHTML = '';

  if (currentQuestion === 0) {
    document.querySelector('h3').innerText = "Question 0: What will your agenda be?";
    addOption("hacker", "Learn to be a hacker (Difficulty: 38)");
    addOption("craftsman", "Learn to be a craftsman (Difficulty: 33)");
    addOption("warrior", "Learn to be a warrior (Difficulty: 28)");
    addOption("farmer", "Learn to be a farmer (Difficulty: 23)");
    addOption("worker", "Learn to be a worker (Difficulty: 18)");
  } else if (currentQuestion === 1 && users[currentUser].chosenPath === "farmer") {
    document.querySelector('h3').innerText = "You need to get some land, how do you plan on doing that?";
    addOption("own10", "Have 10 acres already (Difficulty: 38)");
    addOption("buy10", "Have money to buy 10 acres (Difficulty: 33)");
    addOption("work2", "Will work to get money for 2 acres (Difficulty: 28)");
    addOption("rent2", "Will rent 2 acres (Difficulty: 23)");
    addOption("steal", "Will work on someone else's land without permission (Difficulty: 18)");
  } else {
    document.querySelector('h3').innerText = "More questions coming soon!";
    document.getElementById('questionForm').innerHTML = '';
  }
}

function addOption(value, text) {
  const form = document.getElementById('questionForm');
  const option = document.createElement('input');
  option.type = 'radio';
  option.name = 'option';
  option.value = value;
  form.appendChild(option);
  form.appendChild(document.createTextNode(' ' + text));
  form.appendChild(document.createElement('br'));
}

function rollDice() {
  const selectedOption = document.querySelector('input[name="option"]:checked');

  if (!selectedOption) {
    alert('Please select an option first!');
    return;
  }

  const diceRoll = Math.floor(Math.random() * 39) + 1; // Dice 1-39
  document.getElementById('diceResult').innerText = `You rolled: ${diceRoll}`;

  let difficulty = getDifficulty(selectedOption.value);

  if (diceRoll >= difficulty) {
    users[currentUser].wins++;
    users[currentUser].experience += 1000;
    document.getElementById('outcome').innerText = "You succeeded! +1000 experience.";

    if (currentQuestion === 0) {
      users[currentUser].chosenPath = selectedOption.value;
      users[currentUser].position = 1; // Move to next question
      currentQuestion = 1;
      saveData();
      setTimeout(() => {
        loadQuestion();
        document.getElementById('diceResult').innerText = '';
        document.getElementById('outcome').innerText = '';
      }, 1500);
    } else {
      users[currentUser].position = 2;
      currentQuestion = 2;
      saveData();
      setTimeout(() => {
        loadQuestion();
        document.getElementById('diceResult').innerText = '';
        document.getElementById('outcome').innerText = '';
      }, 1500);
    }
  } else {
    users[currentUser].losses++;
    users[currentUser].experience -= 1000;
    document.getElementById('outcome').innerText = "You failed! -1000 experience.";
    saveData();
  }

  updateExperience();
}

function getDifficulty(option) {
  switch (option) {
    case 'hacker': return 38;
    case 'craftsman': return 33;
    case 'warrior': return 28;
    case 'farmer': return 23;
    case 'worker': return 18;
    case 'own10': return 38;
    case 'buy10': return 33;
    case 'work2': return 28;
    case 'rent2': return 23;
    case 'steal': return 18;
    default: return 39;
  }
}

function saveData() {
  localStorage.setItem('dnd_users', JSON.stringify(users));
  console.log('Saved user data:', users);
}