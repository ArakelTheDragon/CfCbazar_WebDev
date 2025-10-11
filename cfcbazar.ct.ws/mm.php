<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Play Memory Match Game Online – Free Brain Training</title>
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="Play the free Memory Match Game online! Flip cards, find matching pairs, and train your brain while having fun. No downloads, instant play.">
  <meta name="keywords" content="memory match game, free online game, matching pairs, card game, brain training, memory game online, puzzle game, fun game, cognitive training">
  <meta name="author" content="CfCbazar">
  
  <!-- Open Graph / Facebook -->
  <meta property="og:title" content="Memory Match Game – Free Online Brain Training">
  <meta property="og:description" content="Challenge your memory with this fun and free matching pairs card game. Play instantly in your browser.">
  <meta property="og:image" content="https://example.com/memory-match-thumbnail.jpg">
  <meta property="og:url" content="https://example.com/memory-match">
  <meta property="og:type" content="website">
  
  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Memory Match Game – Play Free Online">
  <meta name="twitter:description" content="Flip cards, match pairs, and improve your memory. Free browser game, no download needed.">
  <meta name="twitter:image" content="https://example.com/memory-match-thumbnail.jpg">
  
  <!-- Structured Data for Google Rich Snippets -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Game",
    "name": "Memory Match Game",
    "description": "A free online card matching game to train memory and cognitive skills.",
    "genre": ["Puzzle", "Memory", "Card Game"],
    "image": "https://example.com/memory-match-thumbnail.jpg",
    "url": "https://example.com/memory-match",
    "author": {
      "@type": "Organization",
      "name": "CfCbazar"
    }
  }
  </script>
  
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #2c3e50;
      color: #ecf0f1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }
    h1 {
      margin-bottom: 10px;
      font-size: 2em;
      text-align: center;
    }
    .game-board {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      width: 90%;
      max-width: 400px;
    }
    .card {
      background: #3498db;
      width: 100%;
      padding-top: 100%;
      position: relative;
      border-radius: 5px;
      cursor: pointer;
      transition: transform 0.3s;
    }
    .card.flip {
      transform: rotateY(180deg);
    }
    .card-inner {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      backface-visibility: hidden;
      border-radius: 5px;
    }
    .card-front {
      background: #ecf0f1;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5em;
      font-weight: bold;
      color: #2c3e50;
    }
    .card-back {
      background: #3498db;
    }
    .score {
      margin-top: 10px;
      font-size: 1.2em;
    }
    button {
      margin-top: 20px;
      padding: 10px 20px;
      background: #e74c3c;
      color: #fff;
      border: none;
      border-radius: 5px;
      font-size: 1em;
      cursor: pointer;
    }
    button:hover {
      background: #c0392b;
    }
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.7);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background-color: #ecf0f1;
      padding: 20px;
      border-radius: 5px;
      text-align: center;
      max-width: 80%;
      width: 300px;
      color: #2c3e50;
    }
    .close {
      background: #e74c3c;
      color: white;
      padding: 10px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }
    .close:hover {
      background: #c0392b;
    }
  </style>
</head>
<body>

<h1>Free Memory Match Game – Flip, Match & Train Your Brain</h1>
<p>Test your memory skills with our free online memory match card game. Find all the matching pairs as fast as you can!</p>

<div class="game-board" id="gameBoard"></div>
<div class="score" id="score">Score: 0</div>
<button onclick="startGame()">Restart Game</button>

<!-- Modal -->
<div id="howToPlayModal" class="modal">
  <div class="modal-content">
    <h2>How to Play Memory Match</h2>
    <p>Flip the cards to find matching pairs. Try to match all pairs to win!</p>
    <button class="close" onclick="closeModal()">Got it!</button>
  </div>
</div>

<script>
  const gameBoard = document.getElementById('gameBoard');
  const scoreDisplay = document.getElementById('score');
  const howToPlayModal = document.getElementById('howToPlayModal');
  let firstCard = null;
  let secondCard = null;
  let lockBoard = false;
  let score = 0;

  const icons = ['🍎', '🍌', '🍇', '🍓', '🍍', '🥝', '🍒', '🍉'];
  const cards = [...icons, ...icons];

  function shuffle(array) {
    return array.sort(() => Math.random() - 0.5);
  }

  function createCard(icon) {
    const card = document.createElement('div');
    card.classList.add('card');
    card.dataset.icon = icon;

    const cardInner = document.createElement('div');
    cardInner.classList.add('card-inner');

    const cardFront = document.createElement('div');
    cardFront.classList.add('card-front');
    cardFront.textContent = icon;

    const cardBack = document.createElement('div');
    cardBack.classList.add('card-back');

    cardInner.appendChild(cardFront);
    cardInner.appendChild(cardBack);
    card.appendChild(cardInner);

    card.addEventListener('click', flipCard);
    return card;
  }

  function startGame() {
    firstCard = null;
    secondCard = null;
    lockBoard = false;
    score = 0;
    scoreDisplay.textContent = `Score: ${score}`;
    gameBoard.innerHTML = '';

    shuffle(cards).forEach(icon => {
      const card = createCard(icon);
      gameBoard.appendChild(card);
    });

    openModal();
  }

  function flipCard() {
    if (lockBoard || this === firstCard) return;
    this.classList.add('flip');
    if (!firstCard) {
      firstCard = this;
      return;
    }
    secondCard = this;
    checkMatch();
  }

  function checkMatch() {
    const isMatch = firstCard.dataset.icon === secondCard.dataset.icon;
    if (isMatch) {
      disableCards();
      score++;
      scoreDisplay.textContent = `Score: ${score}`;
      if (score === icons.length) {
        setTimeout(() => alert('You win!'), 500);
      }
    } else {
      unflipCards();
    }
  }

  function disableCards() {
    firstCard.removeEventListener('click', flipCard);
    secondCard.removeEventListener('click', flipCard);
    resetBoard();
  }

  function unflipCards() {
    lockBoard = true;
    setTimeout(() => {
      firstCard.classList.remove('flip');
      secondCard.classList.remove('flip');
      resetBoard();
    }, 1000);
  }

  function resetBoard() {
    [firstCard, secondCard] = [null, null];
    lockBoard = false;
  }

  function openModal() {
    howToPlayModal.style.display = 'flex';
  }

  function closeModal() {
    howToPlayModal.style.display = 'none';
  }

  startGame();
</script>

</body>
</html>
