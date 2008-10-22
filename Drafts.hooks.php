<?php

/* Hooks */

function efArticleSaved( &$article, &$user, &$text, &$summary, &$minoredit, &$watchthis,
	&$sectionanchor, &$flags, $revision ) {
	global $wgRequest;

	// Check if the save occured from a draft
	$draftID = $wgRequest->getIntOrNull( 'wpDraftID' );
	if ( $draftID !== null )
	{
		// Get the draft
		$draft = new Draft( $draftID );

		// Discard the draft
		$draft->discard();
	}

	// Continue
	return true;
}

// Load draft
function efDraftsLoad( &$editpage ) {
	global $wgUser, $wgRequest, $wgOut, $wgTitle;

	// Check permissions
	if ( $wgUser->isAllowed( 'edit' ) && $wgUser->isLoggedIn() ) {
		// Load draft if asked to
		$draftID = $wgRequest->getIntOrNull( 'draft' );
		if ( $draftID !== null )
		{
			// Create Draft
			$draft = new Draft( $draftID );

			// Override initial values in the form with draft data
			$editpage->textbox1 = $draft->getText();
			$editpage->summary = $draft->getSummary();
			$editpage->scrolltop = $draft->getScrollTop();
			$editpage->minoredit = $draft->getMinorEdit() ? true : false;
		}

		// Handle preview or non-javascript draft saving
		if ( $wgRequest->getText( 'action' ) == 'submit' )
		{
			// If the draft wasn't specified in the url, try using a form-submitted one
			if ( $draftID == null )
			{
				$draftID = $wgRequest->getIntOrNull( 'wpDraftID' );
			}

			// Create Draft
			$draft = new Draft( $draftID ? $draftID : '' );

			// Load draft with info
			$draft->setNamespace( $wgRequest->getInt( 'wpDraftNamespace' ) );
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
			$draftID = $draft->getID();
			$wgRequest->setVal( 'draft', $draftID );
		}
	}

	// Get a connection
	$db = wfGetDB( DB_MASTER );

	// Get all drafts for this user and article
	$result = $db->select( 'drafts',
		array(
			'draft_id',
			'draft_title',
			'draft_section',
			'draft_savetime',
			'draft_text',
		),
		array(
			'draft_namespace' => $wgTitle->getNamespace(),
			'draft_title' => $wgTitle->getDBKey(),
			'draft_user' => $wgUser->getID()
		)
	);

	if ( $result )
	{
		
		// Internationalization
		wfLoadExtensionMessages( 'Drafts' );
		$msgArticle = wfMsgHTML( 'drafts-view-article' );
		$msgExisting = wfMsgHTML( 'drafts-view-existing' );
		$msgSaved = wfMsgHTML( 'drafts-view-saved' );
		$msgDiscard = wfMsgHTML( 'drafts-view-discard' );
		
		// Begin existing drafts table
		$htmlDraftList = <<<END
			<div style="margin-bottom:10px;padding-left:10px;padding-right:10px;border:red solid 1px">
			<h3>{$msgExisting}</h3>
			<table cellpadding="5" cellspacing="0" width="100%" border="0" style="margin-left:-5px;margin-right:-5px">
				<tr>
					<th align="left"  width="75%" nowrap="nowrap">{$msgArticle}</th>
					<th align="left" nowrap="nowrap">{$msgSaved}</th>
					<th></th>
				</tr>
END;

		// Add existing drafts for this page and user
		$count = 0;
		while ( $row = $db->fetchRow( $result ) )
		{
			// Article
			$title = Title::newFromDBKey( $row['draft_title'] ) ;
			$urlLoad = wfExpandURL( $title->getEditURL() ) . "&draft={$row['draft_id']}";
			$htmlTitle = $title->getEscapedText();
			$htmlState = (integer) $draftID == (integer) $row['draft_id'] ? 'bold' : 'normal';
			
			// Drafts
			$draftsTitle = SpecialPage::getTitleFor( 'Drafts' );
			$urlDiscard = $draftsTitle->getFullUrl() . "?discard={$row['draft_id']}";
			
			// Section
			if ( $row['draft_section'] > 0 )
			{
				// Detect section name
				$lines = explode( "\n", $row['draft_text'] );
				$sectionName = count($lines) > 0 ? $lines[0] : 'Untitled Section';
				
				// Modify article link and title
				$htmlTitle .= '#' . trim( str_replace( '=', '', $sectionName ) );
				$urlLoad .= "&section={$row['draft_section']}";
				$urlDiscard .= "&section={$row['draft_section']}";
			}
			
			// Times
			$htmlSaved = gmdate( 'F jS g:ia', wfTimestamp( TS_UNIX, $row['draft_savetime'] ) );

			// Build HTML
			$htmlDraftList .= <<<END
				<tr>
					<td align="left" nowrap="nowrap"><a href="{$urlLoad}" style="font-weight:{$htmlState}">{$htmlTitle}</a></td>
					<td align-"left" nowrap="nowrap">{$htmlSaved}</td>
					<td align="right" nowrap="nowrap"><a href="{$urlDiscard}">{$msgDiscard}</a></td>
				</tr>
END;
			$count++;
		}

		// End existing drafts table
		$htmlDraftList .= <<<END
			</table>
			</div>
END;

		// If there were any drafts for this page and user
		if ( $count > 0 )
		{
			// Show list of drafts
			$wgOut->addHTML( $htmlDraftList );
		}
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
		$error = ' ' . $wgRequest->getText( 'wpDraftSave' );
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

		// Use internationalized text in controls
		$msgSaveDraft = wfMsgHTML( 'drafts-save-save' );
		$msgSaveDraftTitle = wfMsgHTML( 'drafts-save-savetitle' );
		$msgSaved = wfMsgHTML( 'drafts-save-saved' );
		$msgError = wfMsgHTML( 'drafts-save-error' );

		// Get the namespace, title and draft id
		$namespace = $wgTitle->getNamespace();
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
			<input type="hidden" name="wpDraftNamespace" value="{$namespace}" />
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
	global $wgJsMimeType, $wgScriptPath;

	// Add javascript to support ajax draft saving
	$out->addScript(
		sprintf( '<script type="%s" src="%s/extensions/Drafts/Drafts.js"></script>' . "\n",
			$wgJsMimeType, $wgScriptPath )
	);

	// Continue
	return true;
}

// Respond to ajax queries
function efDraftsSave( $id, $namespace, $title, $section, $starttime, $edittime, $scrolltop, $text, $summary, $minoredit ) {
	// Create Draft
	$draft = new Draft( $id );

	// Load draft with info
	$draft->setNamespace( $namespace );
	$draft->setTitle( $title );
	$draft->setSection( $section );
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
