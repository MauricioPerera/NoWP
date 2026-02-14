import { router } from '../router.js';
import { api } from '../api.js';

let currentPage = 1;
let currentFilters = {};

router.register('content', async () => {
  const container = document.getElementById('page-content');
  
  container.innerHTML = `
    <div class="card">
      <div class="card-header">
        <h1 class="card-title">Content</h1>
        <button class="btn btn-primary" onclick="window.showContentModal()">
          ➕ New Content
        </button>
      </div>

      <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <select id="filter-type" class="form-control">
          <option value="">All Types</option>
          <option value="post">Post</option>
          <option value="page">Page</option>
          <option value="custom">Custom</option>
        </select>
        <select id="filter-status" class="form-control">
          <option value="">All Status</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
        </select>
        <input type="search" id="search-content" placeholder="Search..." class="form-control">
      </div>

      <div id="content-list" class="loading">Loading...</div>
      <div id="content-pagination"></div>
    </div>

    <!-- Content Modal -->
    <div id="content-modal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 id="modal-title">New Content</h2>
          <button class="modal-close" onclick="window.hideContentModal()">×</button>
        </div>
        <form id="content-form">
          <input type="hidden" id="content-id">
          <div class="form-group">
            <label for="content-title">Title</label>
            <input type="text" id="content-title" required>
          </div>
          <div class="form-group">
            <label for="content-slug">Slug</label>
            <input type="text" id="content-slug">
          </div>
          <div class="form-group">
            <label for="content-content">Content</label>
            <textarea id="content-content" rows="10" required></textarea>
          </div>
          <div class="form-group">
            <label for="content-type">Type</label>
            <select id="content-type">
              <option value="post">Post</option>
              <option value="page">Page</option>
              <option value="custom">Custom</option>
            </select>
          </div>
          <div class="form-group">
            <label for="content-status">Status</label>
            <select id="content-status">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
          </div>
          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" onclick="window.hideContentModal()">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  `;

  setupContentHandlers();
  loadContent();
});

function setupContentHandlers() {
  // Filters
  document.getElementById('filter-type').addEventListener('change', (e) => {
    currentFilters.type = e.target.value || undefined;
    currentPage = 1;
    loadContent();
  });

  document.getElementById('filter-status').addEventListener('change', (e) => {
    currentFilters.status = e.target.value || undefined;
    currentPage = 1;
    loadContent();
  });

  document.getElementById('search-content').addEventListener('input', (e) => {
    currentFilters.search = e.target.value || undefined;
    currentPage = 1;
    loadContent();
  });

  // Form
  document.getElementById('content-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    await saveContent();
  });

  // Auto-generate slug
  document.getElementById('content-title').addEventListener('input', (e) => {
    const slugInput = document.getElementById('content-slug');
    if (!slugInput.value || slugInput.dataset.auto === 'true') {
      slugInput.value = e.target.value.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
      slugInput.dataset.auto = 'true';
    }
  });

  document.getElementById('content-slug').addEventListener('input', () => {
    document.getElementById('content-slug').dataset.auto = 'false';
  });
}

async function loadContent() {
  const listEl = document.getElementById('content-list');
  listEl.innerHTML = '<div class="loading">Loading...</div>';

  try {
    const result = await api.getContents({
      ...currentFilters,
      page: currentPage,
      perPage: 10,
    });

    if (result.data.length === 0) {
      listEl.innerHTML = '<p>No content found.</p>';
      return;
    }

    listEl.innerHTML = `
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Status</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            ${result.data.map(item => `
              <tr>
                <td><strong>${item.title}</strong><br><small>${item.slug}</small></td>
                <td>${item.type}</td>
                <td><span class="badge badge-${item.status}">${item.status}</span></td>
                <td>${new Date(item.updatedAt).toLocaleDateString()}</td>
                <td>
                  <button class="btn btn-sm btn-secondary" onclick="window.editContent(${item.id})">Edit</button>
                  <button class="btn btn-sm btn-danger" onclick="window.deleteContent(${item.id})">Delete</button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;

    renderPagination(result.meta);
  } catch (error) {
    listEl.innerHTML = `<p class="error-message show">${error.message}</p>`;
  }
}

function renderPagination(meta) {
  const paginationEl = document.getElementById('content-pagination');
  
  if (meta.lastPage <= 1) {
    paginationEl.innerHTML = '';
    return;
  }

  const pages = [];
  for (let i = 1; i <= meta.lastPage; i++) {
    pages.push(`
      <button 
        class="${i === currentPage ? 'active' : ''}"
        onclick="window.goToPage(${i})"
      >${i}</button>
    `);
  }

  paginationEl.innerHTML = `
    <div class="pagination">
      <button ${currentPage === 1 ? 'disabled' : ''} onclick="window.goToPage(${currentPage - 1})">Previous</button>
      ${pages.join('')}
      <button ${currentPage === meta.lastPage ? 'disabled' : ''} onclick="window.goToPage(${currentPage + 1})">Next</button>
    </div>
  `;
}

async function saveContent() {
  const id = document.getElementById('content-id').value;
  const data = {
    title: document.getElementById('content-title').value,
    slug: document.getElementById('content-slug').value,
    content: document.getElementById('content-content').value,
    type: document.getElementById('content-type').value,
    status: document.getElementById('content-status').value,
  };

  try {
    if (id) {
      await api.updateContent(id, data);
    } else {
      await api.createContent(data);
    }
    window.hideContentModal();
    loadContent();
  } catch (error) {
    alert(error.message);
  }
}

// Global functions
window.showContentModal = (id = null) => {
  document.getElementById('content-modal').classList.add('show');
  document.getElementById('content-form').reset();
  document.getElementById('content-id').value = '';
  document.getElementById('modal-title').textContent = 'New Content';
};

window.hideContentModal = () => {
  document.getElementById('content-modal').classList.remove('show');
};

window.editContent = async (id) => {
  try {
    const content = await api.getContent(id);
    document.getElementById('content-id').value = content.id;
    document.getElementById('content-title').value = content.title;
    document.getElementById('content-slug').value = content.slug;
    document.getElementById('content-content').value = content.content;
    document.getElementById('content-type').value = content.type;
    document.getElementById('content-status').value = content.status;
    document.getElementById('modal-title').textContent = 'Edit Content';
    document.getElementById('content-modal').classList.add('show');
  } catch (error) {
    alert(error.message);
  }
};

window.deleteContent = async (id) => {
  if (!confirm('Are you sure you want to delete this content?')) return;
  
  try {
    await api.deleteContent(id);
    loadContent();
  } catch (error) {
    alert(error.message);
  }
};

window.goToPage = (page) => {
  currentPage = page;
  loadContent();
};
