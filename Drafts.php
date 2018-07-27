<?php
/**
 * Drafts extension
 *
 * @file
 * @ingroup Extensions
 *
 * This file contains the main include file for the Drafts extension of
 * MediaWiki.
 *
 * Usage: Add the following line in LocalSettings.php:
 * require_once( "$IP/extensions/Drafts/Drafts.php" );
 *
 * @author Trevor Parscal <tparscal@wikimedia.org>
 * @author enhanced by Petr Bena <benapetr@gmail.com>
 * @license GPL v2
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Drafts' );
	wfWarn(
		'Deprecated PHP entry point used for Drafts extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the Drafts extension requires MediaWiki 1.25+' );
}
