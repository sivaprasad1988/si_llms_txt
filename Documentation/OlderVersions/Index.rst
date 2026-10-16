..  _older-versions:

===================
TYPO3 12.4 and 13.4
===================

Version 0.9.x (branch ``typo3-12-13``) supports TYPO3 12.4 LTS and 13.4 LTS.
For TYPO3 14.3 use version 1.x. All endpoints, page fields and the EXT:news
support work the same as in 1.x. The differences are in the setup only.

Tested on fresh installations of TYPO3 12.4.45 and 13.4.36, each with two
languages and EXT:news (13.0.2 on TYPO3 12, 14.1.2 on TYPO3 13).

..  _older-versions-13:

TYPO3 13.4
==========

Same as TYPO3 14: add the site set in :guilabel:`Site Management > Sites`,
field :guilabel:`Sets for this Site`, see :ref:`installation`. The settings
can be edited in :guilabel:`Site Management > Settings` or in
:file:`config/sites/<identifier>/settings.yaml`.

..  _older-versions-12:

TYPO3 12.4
==========

TYPO3 12 has no site sets. The field :guilabel:`Sets for this Site` and the
module :guilabel:`Site Management > Settings` do not exist.

Activate the extension for a site
---------------------------------

Add the entry by hand to the site configuration. The backend keeps it when
the site is saved in :guilabel:`Site Management > Sites`.

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    dependencies:
      - woit/si-llms-txt

Settings
--------

Put the settings (see :ref:`configuration`) into the site's
:file:`settings.yaml`. Without the file the defaults apply.

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    sillmstxt:
      articleDoktypes: '12'
      news:
        detailPid: 42
        storagePids: '43'

The values are not validated, as TYPO3 12 does not read the setting
definitions of the site set. Check the spelling of the keys.
