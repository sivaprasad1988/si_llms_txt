..  _endpoints:

=========
Endpoints
=========

All URLs below are answered for every site that uses the site set. For a site
with the languages ``/de/`` (default) and ``/en/``:

..  list-table::
    :header-rows: 1
    :widths: 35 65

    *   -   URL
        -   Content
    *   -   :file:`/llms.txt`
        -   Index of the default language (same as :file:`/de/llms.txt`)
    *   -   :file:`/de/llms.txt`, :file:`/en/llms.txt`
        -   Index of one language
    *   -   :file:`/de/llms-full.txt`
        -   Markdown of all listed pages (and news) of one language
    *   -   :file:`/de/<page>.md`
        -   One page as Markdown
    *   -   :file:`/de/index.md`
        -   The root page of the language as Markdown

All text responses use ``X-Robots-Tag: noindex`` so that search engines do
not index them as duplicates of the HTML pages.

..  _endpoints-llms-txt:

llms.txt
========

#.  Heading with the website title, then the intro of the language
    (see :ref:`configuration-site-language`)
#.  Section with the root page and top-level pages without subpages
#.  One section per top-level page with its subpages (title = page title,
    or folder title)
#.  Section "Articles": article pages, newest first
#.  Section "News" (only with :ref:`news`)
#.  Section "Languages": links to the :file:`llms.txt` of the other languages
#.  Section "Optional": link to :file:`llms-full.txt`

Each entry links the ``.md`` URL and adds the LLM description or meta
description:

..  code-block:: markdown

    - [Pricing](https://example.org/en/pricing.md): Plans and prices …

..  _endpoints-md:

Markdown of a page
==================

Add ``.md`` to the URL of a page. For the root page of a language use
:file:`index.md`. Example output:

..  code-block:: markdown

    ---
    title: "Pricing"
    description: "Plans and prices …"
    language: "en"
    lastmod: "2026-10-11"
    canonical: "https://example.org/en/pricing"
    source: "generated"
    generator: "TYPO3 si_llms_txt"
    ---

    # Start now …

``source`` is ``editor`` when the field "LLM Markdown" is used.

A ``.md`` URL returns 404 (plain text) when the page does not exist, is
excluded, or has no translation in that language.

..  _endpoints-full:

llms-full.txt
=============

Contains the header and intro of :file:`llms.txt` and then, for every listed
page, a separator, the source URL and its Markdown (without frontmatter).
It is built by requesting every ``.md`` URL from the server itself and is
cached afterwards; the first request after a cache flush takes longer.
