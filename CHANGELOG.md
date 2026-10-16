# Changelog

## 0.9.0 (2026-10-16)

Release line for TYPO3 12.4 and 13.4 (branch `typo3-12-13`); TYPO3 14.3 stays on 1.x (branch `main`).
Based on 1.0.7, tested on fresh installations of 12.4.45 and 13.4.36 (EXT:news, two languages):
  - Resolved page read from `frontend.page.information` (13+) or the TypoScriptFrontendController (12).
  - `<link>` tags: page content from the event (14) or the TypoScriptFrontendController (12/13).
  - Event listener registered in `Services.yaml` instead of `#[AsEventListener]` (attribute needs 13+).
  - Page translations detected via `_PAGES_OVERLAY_UID` on TYPO3 12 (translated pages were missing from llms.txt).
  - llms.txt headings from `Resources/Private/Language/locallang.xlf` in the language of the file (was hardcoded German/English; "Languages" was never translated). Further languages: add an XLF file or use `locallangXMLOverride`.
  - TYPO3 12 has no Site Sets: add `woit/si-llms-txt` to the site's `dependencies` by hand.

## 1.0.7 (2026-10-13)

Tested on a second TYPO3 14.3 site with a different theme; these fixes make the Markdown work for other templates:

- Content area: without an element with the configured `contentElementId`, the first `<main>` is used before `<body>`
  (header, footer and cookie banners outside `<main>` no longer end up in the Markdown).
- Accordion triggers inside headings (`<h3><button>Title</button></h3>`) keep their title; headings without text are removed.
- Lazy-loaded images use `data-src` / `data-lazy-src` / `srcset` instead of a `data:` placeholder, or are dropped.
- Card links around a heading and text become a link on the heading instead of a broken multi-line link.
- Links with an empty `href` are treated like links without `href`.
- llms.txt H1 falls back to the root page title when the site has no website title (was an empty `# `).

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
