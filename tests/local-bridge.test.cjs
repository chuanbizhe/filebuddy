const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { handleRequest } = require('../electron/local-bridge.cjs');

function makeZip(name, text) {
  const content = Buffer.from(text, 'utf8'); const filename = Buffer.from(name, 'utf8');
  const local = Buffer.alloc(30 + filename.length + content.length); local.writeUInt32LE(0x04034b50, 0); local.writeUInt16LE(20, 4); local.writeUInt16LE(0, 6); local.writeUInt16LE(0, 8); local.writeUInt32LE(content.length, 18); local.writeUInt32LE(content.length, 22); local.writeUInt16LE(filename.length, 26); filename.copy(local, 30); content.copy(local, 30 + filename.length);
  const central = Buffer.alloc(46 + filename.length); central.writeUInt32LE(0x02014b50, 0); central.writeUInt16LE(20, 4); central.writeUInt16LE(20, 6); central.writeUInt32LE(content.length, 20); central.writeUInt32LE(content.length, 24); central.writeUInt16LE(filename.length, 28); central.writeUInt32LE(0, 42); filename.copy(central, 46);
  const end = Buffer.alloc(22); end.writeUInt32LE(0x06054b50, 0); end.writeUInt16LE(1, 8); end.writeUInt16LE(1, 10); end.writeUInt32LE(central.length, 12); end.writeUInt32LE(local.length, 16);
  return Buffer.concat([local, central, end]);
}

test('local bridge reads workspace files and blocks traversal', async () => {
  const root = await fs.mkdtemp(path.join(os.tmpdir(), 'filebuddy-'));
  await fs.writeFile(path.join(root, 'hello.txt'), 'hello FileBuddy', 'utf8');
  const workspace = { name: 'Test', folder: root, permission: 'read_write' };
  const info = await handleRequest(workspace, { method: 'tools/call', params: { name: 'get_workspace_info', arguments: {} } });
  assert.equal(info.workspace, '/workspace');
  const file = await handleRequest(workspace, { method: 'tools/call', params: { name: 'read_file', arguments: { path: '/workspace/hello.txt' } } });
  assert.equal(file.content, 'hello FileBuddy');
  await assert.rejects(() => handleRequest(workspace, { method: 'tools/call', params: { name: 'read_file', arguments: { path: '/workspace/../secret.txt' } } }), /path_outside_workspace/);
  await assert.rejects(() => handleRequest({ ...workspace, permission: 'read_only' }, { method: 'tools/call', params: { name: 'write_file', arguments: { path: '/workspace/new.txt', content: 'blocked' } } }), /workspace_read_only/);
  await fs.rm(root, { recursive: true, force: true });
});

test('local bridge extracts readable text from docx containers', async () => {
  const root = await fs.mkdtemp(path.join(os.tmpdir(), 'filebuddy-office-'));
  await fs.writeFile(path.join(root, 'note.docx'), makeZip('word/document.xml', '<w:document><w:p><w:r><w:t>合同标题</w:t></w:r></w:p></w:document>'));
  const result = await handleRequest({ name: 'Office', folder: root, permission: 'read_only' }, { method: 'tools/call', params: { name: 'read_file', arguments: { path: '/workspace/note.docx' } } });
  assert.equal(result.contentType, 'office-text'); assert.match(result.content, /合同标题/);
  await fs.rm(root, { recursive: true, force: true });
});
