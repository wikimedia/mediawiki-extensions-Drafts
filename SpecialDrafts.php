<?php
/**
 * Special Pages for Drafts extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialDrafts extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Drafts' );
	}

	/**
	 * Executes special page rendering and data processing
	 *
	 * @param $sub Mixed: MediaWiki supplied sub-page path
	 * @throws PermissionsError
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();
		$user = $this->getUser();
		$request = $this->getRequest();

		// Begin output
		$this->setHeaders();
		// Make sure the user is logged in
		if ( !$user->isLoggedIn() ) {
			throw new PermissionsError( 'read' );
		}
		// Handle discarding
		$draft = Draft::newFromID( $request->getIntOrNull( 'discard' ) );
		if ( $draft->exists() ) {
			// Discard draft
			$draft->discard();
			// Redirect to the article editor or view if returnto was set
			$section = $request->getIntOrNull( 'section' );
			$urlSection = $section !== null ? "&section={$section}" : '';
			switch( $request->getText( 'returnto' ) ) {
				case 'edit':
					$title = Title::newFromDBKey( $draft->getTitle() );
					$out->redirect(
						wfExpandURL( $title->getEditURL() . $urlSection )
					);
					break;
				case 'view':
					$title = Title::newFromDBKey( $draft->getTitle() );
					$out->redirect(
						wfExpandURL( $title->getFullURL() . $urlSection )
					);
					break;
			}
		}
		// Show list of drafts, or a message that there are none
		if ( Drafts::display() == 0 ) {
			$out->addWikiMsg( 'drafts-view-nonesaved' );
		}
	}
}
