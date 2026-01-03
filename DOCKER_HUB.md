# DB Backuper

🛡️ **Open-Source Database Backup Management System**

A powerful, self-hosted database backup solution built with Laravel and Filament. Manage multiple database connections, schedule automated backups, and store them locally or in S3-compatible cloud storage.

[![Docker Pulls](https://img.shields.io/docker/pulls/almossaidllc/db-backuper)](https://hub.docker.com/r/almossaidllc/db-backuper)
[![Docker Image Size](https://img.shields.io/docker/image-size/almossaidllc/db-backuper/latest)](https://hub.docker.com/r/almossaidllc/db-backuper)

---

## ✨ Features

- 🗄️ **Multi-Database Support** - MySQL, MariaDB, PostgreSQL, SQLite
- ☁️ **Cloud Storage** - AWS S3, MinIO, DigitalOcean Spaces, Backblaze B2, Wasabi
- ⏰ **Flexible Scheduling** - Hourly, daily, weekly, monthly, or custom cron
- 📧 **Email Notifications** - Get notified on backup success or failure
- 🎨 **Modern UI** - Beautiful Filament admin panel with dark mode
- 🔒 **Secure** - Encrypted credentials, compressed backups (gzip)

---

## 🚀 Quick Start

### Run with a single command

```bash
docker run -d --name db-backuper -p 9033:80 almossaidllc/db-backuper:latest
```

Access the application at **http://localhost:9033**

> ✅ The image auto-configures everything: generates `APP_KEY`, creates SQLite database, and runs migrations automatically.

---

## 📦 Recommended: With Data Persistence

To persist your database and backups across container restarts:

```bash
docker run -d \
    --name db-backuper \
    -p 9033:80 \
    -v db-backuper-data:/var/www/html/database \
    -v db-backuper-storage:/var/www/html/storage/app \
    almossaidllc/db-backuper:latest
```

---

## 🐳 Docker Compose

Create a `docker-compose.yml` file:

```yaml
services:
  db-backuper:
    image: almossaidllc/db-backuper:latest
    container_name: db-backuper
    restart: unless-stopped
    ports:
      - "9033:80"
    volumes:
      - db-backuper-data:/var/www/html/database
      - db-backuper-storage:/var/www/html/storage/app
    environment:
      - APP_NAME=DB Backuper
      - APP_URL=http://localhost:9033

volumes:
  db-backuper-data:
  db-backuper-storage:
```

Then run:

```bash
docker-compose up -d
```

---

## 🔐 Default Login Credentials

| Field    | Value           |
|----------|-----------------|
| Email    | `demo@demo.com` |
| Password | `backuper`      |

> ⚠️ **Security Note:** Change these credentials immediately after first login!

---

## ⚙️ Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `DB Backuper` | Application name |
| `APP_URL` | `http://localhost` | Application URL |
| `APP_ENV` | `production` | Environment mode |
| `APP_DEBUG` | `false` | Debug mode |
| `APP_KEY` | *Auto-generated* | Encryption key (auto-generated if not set) |
| `DB_CONNECTION` | `sqlite` | Database driver |
| `DB_DATABASE` | `/var/www/html/database/database.sqlite` | Database path |
| `LOG_LEVEL` | `error` | Log verbosity |

---

## 🗄️ Supported Databases

| Database   | Status | Dump Tool |
|------------|--------|-----------|
| MySQL      | ✅ Full Support | mysqldump |
| MariaDB    | ✅ Full Support | mariadb-dump |
| PostgreSQL | ✅ Full Support | pg_dump |
| SQLite     | ✅ Full Support | Native copy |

---

## ☁️ Supported Storage Providers

- **Local Storage** - Server filesystem
- **Amazon S3**
- **MinIO**
- **DigitalOcean Spaces**
- **Backblaze B2**
- **Wasabi**
- Any S3-compatible storage

---

## 📁 Volume Mounts

| Path | Purpose |
|------|---------|
| `/var/www/html/database` | SQLite database & APP_KEY storage |
| `/var/www/html/storage/app` | Backup files (when using local storage) |

---

## 🔧 Architecture Support

This image supports multiple architectures:

- `linux/amd64` (x86_64)
- `linux/arm64` (Apple Silicon, ARM servers)

---

## 📖 Documentation

For full documentation, visit: [GitHub Repository](https://github.com/AlmossaidLLC/db-backuper)

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/AlmossaidLLC">Almossaid LLC</a>
</p>
