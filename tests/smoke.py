#!/usr/bin/env python3
"""MCP smoke test for WP MCP Tools (stdlib only).

Usage: python3 tests/smoke.py BASE_URL USER APP_PASSWORD
Checks: unauthenticated probe -> 401, initialize, tools/list, tools/call wpmcp-get-site-info.
Prints the bundled MCP Adapter version reported by the server and exits 1 on any failure.
"""
import base64, json, sys, urllib.request, urllib.error

base, user, pw = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
url = base + '/wp-json/wp-mcp-tools/mcp'
auth = 'Basic ' + base64.b64encode(f'{user}:{pw}'.encode()).decode()
fails = []

def call(payload, session=None, authed=True):
    h = {'Content-Type': 'application/json', 'Accept': 'application/json, text/event-stream',
         'MCP-Protocol-Version': '2025-06-18'}
    if authed:
        h['Authorization'] = auth
    if session:
        h['Mcp-Session-Id'] = session
    req = urllib.request.Request(url, data=json.dumps(payload).encode(), headers=h, method='POST')
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            body = r.read().decode()
            return r.status, dict(r.headers), (json.loads(body) if body.strip() else None)
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        try:
            data = json.loads(body)
        except ValueError:
            data = body
        return e.code, dict(e.headers), data

def check(name, ok, info=''):
    print(('PASS ' if ok else 'FAIL ') + name + (f' — {info}' if info else ''))
    if not ok:
        fails.append(name)

init = {'jsonrpc': '2.0', 'id': 1, 'method': 'initialize', 'params': {
    'protocolVersion': '2025-06-18', 'capabilities': {}, 'clientInfo': {'name': 'smoke', 'version': '1'}}}

st, hd, _ = call(init, authed=False)
check('unauthenticated request is rejected', st == 401, f'HTTP {st}')

st, hd, data = call(init)
sid = {k.lower(): v for k, v in hd.items()}.get('mcp-session-id')
ok = st == 200 and isinstance(data, dict) and 'result' in data
check('initialize', ok and bool(sid), f'HTTP {st}, session={bool(sid)}')
if ok:
    si = data['result'].get('serverInfo', {})
    print('     server:', si.get('name'), si.get('version'))
call({'jsonrpc': '2.0', 'method': 'notifications/initialized'}, sid)

st, _, data = call({'jsonrpc': '2.0', 'id': 2, 'method': 'tools/list', 'params': {}}, sid)
tools = [t['name'] for t in (data or {}).get('result', {}).get('tools', [])] if isinstance(data, dict) else []
check('tools/list', st == 200 and 'wpmcp-get-site-info' in tools, f'{len(tools)} tools')

st, _, data = call({'jsonrpc': '2.0', 'id': 3, 'method': 'tools/call',
                    'params': {'name': 'wpmcp-get-site-info', 'arguments': {}}}, sid)
res = (data or {}).get('result', {}) if isinstance(data, dict) else {}
check('tools/call wpmcp-get-site-info', st == 200 and res and not res.get('isError'),
      json.dumps(res)[:160])

print('RESULT:', 'OK' if not fails else 'FAILED: ' + ', '.join(fails))
sys.exit(1 if fails else 0)
