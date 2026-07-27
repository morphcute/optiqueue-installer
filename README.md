# OptiQueue Project Installer Wizard

A lightweight, self-contained PHP Web Installer application for **OptiQueue Queuing System**.

---

## 🌟 What This Project Does

The **OptiQueue Project Installer** automates fresh deployment of the OptiQueue application on any PHP server (XAMPP/WAMP local development or live cPanel/VPS web hosting) in **5 simple steps**:

1. **System Environment Check**: Checks PHP version (>= 8.1), PDO MySQL, ZipArchive, OpenSSL, and file permissions.
2. **Database Configuration**: Configures Host, Port, Database Name, Username, and Password with a live **Test Connection** button that creates the database and generates `.env`.
3. **Project ZIP Extraction**: Select or upload the `optiqueue-laravel.zip` package to automatically extract source files.
4. **Automated Setup Engine**: Generates `APP_KEY` and runs database migrations (`php artisan migrate --force`).
5. **Initial Administrator Creation**: Sets up the initial Administrator Name, Email, and Password.
6. **Instant System Launch**: Redirects straight to OptiQueue Login page.

---

## 📁 File Structure

```
optiqueue-installer/
├── index.php             # Interactive Installation Wizard UI
├── installer.css         # Modern Dark Mode & Glassmorphism Stylesheet
├── installer-backend.php # Secure PHP AJAX Installation API Handler
└── README.md             # Usage & Deployment Guide
```

---

## 🚀 How to Use / Package for Distribution

1. **Place Installer Folder**:
   Copy the `optiqueue-installer` directory to your web root (e.g. `C:\xampp\htdocs\optiqueue-installer` or `/public_html/install`).

2. **Open Installer in Browser**:
   Navigate to `http://localhost/optiqueue-installer` or `http://your-domain.com/optiqueue-installer`.

3. **Provide Project ZIP**:
   In **Step 3**, select your `optiqueue-laravel.zip` file.

4. **Complete Installation**:
   Follow the wizard to configure your database and create your admin login. Click **Launch OptiQueue System**!
