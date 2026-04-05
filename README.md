<p align="center">
  <img src="https://raw.githubusercontent.com/Bara-BSI/niham/main/public/niham-logo.png" alt="NIHAM Logo" width="150" height="auto" />
</p>

# 🏨 NIHAM (New Integrated Hotel Asset Management)

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)
![openSUSE](https://img.shields.io/badge/openSUSE-Leap_16.0-73BA25?style=for-the-badge&logo=opensuse&logoColor=white)

**NIHAM** is a simple and modern asset management tool for the hotel industry. It helps you keep track of your hotel's equipment, furniture, and other assets easily using the latest web technologies.

---

## ✨ Main Features

- **Location Management:** Track and organize assets by specific physical locations within your hotel property.
- **Smart Scanning:** Upload an image of an asset's data plate, and let the AI automatically fill in the details like Serial Numbers and Brands.
- **Easy Tracking:** Monitor where your assets are, their current condition, and when their warranty expires.
- **QR Codes:** Every asset gets its own QR code. Scan it with a phone to instantly see the asset details.
- **Reports:** Generate clean PDF or Excel reports for your manager or for auditing.
- **Multi-Property:** Manage multiple hotels from a single system.
- **Beautiful Design:** A modern, clean interface that works perfectly on both desktops and mobile phones.

---

## 🚀 Getting Started

Follow these steps to set up the project on a native openSUSE system.

### Requirements
- **PHP 8.4** (with `gd`, `imagick`, and `pgsql` extensions)
- **Nginx** web server
- **PostgreSQL 16** database
- **Composer** for managing PHP packages
- **Node.js 22** for frontend assets

### Installation

1.  **Clone the project**
    ```bash
    git clone https://github.com/Bara-BSI/niham.git
    cd niham
    ```

2.  **Install dependencies**
    ```bash
    composer install
    npm install
    ```

3.  **Environment Setup**
    Copy the example configuration file:
    ```bash
    cp .env.example .env
    ```
    *Open the `.env` file and set your database name, username, and password.*

4.  **Database Setup**
    Run the migrations to create the database structure:
    ```bash
    php artisan migrate
    ```

5.  **Final Steps**
    Generate the application security key and link the storage folder:
    ```bash
    php artisan key:generate
    php artisan storage:link
    ```

---

## 🛠️ Running the Application

Since the project is set up natively on openSUSE with `systemd`, you can manage the services using these standard commands:

```bash
# To check if Nginx is running
sudo systemctl status nginx

# To check if PHP is running
sudo systemctl status php-fpm

# To check if PostgreSQL is running
sudo systemctl status postgresql
```

To see the website, open your browser and navigate to the IP address or domain configured in your Nginx virtual host.

---

## 📄 License

The NIHAM project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
