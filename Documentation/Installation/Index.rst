..  _installation:

============
Installation
============

..  _installation-composer:

..  important::
    The extension supports **Composer mode only**. It depends on
    `league/html-to-markdown <https://github.com/thephpleague/html-to-markdown>`__,
    which is installed by Composer. Installations without Composer (classic
    mode) are not supported: :file:`llms.txt` would work, the Markdown
    conversion of pages would fail.

Install the package
===================

..  code-block:: bash
    :caption: In the project root

    composer require woit/si-llms-txt
    vendor/bin/typo3 extension:setup
    vendor/bin/typo3 cache:flush

The package is available on
`Packagist <https://packagist.org/packages/woit/si-llms-txt>`__.
:bash:`extension:setup` adds the page fields and the cache tables
``cache_sillmstxt`` and ``cache_sillmstxt_tags``.

..  _installation-site-set:

Add the site set
================

The extension only works for sites that depend on its site set. Add it in
:guilabel:`Site Management > Sites`, tab :guilabel:`General`, field
:guilabel:`Sets for this Site`, or directly in the site configuration:

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    dependencies:
      - woit/si-llms-txt

..  _installation-static-file:

Remove a static llms.txt
========================

A file :file:`public/llms.txt` is delivered by the web server before TYPO3 is
called and hides the generated one. Delete it, and make sure no deployment
script creates it again.

..  _installation-check:

Check the installation
======================

..  code-block:: bash

    curl https://example.org/llms.txt
    curl https://example.org/de/llms.txt
    curl https://example.org/de/<page-slug>.md

All three return status 200. The HTML of a page contains
``<link rel="alternate" type="text/markdown" …>``.
