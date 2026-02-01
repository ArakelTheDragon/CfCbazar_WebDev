<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Browser Poker – 1 vs 3 CPU</title>
<style>
  body { font-family: Arial, sans-serif; background: #0b3d0b; color: #fff; margin: 0; padding: 20px; }
  h1 { text-align: center; }
  #table { max-width: 900px; margin: 0 auto; background: #145214; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #000; }
  .players, .community { display: flex; justify-content: space-around; margin: 15px 0; }
  .player, .community-cards { background: #0f3a0f; padding: 10px; border-radius: 8px; min-width: 150px; text-align: center; }
  .cards { margin-top: 5px; }
  .card { display: inline-block; padding: 4px 6px; margin: 2px; border-radius: 4px; background: #fff; color: #000; font-weight: bold; }
  .you { border: 2px solid #ffd700; }
  #log { background: #062406; padding: 10px; border-radius: 8px; max-height: 200px; overflow-y: auto; font-size: 0.9em; }
  #controls { text-align: center; margin-top: 15px; }
  button { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
  button#deal { background: #ffd700; color: #000; }
  button#next { background: #eee; color: #000; }
  button:disabled { opacity: 0.5; cursor: default; }
</style>
</head>
<body>
<h1>Texas Hold’em – You vs 3 CPU</h1>
<div id="table">
  <div class="players">
    <div class="player" id="p0">
      <strong>You</strong>
      <div class="cards"></div>
    </div>
    <div class="player" id="p1">
      <strong>CPU 1</strong>
      <div class="cards"></div>
    </div>
    <div class="player" id="p2">
      <strong>CPU 2</strong>
      <div class="cards"></div>
    </div>
    <div class="player" id="p3">
      <strong>CPU 3</strong>
      <div class="cards"></div>
    </div>
  </div>

  <div class="community">
    <div class="community-cards" id="community">
      <strong>Community Cards</strong>
      <div class="cards"></div>
    </div>
  </div>

  <div id="controls">
    <button id="deal">Deal New Hand</button>
    <button id="next" disabled>Next Stage</button>
  </div>

  <h3>Game Log</h3>
  <div id="log"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/pokersolver@2.0.0/pokersolver.min.js"></script>

<script>
(function() {
  const suits = ['♠','♥','♦','♣'];
  const ranks = ['2','3','4','5','6','7','8','9','T','J','Q','K','A'];

  let deck = [];
  let players = [];
  let community = [];
  let stage = 0;

  const logEl = document.getElementById('log');
  const dealBtn = document.getElementById('deal');
  const nextBtn = document.getElementById('next');

  function log(msg) {
    logEl.innerHTML += msg + '<br>';
    logEl.scrollTop = logEl.scrollHeight;
  }

  function createDeck() {
    deck = [];
    for (let s of suits) {
      for (let r of ranks) {
        deck.push({rank: r, suit: s});
      }
    }
  }

  function shuffle() {
    for (let i = deck.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [deck[i], deck[j]] = [deck[j], deck[i]];
    }
  }

  function dealCard() {
    return deck.pop();
  }

  function startHand() {
    stage = 0;
    logEl.innerHTML = '';
    community = [];
    players = [];
    createDeck();
    shuffle();

    for (let i = 0; i < 4; i++) {
      players.push({
        name: i === 0 ? 'You' : 'CPU ' + i,
        cards: [dealCard(), dealCard()]
      });
    }

    render();
    log('New hand dealt. You vs 3 CPU players.');
    log('Click "Next Stage" to reveal the flop.');
    dealBtn.disabled = true;
    nextBtn.disabled = false;
  }

  function render() {
    for (let i = 0; i < 4; i++) {
      const pEl = document.getElementById('p' + i);
      const cardsEl = pEl.querySelector('.cards');
      cardsEl.innerHTML = '';
      pEl.classList.toggle('you', i === 0);

      players[i].cards.forEach((c) => {
        const cardDiv = document.createElement('div');
        cardDiv.className = 'card';
        if (i === 0 || stage === 4) {
          cardDiv.textContent = c.rank + c.suit;
        } else {
          cardDiv.textContent = stage < 4 ? '🂠' : c.rank + c.suit;
        }
        cardsEl.appendChild(cardDiv);
      });
    }

    const commEl = document.querySelector('#community .cards');
    commEl.innerHTML = '';
    community.forEach(c => {
      const cardDiv = document.createElement('div');
      cardDiv.className = 'card';
      cardDiv.textContent = c.rank + c.suit;
      commEl.appendChild(cardDiv);
    });
  }

  function nextStage() {
    if (stage === 0) {
      dealCard();
      community.push(dealCard(), dealCard(), dealCard());
      stage = 1;
      log('Flop dealt.');
    } else if (stage === 1) {
      dealCard();
      community.push(dealCard());
      stage = 2;
      log('Turn dealt.');
    } else if (stage === 2) {
      dealCard();
      community.push(dealCard());
      stage = 3;
      log('River dealt.');
    } else if (stage === 3) {
      stage = 4;
      showdown();
      nextBtn.disabled = true;
      dealBtn.disabled = false;
    }
    render();
  }

  // Convert your card format → PokerSolver format
  function toSolverCard(card) {
    const suitMap = { '♠':'s', '♥':'h', '♦':'d', '♣':'c' };
    return card.rank + suitMap[card.suit];
  }

  function showdown() {
    log('Showdown!');

    let bestHand = null;
    let winners = [];

    players.forEach((p) => {
      const allCards = p.cards.concat(community).map(toSolverCard);

      const hand = Hand.solve(allCards);

      log(p.name + ' → ' + hand.descr);

      if (!bestHand || hand.rank > bestHand.rank) {
        bestHand = hand;
        winners = [p.name];
      } else if (hand.rank === bestHand.rank) {
        winners.push(p.name);
      }
    });

    if (winners.length === 1) {
      log('<strong>Winner: ' + winners[0] + '</strong>');
    } else {
      log('<strong>Split pot between: ' + winners.join(', ') + '</strong>');
    }

    render();
  }

  dealBtn.addEventListener('click', startHand);
  nextBtn.addEventListener('click', nextStage);
})();
</script>
<center>Free Browser Poker by CfCbazar</center>
</body>
</html>

