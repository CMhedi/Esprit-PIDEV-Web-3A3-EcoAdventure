# 🌿 EcoAdventure - Web Platform

[![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=for-the-badge&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)](LICENSE)

> **EcoAdventure** is a premium eco-tourism and wellness ecosystem designed to connect nature enthusiasts with unique outdoor experiences while promoting a healthy lifestyle through AI-driven nutrition and activity tracking.

---

## 🚀 Key Features

### 👤 User & Security
- **Secure Authentication**: Multi-factor authentication support and Telegram-based 2FA.
- **Role-Based Access Control**: Separate interfaces for Users, Coaches, and Administrators.
- **Social Login**: Integrated Google OAuth2 for seamless onboarding.

### 📅 Event & Activity Management
- **Booking System**: Real-time reservation for eco-tours, workshops, and sports sessions.
- **Dynamic Planning**: Interactive calendars and scheduling for activities.
- **QR Code Verification**: Instant check-in via generated QR codes for event participants.

### 🥗 Wellness & Nutrition
- **AI-Powered Recommendations**: Personalized nutrition plans based on user profiles and goals.
- **Nutrition Logs**: Track daily intake and monitor progress with interactive charts.
- **Integration**: Sync with activity levels for holistic health monitoring.

### 💬 Communication & Support
- **Messaging System**: Real-time chat between users and coaches.
- **Reclamation Management**: Structured support ticket system for handling user feedback and issues.
- **Notifications**: Instant alerts for bookings, messages, and updates.

### 💳 Payments & Finance
- **Stripe Integration**: Secure payment processing for premium packs and event bookings.
- **Invoicing**: Automatic PDF invoice generation for all transactions.

---

## 🛠️ Technology Stack

| Category | Technologies |
| :--- | :--- |
| **Backend** | PHP 8.1+, Symfony 6.4, Doctrine ORM |
| **Frontend** | Twig, Stimulus, Symfony UX Turbo, Vanilla CSS |
| **Database** | MySQL |
| **AI / ML** | Python (Flask API), Machine Learning Models |
| **APIs & Services** | Stripe, Google Calendar/Charts, Twilio, Vonage |
| **Tools** | Composer, PHPUnit, PHPStan, Docker (Compose) |

---

## 📦 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/CMhedi/Esprit-PIDEV-Web-3A3-EcoAdventure.git
   cd Esprit-PIDEV-Web-3A3-EcoAdventure
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment Setup**
   Copy `.env` to `.env.local` and configure your database and API keys:
   ```bash
   cp .env .env.local
   ```

4. **Database Migration**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Start the server**
   ```bash
   symfony serve
   ```

---

## 🏷️ Topics & Keywords

`#EcoTourism` `#Wellness` `#Symfony` `#WebDevelopment` `#AI` `#Nutrition` `#EcoFriendly` `#PHP` `#FullStack` `#Esprit` `#PIDEV`

---

## 👨‍💻 Contributors

- **Hedi** (Lead Developer)
- **Team EcoAdventure**

---
*Developed as part of the 3A3 Integrated Project (PIDEV) at Esprit.*
