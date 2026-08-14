const $ = (selector) => document.querySelector(selector);
let workspaces = [];
let authMode = 'login';
let authToken = localStorage.getItem('filebuddy_token') || '';
let apiBase = localStorage.getItem('filebuddy_api') || window.filebuddy.getApiBase();

function escapeHtml(value) { return String(value).replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }
function permissionText(value) { return value === 'read_only' ? '只读' : '读写'; }
async function api(path, options = {}) {
  const response = await fetch(`${apiBase.replace(/\/$/, '')}${path}`, { ...options, headers: { 'Content-Type': 'application/json', ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}), ...(options.headers || {}) } });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || `请求失败（${response.status}）`);
  return data;
}
function setAuth(token, base) { authToken = token; apiBase = base.replace(/\/$/, ''); localStorage.setItem('filebuddy_token', token); localStorage.setItem('filebuddy_api', apiBase); }
function showAuth() { $('#authDialog').showModal(); }
function renderList() {
  $('#projectCount').textContent = workspaces.length ? `${workspaces.length} 个项目` : '';
  $('#workspaceList').innerHTML = workspaces.map((item) => `<article class="workspace-card card"><div><div class="status"><i></i>${item.online ? '在线' : '离线'}</div><h3>${escapeHtml(item.name)}</h3><div class="path" title="${escapeHtml(item.folder)}">${escapeHtml(item.folder)}</div><div class="tags"><span class="tag">${permissionText(item.permission)}</span>${item.allowDelete ? '<span class="tag">允许删除</span>' : '<span class="tag">删除已关闭</span>'}</div></div><div class="card-foot"><span class="muted">本地 Workspace</span><button class="text-button" data-id="${item.id}">查看项目 →</button></div></article>`).join('');
  $('#emptyState').classList.toggle('hidden', workspaces.length > 0); $('#workspaceList').classList.toggle('hidden', workspaces.length === 0);
  document.querySelectorAll('[data-id]').forEach((button) => button.addEventListener('click', () => showDetail(button.dataset.id)));
}
async function generateConnection(item) {
  if (!item.remoteId) throw new Error('该项目尚未同步到服务端');
  const connection = await api(`/v1/workspaces/${encodeURIComponent(item.remoteId)}/connections`, { method: 'POST', body: '{}' });
  item.connection = connection;
  await window.filebuddy.updateWorkspace({ id: item.id, connection });
  return connection;
}
function connectionHtml(connection) {
  if (!connection) return '<p class="muted">尚未生成连接信息。点击下方按钮生成一次性 API Key。</p><button class="primary" id="generateButton">生成 MCP / API 连接</button>';
  return `<div class="connection-lines"><label>MCP 地址<code>${escapeHtml(connection.mcpUrl)}</code></label><label>API 地址<code>${escapeHtml(connection.apiUrl)}</code></label><label>API Key（请妥善保存）<code>${escapeHtml(connection.apiKey || '已生成，请重新生成以查看')}</code></label></div><button class="secondary" id="copyConnectionButton">复制完整接入信息</button>`;
}
function showDetail(id) {
  const item = workspaces.find((entry) => entry.id === id); if (!item) return;
  $('#listView').classList.add('hidden'); $('#detailView').classList.remove('hidden');
  $('#detailView').innerHTML = `<div class="detail-card card"><button class="back" id="backButton">← 返回项目</button><div class="detail-header"><div><div class="status"><i></i>当前设备在线</div><h2>${escapeHtml(item.name)}</h2><div class="detail-path">${escapeHtml(item.folder)}</div></div><span class="tag">${permissionText(item.permission)}</span></div><div class="copy-card"><h3>把这个项目交给 AI</h3><p>连接地址和 Key 与当前 Workspace 权限绑定。</p>${connectionHtml(item.connection)}</div><div class="detail-stats"><div class="stat"><small>连接方式</small><strong>直连优先</strong></div><div class="stat"><small>删除权限</small><strong>${item.allowDelete ? '已开启' : '已关闭'}</strong></div><div class="stat"><small>登录账号</small><strong>${escapeHtml(localStorage.getItem('filebuddy_email') || '已鉴权')}</strong></div></div><div class="detail-actions"><button class="secondary" id="openButton">打开文件夹</button><button class="secondary danger" id="removeButton">移除项目</button></div></div>`;
  $('#backButton').addEventListener('click', () => { $('#detailView').classList.add('hidden'); $('#listView').classList.remove('hidden'); });
  $('#openButton').addEventListener('click', () => window.filebuddy.openFolder(item.folder));
  $('#removeButton').addEventListener('click', async () => { if (confirm('只移除项目记录，不会删除本地文件。继续吗？')) { workspaces = await window.filebuddy.removeWorkspace(item.id); $('#backButton').click(); renderList(); } });
  const generateButton = $('#generateButton');
  if (generateButton) generateButton.addEventListener('click', async () => { generateButton.disabled = true; try { item.connection = await generateConnection(item); showDetail(item.id); } catch (error) { generateButton.textContent = error.message; } });
  const copyButton = $('#copyConnectionButton');
  if (copyButton) copyButton.addEventListener('click', async () => { const c = item.connection; const text = `FileBuddy 项目：${item.name}\nMCP 地址：${c.mcpUrl}\nAPI 地址：${c.apiUrl}\nAPI Key：${c.apiKey || '请在客户端重新生成'}\n权限：${permissionText(item.permission)}\n规则：仅访问 /workspace；修改前读取最新版本。`; await navigator.clipboard.writeText(text); copyButton.textContent = '已复制'; });
}
async function openCreate() { $('#formError').textContent = ''; $('#createForm').reset(); $('#folderInput').value = ''; $('#createDialog').showModal(); }
async function loadWorkspaces() { workspaces = await window.filebuddy.listWorkspaces(); renderList(); }
$('#addButton').addEventListener('click', openCreate); $('#emptyAdd').addEventListener('click', openCreate);
$('#chooseFolder').addEventListener('click', async () => { const folder = await window.filebuddy.chooseFolder(); if (folder) { $('#folderInput').value = folder; if (!$('#nameInput').value) $('#nameInput').value = folder.split(/[\\/]/).pop(); } });
$('#createForm').addEventListener('submit', async (event) => { event.preventDefault(); try { const input = { name: $('#nameInput').value.trim(), folder: $('#folderInput').value, permission: $('#permissionInput').value, allowDelete: $('#deleteInput').checked }; const item = await window.filebuddy.createWorkspace(input); const remote = await api('/v1/workspaces', { method: 'POST', body: JSON.stringify({ name: input.name, permission: input.permission, allowDelete: input.allowDelete }) }); item.remoteId = remote.id; item.connection = await generateConnection(item); await window.filebuddy.updateWorkspace(item); workspaces.push(item); $('#createDialog').close(); renderList(); showDetail(item.id); } catch (error) { $('#formError').textContent = error.message || '创建项目失败'; } });
$('#authModeButton').addEventListener('click', () => { authMode = authMode === 'login' ? 'register' : 'login'; $('#authTitle').textContent = authMode === 'login' ? '登录文小哥' : '创建文小哥账号'; $('#authSubmit').textContent = authMode === 'login' ? '登录' : '注册'; $('#authModeButton').textContent = authMode === 'login' ? '创建账号' : '返回登录'; });
$('#authForm').addEventListener('submit', async (event) => { event.preventDefault(); $('#authError').textContent = ''; try { apiBase = $('#apiBaseInput').value.trim().replace(/\/$/, ''); const payload = { email: $('#emailInput').value.trim(), password: $('#passwordInput').value }; const result = await api(authMode === 'login' ? '/v1/auth/login' : '/v1/auth/register', { method: 'POST', body: JSON.stringify(payload) }); setAuth(result.token, apiBase); localStorage.setItem('filebuddy_email', result.user.email); $('#authDialog').close(); await loadWorkspaces(); } catch (error) { $('#authError').textContent = error.message || '登录失败'; } });
(async () => { try { if (!authToken) return showAuth(); await api('/v1/me'); await loadWorkspaces(); } catch { authToken = ''; localStorage.removeItem('filebuddy_token'); showAuth(); } })();
