const fs = require('node:fs/promises');
const path = require('node:path');
const zlib = require('node:zlib');

function normalizeVirtual(input) {
  const value = String(input || '/workspace').replaceAll('\\', '/');
  const virtual = value.startsWith('/workspace') ? value.slice('/workspace'.length) : value;
  if (virtual.split('/').some(part => part === '..')) throw new Error('path_outside_workspace');
  const normalized = path.posix.normalize('/' + virtual).replace(/^\/+/, '');
  if (!normalized || normalized === '.') return '';
  if (normalized === '..' || normalized.startsWith('../')) throw new Error('path_outside_workspace');
  return normalized;
}

function localPath(root, virtual) {
  const relative = normalizeVirtual(virtual);
  const target = path.resolve(root, relative);
  const base = path.resolve(root);
  if (target !== base && !target.startsWith(`${base}${path.sep}`)) throw new Error('path_outside_workspace');
  return target;
}

async function walk(root, current = root, result = []) {
  const entries = await fs.readdir(current, { withFileTypes: true });
  for (const entry of entries) {
    const absolute = path.join(current, entry.name);
    const relative = path.relative(root, absolute).split(path.sep).join('/');
    result.push({ path: `/workspace/${relative}`, type: entry.isDirectory() ? 'directory' : 'file' });
    if (entry.isDirectory()) await walk(root, absolute, result);
  }
  return result;
}

function zipEntries(buffer) {
  const entries = new Map(); let offset = 0;
  while (offset + 30 <= buffer.length) {
    const signature = buffer.readUInt32LE(offset); if (signature !== 0x04034b50) { offset += 1; continue; }
    const method = buffer.readUInt16LE(offset + 8); const compressedSize = buffer.readUInt32LE(offset + 18);
    const nameLength = buffer.readUInt16LE(offset + 26); const extraLength = buffer.readUInt16LE(offset + 28);
    const name = buffer.subarray(offset + 30, offset + 30 + nameLength).toString('utf8');
    const dataStart = offset + 30 + nameLength + extraLength; const data = buffer.subarray(dataStart, dataStart + compressedSize);
    try { entries.set(name, method === 0 ? data : method === 8 ? zlib.inflateRawSync(data) : null); } catch { entries.set(name, null); }
    offset = dataStart + compressedSize;
  }
  return entries;
}

function xmlText(xml) {
  return String(xml).replace(/<w:tab\s*\/?>(?!\n)/g, '\t').replace(/<[^>]+>/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&#39;/g, "'").replace(/&quot;/g, '"').replace(/\s+/g, ' ').trim();
}

function parseOffice(buffer, extension) {
  const entries = zipEntries(buffer); const xml = [];
  for (const [name, data] of entries) {
    if (!data || !name.toLowerCase().endsWith('.xml')) continue;
    const lower = name.toLowerCase();
    if (extension === '.docx' && lower.includes('word/document.xml')) xml.push(data.toString('utf8'));
    if (extension === '.xlsx' && (lower.includes('sharedstrings.xml') || lower.includes('/worksheets/'))) xml.push(data.toString('utf8'));
    if (extension === '.pptx' && lower.includes('/slides/slide')) xml.push(data.toString('utf8'));
  }
  return xmlText(xml.join('\n'));
}

async function readContent(target, virtualPath) {
  const stat = await fs.stat(target); if (stat.size > 50 * 1024 * 1024) throw new Error('file_too_large_for_direct_read');
  const extension = path.extname(virtualPath).toLowerCase(); const buffer = await fs.readFile(target);
  if (['.docx', '.xlsx', '.pptx'].includes(extension)) return { content: parseOffice(buffer, extension), contentType: 'office-text', size: stat.size };
  return { content: buffer.toString('utf8'), contentType: 'text', size: stat.size };
}

async function handleRequest(workspace, request) {
  const root = workspace.folder;
  const params = request.params || {};
  const name = request.method === 'tools/call' ? params.name : request.method;
  const args = request.method === 'tools/call' ? (params.arguments || {}) : params;
  if (request.method === 'initialize') return { protocolVersion: '2025-03-26', capabilities: { tools: {} }, serverInfo: { name: 'filebuddy-local', version: '0.1.1' } };
  if (request.method === 'tools/list') return { tools: ['get_workspace_info', 'list_files', 'get_file_info', 'read_file', 'read_file_range', 'search_text', 'write_file', 'apply_patch', 'upload_large_file'].map(name => ({ name, description: `FileBuddy ${name}`, inputSchema: { type: 'object' } })) };
  if (name === 'get_workspace_info') return { name: workspace.name, workspace: '/workspace', permission: workspace.permission };
  if (name === 'list_files') return { files: await walk(root) };
  if (name === 'get_file_info') { const target = localPath(root, args.path); const stat = await fs.stat(target); return { path: args.path, type: stat.isDirectory() ? 'directory' : 'file', size: stat.size, modifiedAt: stat.mtime.toISOString() }; }
  if (name === 'read_file' || name === 'read_file_range') {
    const target = localPath(root, args.path); const result = await readContent(target, args.path);
    if (name === 'read_file_range') return { path: args.path, content: result.content.slice(Number(args.start || 0), Number(args.end || result.content.length)), contentType: result.contentType, size: result.size };
    return { path: args.path, ...result };
  }
  if (name === 'search_text') {
    const files = await walk(root); const needle = String(args.query || ''); const matches = [];
    for (const file of files.filter(item => item.type === 'file')) { try { const content = await fs.readFile(localPath(root, file.path), 'utf8'); if (content.includes(needle)) matches.push(file.path); } catch {} }
    return { query: needle, matches };
  }
  if (name === 'write_file' || name === 'apply_patch') {
    if (workspace.permission !== 'read_write') throw new Error('workspace_read_only');
    if (name === 'apply_patch') throw new Error('apply_patch_requires_explicit_file_content');
    const target = localPath(root, args.path); await fs.mkdir(path.dirname(target), { recursive: true }); await fs.writeFile(target, String(args.content || ''), 'utf8'); return { path: args.path, written: true };
  }
  throw new Error('unsupported_tool');
}

module.exports = { handleRequest };
