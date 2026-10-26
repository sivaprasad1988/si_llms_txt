..  _developer:

=========
Developer
=========

..  _developer-request-flow:

Request flow
============

The extension adds three PSR-15 middlewares to the frontend stack. No route
enhancer is involved: the middlewares answer the text files themselves and
remove ``.md`` before TYPO3 routes the request.

..  code-block:: text

    request
      ├─ woit/si-llms-txt/markdown-suffix   before typo3/cms-frontend/site
      ├─   typo3/cms-frontend/site
      ├─ woit/si-llms-txt/llms-txt          before typo3/cms-frontend/base-redirect-resolver
      ├─   … page-resolver, prepare-tsfe-rendering, shortcut redirect
      ├─ woit/si-llms-txt/markdown-render   after prepare-tsfe-rendering
      └─   rendering

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Middleware
        -   Task
    *   -   ``MarkdownSuffixMiddleware``
        -   ``/de/page.md`` → routes ``/de/page`` and sets the request attribute
            ``sillmstxt.markdown``. Responses without Markdown become a plain
            404; redirects keep the ``.md`` suffix.
    *   -   ``LlmsTxtMiddleware``
        -   Answers :file:`llms.txt` / :file:`llms-full.txt` for the site root
            and every language base.
    *   -   ``MarkdownRenderMiddleware``
        -   Uses the page field "LLM Markdown" or lets the page render and
            converts the HTML. For the EXT:news detail page it takes the
            news record (see :ref:`news`).

For normal HTML requests each middleware only compares a string and passes
the request on.

The event listener ``AddMarkdownAlternateLink``
(``AfterCacheableContentIsGeneratedEvent``) adds the ``<link rel="alternate">``
and ``<link rel="describedby">`` tags when a page is rendered; the result is
part of the page cache. The HTTP ``Link`` header with the same relations is
added by ``MarkdownRenderMiddleware`` on every request (see
:ref:`endpoints-discovery`).

..  _developer-classes:

Classes
=======

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Class
        -   Purpose
    *   -   ``Service\LlmsTxtBuilder``
        -   Builds :file:`llms.txt`, :file:`llms-full.txt` and the frontmatter
            of page and news documents; caching
    *   -   ``Service\PageCollector``
        -   Walks the page tree, language overlay (strict)
    *   -   ``Service\NewsCollector``
        -   News records of EXT:news (optional)
    *   -   ``Service\MarkdownConverter``
        -   HTML → Markdown with league/html-to-markdown; removes navigation,
            breadcrumbs, asides, forms, buttons, scripts, decorative images
            and links without text; accordion titles and ``<summary>``
            become h3 headings; keeps button titles in headings; resolves
            lazy-loaded images; moves card links onto their heading; makes
            links absolute
    *   -   ``Service\DiscoveryLinks``
        -   ``alternate`` and ``describedby`` links of a page, as HTML tags or
            HTTP ``Link`` header
    *   -   ``Service\MarkdownUrl``
        -   Page URL ↔ ``.md`` URL
    *   -   ``Service\SiteConfig``
        -   Site set active? Settings, included page types

..  _developer-events:

PSR-14 events
=============

Listeners of other extensions can change what the extension generates, for
example list all records of a plugin that shows only the first result page.

ModifyLlmsTxtEvent
------------------

``Woit\SiLlmsTxt\Event\ModifyLlmsTxtEvent`` is dispatched before
:file:`llms.txt` or :file:`llms-full.txt` of a site language is built. It
only runs on a cache miss.

*   ``getSite()``, ``getLanguage()``, ``getFileName()``
*   ``getTitle()`` / ``setTitle()``, ``getIntro()`` / ``setIntro()``
*   ``getSections()`` / ``setSections()``, ``getSection($id)``,
    ``addSection($id, $section, $before)``, ``removeSection($id)``
*   ``addItem($sectionId, $item)``: a missing section is created at the end
    with the id as title
*   ``addCacheTags(...$tags)``, ``setCacheLifetime($seconds)``

Section ids: ``pages``, ``section-<uid>`` (top-level page), ``articles``,
``news``. The sections "Languages" and "Optional" are added afterwards.

A ``Woit\SiLlmsTxt\LlmsTxt\Section`` has ``title`` and ``items``. An
``Woit\SiLlmsTxt\LlmsTxt\Item`` has ``title``, ``url``, ``description``,
``date``, ``includeInFull`` and ``markdown``. An item goes into
:file:`llms-full.txt` with its ``markdown`` if set, otherwise only with
``includeInFull``: then ``url`` must be a ``.md`` URL of the same site and
is fetched like a page.

..  code-block:: php
    :caption: EXT:my_ext/Classes/EventListener/AddRecordsToLlmsTxt.php

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
                $event->addItem('records', new Item(
                    $record->getTitle(),
                    $record->getUrl(),
                    $record->getTeaser(),
                ));
            }
            $event->addCacheTags('tx_myext_record');
        }
    }

ModifyMarkdownEvent
-------------------

``Woit\SiLlmsTxt\Event\ModifyMarkdownEvent`` is dispatched for every
``.md`` response of a page or EXT:news record.

*   ``getRequest()``, ``getSite()``, ``getLanguage()``
*   ``getTable()`` (``pages`` or ``tx_news_domain_model_news``),
    ``getRecord()``
*   ``getFrontmatter()`` / ``setFrontmatter()``: ``title``,
    ``description``, ``language``, ``date``, ``lastmod``, ``canonical``,
    ``source``, ``generator``; empty values are not written
*   ``getMarkdown()`` / ``setMarkdown()``: body without frontmatter
*   ``getHtml()``: rendered page, ``null`` for the field "LLM Markdown"
*   ``setNotFound()``: answer 404

A ``.md`` blocked with ``setNotFound()`` is still listed in
:file:`llms.txt`; remove it there with ``ModifyLlmsTxtEvent``.

Example: list all records on a list page that renders only the first result
page.

..  code-block:: php
    :caption: EXT:my_ext/Classes/EventListener/ListAllRecordsInMarkdown.php

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

Example: describe the record shown on a detail page, answer 404 without a
record.

..  code-block:: php
    :caption: EXT:my_ext/Classes/EventListener/DescribeDetailRecord.php

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

..  _developer-caching:

Caching
=======

*   Cache ``sillmstxt`` (database backend, group ``pages``)
*   Entries for :file:`llms.txt` and :file:`llms-full.txt` per site language
*   Tags: ``pageId_<uid>`` of every listed page and the root page;
    with EXT:news also ``tx_news``, ``tx_news_pid_<pid>``, ``tx_news_uid_<uid>``;
    plus the tags of ``ModifyLlmsTxtEvent`` listeners
*   The intro of the language is part of the cache identifier
*   :file:`llms-full.txt` with pages that could not be fetched is kept for
    5 minutes only
*   ``.md`` documents are not cached separately: the HTML comes from the page
    cache, only the conversion runs per request

..  _developer-database:

Database
========

..  code-block:: sql
    :caption: ext_tables.sql

    CREATE TABLE pages (
        tx_sillmstxt_exclude smallint(5) unsigned DEFAULT '0' NOT NULL,
        tx_sillmstxt_priority int(11) DEFAULT '0' NOT NULL,
        tx_sillmstxt_description text,
        tx_sillmstxt_markdown mediumtext
    );

``tx_sillmstxt_exclude`` and ``tx_sillmstxt_priority`` use
``l10n_mode = exclude`` (same value in all languages).
