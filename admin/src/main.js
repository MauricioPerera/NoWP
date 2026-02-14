import { api } from './api.js';
import { router } from './router.js';
import './pages/dashboard.js';
import './pages/content.js';
import './pages/media.js';
import './pages/users.js';
import './pages/plugins.js';

class App {
  constructor() {
    this.currentUser = null;
    this.init();
  }

  async init() {
    // Check if user is logged in
    if (api.token) {
      try {
        this.currentUser = await api.me();
        this.showMainScreen();
        router.navigate(window.location.hash || '#dashboard');
      } catch (error) {
        this.showLoginScreen();
      }
    } else {
      this.showLoginScreen();
    }

    this.setupEventListeners();
  }

  setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('login-form');
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleLogin(e);
    });

    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    logoutBtn.addEventListener('click', () => this.handleLogout());

    // Navigation
    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const page = item.dataset.page;
        router.navigate(`#${page}`);
      });
    });

    // Hash change
    window.addEventListener('hashchange', () => {
      router.navigate(window.location.hash);
    });
  }

  async handleLogin(e) {
    const email = e.target.email.value;
    const password = e.target.password.value;
    const errorEl = document.getElementById('login-error');

    try {
      await api.login(email, password);
      this.currentUser = await api.me();
      this.showMainScreen();
      router.navigate('#dashboard');
    } catch (error) {
      errorEl.textContent = error.message;
      errorEl.classList.add('show');
    }
  }

  async handleLogout() {
    try {
      await api.logout();
    } catch (error) {
      console.error('Logout error:', error);
    }
    this.currentUser = null;
    this.showLoginScreen();
  }

  showLoginScreen() {
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('main-screen').classList.add('hidden');
  }

  showMainScreen() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('main-screen').classList.remove('hidden');
    this.updateUserInfo();
  }

  updateUserInfo() {
    if (this.currentUser) {
      document.getElementById('user-name').textContent = this.currentUser.name;
      const roleEl = document.getElementById('user-role');
      roleEl.textContent = this.currentUser.role;
      roleEl.className = `badge badge-${this.currentUser.role}`;
    }
  }
}

// Start app
new App();
