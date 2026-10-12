..  _start:

==========================
LLMs.txt & Markdown for AI
==========================

:Extension key:
    si_llms_txt

:Package name:
    woit/si-llms-txt

:Version:
    |release|

:Language:
    en

:Author:
    Siva

:License:
    This document is published under the
    `GNU General Public License v2.0 or later <https://www.gnu.org/licenses/gpl-2.0.html>`__.

:Rendered:
    |today|

----

Serves `llms.txt <https://llmstxt.org/>`__, :file:`llms-full.txt` and a Markdown
version of every page for each site language, so that AI agents and crawlers
can read the website without navigation, scripts and layout.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extension does and which URLs it adds.

    ..  card:: :ref:`Installation <installation>`

        Install the package, add the site set, remove a static llms.txt.

    ..  card:: :ref:`Configuration <configuration>`

        Site settings and the llms.txt intro per language.

    ..  card:: :ref:`For editors <editor>`

        The "LLM" tab in the page properties.

    ..  card:: :ref:`Endpoints <endpoints>`

        llms.txt, llms-full.txt and the .md URLs in detail.

    ..  card:: :ref:`EXT:news <news>`

        Optional support for news records of georgringer/news.

    ..  card:: :ref:`Developer <developer>`

        Middlewares, Markdown conversion, caching.

    ..  card:: :ref:`Known problems <known-problems>`

        Static files, proxies, deployments.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Editor/Index
    Endpoints/Index
    News/Index
    Developer/Index
    KnownProblems/Index
