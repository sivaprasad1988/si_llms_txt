..  _configuration:

=============
Configuration
=============

..  _configuration-site-settings:

Site settings
=============

The settings come with the site set and are edited in
:guilabel:`Site Management > Settings` or in
:file:`config/sites/<identifier>/settings.yaml`.

..  confval-menu::
    :display: table
    :type:
    :default:

..  _confval-articleDoktypes:

..  confval:: sillmstxt.articleDoktypes
    :type: string
    :default: '12'

    Comma-separated page types (doktypes) listed in the section "Articles"
    of :file:`llms.txt`, newest first. ``12`` is the news page type of
    EXT:just_news. Page type ``1`` (standard) is always included.

..  _confval-contentElementId:

..  confval:: sillmstxt.contentElementId
    :type: string
    :default: 'wrapper'

    ``id`` of the HTML element whose content becomes the generated Markdown
    of a page. Everything outside of it (header, footer, menus, modals) is
    ignored. If the element is missing, ``<body>`` is used.

    Inside the element, anything with the attribute
    ``data-sillmstxt-ignore`` is left out as well, for example a share bar:

    ..  code-block:: html

        <div class="share" data-sillmstxt-ignore>…</div>

..  _confval-loopbackUrl:

..  confval:: sillmstxt.loopbackUrl
    :type: string
    :default: 'http://127.0.0.1'

    How the web server reaches itself to build :file:`llms-full.txt`. The
    requests are sent to this address with the ``Host`` header and
    ``X-Forwarded-Proto`` of the site base. See :ref:`known-problems-loopback`.

..  _confval-news-detailPid:

..  confval:: sillmstxt.news.detailPid
    :type: int
    :default: 0

    Page with the detail plugin of EXT:news. ``0`` switches the news support
    off. Only used when EXT:news is installed, see :ref:`news`.

..  _confval-news-storagePids:

..  confval:: sillmstxt.news.storagePids
    :type: string
    :default: ''

    Comma-separated page uids that contain news records. Empty: all news
    within the page tree of the site (hidden storage folders included).

..  _configuration-site-language:

llms.txt intro per language
===========================

Each site language has the field :guilabel:`llms.txt intro (Markdown)`. It
is shown below the website title in the :file:`llms.txt` of that language,
for example a one-line summary and a few key facts.

Where to find it: :guilabel:`Site Management > Sites` > edit the site >
tab :guilabel:`Languages` > expand a language > tab :guilabel:`LLM`.

The value is stored in the site configuration:

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    languages:
      -
        title: English
        languageId: 1
        base: /en/
        sillmstxtIntro: |-
          Example is a tool for …

..  note::
    The site configuration is a file. If it is kept in version control and
    deployed, change the intro there – an edit in the backend of a server
    is overwritten by the next deployment.
