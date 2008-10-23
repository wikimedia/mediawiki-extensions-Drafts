<?php

/* Hooks */

function efDraftsDiscard( &$article, &$user, &$text, &$summary, &$minoredit, &$watchthis,
	&$sectionanchor, &$flags, $revision ) {
	global $wgRequest, $wgUser;

	if ( $wgUser->editToken() == $wgRequest->getText( 'wpEditToken' ) ) {
		// Check if the save occured from a draft
		$draft = Draft::newFromID( $wgRequest->getIntOrNull( 'wpDraftID' ) );
		if ( $draft->exists() ) {
			// Discard the draft
			$draft->discard();
		}
	}

	// Continue
	return true;
}

// Load draft
function efDraftsLoad( &$editpage ) {
	global $wgUser, $wgRequest, $wgOut, $wgTitle;

	// Check permissions
	if ( $wgUser->isAllowed( 'edit' ) && $wgUser->isLoggedIn() ) {
		// Get draft
		$draft = Draft::newFromID( $wgRequest->getIntOrNull( 'draft' ) );
		
		// Load form values
		if ( $draft->exists() )
		{
			// Override initial values in the form with draft data
			$editpage->textbox1 = $draft->getText();
			$editpage->summary = $draft->getSummary();
			$editpage->scrolltop = $draft->getScrollTop();
			$editpage->minoredit = $draft->getMinorEdit() ? true : false;
		}

		// Save draft on non-save submission
		if ( $wgRequest->getText( 'action' ) == 'submit' &&
			$wgUser->editToken() == $wgRequest->getText( 'wpEditToken' ) )
		{
			// If the draft wasn't specified in the url, try using a form-submitted one
			if ( !$draft->exists() )
			{
				$draft = Draft::newFromID( $wgRequest->getIntOrNull( 'wpDraftID' ) );
			}

			// Load draft with info
			$draft->setTitle( Title::newFromText( $wgRequest->getText( 'wpDraftTitle' ) ) );
			$draft->setSection( $wgRequest->getInt( 'wpSection' ) );
			$draft->setStartTime( $wgRequest->getText( 'wpStarttime' ) );
			$draft->setEditTime( $wgRequest->getText( 'wpEdittime' ) );
			$draft->setSaveTime( wfTimestampNow() );
			$draft->setScrollTop( $wgRequest->getInt( 'wpScrolltop' ) );
			$draft->setText( $wgRequest->getText( 'wpTextbox1' ) );
			$draft->setSummary( $wgRequest->getText( 'wpSummary' ) );
			$draft->setMinorEdit( $wgRequest->getInt( 'wpMinoredit', 0 ) );

			// Save draft
			$draft->save();

			// Use the new draft id
			$wgRequest->setVal( 'draft', $draft->getID() );
		}
	}

	// Internationalization
	wfLoadExtensionMessages( 'Drafts' );
	
	// Show list of drafts
	if ( Draft::countDrafts( $wgTitle ) > 0 && $wgRequest->getText( 'action' ) !== 'submit' ) {
		$wgOut->addHTML( Xml::openElement( 'div', array( 'style' => 'margin-bottom:10px;padding-left:10px;padding-right:10px;border:red solid 1px' ) ) );
		$wgOut->addHTML( Xml::element( 'h3', null, wfMsg( 'drafts-view-existing' ) ) );
		Draft::ListDrafts( $wgTitle );
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	// Continue
	return true;
}

// Intercept the saving of an article to detect if the submission was from the non-javascript
// save draft button
function efDraftsInterceptSave( $editor, $text, $section, &$error ) {
	global $wgRequest;

	// Don't save if the save draft button caused the submit
	if ( $wgRequest->getText( 'wpDraftSave' ) !== '' ) {
		// Modify the error so it's clear we want to remain in edit mode
		$error = ' ';
	}

	// Continue
	return true;
}

// Add draft saving controls
function efDraftsControls( &$editpage, &$buttons ) {
	global $wgUser, $wgTitle, $wgRequest, $wgDraftsAutoSaveWait;

	// Check permissions
	if ( $wgUser->isAllowed( 'edit' ) && $wgUser->isLoggedIn() ) {
		// Internationalization
		wfLoadExtensionMessages( 'Drafts' );

		// Build XML
		$buttons['savedraft'] = Xml::openElement( 'script',
			array(
				'type' => 'text/javascript',
				'lanuguage' => 'javascript'
			)
		);
		$ajaxButton = Xml::escapeJsString(
			Xml::element( 'input',
				array(
					'type' => 'button',
					'id' => 'wpDraftSave',
					'name' => 'wpDraftSave',
					'tabindex' => 8,
					'accesskey' => 'd',
					'value' => wfMsg( 'drafts-save-save' ),
					'title' => wfMsg( 'drafts-save-title' )
				) + ( $wgRequest->getText( 'action' ) !== 'submit' ? array ( 'disabled' => 'disabled' ) : array() )
			)
		);
		$buttons['savedraft'] .= "document.write( '{$ajaxButton}' );";
		$buttons['savedraft'] .= Xml::closeElement( 'script' );
		$buttons['savedraft'] .= Xml::openElement( 'noscript' );
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'submit',
				'id' => 'wpDraftSave',
				'name' => 'wpDraftSave',
				'tabindex' => 8,
				'accesskey' => 'd',
				'value' => wfMsg( 'drafts-save-save' ),
				'title' => wfMsg( 'drafts-save-title' )
			)
		);
		$buttons['savedraft'] .= Xml::closeElement( 'noscript' );
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'hidden',
				'name' => 'wpDraftAutoSaveWait',
				'value' => $wgDraftsAutoSaveWait
			)
		);
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'hidden',
				'name' => 'wpDraftID',
				'value' => $wgRequest->getInt( 'draft', '' )
			)
		);
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'hidden',
				'name' => 'wpDraftTitle',
				'value' => $wgTitle->getPrefixedText()
			)
		);
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'hidden',
				'name' => 'wpMsgSaved',
				'value' => wfMsg( 'drafts-save-saved' )
			)
		);
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'hidden',
				'name' => 'wpMsgSaveDraft',
				'value' => wfMsg( 'drafts-save-save' )
			)
		);
		$buttons['savedraft'] .= Xml::element( 'input',
			array(
				'type' => 'hidden',
				'name' => 'wpMsgError',
				'value' => wfMsg( 'drafts-save-error' )
			)
		);
	}

	// Continue
	return true;
}

// Add ajax support script
function efDraftsAddJS( $out ) {
	global $wgScriptPath;

	// Add javascript to support ajax draft saving
	$out->addScriptFile( $wgScriptPath . '/extensions/Drafts/Drafts.js' );

	// Continue
	return true;
}

// Respond to ajax queries
function efDraftsSave( $token, $id, $title, $section, $starttime, $edittime, $scrolltop, $text, $summary, $minoredit ) {
	global $wgUser;
	
	// Verify token
	if ( $wgUser->editToken() == $token ) {
		// Create Draft
		$draft = Draft::newFromID( $id );
	
		// Load draft with info
		$draft->setTitle( Title::newFromText( $title ) );
		$draft->setSection( $section == '' ? null : $section );
		$draft->setStartTime( $starttime );
		$draft->setEditTime( $edittime );
		$draft->setSaveTime( wfTimestampNow() );
		$draft->setScrollTop( $scrolltop );
		$draft->setText( $text );
		$draft->setSummary( $summary );
		$draft->setMinorEdit( $minoredit );
	
		// Save draft
		$draft->save();
	
		// Return draft id to client (used for next save)
		return (string) $draft->getID();
	}
	else {
		// Return failure
		return '-1';
	}
	
}

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
