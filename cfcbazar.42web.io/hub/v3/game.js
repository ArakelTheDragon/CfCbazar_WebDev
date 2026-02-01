let questions = {};
let currentIndex = 0;
let currentClass = "";
let answeredCount = 0;
let totalEarned = 0;
let failedAttempts = 0;

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("fetchTokensBtn").addEventListener("click", fetchTokens);

  fetch("questions.json")
    .then(response => response.json())
    .then(data => {
      questions = data;
    })
    .catch(error => {
      console.error("Failed to load questions:", error);
      alert("Could not load questions.");
    });
});