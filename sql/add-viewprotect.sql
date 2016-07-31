--
-- Tables for the ViewProtect extension
--

-- Notes table
CREATE TABLE /*_*/viewprotect (
  -- Key to page.page_id.
  viewprotect_page int unsigned NOT NULL,

  -- Group name
  -- Later this will point to the foreign key of our group editor
  viewprotect_group varbinary(255) NOT NULL,

  -- Permission
  -- right now this is the permission name
  viewprotect_permission varchar(32) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/viewprotect_index
			 ON /*_*/viewprotect (viewprotect_page, viewprotect_group, viewprotect_permission);

-- For querying of all groups on a page
CREATE INDEX /*i*/viewprotect_group_page ON /*_*/viewprotect (viewprotect_group, viewprotect_page);

-- For querying of all permission from on a certain page
CREATE INDEX /*i*/viewprotect_page_permission
			 ON /*_*/viewprotect (viewprotect_page, viewprotect_permission);

-- For querying of all permissions for a group
CREATE INDEX /*i*/viewprotect_group_permission
			 ON /*_*/viewprotect (viewprotect_group, viewprotect_permission);
