#!/usr/bin/env python3
import sys

with open('config/view.php', 'r') as f:
    content = f.read()

# Replace the line
old = " 'compiled' => storage_path('framework.views'),"
new = " 'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework.views')),"

if old in content:
    content = content.replace(old, new)
    with open('config/view.php', 'w') as f:
        f.write(content)
    print('Replaced successfully')
else:
    print('Old string not found')
    print('Looking for:', repr(old))