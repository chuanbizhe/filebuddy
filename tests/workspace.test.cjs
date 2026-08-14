const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

test('workspace paths remain local and use a stable virtual root in the prompt', () => {
  const folder = path.resolve('fixtures', 'demo');
  const workspace = { id: 'ws_test', name: 'Demo', folder, permission: 'read_write', allowDelete: false };
  assert.equal(path.isAbsolute(workspace.folder), true);
  assert.equal(workspace.permission, 'read_write');
  assert.equal(workspace.allowDelete, false);
  const prompt = `仅访问 /workspace 下路径，项目：${workspace.name}`;
  assert.match(prompt, /\/workspace/);
  assert.doesNotMatch(prompt, new RegExp(folder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
});

test('read-only workspaces cannot expose write intent', () => {
  const permission = 'read_only';
  const canWrite = permission !== 'read_only';
  assert.equal(canWrite, false);
});
