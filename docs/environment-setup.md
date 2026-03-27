# Environment Setup

## Tools Installed
- XAMPP (Apache, MySQL, PHP)
- Composer (Dependency Manager)
- Git (Version Control)
- Visual Studio Code (Code Editor)

## Purpose of Tools
- XAMPP: Provides local server environment
- Composer: Manages PHP dependencies
- Git: Tracks project changes
- VS Code: Code editing and debugging

## Setup Approach
The application will be deployed locally using XAMPP.
All testing will be conducted in a controlled environment.

# Environment Setup & Local Deployment

This document explains the steps followed to deploy the Invoice Ninja application locally and the issues encountered during setup.

## Tools Used

- Git & GitHub
- XAMPP (initial attempt)
- Composer
- Docker Desktop
- Visual Studio Code

## Initial Setup using Composer

Initially, the application was deployed using XAMPP and Composer.

Steps:
- Cloned the repository
- Ran composer install
- Attempted to generate vendor files

### Issues Faced

- Composer installation was extremely slow
- Installation got stuck at "Generating optimized autoload files"
- Errors related to symbolic links and file extraction
- Vendor/autoload.php was not generated properly

Due to these issues, the application could not run successfully using Composer.

## Switching to Docker Deployment

To overcome the Composer-related issues, Docker was used to deploy the application in a containerized environment.

Advantages:
- Avoids local environment conflicts
- Pre-configured dependencies
- Faster and more reliable setup

## Switching to Docker Deployment

To overcome the Composer-related issues, Docker was used to deploy the application in a containerized environment.

Advantages:
- Avoids local environment conflicts
- Pre-configured dependencies
- Faster and more reliable setup

## Issues Encountered in Docker Setup

- Application container exited due to database connection issues
- Nginx initially showed default welcome page
- 502 Bad Gateway error due to incorrect proxy configuration
- 500 Internal Server Error due to missing APP_KEY

## Fixes Applied

- Ensured database container starts before application
- Configured Nginx correctly using FastCGI
- Created .env file inside the container
- Generated application key using:

  php artisan key:generate

- Cleared cache and configuration:

  php artisan optimize:clear

After applying these fixes, the application ran successfully.


## Final Result

The Invoice Ninja application was successfully deployed locally using Docker.

The application is accessible via:

http://localhost:8000