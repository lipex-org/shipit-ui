# ShipIt UI

[![✨ Version 0.0.2 Ready](https://img.shields.io/badge/version-0.0.2-blue.svg)](#)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**The missing bridge between Git and your server.**

ShipIt UI is the official web-based management interface for the ShipIt deployment tool. It provides a simple, secure, and zero-downtime deployment workflow specifically optimized for Shared Hosting and VPS environments.

## 🚀 Key Features

- **Zero-Downtime Deployments:** Automatic backups and safe merge logic ensure your applications stay online during updates.
- **Project Dashboard:** A centralized overview of all your registered projects and their deployment status.
- **One-Click Actions:** Trigger deployments or roll back to previous versions instantly from the browser.
- **Live Monitoring:** Real-time polling of deployment and rollback logs to monitor progress as it happens.
- **Environment Management:** Edit project-specific `.env` files and deployment configurations (`.deploy/config.json`) directly through the interface.
- **Automated Webhooks:** Seamless integration with GitHub, GitLab, and Bitbucket for automated "push-to-deploy" workflows.
- **Environment Aware:** Built-in support for modern frameworks like CodeIgniter 4, Laravel, and React, including automated server permission handling.
- **Security First:** Permission-based project management and secure session-based authentication.

## 🛠 Tech Stack

- **Backend:** [CodeIgniter 4](https://codeigniter.com/) (PHP 8.2+)
- **Frontend:** [Vite](https://vitejs.dev/), [Tailwind CSS](https://tailwindcss.com/), [Turbo](https://turbo.hotwired.dev/)
- **Core Engine:** [lipex-org/shipit-cli](https://github.com/lipex-org/shipit-cli)

## 📦 Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & pnpm (for frontend assets)
- Git

### Setup Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/lipex-org/shipit-ui.git
   cd shipit-ui
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Frontend dependencies:**
   ```bash
   pnpm install
   ```

4. **Configuration:**
   Copy the `env` template to `.env` and configure your environment:
   ```bash
   cp env .env
   ```
   *Note: Ensure `app.baseURL` and `database` settings are correctly configured.*

5. **Build Assets:**
   ```bash
   pnpm run build
   ```

6. **Initialize Database:**
   ```bash
   php spark migrate
   ```

## 🖥 Usage

### Running the Development Server

To start the local development server:
```bash
php spark serve
```

For frontend asset development with HMR:
```bash
pnpm run dev
```

### Deployment Configuration

ShipIt looks for a `.deploy/config.json` file in each managed project. You can initialize a project and its configuration directly through the ShipIt UI dashboard.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request or open an issue for any bugs or feature requests.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
