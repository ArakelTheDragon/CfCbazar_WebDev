<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Project Directory with Descriptions</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
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
    .error {
      color: red;
      text-align: center;
    }
    .loading {
      text-align: center;
      color: #555;
    }
  </style>
</head>
<body>
  <h1>Project List</h1>
  <input type="text" id="search" placeholder="Search projects..." aria-label="Search projects" />
  <div id="projects" role="list"></div>

  <script>
    let projectList = []; // Initialize empty, will be populated by fetch
    const projectsDiv = document.getElementById("projects");
    const searchBox = document.getElementById("search");

    // Function to display projects
    function displayProjects(list) {
      projectsDiv.innerHTML = "";
      if (list.length === 0) {
        projectsDiv.innerHTML = "<p>No projects found.</p>";
        return;
      }
      list.forEach(proj => {
        const div = document.createElement("div");
        div.className = "project";
        div.setAttribute("role", "listitem");
        div.innerHTML = `
          <a href="${proj.url}" target="_blank" rel="noopener">${proj.name}</a>
          <p class="description">${proj.description}</p>
        `;
        projectsDiv.appendChild(div);
      });
    }

    // Search and rank projects
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

    // Fetch projects from JSON file
    function loadProjects() {
      projectsDiv.innerHTML = "<p class='loading'>Loading projects...</p>";
      fetch("projects.json")
        .then(response => {
          if (!response.ok) {
            throw new Error("Failed to load projects.json");
          }
          return response.json();
        })
        .then(data => {
          projectList = data;
          projectsDiv.innerHTML = "";
          displayProjects(projectList);
        })
        .catch(error => {
          console.error("Error fetching projects:", error);
          projectsDiv.innerHTML = "<p class='error'>Error loading projects. Please try again later.</p>";
        });
    }

    // Debounce search input
    function debounce(func, wait) {
      let timeout;
      return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
      };
    }

    // Add search event listener
    searchBox.addEventListener("input", debounce(e => {
      searchProjects(e.target.value);
    }, 300));

    // Load projects on page load
    loadProjects();
  </script>
</body>
</html>