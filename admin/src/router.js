/**
 * Simple Router
 */

class Router {
  constructor() {
    this.routes = {};
    this.currentPage = null;
  }

  register(path, handler) {
    this.routes[path] = handler;
  }

  navigate(hash) {
    const path = hash.replace('#', '') || 'dashboard';
    const handler = this.routes[path];

    if (handler) {
      // Update active nav item
      document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.page === path) {
          item.classList.add('active');
        }
      });

      // Render page
      this.currentPage = path;
      handler();
    }
  }
}

export const router = new Router();
