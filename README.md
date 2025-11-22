# Suppliers Management System

<p align="center">
  <img src="https://laravel.com/img/logomark.min.svg" alt="Laravel" width="100">
  <h1 align="center">Suppliers Management System</h1>
  <p align="center">A comprehensive platform for managing suppliers and services</p>
</p>

## 📋 Table of Contents

- [Introduction](#-introduction)
- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [API Documentation](#-api-documentation)
- [Deployment](#-deployment)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [Security](#-security)
- [License](#-license)

## 🌟 Introduction

Suppliers Management System is a robust Laravel-based platform designed to streamline supplier management, document handling, and service tracking. It provides a seamless experience for both suppliers and administrators with intuitive dashboards and powerful features.

## ✨ Features

### Supplier Dashboard
- User authentication and registration
- Profile and document management
- Branch and service management
- Real-time notifications
- Service ratings and reviews

### Admin Dashboard
- Comprehensive supplier management
- Document verification and approval
- Advanced reporting and analytics
- User activity monitoring
- System configuration

## 🛠 Requirements

- PHP 8.1+
- Composer 2.0+
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 16+ and NPM 8+
- Redis 6.0+
- Web server (Nginx/Apache)

## 🚀 Installation

1. Clone the repository:
```bash
git clone https://github.com/ahmedkamalyoussef/Suppliers.sa.git
cd Suppliers.sa
```

2. Install PHP dependencies:
```bash
composer install --no-dev --optimize-autoloader
```

3. Install NPM dependencies and build assets:
```bash
npm install
npm run build
```

4. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

5. Update `.env` with your database and mail configuration

6. Run database migrations and seeders:
```bash
php artisan migrate --seed --force
```

7. Create storage link:
```bash
php artisan storage:link
```

8. Start the development server:
```bash
php artisan serve
```

## ⚙️ Configuration

### Environment Variables
Key configuration options in `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=suppliers
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### File Storage
- Uploads are stored in `storage/app/public`
- Ensure proper permissions:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## 📚 API Documentation

API documentation is available at `/api/documentation` after setting up the project.

## 🚀 Deployment

### Production Setup
1. Configure your web server (Nginx/Apache)
2. Set up SSL certificate
3. Configure queue workers:
```bash
php artisan queue:work --daemon
```

### Scheduler Setup
Add this to your server's crontab:
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🧪 Testing

Run PHPUnit tests:
```bash
composer test
```

Run PHPStan for static analysis:
```bash
composer analyse
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 🔒 Security

If you discover any security vulnerabilities, please email security@example.com instead of using the issue tracker.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🏛️ Credits

- [Laravel](https://laravel.com)
- [Vue.js](https://vuejs.org/)
- [Tailwind CSS](https://tailwindcss.com/)
