#!/usr/bin/env python3
"""Heuristic static checks for a PHP codebase without PHP available."""

import os, re, collections

ROOTS = ['wavira-core', 'wavira', 'tests']
GLOBAL_OK = re.compile(r'^(WP_.*|WP|wpdb|stdClass|Exception|Error|Closure|Reflection\w*|PHPUnit\w*)$')
SKIP = {'self', 'static', 'parent', 'this'}

files = []
for root in ROOTS:
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in {'node_modules', 'vendor', 'dist'}]
        files += [os.path.join(dirpath, f) for f in filenames if f.endswith('.php')]

declared, methods, per_file = set(), collections.defaultdict(set), {}
for path in files:
    src = open(path, encoding='utf-8').read()
    # comments are prose, not code: drop them before the reference scan
    src = re.sub(r'/\*.*?\*/', '', src, flags=re.S)
    src = re.sub(r'^[ \t]*//.*$', '', src, flags=re.M)
    per_file[path] = src
    m = re.search(r'^namespace\s+([^;]+);', src, re.M)
    ns = m.group(1).strip() if m else ''
    for name in re.findall(r'^(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+(\w+)', src, re.M):
        fqn = (ns + '\\' + name) if ns else name
        declared.add(fqn)
        methods[fqn] |= set(re.findall(r'function\s+(\w+)\s*\(', src))
        methods[fqn] |= set(re.findall(r'(?:const|static)\s+(\w+)', src))

problems = []
for path, src in per_file.items():
    m = re.search(r'^namespace\s+([^;]+);', src, re.M)
    ns = m.group(1).strip() if m else ''
    aliases = {}
    for target, alias in re.findall(r'^use\s+([^;]+?)(?:\s+as\s+(\w+))?;', src, re.M):
        target = target.strip()
        aliases[alias or target.split('\\')[-1]] = target
    refs = [(n, mb) for n, mb in re.findall(r'(?<![\w\\$>:-])([A-Z]\w*)::(\w+)\s*\(', src)]
    refs += [(n, None) for n in re.findall(r'new\s+([A-Z]\w*)\s*\(', src)]
    refs += [(n, None) for n in re.findall(r'extends\s+([A-Z]\w*)', src)]
    for name, member in refs:
        if name in SKIP or GLOBAL_OK.match(name):
            continue
        fqn = aliases.get(name) or ((ns + '\\' + name) if ns else name)
        if fqn not in declared:
            problems.append(f'{path}: unresolved class {name} (looked for {fqn})')
            continue
        if member and member not in methods[fqn]:
            problems.append(f'{path}: {name}::{member}() not found in {fqn}')

for line in sorted(set(problems)):
    print(line)
print(f'checked {len(files)} file(s); {len(set(problems))} problem(s)')
