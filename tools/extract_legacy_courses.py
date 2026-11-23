#!/usr/bin/env python3
"""Extract Drupal course nodes and detail fields into reviewable JSON."""

import argparse
import csv
import json
import re
from pathlib import Path


def make_slug(value):
    value = value.casefold()
    value = re.sub(r'[^a-z0-9]+', '-', value)
    return value.strip('-')


def iter_tuples(line):
    marker = "VALUES"
    start = line.find(marker)
    if start < 0:
        return

    buffer = []
    depth = 0
    quoted = False
    escaped = False

    for char in line[start + len(marker):]:
        if escaped:
            if depth:
                buffer.append(char)
            escaped = False
            continue
        if char == "\\" and quoted:
            if depth:
                buffer.append(char)
            escaped = True
            continue
        if char == "'":
            quoted = not quoted
        if not quoted and char == '(':
            depth += 1
            if depth == 1:
                buffer = []
                continue
        if depth:
            buffer.append(char)
        if not quoted and char == ')':
            depth -= 1
            if depth == 0:
                yield ''.join(buffer[:-1])
                buffer = []


def parse_tuple(raw):
    return next(csv.reader([raw], delimiter=',', quotechar="'", escapechar='\\'))


def clean(value):
    if value is None:
        return None
    value = str(value).replace('\\r\\n', '\n').replace('\\n', '\n').replace('\\r', '\n').strip()
    return value or None


def table_name(line, kind):
    match = re.search(rf'{kind} `([^`]+)`', line)
    return match.group(1) if match else None


def column_name(line):
    match = re.match(r'\s*`([^`]+)`\s+', line)
    return match.group(1) if match else None


def relevant_table(name):
    return name in {'node_field_data', 'node__body', 'file_managed'} or (
        name.startswith('node__field_')
        and any(token in name for token in ('hasil_belajar', 'pengajar', 'level', 'image', 'link', 'lokasi', 'period', 'program'))
    )


def first_field(row, prefixes):
    for key, value in row.items():
        if any(key == prefix or key.startswith(prefix + '_') for prefix in prefixes):
            if value not in (None, ''):
                return value
    return None


def classify(title):
    value = title.casefold()
    if any(word in value for word in ('bipa', 'bahasa indonesia')):
        return 'bipa'
    if any(word in value for word in ('penerjemah', 'penerjemahan', 'interpretasi', 'jurubahasa')):
        return 'ppp'
    if any(word in value for word in ('toefl', 'ielts', 'tes bahasa', 'test bahasa')):
        return 'testing'
    if any(word in value for word in ('english', 'inggris', 'grammar', 'writing', 'speaking', 'conversation')):
        return 'ppb_english'
    return 'ppb_foreign'


def extract(source):
    schemas = {}
    current_table = None
    rows = {}
    file_uris = {}

    with source.open('r', encoding='utf-8', errors='replace', newline='') as handle:
        for line in handle:
            created = table_name(line, 'CREATE TABLE')
            if created:
                current_table = created if relevant_table(created) else None
                if current_table:
                    schemas[current_table] = []
                continue

            if current_table and line.lstrip().startswith('`'):
                column = column_name(line)
                if column:
                    schemas[current_table].append(column)

            inserted = table_name(line, 'INSERT INTO')
            if not inserted or inserted not in schemas:
                continue

            for raw in iter_tuples(line):
                row = dict(zip(schemas[inserted], parse_tuple(raw)))
                entity_id = row.get('nid') or row.get('entity_id')
                if inserted == 'file_managed':
                    if row.get('fid') and row.get('uri'):
                        file_uris[str(row['fid'])] = row['uri']
                    continue
                if entity_id:
                    rows.setdefault(inserted, {}).setdefault(str(entity_id), []).append(row)

    node_rows = rows.get('node_field_data', {})
    body_rows = rows.get('node__body', {})
    records = {}

    for nid, node_list in node_rows.items():
        node = next((item for item in node_list if item.get('type') == 'kursus'), None)
        if not node:
            continue

        title = clean(node.get('title'))
        key = make_slug(title or '')
        if not title or not key:
            continue

        def field_value(fragment):
            table = next((name for name in rows if name == f'node__field_{fragment}' or name.startswith(f'node__field_{fragment}_')), None)
            return first_field((rows.get(table, {}).get(nid) or [{}])[0], [fragment, f'field_{fragment}']) if table else None

        body = first_field((body_rows.get(nid) or [{}])[0], ['body'])
        image_fid = field_value('image')
        records[key] = {
            'legacy_nid': int(nid) if nid.isdigit() else None,
            'title': title,
            'slug': key,
            'division': classify(title),
            'summary': clean(body)[:500] if body else None,
            'description': clean(body),
            'learning_outcomes': clean(field_value('hasil_belajar')),
            'instructor_info': clean(field_value('pengajar')),
            'level': clean(field_value('level')),
            'image_url': file_uris.get(str(image_fid)) if image_fid else None,
            'location': clean(field_value('lokasi')),
            'period': clean(field_value('period')),
            'registration_url': clean(field_value('link')),
            'source': 'lbifib_db_backup.sql',
        }

    return sorted(records.values(), key=lambda item: (item['division'], item['title'].casefold()))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('source', type=Path)
    parser.add_argument('output', type=Path)
    args = parser.parse_args()

    records = extract(args.source)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(records, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    filled = sum(bool(item['description']) for item in records)
    print(f'Extracted {len(records)} course records; {filled} include body content')
    print(f'Wrote staging file: {args.output}')


if __name__ == '__main__':
    main()
