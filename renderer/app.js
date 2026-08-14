const $ = (selector) => document.querySelector(selector);
let workspaces = [];

function escapeHtml(value) { return String(value).replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])); }
function permissionText(value) { return value === 'read_only' ? '只读' : '读写'; }
function renderList() {
  $('#projectCount').textContent = workspaces.length ? `${workspaces.length} 个项目` : '';
  $('#workspaceList').innerHTML = workspaces.map((item) => `<article class="workspace-card card"><div><div class="status"><i></i>${item.online ? '在线' : '离线'}</div><h3>${escapeHtml(item.name)}</h3><div class="path" title="${escapeHtml(item.folder)}">${escapeHtml(item.folder)}</div><div class="tags"><span class="tag">${permissionText(item.permission)}</span>${item.allowDelete ? '<span class="tag">允许删除</span>' : '<span class="tag">删除已关闭</span>'}</div></div><div class="card-foot"><span class="muted">本地 Workspace</span><button class="text-button" data-id="${item.id}">查看项目 →</button></div></article>`).join('');
  $('#emptyState').classList.toggle('hidden', workspaces.length > 0);
  $('#workspaceList').classList.toggle('hidden', workspaces.length === 0);
  document.querySelectorAll('[data-id]').forEach((button) => button.addEventListener('click', () => showDetail(button.dataset.id)));
}
function showDetail(id) {
  const item = workspaces.find((entry) => entry.id === id); if (!item) return;
  $('#listView').classList.add('hidden'); $('#detailView').classList.remove('hidden');
  $('#detailView').innerHTML = `<div class="detail-card card"><button class="back" id="backButton">← 返回项目</button><div class="detail-header"><div><div class="status"><i></i>当前设备在线</div><h2>${escapeHtml(item.name)}</h2><div class="detail-path">${escapeHtml(item.folder)}</div></div><span class="tag">${permissionText(item.permission)}</span></div><div class="copy-card"><h3>把这个项目交给 AI</h3><p>生成与当前权限一致的连接信息和 Agent 提示词。</p><button class="primary" id="copyButton">复制给 AI</button></div><div class="detail-stats"><div class="stat"><small>连接方式</small><strong>直连优先</strong></div><div class="stat"><small>删除权限</small><strong>${item.allowDelete ? '已开启' : '已关闭'}</strong></div><div class="stat"><small>本地目录</small><strong>已授权</strong></div></div><div class="detail-actions"><button class="secondary" id="openButton">打开文件夹</button><button class="secondary danger" id="removeButton">移除项目</button></div></div>`;
  $('#backButton').addEventListener('click', () => { $('#detailView').classList.add('hidden'); $('#listView').classList.remove('hidden'); });
  $('#openButton').addEventListener('click', () => window.filebuddy.openFolder(item.folder));
  $('#removeButton').addEventListener('click', async () => { if (confirm('只移除 FileBuddy 项目记录，不会删除本地文件。继续吗？')) { workspaces = await window.filebuddy.removeWorkspace(item.id); $('#backButton').click(); renderList(); } });
  $('#copyButton').addEventListener('click', async () => { const text = `你现在可以访问我授权的本地项目“${item.name}”。请优先使用 FileBuddy 连接。当前权限：${permissionText(item.permission)}。仅访问 /workspace 下路径，修改前读取最新版本并优先使用 Patch；如设备离线请提示我打开文小哥。`; await navigator.clipboard.writeText(text); $('#copyButton').textContent = '已复制'; setTimeout(() => { $('#copyButton').textContent = '复制给 AI'; }, 1600); });
}
async function openCreate() { $('#formError').textContent = ''; $('#createForm').reset(); $('#folderInput').value = ''; $('#createDialog').showModal(); }
$('#addButton').addEventListener('click', openCreate); $('#emptyAdd').addEventListener('click', openCreate);
$('#chooseFolder').addEventListener('click', async () => { const folder = await window.filebuddy.chooseFolder(); if (folder) { $('#folderInput').value = folder; if (!$('#nameInput').value) $('#nameInput').value = folder.split(/[\\/]/).pop(); } });
$('#createForm').addEventListener('submit', async (event) => { event.preventDefault(); try { const item = await window.filebuddy.createWorkspace({ name: $('#nameInput').value.trim(), folder: $('#folderInput').value, permission: $('#permissionInput').value, allowDelete: $('#deleteInput').checked }); workspaces.push(item); $('#createDialog').close(); renderList(); showDetail(item.id); } catch (error) { $('#formError').textContent = error.message || '创建项目失败'; } });
(async () => { workspaces = await window.filebuddy.listWorkspaces(); renderList(); })();
