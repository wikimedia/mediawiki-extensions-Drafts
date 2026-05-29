<?php
/**
 * Schema hooks for Drafts extension
 *
 * @file
 * @ingroup Extensions
 */

class SchemaHooks implements
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{
	/**
	 * Apply the database schema updates when the sysadmin re-runs
	 * MediaWiki's core updater script, maintenance/update.php.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlDir = __DIR__ . '/../sql';
		$updater->addExtensionTable( 'drafts', $sqlDir . '/Drafts.sql' );
		if ( $updater->getDB()->getType() !== 'sqlite' ) {
			$updater->modifyExtensionField( 'drafts', 'draft_token',
				$sqlDir . '/patch-draft_token.sql' );
		}
		$updater->addExtensionIndex( 'drafts', 'draft_title', $sqlDir . '/patch-titlensindex.sql' );
	}
}
