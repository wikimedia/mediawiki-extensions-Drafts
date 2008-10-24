<?php
/**
 * Drafts extension
 *
 * @file
 * @ingroup Extensions
 */

// Check environment
if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( - 1 );
}

// Credits
$wgExtensionCredits['other'][] = array(
	'name' => 'Drafts',
	'author' => 'Trevor Parscal',
	'url' => 'http://www.mediawiki.org/wiki/Extension:Drafts',
	'description' => 'Save and view draft versions of pages',
	'svn-date' => '$LastChangedDate$',
	'svn-revision' => '$LastChangedRevision$',
	'description-msg' => 'drafts-desc',
);

/* Configuration */

// Shortcut to this extension directory
$dir = dirname( __FILE__ ) . '/';

// Seconds of inactivity after change before autosaving
// Use the value 0 to disable autosave
$wgDraftsAutoSaveWait = 120;

// Days to keep drafts around before automatic deletion
$wgDraftsLifeSpan = 30;

// Save and View components
$wgAutoloadClasses['Draft'] = $dir . 'Drafts.classes.php';
$wgAutoloadClasses['DraftHooks'] = $dir . 'Drafts.hooks.php';

// Internationalization
$wgExtensionMessagesFiles['Drafts'] = $dir . 'Drafts.i18n.php';
$wgExtensionAliasesFiles['Drafts'] = $dir . 'Drafts.alias.php';

// Register the Drafts special page
$wgSpecialPages['Drafts'] = 'DraftsPage';
$wgAutoloadClasses['DraftsPage'] = $dir . 'Drafts.pages.php';

// Register save interception to detect non-javascript draft saving
$wgHooks['EditFilter'][] = 'DraftHooks::interceptSave';

// Register article save hook
$wgHooks['ArticleSaveComplete'][] = 'DraftHooks::discard';

// Register controls hook
$wgHooks['EditPageBeforeEditButtons'][] = 'DraftHooks::controls';

// Register load hook
$wgHooks['EditPage::showEditForm:initial'][] = 'DraftHooks::loadForm';

// Register ajax response hook
$wgAjaxExportList[] = 'DraftHooks::AjaxSave';

// Register ajax add script hook
$wgHooks['AjaxAddScript'][] = 'DraftHooks::addJS';

// Register css add script hook
$wgHooks['BeforePageDisplay'][] = 'DraftHooks::addCSS';

// Register database operations
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efCheckSchema';

function efCheckSchema() {
	// Get a connection
	$db = wfGetDB( DB_MASTER );
	// Get statements from file
	$statement = file_get_contents( dirname( __FILE__  ) . '/Drafts.sql' );
	// Create table if it doesn't exist
	if ( !$db->tableExists( 'drafts' ) ) {
		$db->query( $statement, __METHOD__);
	}
	// Continue
	return true;
}
