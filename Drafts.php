<?php

/*
 * This SQL statement must be executed before using the drafts extension.
 *
create table drafts (
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
);
 */


// Check environment
if ( !defined( 'MEDIAWIKI' ) ) {
    echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
    die( - 1 );
}

// Credits
$wgExtensionCredits['other'][] = array(
	'name' => 'Save Drafts',
	'author' => 'Trevor Parscal',
   'url' => 'http://www.mediawiki.org/wiki/Extension:Drafts',
   'description' => 'Allow users to save drafts'
);

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'View Drafts',
   'author' => 'Trevor Parscal',
   'url' => 'http://www.mediawiki.org/wiki/Extension:Drafts',
   'description' => 'Drafts extension page'
);

/* Configuration */

// Shortcut to this extension directory
$dir = dirname( __FILE__ ) . '/';

// Seconds of inactivity after change before autosaving
// Use the value 0 to disable autosave
$wgDraftsAutoSaveWait = 120;

if ( true ) {
	/* Includes */

	// Save and View components
	require_once $dir . 'Drafts.Classes.php';
	require_once $dir . 'Drafts.Hooks.php';

	/* MediaWiki Connections */

	// Internationalization
	$wgExtensionMessagesFiles['Drafts'] = $dir . 'Drafts.i18n.php';
	$wgExtensionAliasesFiles['Drafts'] = $dir . 'Drafts.alias.php';

	// Register the Drafts special page
	$wgSpecialPages['Drafts'] = 'DraftsPage';

	// Autoload SpecialDrafts from Drafts.Classes.php
	$wgAutoloadClasses['DraftsPage'] = $dir . 'Drafts.Pages.php';

	// Register save interception to detect non-javascript draft saving
	$wgHooks['EditFilter'][] = 'efDraftsInterceptSave';

	// Register article save hook
	$wgHooks['ArticleSaveComplete'][] = 'efArticleSaved';

	// Register controls hook
	$wgHooks['EditPageBeforeEditButtons'][] = 'efDraftsControls';

	// Register load hook
	$wgHooks['EditPage::showEditForm:initial'][] = 'efDraftsLoad';

	// Register ajax response hook
	$wgAjaxExportList[] = "efDraftsSave";

	// Register ajax add script hook
	$wgHooks['AjaxAddScript'][] = 'efDraftsAddJS';
}
