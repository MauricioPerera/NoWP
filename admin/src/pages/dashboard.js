import { router } from '../router.js';
import { api } from '../api.js';

router.register('dashboard', async () => {
  const container = document.getElementById('page-content');
  
  container.innerHTML = `
    <h1>Dashboard</h1>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Content</div>
        <div class="stat-value" id="stat-content">-</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Published</div>
        <div class="stat-value" id="stat-published">-</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Drafts</div>
        <div class="stat-value" id="stat-drafts">-</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Media Files</div>
        <div class="stat-value" id="stat-media">-</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Recent Content</h2>
      </div>
      <div id="recent-content" class="loading">Loading...</div>
    </div>
  `;

  try {
    // Load stats
    const [allContent, published, drafts, media] = await Promise.all([
      api.getContents({ perPage: 1 }),
      api.getContents({ status: 'published', perPage: 1 }),
      api.getContents({ status: 'draft', perPage: 1 }),
      api.getMedia({ perPage: 1 }),
    ]);

    document.getElementById('stat-content').textContent = allContent.meta.total;
    document.getElementById('stat-published').textContent = published.meta.total;
    document.getElementById('stat-drafts').textContent = drafts.meta.total;
    document.getElementById('stat-media').textContent = media.meta.total;

    // Load recent content
    const recent = await api.getContents({ perPage: 5 });
    
    const recentEl = document.getElementById('recent-content');
    if (recent.data.length === 0) {
      recentEl.innerHTML = '<p>No content yet.</p>';
    } else {
      recentEl.innerHTML = `
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Status</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            ${recent.data.map(item => `
              <tr>
                <td>${item.title}</td>
                <td>${item.type}</td>
                <td><span class="badge badge-${item.status}">${item.status}</span></td>
                <td>${new Date(item.updatedAt).toLocaleDateString()}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    }
  } catch (error) {
    console.error('Dashboard error:', error);
    document.getElementById('recent-content').innerHTML = 
      `<p class="error-message show">${error.message}</p>`;
  }
});
