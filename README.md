# News Portal Project

## 1. Project Overview

This project is a simple website for managing and publishing news in Arabic. It was developed using PHP and MySQL.

The website allows visitors to browse news and search for it by title or category. It also includes a dashboard that can be used to manage news, categories, and users.

In this assignment, I used Docker to run the website and the database in separate containers, so the project can run on other computers without needing to install PHP and MySQL separately.

## 2. Technologies Used

- **PHP 8.2:** For programming the website's functions and interacting with the database.
- **MySQL 8.0:** For storing news, users, and categories.
- **Apache:** For running the website on a web server.
- **HTML, CSS, and JavaScript:** For building the user interface.
- **Docker:** For running the application inside a container.
- **Docker Compose:** For running the website and the database together.
- **Git and GitHub:** For version control and storing the code.

## 3. Requirements

To run the project using Docker, we need:

- Docker Desktop
- Git
- An internet connection to download Docker images and the external libraries used in the design.

We do not need to install XAMPP when running the project using Docker.

## 4. Downloading and Running the Project

**Step One: Download the project from GitHub**
```bash
git clone https://github.com/reemShorafa/news-portal-php.git
cd news-portal-php
```

**Step Two: Prepare the configuration file**

We copy the `.env.example` file to a new file named `.env`, then review the database configuration values and set suitable local passwords.

In PowerShell:
```powershell
Copy-Item .env.example .env
```

**Step Three: Build and run the project**
```bash
docker compose up -d --build
```

This command builds the application image and runs two containers: one for PHP and Apache, and the other for the MySQL database.

**Step Four: Open the website**

After running the containers, we open the following link in the browser:

[http://localhost:8080](http://localhost:8080)

When running the project for the first time with a new database, we create the administrator account from this page:

[http://localhost:8080/setup-admin.php](http://localhost:8080/setup-admin.php)

After creating the administrator account, we can log in and use the dashboard.

## 5. Project Configuration

The project uses the `.env` file to define the database connection settings.

Before starting Docker, all `CHANGE_ME` values from `.env.example` must be replaced in the `.env` file. The values of `DB_PASS` and `MYSQL_APP_PASSWORD` must be identical, while `MYSQL_ROOT_PASSWORD` should be different and strong.

The settings include the database server address, database name, username, and password.

The website runs locally on port `8080`, while the PHP container connects to the MySQL database through Docker's internal network.

The `.env` file is not uploaded to GitHub because it contains settings specific to the runtime environment.

## 6. How Can the Project Be Tested?

After running the project, we can make sure it works by:

1. Opening the home page and making sure the website interface appears.
2. Creating the administrator account when running the project for the first time.
3. Logging in to the dashboard.
4. Adding a new news article and selecting its category.
5. Editing or deleting the news article.
6. Opening the home page and making sure the published news appears.
7. Trying to search for a news article.

The following command can also be used to check the status of the containers:
```bash
docker compose ps
```

If the application container appears with the status `Up` and the database appears with the status `Up (healthy)`, this indicates that the containers are running and the database health check succeeded.

## 7. Stopping and Cleaning Up the Project

To stop the containers:
```bash
docker compose stop
```

To run them again:
```bash
docker compose start
```

To stop and remove the containers while keeping the stored data:
```bash
docker compose down
```

The following command removes the containers and the project's Volumes, including the data stored inside them:
```bash
docker compose down -v
```

The last command should be used carefully because it may cause the database and uploaded images to be lost.

## 8. Project File Organization

The project contains the following main folders and files:

- `admin/`: Dashboard pages.
- `assets/`: CSS and JavaScript files.
- `config/`: Database connection settings.
- `database/`: The SQL file for creating the tables and default data.
- `includes/`: Shared files and PHP functions.
- `scripts/`: Helper scripts for managing the project.
- `uploads/`: Images uploaded to the website.
- `docs/`: Technical notes and screenshots that show the project running.
- `Dockerfile`: Instructions for building the application image.
- `docker-compose.yml`: Configuration for running the application and database together.
- `.env.example`: An environment configuration template.

## 9. Project Source

This project was prepared for an Operating Systems Lab assignment. A complete open-source project was not used as its foundation.

The project uses some external design libraries, such as Bootstrap, Bootstrap Icons, and Google Fonts.

## 10. Additional Notes

While implementing the project, I faced a problem loading the CSS and JavaScript files when running it inside Docker because the file paths differ between XAMPP and Docker.

The method used to create the links was modified so that the files work correctly in both environments.

The project was also tested with a new database to make sure that another developer can run it by following the steps in this file.
