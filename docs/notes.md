# Technical Notes – Docker and GitHub

## 1. Problems I Faced While Using Docker

### Problem 1: Virtualization and WSL 2

At the beginning of the assignment, I had difficulty running Docker Desktop on my Windows computer because of the virtualization requirements.

I needed to check the virtualization settings and WSL 2 requirements. After preparing the environment correctly, Docker Desktop started working, and I was able to build and run my containers.

From this problem, I learned that Docker needs more than just the project files. The computer must also have the correct environment and settings.

### Problem 2: PHP Container Stopping (Exited 255)

While working on the project, I noticed that the PHP container had stopped with an `Exited (255)` status.

I checked the Docker logs, reviewed the application setup, and rebuilt and tested the containers. After the changes, the problem did not appear again during testing.

I could not confirm the exact reason for exit code 255, but this experience taught me how to use Docker logs and container status commands when something goes wrong.

### Problem 3: CSS and JavaScript Files Returning 404

After running my project in Docker, the homepage loaded, but some CSS and JavaScript files were not loading correctly.

The problem happened because the application used different paths in XAMPP and Docker.

In XAMPP, the project runs at:

`http://localhost/news-portal/`

In Docker, it runs at:

`http://localhost:8080/`

The application was generating links containing `/news-portal/`, which did not match the Docker directory structure.

To solve this issue, I updated the `url()` function in `includes/functions.php` so it could detect the correct base path automatically.

After this change, the CSS and JavaScript files loaded correctly in both XAMPP and Docker.

## 2. Lessons I Learned from Git and GitHub

During this assignment, I learned the difference between using Git locally and uploading my work to GitHub.

Some important commands I learned were:

- `git init` to initialize a Git repository.
- `git status` to check the status of my files.
- `git add` to stage changes.
- `git commit` to save changes locally.
- `git push` to upload commits to GitHub.
- `git log --oneline` to view my commit history.

I also learned that commit messages should clearly explain the changes made to the project.

Another important lesson was using `.gitignore` to prevent private files, such as `.env`, from being uploaded to GitHub.

I understood that Docker volumes are useful for keeping database data even when containers are stopped or recreated.

## 3. Conclusion

This assignment helped me understand how to run a PHP application and a MySQL database using Docker containers.

I also learned how to solve basic Docker problems, manage my project with Git, and publish it on GitHub with clear documentation.

Most importantly, I learned how to prepare a project so another developer can download and run it by following the instructions in the README.
