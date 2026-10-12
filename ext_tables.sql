CREATE TABLE pages (
    tx_sillmstxt_exclude smallint(5) unsigned DEFAULT '0' NOT NULL,
    tx_sillmstxt_priority int(11) DEFAULT '0' NOT NULL,
    tx_sillmstxt_description text,
    tx_sillmstxt_markdown mediumtext
);
