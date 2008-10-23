create table /*$wgDBPrefix*/drafts (
    draft_id INTEGER AUTO_INCREMENT PRIMARY KEY,
    draft_user INTEGER,
    draft_namespace INTEGER,
    draft_title VARBINARY(255),
    draft_section INTEGER,
    draft_starttime BINARY(14),
    draft_edittime BINARY(14),
    draft_savetime BINARY(14),
    draft_scrolltop INTEGER,
    draft_text BLOB,
    draft_summary TINYBLOB,
    draft_minoredit BOOL
) /*$wgDBTableOptions*/;
