<?php

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
	require_once $dir . 'Drafts.classes.php';
	require_once $dir . 'Drafts.hooks.php';

	/* MediaWiki Connections */

	// Internationalization
	$wgExtensionMessagesFiles['Drafts'] = $dir . 'Drafts.i18n.php';
	$wgExtensionAliasesFiles['Drafts'] = $dir . 'Drafts.alias.php';

	// Register the Drafts special page
	$wgSpecialPages['Drafts'] = 'DraftsPage';

	// Autoload SpecialDrafts from Drafts.Classes.php
	$wgAutoloadClasses['DraftsPage'] = $dir . 'Drafts.pages.php';

	// Register save interception to detect non-javascript draft saving
	$wgHooks['EditFilter'][] = 'efDraftsInterceptSave';

	// Register article save hook
	$wgHooks['ArticleSaveComplete'][] = 'efDraftsDiscard';

	// Register controls hook
	$wgHooks['EditPageBeforeEditButtons'][] = 'efDraftsControls';

	// Register load hook
	$wgHooks['EditPage::showEditForm:initial'][] = 'efDraftsLoad';

	// Register ajax response hook
	$wgAjaxExportList[] = "efDraftsSave";

	// Register ajax add script hook
	$wgHooks['AjaxAddScript'][] = 'efDraftsAddJS';
	
	// Register database operations
	$wgHooks['LoadExtensionSchemaUpdates'][] = 'efCheckSchema';
}
