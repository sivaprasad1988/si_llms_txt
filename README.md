# si_llms_txt – LLMs.txt Generator

Serves [llms.txt](https://llmstxt.org/) files and a Markdown version of every page, per site language.

| URL | Content |
|-----|---------|
| `/llms.txt` | Index of the default language |
| `/<lang>/llms.txt` | Index of one language: pages grouped by top-level page, articles (newest first), links to other languages |
| `/<lang>/llms-full.txt` | Full Markdown of all pages of one language |
| `/<page>.md`, `/<lang>/index.md` (root page) | One page as Markdown with YAML frontmatter |

Untranslated pages (strict fallback) are left out and their `.md` returns 404.
Every included HTML page gets `<link rel="alternate" type="text/markdown" href="….md">`.

## Setup

1. Add the repository and require the package, then run `typo3 extension:setup`:
   ```bash
   composer config repositories.si-llms-txt vcs https://github.com/sivaprasad1988/si_llms_txt.git
   composer require woit/si-llms-txt:^1.0
   vendor/bin/typo3 extension:setup
   ```
2. Add the Site Set `woit/si-llms-txt` to the site dependencies
3. Sites module → language → tab "LLM" → **llms.txt intro** (Markdown below the site title)
4. Do not put a static `public/llms.txt` in place – the web server would serve it instead

## Page properties (tab "LLM", standard pages and articles)

| Field | Effect |
|-------|--------|
| Exclude from llms.txt | Page missing from llms.txt / llms-full.txt, `.md` returns 404 (applies to all languages) |
| LLM priority | 0–100, higher first among siblings (applies to all languages) |
| LLM description | Text next to the link in llms.txt; fallback: meta description |
| LLM Markdown | Served at the `.md` URL instead of the generated version (translatable) |

Pages with "no index" (EXT:seo) are left out as well.

## How the Markdown is made

Without "LLM Markdown", the page is rendered normally and the element with the id from
`sillmstxt.contentElementId` (default `wrapper`, i.e. `<main id="wrapper">`) is converted with
league/html-to-markdown. Navigation, breadcrumbs, asides, forms, buttons, scripts and decorative images are removed.

`llms-full.txt` fetches each page's `.md` from the server itself (`sillmstxt.loopbackUrl`, default
`http://127.0.0.1`, with the Host and X-Forwarded-Proto of the site base). It needs TYPO3 to trust
X-Forwarded-Proto from 127.0.0.1 when the site base is https (`reverseProxyIP`/`reverseProxySSL`).

## EXT:news (tx_news)

Optional, only active when `georgringer/news` is installed **and** `sillmstxt.news.detailPid` is set:

- llms.txt gets a `## News` section (newest first, with date and teaser/description); llms-full.txt contains every news record
- Each news detail URL has a `.md` variant (e.g. `/de/news/my-article.md`, or the query-string URL with `.md` on the path)
  with frontmatter from the news record (title, description/teaser, date, lastmod, canonical)
- Only news of type "News" (not internal/external links), visible in the frontend, and – in other languages – only real translations
- The detail page alone (without a news record) is not listed; its `.md` returns 404
- Storage: `sillmstxt.news.storagePids`, or every page of the site tree (hidden storage folders included)
- Editing or creating news flushes llms.txt/llms-full.txt (tags `tx_news_uid_*`, `tx_news_pid_*` flushed by EXT:news)

Just_news articles (page doktype 12) need no extra setup, see `sillmstxt.articleDoktypes`.

## Caching

llms.txt and llms-full.txt are cached in `cache_sillmstxt` (group "pages") and tagged with every listed
page, so editing a page or its content regenerates them. The intro is part of the cache key.
If a page cannot be fetched for llms-full.txt, the file is kept for 5 minutes only.

## Site settings

| Setting | Default |
|---------|---------|
| `sillmstxt.articleDoktypes` | `12` (just_news) |
| `sillmstxt.contentElementId` | `wrapper` |
| `sillmstxt.loopbackUrl` | `http://127.0.0.1` |
| `sillmstxt.news.detailPid` | `0` (off) |
| `sillmstxt.news.storagePids` | empty = whole site tree |

## Documentation

Full documentation in TYPO3 format: [`Documentation/`](Documentation/Index.rst). Render it with
`docker run --rm -v $(pwd):/project ghcr.io/typo3-documentation/render-guides:latest --config=Documentation`.

## License

GPL-2.0-or-later, see [LICENSE.md](LICENSE.md).
