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
    """Return the raw fields of one entry (still escaped), plural forms included.

    A plural entry has no `msgstr ""` line at all: it has `msgid_plural` and
    `msgstr[0]`, `msgstr[1]`, … . Reading it as a singular entry with a missing
    msgstr would report a translated string as untranslated, and rewriting it
    without its plural forms would delete the translation.
    """
    fields = {'msgctxt': None, 'msgid': None, 'msgid_plural': None, 'msgstr': None, 'plurals': []}
    current = None
    for line in chunk.split('\n'):
        line = line.rstrip()
        if not line:
            continue
        match = re.match(r'^(msgctxt|msgid_plural|msgid|msgstr\[(\d+)\]|msgstr)\s+"(.*)"$', line)
        if match:
            name, index, text = match.group(1), match.group(2), match.group(3)
            if index is not None:
                current = 'plural:%s' % index
                fields['plurals'].append([int(index), text])
            else:
                current = name
                fields[name] = text
            continue
        if current and line.startswith('"') and line.endswith('"'):
            if current.startswith('plural:'):
                fields['plurals'][-1][1] += line[1:-1]
            else:
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
            'msgid_plural_raw': fields['msgid_plural'],
            'msgstr_raw': fields['msgstr'],
            'plurals_raw': fields['plurals'],
            'key': unescape(fields['msgid']),
            'ctxt_key': unescape(fields['msgctxt']) if fields['msgctxt'] is not None else None,
        }
        if fields['msgid'] == '':
            # The header block: `msgid ""` and a `msgstr` that carries the header
            # fields (in a catalogue) or nothing at all (in a template).
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
    plurals = {}
    flags = {}
    for entry in po:
        existing[entry['key']] = entry['msgstr_raw']
        if entry['plurals_raw']:
            plurals[entry['key']] = entry['plurals_raw']
        kept = [c for c in entry['comments'] if c.startswith('#,')]
        if kept:
            flags[entry['key']] = kept

    pot_date = ''
    match = re.search(r'"POT-Creation-Date: ([^\\"]+)\\n', pot_header or '')
    if match:
        pot_date = match.group(1)

    pot_version = ''
    match = re.search(r'"Project-Id-Version: ([^\\"]+)\\n', pot_header or '')
    if match:
        pot_version = match.group(1)

    header = po_header or ''
    updates = []

    if pot_date:
        updates.append(('POT-Creation-Date', pot_date))

    if pot_version:
        updates.append(('Project-Id-Version', pot_version))

    updates.append(
        ('PO-Revision-Date', datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M+0000'))
    )

    for field, value in updates:
        # `re.sub` would read `\n` in a replacement *string* as a newline and
        # split the header field it is meant to rewrite, so the replacement is a
        # callable and its value is used as written. The quotes around the field
        # are not part of the pattern: a catalogue may wrap the header fields
        # across lines or hold them all in one long msgstr.
        header = re.sub(
            r'%s: [^\\"]+\\n' % field,
            lambda match: '%s: %s\\n' % (field, value),
            header,
        )

    out = [header, '']
    missing = []

    for entry in pot:
        key = entry['key']
        msgstr = existing.get(key)
        forms = plurals.get(key, [])

        if msgstr is None and key in translations:
            msgstr = escape(translations[key])

        # A plural entry is translated when its forms are; its singular msgstr is
        # legitimately absent from the template and from the catalogue.
        if entry['msgid_plural_raw'] is not None or forms:
            if not any(raw for _, raw in forms) and key != '':
                missing.append(key)
        else:
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

        if entry['msgid_plural_raw'] is not None:
            out.append('msgid_plural "%s"' % entry['msgid_plural_raw'])

        if msgstr is not None:
            out.append('msgstr "%s"' % msgstr)

        for index, raw in sorted(forms):
            out.append('msgstr[%d] "%s"' % (index, raw))

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
