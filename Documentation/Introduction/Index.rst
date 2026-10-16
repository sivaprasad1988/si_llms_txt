..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

The extension makes the content of a TYPO3 site available in a form that AI
agents, LLM crawlers and tools can read directly:

*   :file:`llms.txt` per language – an index of all pages and articles with
    links and short descriptions, following the
    `llmstxt.org <https://llmstxt.org/>`__ proposal
*   :file:`llms-full.txt` per language – the full Markdown of all pages in one file
*   a Markdown version of every page, reached by adding ``.md`` to its URL
*   a ``<link rel="alternate" type="text/markdown">`` tag in the HTML head of
    every included page

The Markdown of a page is either written by an editor (field "LLM Markdown",
translatable) or generated from the rendered page: the main content area is
converted to Markdown, navigation, footer, forms and scripts are left out.
Because the rendered page is used, any content element type works, including
Content Blocks and custom templates.

..  _features:

Features
========

*   One :file:`llms.txt` and :file:`llms-full.txt` per site language, linked to
    each other
*   Strict language handling: untranslated pages are not listed and their
    ``.md`` URL returns 404
*   Pages grouped by top-level page; articles (page type 12 of EXT:just_news,
    configurable) in their own section, newest first
*   Optional support for news records of EXT:news (see :ref:`news`)
*   YAML frontmatter in every Markdown document (title, description,
    language, last change, canonical URL)
*   Page properties tab "LLM": exclude, priority, description, own Markdown
*   Pages with "no index" (EXT:seo) are left out
*   Caches tagged per page – editing a page or its content regenerates the files
*   No route enhancer needed

..  _requirements:

Requirements
============

*   TYPO3 13.4 or 12.4 (see :ref:`older-versions`); for TYPO3 14.3 use version 1.x
*   PHP 8.2 or higher
*   `league/html-to-markdown <https://github.com/thephpleague/html-to-markdown>`__
    (installed by Composer)
*   Composer mode (classic mode is not supported, see :ref:`installation`)
