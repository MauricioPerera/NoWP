import { router } from '../router.js';
import { api } from '../api.js';

let currentPage = 1;

router.register('users', async () => {
  const container = document.getElementById('page-content');
  
  container.innerHTML = `
    <div class="card">
      <div class="card-header">
        <h1 class="card-title">Users</h1>
        <button class="btn btn-primary" onclick="window.showUserModal()">
          ➕ New User
        </button>
      </div>

      <div id="users-list" class="loading">Loading...</div>
      <div id="users-pagination"></div>
    </div>

    <!-- User Modal -->
    <div id="user-modal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 id="user-modal-title">New User</h2>
          <button class="modal-close" onclick="window.hideUserModal()">×</button>
        </div>
        <form id="user-form">
          <input type="hidden" id="user-id">
          <div class="form-group">
            <label for="user-name">Name</label>
            <input type="text" id="user-name" required>
          </div>
          <div class="form-group">
            <label for="user-email">Email</label>
            <input type="email" id="user-email" required>
          </div>
          <div class="form-group">
            <label for="user-password">Password</label>
            <input type="password" id="user-password">
            <small>Leave blank to keep current password</small>
          </div>
          <div class="form-group">
            <label for="user-role">Role</label>
            <select id="user-role">
              <option value="subscriber">Subscriber</option>
              <option value="author">Author</option>
              <option value="editor">Editor</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" onclick="window.hideUserModal()">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  `;

  document.getElementById('user-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    await saveUser();
  });

  loadUsers();
});

async function loadUsers() {
  const listEl = document.getElementById('users-list');
  listEl.innerHTML = '<div class="loading">Loading...</div>';

  try {
    const result = await api.getUsers({ page: currentPage, perPage: 10 });

    if (result.data.length === 0) {
      listEl.innerHTML = '<p>No users found.</p>';
      return;
    }

    listEl.innerHTML = `
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            ${result.data.map(user => `
              <tr>
                <td>${user.name}</td>
                <td>${user.email}</td>
                <td><span class="badge badge-${user.role}">${user.role}</span></td>
                <td>${new Date(user.createdAt).toLocaleDateString()}</td>
                <td>
                  <button class="btn btn-sm btn-secondary" onclick="window.editUser(${user.id})">Edit</button>
                  <button class="btn btn-sm btn-danger" onclick="window.deleteUser(${user.id})">Delete</button>
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
  const paginationEl = document.getElementById('users-pagination');
  
  if (meta.lastPage <= 1) {
    paginationEl.innerHTML = '';
    return;
  }

  paginationEl.innerHTML = `
    <div class="pagination">
      <button ${currentPage === 1 ? 'disabled' : ''} onclick="window.goToUserPage(${currentPage - 1})">Previous</button>
      <span>Page ${currentPage} of ${meta.lastPage}</span>
      <button ${currentPage === meta.lastPage ? 'disabled' : ''} onclick="window.goToUserPage(${currentPage + 1})">Next</button>
    </div>
  `;
}

async function saveUser() {
  const id = document.getElementById('user-id').value;
  const data = {
    name: document.getElementById('user-name').value,
    email: document.getElementById('user-email').value,
    role: document.getElementById('user-role').value,
  };

  const password = document.getElementById('user-password').value;
  if (password) {
    data.password = password;
  }

  try {
    if (id) {
      await api.updateUser(id, data);
    } else {
      if (!password) {
        alert('Password is required for new users');
        return;
      }
      await api.createUser(data);
    }
    window.hideUserModal();
    loadUsers();
  } catch (error) {
    alert(error.message);
  }
}

window.showUserModal = () => {
  document.getElementById('user-modal').classList.add('show');
  document.getElementById('user-form').reset();
  document.getElementById('user-id').value = '';
  document.getElementById('user-modal-title').textContent = 'New User';
  document.getElementById('user-password').required = true;
};

window.hideUserModal = () => {
  document.getElementById('user-modal').classList.remove('show');
};

window.editUser = async (id) => {
  try {
    const user = await api.getUser(id);
    document.getElementById('user-id').value = user.id;
    document.getElementById('user-name').value = user.name;
    document.getElementById('user-email').value = user.email;
    document.getElementById('user-role').value = user.role;
    document.getElementById('user-password').value = '';
    document.getElementById('user-password').required = false;
    document.getElementById('user-modal-title').textContent = 'Edit User';
    document.getElementById('user-modal').classList.add('show');
  } catch (error) {
    alert(error.message);
  }
};

window.deleteUser = async (id) => {
  if (!confirm('Delete this user?')) return;
  
  try {
    await api.deleteUser(id);
    loadUsers();
  } catch (error) {
    alert(error.message);
  }
};

window.goToUserPage = (page) => {
  currentPage = page;
  loadUsers();
};
