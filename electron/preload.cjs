const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('filebuddy', {
  listWorkspaces: () => ipcRenderer.invoke('workspace:list'),
  chooseFolder: () => ipcRenderer.invoke('workspace:choose-folder'),
  createWorkspace: (input) => ipcRenderer.invoke('workspace:create', input),
  removeWorkspace: (id) => ipcRenderer.invoke('workspace:remove', id),
  openFolder: (folder) => ipcRenderer.invoke('workspace:open-folder', folder)
});
