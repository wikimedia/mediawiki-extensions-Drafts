-- Schema change for r54462
ALTER TABLE /*_*/drafts change column draft_token draft_token VARBINARY(255);
