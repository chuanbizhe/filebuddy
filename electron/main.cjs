const { app, BrowserWindow, dialog, ipcMain, shell, Menu } = require('electron');
const fs = require('node:fs/promises');
const path = require('node:path');
const { handleRequest } = require('./local-bridge.cjs');

const storePath = () => path.join(app.getPath('userData'), 'workspaces.json');
const bridgeLoops = new Map();
const apiBase = () => process.env.FILEBUDDY_API_URL || 'https://filebuddy.elo.ink/index.php';

async function readWorkspaces() {
  try { return JSON.parse(await fs.readFile(storePath(), 'utf8')); }
  catch { return []; }
}

async function writeWorkspaces(items) {
  await fs.mkdir(path.dirname(storePath()), { recursive: true });
  await fs.writeFile(storePath(), JSON.stringify(items, null, 2), 'utf8');
}

function startBridge(item) {
  if (!item.remoteId || !item.connection?.apiKey || bridgeLoops.has(item.id)) return;
  let stopped = false;
  bridgeLoops.set(item.id, () => { stopped = true; });
  (async () => {
    while (!stopped) {
      try {
        const response = await fetch(`${apiBase()}/v1/bridge/${encodeURIComponent(item.remoteId)}/poll`, { headers: { 'X-FileBuddy-Key': item.connection.apiKey } });
        const data = await response.json();
        if (data.request) {
          let result;
          try { result = { jsonrpc: '2.0', id: data.request.payload.id ?? null, result: await handleRequest(item, data.request.payload) }; }
          catch (error) { result = { jsonrpc: '2.0', id: data.request.payload.id ?? null, error: { code: -32000, message: error.message || 'local_bridge_error' } }; }
          await fetch(`${apiBase()}/v1/bridge/${encodeURIComponent(item.remoteId)}/respond`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-FileBuddy-Key': item.connection.apiKey }, body: JSON.stringify({ requestId: data.request.id, response: result }) });
        }
      } catch {}
      await new Promise(resolve => setTimeout(resolve, 700));
    }
  })();
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1180, height: 780, minWidth: 900, minHeight: 620,
    title: '文小哥 FileBuddy',
    backgroundColor: '#f7f7f5',
    webPreferences: { preload: path.join(__dirname, 'preload.cjs'), contextIsolation: true, nodeIntegration: false }
  });
  win.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'));
}

ipcMain.handle('workspace:list', async () => { const items = await readWorkspaces(); items.forEach(startBridge); return items; });
ipcMain.handle('workspace:choose-folder', async () => {
  const result = await dialog.showOpenDialog({ properties: ['openDirectory', 'createDirectory'] });
  return result.canceled ? null : result.filePaths[0];
});
ipcMain.handle('workspace:create', async (_event, input) => {
  const folder = path.resolve(String(input.folder || ''));
  const stat = await fs.stat(folder);
  if (!stat.isDirectory()) throw new Error('选择的路径不是文件夹');
  const items = await readWorkspaces();
  const item = { id: `ws_${Date.now().toString(36)}`, name: String(input.name || path.basename(folder)), folder, permission: input.permission === 'read_only' ? 'read_only' : 'read_write', allowDelete: Boolean(input.allowDelete), createdAt: new Date().toISOString(), online: true };
  items.push(item); await writeWorkspaces(items); return item;
});
ipcMain.handle('workspace:update', async (_event, input) => {
  const items = await readWorkspaces();
  const index = items.findIndex((item) => item.id === input.id);
  if (index < 0) throw new Error('项目不存在');
  items[index] = { ...items[index], ...input };
  await writeWorkspaces(items);
  startBridge(items[index]);
  return items[index];
});
ipcMain.handle('workspace:remove', async (_event, id) => {
  const items = (await readWorkspaces()).filter(item => item.id !== id);
  await writeWorkspaces(items); return items;
});
ipcMain.handle('workspace:open-folder', async (_event, folder) => { await shell.openPath(folder); return true; });

app.whenReady().then(() => { Menu.setApplicationMenu(null); createWindow(); app.on('activate', () => { if (!BrowserWindow.getAllWindows().length) createWindow(); }); });
app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
