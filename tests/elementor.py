#!/usr/bin/env python3
"""Elementor bridge test (stdlib only).

Usage: python3 tests/elementor.py BASE_URL USER APP_PASSWORD [--expect-off]
Needs a site where Elementor 4.x (+ optionally Pro) is active. Checks that Elementor's own abilities are
served on the WP MCP Tools server (tools/list, resources/list), that a read tool and a page-creating tool
work, and that get-site-info reports Elementor. --expect-off: the "elementor" group is switched off.
"""
import base64, json, sys, urllib.request, urllib.error

base, user, pw = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
expect_off = '--expect-off' in sys.argv
url = base + '/wp-json/wp-mcp-tools/mcp'
auth = 'Basic ' + base64.b64encode(f'{user}:{pw}'.encode()).decode()
fails = []

def call(payload, session=None):
    h = {'Content-Type': 'application/json', 'Accept': 'application/json, text/event-stream',
         'MCP-Protocol-Version': '2025-06-18', 'Authorization': auth}
    if session:
        h['Mcp-Session-Id'] = session
    req = urllib.request.Request(url, data=json.dumps(payload).encode(), headers=h, method='POST')
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            body = r.read().decode()
            return r.status, dict(r.headers), (json.loads(body) if body.strip() else None)
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode()

def check(name, ok, info=''):
    print(('PASS ' if ok else 'FAIL ') + name + (f' — {info}' if info else ''))
    if not ok:
        fails.append(name)

def tool(sid, name, args, i):
    st, _, data = call({'jsonrpc': '2.0', 'id': i, 'method': 'tools/call', 'params': {'name': name, 'arguments': args}}, sid)
    res = (data or {}).get('result', {}) if isinstance(data, dict) else {}
    text = ''.join(c.get('text', '') for c in res.get('content', []) if isinstance(c, dict))
    return st, res, text

st, hd, data = call({'jsonrpc': '2.0', 'id': 1, 'method': 'initialize', 'params': {
    'protocolVersion': '2025-06-18', 'capabilities': {}, 'clientInfo': {'name': 'el-test', 'version': '1'}}})
sid = {k.lower(): v for k, v in hd.items()}.get('mcp-session-id')
check('initialize', st == 200 and bool(sid), f'HTTP {st}')
call({'jsonrpc': '2.0', 'method': 'notifications/initialized'}, sid)

st, _, data = call({'jsonrpc': '2.0', 'id': 2, 'method': 'tools/list', 'params': {}}, sid)
names = [t['name'] for t in (data or {}).get('result', {}).get('tools', [])] if isinstance(data, dict) else []
el = sorted(n for n in names if n.startswith('elementor'))
print('     elementor tools:', ', '.join(el) or '-')
if expect_off:
    check('no Elementor tools when the group is off', not el and 'wpmcp-get-site-info' in names, f'{len(names)} tools')
else:
    check('tools/list has Elementor tools', len(el) >= 10 and 'elementor-get-page-structure' in el, f'{len(el)} Elementor of {len(names)}')
    check('WordPress tools still there', 'wpmcp-get-site-info' in names and 'wpmcp-create-post' in names)

    st, _, data = call({'jsonrpc': '2.0', 'id': 3, 'method': 'resources/list', 'params': {}}, sid)
    res = [r.get('uri') for r in (data or {}).get('result', {}).get('resources', [])] if isinstance(data, dict) else []
    print('     resources:', ', '.join(res) or '-')
    check('resources/list answers', st == 200, f'{len(res)} resources')

    st, r, text = tool(sid, 'wpmcp-get-site-info', {}, 4)
    info = json.loads(text).get('elementor') if text.startswith('{') else None
    check('get-site-info reports Elementor', isinstance(info, dict) and info.get('served_here') is True, json.dumps(info))

    st, r, text = tool(sid, 'elementor-create-page', {'title': 'MCP bridge test'}, 5)
    check('elementor-create-page', st == 200 and not r.get('isError'), text[:200])
    pid = None
    try:
        j = json.loads(text)
        pid = j.get('post_id') or j.get('id') or (j.get('data') or {}).get('post_id')
    except ValueError:
        pass
    if pid:
        st, r, text = tool(sid, 'elementor-get-page-structure', {'post_id': pid}, 6)
        check('elementor-get-page-structure', st == 200 and not r.get('isError'), text[:200])
    st, r, text = tool(sid, 'elementor-list-posts', {}, 7)
    check('elementor-list-posts', st == 200 and not r.get('isError'), text[:160])
    if 'elementor-list-site-parts' in el:
        st, r, text = tool(sid, 'elementor-list-site-parts', {}, 8)
        check('elementor-list-site-parts (Pro)', st == 200, text[:160])

print('RESULT: ' + ('OK' if not fails else 'FAIL ' + ', '.join(fails)))
sys.exit(1 if fails else 0)
