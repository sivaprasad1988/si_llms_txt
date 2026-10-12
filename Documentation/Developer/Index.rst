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
tag when a page is rendered; the result is part of the page cache.

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
            breadcrumbs, asides, forms, buttons, scripts, decorative images;
            makes links absolute
    *   -   ``Service\MarkdownUrl``
        -   Page URL ↔ ``.md`` URL
    *   -   ``Service\SiteConfig``
        -   Site set active? Settings, included page types

..  _developer-caching:

Caching
=======

*   Cache ``sillmstxt`` (database backend, group ``pages``)
*   Entries for :file:`llms.txt` and :file:`llms-full.txt` per site language
*   Tags: ``pageId_<uid>`` of every listed page and the root page;
    with EXT:news also ``tx_news``, ``tx_news_pid_<pid>``, ``tx_news_uid_<uid>``
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
