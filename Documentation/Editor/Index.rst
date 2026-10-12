..  _editor:

===========
For editors
===========

..  _editor-tab:

The "LLM" tab in the page properties
====================================

Standard pages and article pages (EXT:just_news) have the tab
:guilabel:`LLM` in their page properties:

..  confval-menu::
    :display: table
    :type:

..  confval:: Exclude from llms.txt
    :name: field-exclude
    :type: checkbox

    The page is left out of :file:`llms.txt` and :file:`llms-full.txt`, its
    ``.md`` URL returns 404 and the HTML gets no Markdown link. Applies to
    all languages of the page.

..  confval:: LLM priority (0-100)
    :name: field-priority
    :type: number

    Pages with a higher value come first among pages with the same parent.
    Applies to all languages.

..  confval:: LLM description
    :name: field-description
    :type: text

    Short text next to the link in :file:`llms.txt`. Empty: the meta
    description of the page is used. Translatable.

..  confval:: LLM Markdown
    :name: field-markdown
    :type: text (Markdown)

    Served at the ``.md`` URL of the page instead of the generated version.
    Leave it empty in the normal case. Translatable.

..  _editor-markdown:

When to fill "LLM Markdown"
===========================

The generated Markdown follows the page content automatically. Fill the
field only if AI tools should get a different text, for example a shorter
summary, additional facts or a FAQ.

*   Write plain Markdown (``#`` headings, ``-`` lists, ``**bold**``). The
    field is a plain text field on purpose – a rich text editor would store
    HTML.
*   Check the result at ``https://<domain>/<language>/<page>.md``.
*   Remember to update the text when the page changes – it is not
    synchronised with the page content.

..  _editor-excluded:

Pages that are left out automatically
=====================================

*   hidden pages, pages with access restrictions, pages outside their
    start/end time
*   pages with :guilabel:`No index` (tab :guilabel:`SEO`)
*   page types other than standard and article pages (folders, shortcuts,
    links, spacers) – their subpages are still included
*   in other languages: pages without a translation
