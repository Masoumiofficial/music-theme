#!/usr/bin/env python3
"""Fill a .po catalogue from its .pot template, in the template's own order.

Why a script and not a hand edit: a catalogue merge is lossy in ways that are
invisible until the translator loads it (dropped flags, duplicated msgstr, a
msgid that moved), and the i18n gate can only see the result. This keeps the
template's order and comments, carries the existing translations and the
`keep-latin` flags over, and refuses to write a file with an untranslated string
that the caller did not supply.

Usage:
    python3 tools/po-merge.py wavira/languages/wavira.pot wavira/languages/fa_IR.po [new-translations.json]
"""
import io
import json
import re
import sys
from datetime import datetime, timezone


def unescape(value: str) -> str:
    out = []
    i = 0
    while i < len(value):
        ch = value[i]
        if ch == '\\' and i + 1 < len(value):
            nxt = value[i + 1]
            out.append({'n': '\n', 't': '\t', 'r': '\r', '"': '"', '\\': '\\'}.get(nxt, nxt))
            i += 2
            continue
        out.append(ch)
        i += 1
    return ''.join(out)


def escape(value: str) -> str:
    return value.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t')


def read_msg_fields(chunk):
    """Return {'msgctxt': str, 'msgid': str, 'msgstr': str} raw (still escaped)."""
    fields = {'msgctxt': None, 'msgid': None, 'msgstr': None}
    current = None
    for line in chunk.split('\n'):
        line = line.rstrip()
        if not line:
            continue
        match = re.match(r'^(msgctxt|msgid|msgstr)\s+"(.*)"$', line)
        if match:
            current = match.group(1)
            fields[current] = match.group(2)
            continue
        if current and line.startswith('"') and line.endswith('"'):
            fields[current] += line[1:-1]
    return fields


def parse(path):
    raw = io.open(path, encoding='utf-8').read()
    chunks = re.split(r'\n[ \t]*\n', raw)
    entries = []
    header = None
    for chunk in chunks:
        if 'msgid' not in chunk:
            if header is None:
                header = chunk.strip()
            continue
        fields = read_msg_fields(chunk)
        comments = [line.rstrip() for line in chunk.split('\n') if line.startswith('#')]
        entry = {
            'comments': comments,
            'msgctxt_raw': fields['msgctxt'],
            'msgid_raw': fields['msgid'],
            'msgstr_raw': fields['msgstr'],
            'key': unescape(fields['msgid']),
            'ctxt_key': unescape(fields['msgctxt']) if fields['msgctxt'] is not None else None,
        }
        if fields['msgid'] == '' and fields['msgstr'] is not None and fields['msgstr'] == '':
            header = chunk.strip()
            continue
        entries.append(entry)
    return header, entries


def main():
    pot_path, po_path = sys.argv[1], sys.argv[2]
    translations = {}
    if len(sys.argv) > 3:
        translations = json.load(io.open(sys.argv[3], encoding='utf-8'))

    pot_header, pot = parse(pot_path)
    po_header, po = parse(po_path)

    existing = {}
    flags = {}
    for entry in po:
        existing[entry['key']] = entry['msgstr_raw']
        kept = [c for c in entry['comments'] if c.startswith('#,')]
        if kept:
            flags[entry['key']] = kept

    pot_date = ''
    match = re.search(r'"POT-Creation-Date: ([^\\]+)\\n"', pot_header or '')
    if match:
        pot_date = match.group(1)

    header = po_header or ''
    if pot_date:
        header = re.sub(r'"POT-Creation-Date: [^\\]+\\n"', '"POT-Creation-Date: %s\\n"' % pot_date, header)
    header = re.sub(
        r'"PO-Revision-Date: [^\\]+\\n"',
        '"PO-Revision-Date: %s\\n"' % datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M+0000'),
        header,
    )

    out = [header, '']
    missing = []

    for entry in pot:
        key = entry['key']
        msgstr = existing.get(key)

        if msgstr is None and key in translations:
            msgstr = escape(translations[key])

        if msgstr is None:
            missing.append(key)
            msgstr = ''

        if msgstr == '' and key != '':
            missing.append(key)

        # Template references, then the flags the translator set by hand.
        comments = [c for c in entry['comments'] if c.startswith('#:') or c.startswith('#.')]
        comments += flags.get(key, [])

        out.extend(comments)

        if entry['msgctxt_raw'] is not None:
            out.append('msgctxt "%s"' % entry['msgctxt_raw'])

        out.append('msgid "%s"' % entry['msgid_raw'])
        out.append('msgstr "%s"' % msgstr)
        out.append('')

    if missing:
        sys.stderr.write('UNTRANSLATED (%d):\n' % len(missing))
        for key in missing[:40]:
            sys.stderr.write('  %s\n' % key)
        sys.exit(2)

    io.open(po_path, 'w', encoding='utf-8').write('\n'.join(out).rstrip('\n') + '\n')
    print('%s: %d entry/entries written, %d carried over, %d new' % (
        po_path,
        len(pot),
        len([e for e in pot if e['key'] in existing]),
        len([e for e in pot if e['key'] not in existing]),
    ))


if __name__ == '__main__':
    main()
