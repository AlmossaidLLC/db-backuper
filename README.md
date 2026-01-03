<p align="center">
  <img src="public/images/logo.svg" width="120" alt="DB Backuper Logo">
</p>

<h1 align="center">DB Backuper</h1>

<p align="center">
  <strong>🛡️ Open-Source Database Backup Management System</strong>
</p>

<p align="center">
  <a href="#features">Features</a> •
  <a href="#supported-databases">Databases</a> •
  <a href="#installation">Installation</a> •
  <a href="#docker-deployment">Docker</a> •
  <a href="#license">License</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Filament-4-FDAE4B?style=flat-square&logo=filament&logoColor=white" alt="Filament 4">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License">
</p>

---

## 🎯 Overview

**DB Backuper** is a powerful, self-hosted database backup management solution designed for developers, DevOps engineers, and businesses who need reliable, automated database backups without the complexity of enterprise tools.

Built with **Laravel 12** and **Filament v4**, it provides a beautiful, intuitive admin interface to manage multiple database connections, schedule automated backups, and store them locally or in S3-compatible cloud storage.

### Why DB Backuper?

- 🚀 **Self-Hosted & Privacy-First** – Your data stays on your infrastructure
- 🔄 **Multi-Database Support** – MySQL, PostgreSQL, and SQLite in one tool
- ☁️ **Cloud-Ready Storage** – AWS S3, MinIO, DigitalOcean Spaces, Backblaze B2, Wasabi
- ⏰ **Flexible Scheduling** – Hourly, daily, weekly, monthly, or custom cron expressions
- 📧 **Email Notifications** – Get notified on backup success or failure
- 🐳 **Docker Native** – Deploy anywhere with included Docker & Caprover support
- 🎨 **Modern UI** – Clean Filament admin panel with dark mode support
- 🔒 **Secure** – Encrypted credentials, compressed backups (gzip)

---

## ✨ Features

### Core Functionality
- **Multi-Connection Management** – Connect and backup multiple databases from a single dashboard
- **Automated Scheduling** – Set up recurring backups with granular frequency control
- **Manual Backups** – Trigger on-demand backups with a single click
- **Backup History** – Track all backups with status, file size, and timestamps
- **Download & Restore** – Easily download backup files for restoration

### Storage Options
- **Local Storage** – Store backups on your server's filesystem
- **S3-Compatible Storage** – Offload backups to any S3-compatible service:
  - Amazon S3
  - MinIO
  - DigitalOcean Spaces
  - Backblaze B2
  - Wasabi
  - And more...

### Notifications
- **SMTP Email Integration** – Configure any SMTP provider (Gmail, SendGrid, Mailgun, etc.)
- **Success/Failure Alerts** – Receive instant notifications per schedule
- **Customizable Recipients** – Multiple email addresses per backup schedule

### Operations
- **Background Processing** – Queue-based backup execution for non-blocking operations
- **Connection Testing** – Verify database connectivity before creating backups
- **Compression** – Automatic gzip compression to minimize storage usage
- **Retry Logic** – Automatic retry on transient failures

## 🗄️ Supported Databases

| Database   | Status | Dump Tool |
|------------|--------|-----------|
| MySQL      | ✅ Full Support | mysqldump |
| MariaDB    | ✅ Full Support | mariadb-dump |
| PostgreSQL | ✅ Full Support | pg_dump |
| SQLite     | ✅ Full Support | Native copy |

---

## 📋 Requirements

- PHP 8.2+
- Composer
- Node.js & NPM (for frontend assets)
- One of: MySQL/MariaDB, PostgreSQL, or SQLite (for app database)

---

## 🚀 Installation

### Local Development

1. **Clone the repository**
   ```bash
   git clone https://github.com/AlmossaidLLC/db-backuper.git
   cd db-backuper
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your database** in `.env`
   ```env
   DB_CONNECTION=sqlite
   # Or for MySQL/PostgreSQL
   # DB_CONNECTION=mysql
   # DB_HOST=127.0.0.1
   # DB_PORT=3306
   # DB_DATABASE=db_backuper
   # DB_USERNAME=root
   # DB_PASSWORD=
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Build assets & start development server**
   ```bash
   npm run build
   composer run dev
   ```

7. **Access the application** at `http://localhost:8000`

### Default Login Credentials

After running migrations with seeders (`php artisan migrate --seed`), you can log in with:

| Field    | Value           |
|----------|-----------------|
| Email    | `demo@demo.com` |
| Password | `backuper`      |

> ⚠️ **Security Note:** Change these credentials immediately in production environments.

---

## 🐳 Docker Deployment

### Quick Start (Recommended)

Pull and run the pre-built image from Docker Hub:

```bash
docker run -d --name db-backuper -p 9033:80 almossaidllc/db-backuper:latest
```

Access the application at `http://localhost:9033`

> The image auto-configures everything: generates `APP_KEY`, creates SQLite database, and runs migrations automatically.

### With Data Persistence

To persist your database and backups across container restarts:

```bash
docker run -d \
    --name db-backuper \
    -p 9033:80 \
    -v db-backuper-data:/var/www/html/database \
    -v db-backuper-storage:/var/www/html/storage/app \
    almossaidllc/db-backuper:latest
```

### Build from Source

```bash
docker build -f .deploy/production/Dockerfile -t db-backuper:latest .
docker run -d --name db-backuper -p 9033:80 db-backuper:latest
```

### Run with Docker Compose

```bash
docker-compose up -d
```

---

## ☁️ Caprover Deployment

This project is configured for easy deployment on Caprover.

1. Push your code to a Git repository
2. In Caprover dashboard, create a new app
3. Connect to your Git repo
4. The `captain-definition` file will automatically configure:
   - Resource limits: 2 CPU cores, 4GB RAM
   - Environment variables for database connection
   - Automatic deployment with Docker

### Environment Variables

Set these in your Caprover app configuration:

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_NAME` | Application name | DB Backuper |
| `APP_ENV` | Environment | production |
| `APP_KEY` | Encryption key | `php artisan key:generate` |
| `DB_CONNECTION` | Database driver | sqlite |
| `DB_DATABASE` | Database path | /var/www/html/database/database.sqlite |

---

## ⚙️ Configuration

### SMTP Settings (for email notifications)
Configure in the Settings page within the admin panel:
- Mail Host, Port, Username, Password
- Encryption (TLS/SSL)
- From Address & Name

### S3 Storage Settings
Configure in the Settings page for cloud backup storage:
- Access Key & Secret
- Bucket & Region
- Custom Endpoint (for non-AWS S3-compatible services)

---

## 🔧 Background Processes

The Docker container runs:
- **PHP-FPM** – Web request handling
- **Queue Worker** – Background job processing
- **Cron Daemon** – Scheduled backup execution

---

## 🖥️ Screenshots

Access the Filament admin panel at `/admin` after deployment.

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).

---

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) – The PHP framework for web artisans
- [Filament](https://filamentphp.com) – Beautiful admin panels for Laravel
- [Spatie DB Dumper](https://github.com/spatie/db-dumper) – Database backup library

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/AlmossaidLLC">Almossaid LLC</a>
</p>
