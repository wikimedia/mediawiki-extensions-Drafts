<?php

/* Hooks */

function efDraftsDiscard( &$article, &$user, &$text, &$summary, &$minoredit, &$watchthis,
	&$sectionanchor, &$flags, $revision ) {
	global $wgRequest;

	if ( $wgUser->editToken() == $wgRequest->getText( 'token' ) ) {
		// Check if the save occured from a draft
		$draft = new Draft( $wgRequest->getIntOrNull( 'wpDraftID' ) );
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
		$draft = new Draft( $wgRequest->getIntOrNull( 'draft' ) );
		
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
				$draft = new Draft( $wgRequest->getIntOrNull( 'wpDraftID' ) );
			}

			// Load draft with info
			$draft->setTitle( $wgRequest->getText( 'wpDraftTitle' ) );
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

	wfLoadExtensionMessages( 'Drafts' );
	$msgExisting = wfMsgHTML( 'drafts-view-existing' );
	
	// Show list of drafts
	if ( Draft::countDrafts( $wgTitle->getDBKey() ) > 0 ) {
		$wgOut->addHTML( '<div style="margin-bottom:10px;padding-left:10px;padding-right:10px;border:red solid 1px">' );
		$wgOut->addHTML( "<h3>{$msgExisting}</h3>" );
		Draft::ListDrafts( $wgTitle->getDBKey() );	
		$wgOut->addHTML( '</div>' );
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

		// Internationalization
		$msgSaveDraft = wfMsgHTML( 'drafts-save-save' );
		$msgSaveDraftTitle = wfMsgHTML( 'drafts-save-savetitle' );
		$msgSaved = wfMsgHTML( 'drafts-save-saved' );
		$msgError = wfMsgHTML( 'drafts-save-error' );

		// Get the namespace, title and draft id
		$title = $wgTitle->getPrefixedText();
		$draftID = $wgRequest->getInt( 'draft', '' );

		// Build HTML
		$buttons['savedraft'] = <<<END
			<script lanuguage="javascript" type="text/javascript">
				document.write( '<input type="button" id="wpDraftSave" name="wpDraftSave" disabled="disabled" value="{$msgSaveDraft}" tabindex="8" accesskey="d" title="{$msgSaveDraftTitle}" />' );
			</script>
			<noscript>
				<input type="submit" id="wpDraftSave" name="wpDraftSave" value="{$msgSaveDraft}" tabindex="8" accesskey="d" title="{$msgSaveDraftTitle}" />
			</noscript>
			<input type="hidden" name="wpDraftID" value="{$draftID}" />
			<input type="hidden" name="wpDraftTitle" value="{$title}" />
			<input type="hidden" name="wpMsgSaved" value="{$msgSaved}" />
			<input type="hidden" name="wpMsgSaveDraft" value="{$msgSaveDraft}" />
			<input type="hidden" name="wpMsgError" value="{$msgError}" />
			<input type="hidden" name="wpDraftAutoSaveWait" value="{$wgDraftsAutoSaveWait}" />
END;
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
		$draft = new Draft( $id );
	
		// Load draft with info
		$draft->setTitle( $title );
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
	
	// Build create table statement
	$statement = <<<END
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
END;

	// Create table if it doesn't exist
	if ( !$db->tableExists( 'drafts' ) ) {
		$db->query( $statement, __METHOD__);
	}
}
