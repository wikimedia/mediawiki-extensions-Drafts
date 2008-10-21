<?php

/* Pages */

// Drafts Special Page
class DraftsPage extends SpecialPage {

	/* Functions */

	function DraftsPage() {
		// Initialize special page
		SpecialPage::SpecialPage( 'Drafts' );

		// Internationalization
		wfLoadExtensionMessages( 'Drafts' );
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser, $wgTitle;

		// Begin output
		$this->setHeaders();

		if ( !$wgUser->isLoggedIn() ) {
			// Login Link
			$titleUserLogin = SpecialPage::getTitleFor( 'UserLogin' );
			$urlLogin = $titleUserLogin->getFullURL() . "?returnto=Special:Drafts";

			// Show message
			$wgOut->addHTML(
				wfMsgHTML( 'drafts-view-mustlogin',
					sprintf( '<a href="%s">%s</a>',
						$urlLogin, wfMsgHTML( 'drafts-view-login' )
					)
				)
			);

			// Continue
			return true;
		}

		// Handle discarding
		$discard = $wgRequest->getIntOrNull( 'discard' );
		if ( $discard !== null )
		{
			$draft = new Draft( $discard );
			$draft->discard();
			switch( $wgRequest->getText( 'returnto' ) )
			{
				case 'edit':
					$title = Title::newFromDBKey( $draft->getTitle() );
					$wgOut->redirect( wfExpandURL( $title->getEditURL() ) );
					break;
				case 'view':
					$title = Title::newFromDBKey( $draft->getTitle() );
					$wgOut->redirect( wfExpandURL( $title->getFullURL() ) );
					break;
			}
		}

		// Get a connection
		$db = wfGetDB( DB_MASTER );

		// Get all drafts for this user and article
		$result = $db->select( 'drafts',
			array(
				'draft_id',
				'draft_title',
				'draft_savetime'
			),
			array(
				'draft_user' => $wgUser->getID()
			)
		);

		if ( $result )
		{
			// Internationalization
			$msgArticle = wfMsgHTML( 'drafts-view-article' );
			$msgSaved = wfMsgHTML( 'drafts-view-saved' );
			$msgStarted = wfMsgHTML( 'drafts-view-started' );
			$msgEdited = wfMsgHTML( 'drafts-view-edited' );

			$htmlDraftList = <<<END
				<table cellpadding="3" cellspacing="0" width="100%" border="0">
					<tr>
						<th width="17%" align="left">{$msgArticle}</th>
						<th width="17%" align="left">{$msgSaved}</th>
						<th width="17%" align="left">{$msgStarted}</th>
						<th width="17%" align="left">{$msgEdited}</th>
						<th width="17%"></th>
					</tr>
END;

			// Show list of drafts
			$count = 0;
			while ( $row = $db->fetchRow( $result ) )
			{
				// Article
				$title = Title::newFromDBKey( $row['draft_title'] );
				$urlLoad = wfExpandURL( $title->getEditURL() ) . '&draft=' . $row['draft_id'];
				$htmlTitle = $title->getEscapedText();

				// Drafts
				$urlDiscard = $wgTitle->getFullUrl() . "?discard={$row['draft_id']}";

				// Times
				$htmlSaved = gmdate( 'F jS g:ia', wfTimestamp( TS_UNIX, $row['draft_savetime'] ) );
				$htmlStarted = gmdate( 'F jS g:ia', wfTimestamp( TS_UNIX, $row['draft_starttime'] ) );
				$htmlEdited = gmdate( 'F jS g:ia', wfTimestamp( TS_UNIX, $row['draft_edittime'] ) );

				// Internationalization
				$msgDiscard = wfMsgHTML( 'drafts-view-discard' );

				$htmlDraftList .= <<<END
					<tr>
						<td width="17%"><a href="{$urlLoad}">{$htmlTitle}</a></td>
						<td width="17%">{$htmlSaved}</td>
						<td width="17%">{$htmlStarted}</td>
						<td width="17%">{$htmlEdited}</td>
						<td width="17%" align="right"><a href="{$urlDiscard}">{$msgDiscard}</a></td>
					</tr>
END;
				$count++;
			}
			$htmlDraftList .= <<<END
				</table>
				<br />
END;
			if ( $count > 0 ) {
				$wgOut->addHTML( $htmlDraftList );
			}
			else {
				$wgOut->addHTML( wfMsgHTML( 'drafts-view-nonesaved' ) );
			}
		}
	}
}
