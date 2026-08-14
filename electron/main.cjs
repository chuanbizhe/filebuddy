const { app, BrowserWindow, dialog, ipcMain, shell } = require('electron');
const fs = require('node:fs/promises');
const path = require('node:path');

const storePath = () => path.join(app.getPath('userData'), 'workspaces.json');

async function readWorkspaces() {
  try { return JSON.parse(await fs.readFile(storePath(), 'utf8')); }
  catch { return []; }
}

async function writeWorkspaces(items) {
  await fs.mkdir(path.dirname(storePath()), { recursive: true });
  await fs.writeFile(storePath(), JSON.stringify(items, null, 2), 'utf8');
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1180, height: 780, minWidth: 900, minHeight: 620,
    backgroundColor: '#f7f7f5',
    webPreferences: { preload: path.join(__dirname, 'preload.cjs'), contextIsolation: true, nodeIntegration: false }
  });
  win.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'));
}

ipcMain.handle('workspace:list', () => readWorkspaces());
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
ipcMain.handle('workspace:remove', async (_event, id) => {
  const items = (await readWorkspaces()).filter(item => item.id !== id);
  await writeWorkspaces(items); return items;
});
ipcMain.handle('workspace:open-folder', async (_event, folder) => { await shell.openPath(folder); return true; });

app.whenReady().then(() => { createWindow(); app.on('activate', () => { if (!BrowserWindow.getAllWindows().length) createWindow(); }); });
app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
