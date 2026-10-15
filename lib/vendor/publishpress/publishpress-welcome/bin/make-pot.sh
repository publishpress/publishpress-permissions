#!/usr/bin/env bash
# Regenerate languages/publishpress-welcome.pot from src/ with GNU gettext (xgettext).
# Used instead of `wp i18n make-pot` so it also works where PHP has no mbstring extension.
set -euo pipefail

cd "$(dirname "$0")/.."
POT=languages/publishpress-welcome.pot
VERSION=$(php -r 'echo json_decode(file_get_contents("composer.json"))->version;')

mkdir -p languages

find src -name '*.php' | sort | xgettext --language=PHP --from-code=UTF-8 --files-from=- -o "$POT" \
    --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e \
    --keyword=esc_attr__ --keyword=esc_attr_e --keyword=_x:1,2c --keyword=_n:1,2 \
    --add-comments=translators --no-location \
    --package-name="PublishPress Welcome" --package-version="$VERSION" \
    --msgid-bugs-address="https://github.com/publishpress/library-publishpress-welcome/issues" \
    --copyright-holder="PublishPress"

# Tidy the generated header so the template is ready to hand to translators.
sed -i \
    -e 's/charset=CHARSET/charset=UTF-8/' \
    -e 's/^# SOME DESCRIPTIVE TITLE\./# PublishPress Welcome translation template./' \
    -e 's/^# Copyright (C) YEAR PublishPress/# Copyright (C) PublishPress/' \
    -e 's/^# This file is distributed under .*/# This file is distributed under the GPL-3.0-or-later license./' \
    -e '/^# FIRST AUTHOR/d' \
    -e '/^#, fuzzy$/d' \
    -e '/^"PO-Revision-Date:/d' \
    -e '/^"Last-Translator:/d' \
    -e '/^"Language-Team:/d' \
    "$POT"

msgfmt --check-format -o /dev/null "$POT"
echo "Wrote $POT ($(grep -c '^msgid' "$POT") entries including the header)"
