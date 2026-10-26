# LLMs.txt & Markdown for AI (si_llms_txt)

Serves llms.txt, llms-full.txt and a Markdown version of every page per language for AI agents and crawlers – generated from the rendered page or written by editors. Optional EXT:news support.

| URL | Content |
|-----|---------|
| `/llms.txt` | Index of the default language |
| `/<lang>/llms.txt` | Index of one language: pages grouped by top-level page, articles (newest first), links to other languages |
| `/llms-full.txt` | Full Markdown of the default language |
| `/<lang>/llms-full.txt` | Full Markdown of all pages of one language |
| `/<page>.md`, `/<lang>/index.md` (root page) | One page as Markdown with YAML frontmatter |

Untranslated pages (strict fallback) are left out and their `.md` returns 404.
Every included page points to its `.md` (`rel="alternate" type="text/markdown"`) and to the `llms.txt` of its
language (`rel="describedby"`), as `<link>` in the HTML head and as HTTP `Link` header, as recommended by
[llmstxt.org](https://llmstxt.org/). `.md` responses get the `describedby` header.

## llms.txt standard

The output follows the proposal at [llmstxt.org](https://llmstxt.org/):

| llmstxt.org | si_llms_txt |
|-------------|-------------|
| File at `/llms.txt` or a sub-path; it covers everything below its path | `/llms.txt` for the site, `/<lang>/llms.txt` per language |
| H1 with the name of the site (the only required section) | `# <website title of the language>` |
| Blockquote with a short summary | Written by you in the **llms.txt intro** of the language: start it with `> ` |
| Sections without headings with more details | Also in the intro, below the blockquote (paragraphs, lists – no headings) |
| H2 sections with file lists `- [name](url): notes` | One section per top-level page, plus articles and news; notes = LLM description or meta description; absolute URLs pointing to the `.md` versions |
| `## Optional` for secondary information | Link to `llms-full.txt` |
| Clean Markdown of a page at the same URL with `.md` appended; URLs without a file name use `index.md` | `/de/pricing` → `/de/pricing.md`, `/de/` → `/de/index.md` (`/de/pricing/index.md` works as well) |
| `rel="alternate" type="text/markdown"` and `rel="describedby"`, as `<link>` or HTTP `Link` header | Both relations, both ways (see above) |

`llms-full.txt` is not part of the proposal; it is a common convention that contains the Markdown of all listed
pages in one file.

## Setup

**Composer mode only.** The extension needs `league/html-to-markdown`, which Composer installs. Installations without Composer (classic mode) are not supported.

1. Install the package from [Packagist](https://packagist.org/packages/woit/si-llms-txt), then run `typo3 extension:setup`:
   ```bash
   composer require woit/si-llms-txt
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
`sillmstxt.contentElementId` (default `wrapper`, i.e. `<main id="wrapper">`; if missing: `<main>`, then `<body>`) is converted with
league/html-to-markdown. Navigation, breadcrumbs, asides, forms, buttons, scripts and decorative images are removed.
Accordion titles (`<a role="button">` without `href`) and `<details><summary>` become `###` headings above their
answer, links without text (icon links) are removed, so no raw HTML remains in the Markdown. Accordion triggers
(`<h3><button>…</button></h3>`) keep their title, lazy-loaded images use `data-src`/`srcset`, and card links around a
heading put the link on the heading.
To leave out anything else (share bars, teasers), add the attribute `data-sillmstxt-ignore` to the element in your
template, e.g. `<div class="share" data-sillmstxt-ignore>`.

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

## PSR-14 events

Other extensions can change what is generated, e.g. list all records of a plugin that shows only one result page.

- `Woit\SiLlmsTxt\Event\ModifyLlmsTxtEvent` – before llms.txt / llms-full.txt is built (cache miss only):
  add, change, reorder or remove sections and items, set title and intro, add cache tags or a lifetime.
  Section ids: `pages`, `section-<uid>` (top-level page), `articles`, `news`.
- `Woit\SiLlmsTxt\Event\ModifyMarkdownEvent` – for every `.md` response of a page or news record:
  change frontmatter and Markdown, read the rendered HTML, or answer 404 with `setNotFound()`.

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Woit\SiLlmsTxt\Event\ModifyLlmsTxtEvent;
use Woit\SiLlmsTxt\LlmsTxt\Item;
use Woit\SiLlmsTxt\LlmsTxt\Section;

final class AddRecordsToLlmsTxt
{
    #[AsEventListener]
    public function __invoke(ModifyLlmsTxtEvent $event): void
    {
        $event->addSection('records', new Section('Records'), 'news');
        foreach ($this->repository->findAll() as $record) {
            $event->addItem('records', new Item($record->getTitle(), $record->getUrl(), $record->getTeaser()));
        }
        $event->addCacheTags('tx_myext_record');
    }
}
```

Added items are not part of llms-full.txt unless they bring their Markdown (`markdown`) or set `includeInFull`
(then `url` must be a `.md` URL of the same site, it is fetched like a page).

Show all records on a list page that renders only the first result page:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Woit\SiLlmsTxt\Event\ModifyMarkdownEvent;

final class ListAllRecordsInMarkdown
{
    private const LIST_PAGE_UID = 42;

    #[AsEventListener]
    public function __invoke(ModifyMarkdownEvent $event): void
    {
        if ($event->getTable() !== 'pages' || (int)($event->getRecord()['l10n_parent'] ?: $event->getRecord()['uid']) !== self::LIST_PAGE_UID) {
            return;
        }
        $lines = [];
        foreach ($this->repository->findAll() as $record) {
            $lines[] = '- [' . $record->getTitle() . '](' . $record->getUrl() . '): ' . $record->getTeaser();
        }
        $event->setMarkdown($event->getMarkdown() . "\n\n## All records\n\n" . implode("\n", $lines));
    }
}
```

Describe the record shown on a detail page, and answer 404 without a record:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Woit\SiLlmsTxt\Event\ModifyMarkdownEvent;

final class DescribeDetailRecord
{
    private const DETAIL_PAGE_UID = 43;

    #[AsEventListener]
    public function __invoke(ModifyMarkdownEvent $event): void
    {
        if ($event->getTable() !== 'pages' || (int)($event->getRecord()['l10n_parent'] ?: $event->getRecord()['uid']) !== self::DETAIL_PAGE_UID) {
            return;
        }
        $arguments = $event->getRequest()->getAttribute('routing')->getArguments();
        $record = $this->repository->findByUid((int)($arguments['tx_myext_pi1']['record'] ?? 0));
        if ($record === null) {
            $event->setNotFound();
            return;
        }
        $frontmatter = $event->getFrontmatter();
        $frontmatter['title'] = $record->getTitle();
        $frontmatter['description'] = $record->getTeaser();
        $frontmatter['canonical'] = $record->getUrl();
        $event->setFrontmatter($frontmatter);
    }
}
```

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

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later, see [LICENSE.md](LICENSE.md).
