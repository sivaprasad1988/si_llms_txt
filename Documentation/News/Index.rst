..  _news:

===================
EXT:news (tx_news)
===================

News records of `georgringer/news <https://extensions.typo3.org/extension/news>`__
are records on a detail page, not pages of their own. The extension supports
them when both conditions are met:

*   EXT:news is installed
*   :confval:`sillmstxt.news.detailPid` is set to
    the page with the news detail plugin

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    sillmstxt.news.detailPid: 42
    # optional, otherwise the whole page tree of the site is searched
    sillmstxt.news.storagePids: '41'

..  _news-what:

What is added
=============

*   :file:`llms.txt`: section "News" with all news, newest first, with date
    and description (meta description, otherwise teaser)
*   :file:`llms-full.txt`: the Markdown of every news record
*   ``.md`` URL of every news detail URL, for example
    :file:`/en/news/my-article.md`; with frontmatter taken from the news
    record (title, description, date, lastmod, canonical)
*   Markdown link in the HTML head of the detail view

The detail page itself (without a news record) is not listed and its ``.md``
URL returns 404.

..  _news-which:

Which records are included
==========================

*   type "News" only – records of type "Internal page" or "External URL"
    have no content of their own
*   visible in the frontend (hidden, start/end time, access restrictions)
*   in other languages: only translated records (``sys_language_uid = -1``
    counts for all languages)

..  _news-routing:

Routing
=======

The news URLs are built with the site router, so the route enhancer of
EXT:news is used if one is configured (:file:`/news/my-article.md`). Without a
route enhancer the URLs keep their query string
(:file:`/news-detail.md?tx_news_pi1[news]=5&cHash=…`), which works as well.
No route enhancer is needed for the extension itself.

..  _news-cache:

Cache
=====

When a news record is saved, EXT:news flushes the cache tags
``tx_news_uid_<uid>`` and ``tx_news_pid_<pid>`` in the "pages" cache group.
The cached :file:`llms.txt` / :file:`llms-full.txt` carry these tags and are
regenerated on the next request.

..  hint::
    News articles of EXT:just_news are pages (page type 12) and need no
    configuration, see :confval:`sillmstxt.articleDoktypes`.
