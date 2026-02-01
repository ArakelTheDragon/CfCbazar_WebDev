<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Project Directory with Descriptions</title>
<style>
  body {
    font-family: Arial, sans-serif;
    margin: 0; padding: 20px;
    background: #f5f5f5;
  }
  h1 {
    text-align: center;
  }
  #search {
    width: 100%;
    padding: 10px;
    font-size: 16px;
    margin-bottom: 20px;
    border: 1px solid #ccc;
    border-radius: 5px;
  }
  .project {
    background: white;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  .project a {
    color: #007BFF;
    text-decoration: none;
    font-weight: bold;
    font-size: 18px;
  }
  .project a:hover {
    text-decoration: underline;
  }
  .description {
    margin-top: 5px;
    color: #555;
  }
</style>
</head>
<body>

<h1>Project List</h1>
<input type="text" id="search" placeholder="Search projects..." />

<div id="projects"></div>

<script>
// Project list with descriptions
const projectList = [
  {
    name: "ESP Web Server",
    url: "https://github.com/ArakelTheDragon/Library_Other/tree/df8161074e9ce40690fb50805a711ff44f165743/ESP8266_WebServer",
    description: "An HTML/JS web server max 2MB hosted on an ESP8266. Uses your public IP as the web site IP, connects to your WiFi. Open the link, press ctrl+f and search for ESP8266_WebServer.zip."
  }
];

const projectsDiv = document.getElementById("projects");
const searchBox = document.getElementById("search");

// Display projects in the container
function displayProjects(list) {
  projectsDiv.innerHTML = "";
  if (list.length === 0) {
    projectsDiv.innerHTML = "<p>No projects found.</p>";
    return;
  }
  list.forEach(proj => {
    const div = document.createElement("div");
    div.className = "project";
    div.innerHTML = `
      <a href="${proj.url}" target="_blank" rel="noopener">${proj.name}</a>
      <p class="description">${proj.description}</p>
    `;
    projectsDiv.appendChild(div);
  });
}

// Search and rank projects based on matching words in name and description
function searchProjects(query) {
  const words = query.toLowerCase().split(/\s+/).filter(Boolean);

  const ranked = projectList
    .map(proj => {
      const nameLower = proj.name.toLowerCase();
      const descLower = proj.description.toLowerCase();
      let matchCount = 0;
      words.forEach(word => {
        if (nameLower.includes(word)) matchCount++;
        if (descLower.includes(word)) matchCount++;
      });
      return { ...proj, score: matchCount };
    })
    .filter(p => p.score > 0 || query === "")
    .sort((a, b) => b.score - a.score);

  displayProjects(ranked);
}

// Update on input
searchBox.addEventListener("input", e => {
  searchProjects(e.target.value);
});

// Show all projects initially
displayProjects(projectList);
</script>

</body>
</html>
