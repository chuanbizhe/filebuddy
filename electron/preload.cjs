const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('filebuddy', {
  getApiBase: () => process.env.FILEBUDDY_API_URL || 'http://127.0.0.1:8080',
  listWorkspaces: () => ipcRenderer.invoke('workspace:list'),
  chooseFolder: () => ipcRenderer.invoke('workspace:choose-folder'),
  createWorkspace: (input) => ipcRenderer.invoke('workspace:create', input),
  updateWorkspace: (input) => ipcRenderer.invoke('workspace:update', input),
  removeWorkspace: (id) => ipcRenderer.invoke('workspace:remove', id),
  openFolder: (folder) => ipcRenderer.invoke('workspace:open-folder', folder)
});
