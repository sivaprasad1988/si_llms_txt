# Changelog

## 1.1.0 (unreleased)

- PSR-14 event `ModifyLlmsTxtEvent`: listeners add, change, reorder or remove sections and items of
  llms.txt / llms-full.txt (e.g. records of their own extension), set title and intro, add cache tags and a lifetime.
- PSR-14 event `ModifyMarkdownEvent`: listeners change frontmatter and Markdown of every `.md` response,
  get the rendered HTML, or answer 404.
- llms-full.txt follows the section order of llms.txt (pages, top-level sections, articles, news).
- llms-full.txt keeps the query string when fetching a `.md` URL, so news detail URLs without route enhancer are no longer missing.
- Internal: `LlmsTxtBuilder::pageDocument()` / `newsDocument()` replaced by `pageFrontmatter()` / `newsFrontmatter()`.

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
