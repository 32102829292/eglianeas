#!/usr/bin/env python3
with open('config/view.php', 'r') as f:
    content = f.read()
old = "storage_path('framework.views')"
new = "env('VIEW_COMPILED_PATH', storage_path('framework.views'))"
if old in content:
    content = content.replace(old, new)
    with open('config/view.php', 'w') as f:
        f.write(content)
    print('Replaced successfully')
else:
    print('Old string not found')
    print('Content sample:', repr(content[:500]))