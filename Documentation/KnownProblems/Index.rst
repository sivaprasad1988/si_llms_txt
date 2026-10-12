..  _known-problems:

==============
Known problems
==============

..  _known-problems-static:

/llms.txt shows old content
===========================

A static file :file:`public/llms.txt` is delivered by the web server before
TYPO3 runs. Delete it and remove any deployment step that writes it.

..  _known-problems-loopback:

llms-full.txt is incomplete
===========================

:file:`llms-full.txt` fetches every ``.md`` URL from the server itself
(:confval:`sillmstxt.loopbackUrl`). Pages that cannot
be fetched are left out and a warning is logged; the file is then cached for
5 minutes only.

For an ``https`` site base behind a reverse proxy, TYPO3 must accept
``X-Forwarded-Proto`` from the loopback address
(``$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']`` and
``reverseProxySSL``), otherwise the site is not matched. If the web server
listens elsewhere inside the container, change the loopback URL.

..  _known-problems-md-blocked:

Some .md URLs return 403
========================

The default TYPO3 :file:`.htaccess` denies files named :file:`Readme.md`,
:file:`License.md`, :file:`ChangeLog.md` and :file:`ToDo.md`. Pages with these
slugs have no Markdown URL. Other ``.md`` URLs reach TYPO3 because no such
file exists.

..  _known-problems-intro:

The llms.txt intro changes back
===============================

The intro is stored in :file:`config/sites/<identifier>/config.yaml`. If that
file is deployed from version control, backend changes on the server are
overwritten. Change the intro in the repository.

..  _known-problems-tab:

The field "llms.txt intro" is not visible
=========================================

It is inside each language: :guilabel:`Sites` > edit the site >
:guilabel:`Languages` > expand the language > tab :guilabel:`LLM`.

..  _known-problems-crawlers:

AI crawlers get 403
===================

The extension does not block anything. If crawlers receive 403, check the
web application firewall or CDN in front of the site (for example AI bot
blocking in Cloudflare) and :file:`robots.txt`.
