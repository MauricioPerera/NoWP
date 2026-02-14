import { router } from '../router.js';
import { api } from '../api.js';

let currentPage = 1;

router.register('media', async () => {
  const container = document.getElementById('page-content');
  
  container.innerHTML = `
    <div class="card">
      <div class="card-header">
        <h1 class="card-title">Media</h1>
        <div>
          <input type="file" id="media-upload" accept="image/*" style="display:none" multiple>
          <button class="btn btn-primary" onclick="document.getElementById('media-upload').click()">
            ⬆️ Upload
          </button>
        </div>
      </div>

      <div id="media-grid" class="loading">Loading...</div>
      <div id="media-pagination"></div>
    </div>
  `;

  document.getElementById('media-upload').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files);
    for (const file of files) {
      try {
        await api.uploadMedia(file);
      } catch (error) {
        alert(`Failed to upload ${file.name}: ${error.message}`);
      }
    }
    e.target.value = '';
    loadMedia();
  });

  loadMedia();
});

async function loadMedia() {
  const gridEl = document.getElementById('media-grid');
  gridEl.innerHTML = '<div class="loading">Loading...</div>';

  try {
    const result = await api.getMedia({ page: currentPage, perPage: 12 });

    if (result.data.length === 0) {
      gridEl.innerHTML = '<p>No media files yet.</p>';
      return;
    }

    gridEl.innerHTML = `
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
        ${result.data.map(item => `
          <div class="card" style="padding: 10px;">
            <img src="${item.url}" alt="${item.originalName}" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">
            <p style="margin: 10px 0 5px; font-size: 12px; overflow: hidden; text-overflow: ellipsis;">${item.originalName}</p>
            <p style="margin: 0; font-size: 11px; color: var(--gray-600);">${(item.size / 1024).toFixed(1)} KB</p>
            <button class="btn btn-sm btn-danger" style="margin-top: 10px; width: 100%;" onclick="window.deleteMedia(${item.id})">Delete</button>
          </div>
        `).join('')}
      </div>
    `;

    renderPagination(result.meta);
  } catch (error) {
    gridEl.innerHTML = `<p class="error-message show">${error.message}</p>`;
  }
}

function renderPagination(meta) {
  const paginationEl = document.getElementById('media-pagination');
  
  if (meta.lastPage <= 1) {
    paginationEl.innerHTML = '';
    return;
  }

  paginationEl.innerHTML = `
    <div class="pagination">
      <button ${currentPage === 1 ? 'disabled' : ''} onclick="window.goToMediaPage(${currentPage - 1})">Previous</button>
      <span>Page ${currentPage} of ${meta.lastPage}</span>
      <button ${currentPage === meta.lastPage ? 'disabled' : ''} onclick="window.goToMediaPage(${currentPage + 1})">Next</button>
    </div>
  `;
}

window.deleteMedia = async (id) => {
  if (!confirm('Delete this media file?')) return;
  
  try {
    await api.deleteMedia(id);
    loadMedia();
  } catch (error) {
    alert(error.message);
  }
};

window.goToMediaPage = (page) => {
  currentPage = page;
  loadMedia();
};
