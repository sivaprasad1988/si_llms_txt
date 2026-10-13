# Changelog

## 1.0.6 (2026-10-13)

- Elements with the attribute `data-sillmstxt-ignore` are left out of the generated Markdown
  (e.g. share bars or teasers inside the content area).

## 1.0.5 (2026-10-13)

- Accordion titles (`<a role="button">` without href) and `<details><summary>` become h3 headings
  above their answer – no raw HTML in the Markdown any more.
- Links without text (icon links such as share buttons or card overlays) are removed.
- Whitespace inside link texts and after heading markers is trimmed.

## 1.0.4 (2026-10-13)

- Every included page links to its Markdown version (`rel="alternate" type="text/markdown"`) and to the
  llms.txt of its language (`rel="describedby"`), as `<link>` in the HTML head and as HTTP `Link` header,
  as recommended by [llmstxt.org](https://llmstxt.org/). Markdown responses get the `describedby` header.
- `/llms-full.txt` is answered for the default language, like `/llms.txt`.
- README: section on how the output follows llmstxt.org.

## 1.0.3 (2026-10-12)

- `ext_emconf.php` added for the TER metadata (title, description, constraints).

## 1.0.2 (2026-10-12)

- The extension title is used as Composer description (shown as title in the TER).

## 1.0.1 (2026-10-12)

- Extension title "LLMs.txt & Markdown for AI".
- Installation from Packagist documented; Composer mode only.

## 1.0.0 (2026-10-12)

- First release: llms.txt and llms-full.txt per language, a Markdown version of every page
  (generated from the rendered page or written by editors), optional EXT:news support.
