const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { handleRequest } = require('../electron/local-bridge.cjs');

test('local bridge reads workspace files and blocks traversal', async () => {
  const root = await fs.mkdtemp(path.join(os.tmpdir(), 'filebuddy-'));
  await fs.writeFile(path.join(root, 'hello.txt'), 'hello FileBuddy', 'utf8');
  const workspace = { name: 'Test', folder: root, permission: 'read_write' };
  const info = await handleRequest(workspace, { method: 'tools/call', params: { name: 'get_workspace_info', arguments: {} } });
  assert.equal(info.workspace, '/workspace');
  const file = await handleRequest(workspace, { method: 'tools/call', params: { name: 'read_file', arguments: { path: '/workspace/hello.txt' } } });
  assert.equal(file.content, 'hello FileBuddy');
  await assert.rejects(() => handleRequest(workspace, { method: 'tools/call', params: { name: 'read_file', arguments: { path: '/workspace/../secret.txt' } } }), /path_outside_workspace/);
  await fs.rm(root, { recursive: true, force: true });
});
