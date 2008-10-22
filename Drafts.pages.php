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
			$urlSection = $wgRequest->getInt( 'section', '' );
			switch( $wgRequest->getText( 'returnto' ) )
			{
				case 'edit':
					$title = Title::newFromDBKey( $draft->getTitle() );
					$wgOut->redirect( wfExpandURL( $title->getEditURL() . $urlSection ) );
					break;
				case 'view':
					$title = Title::newFromDBKey( $draft->getTitle() );
					$wgOut->redirect( wfExpandURL( $title->getFullURL() . $urlSection ) );
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
				'draft_section',
				'draft_savetime',
				'draft_text'
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
			$msgDiscard = wfMsgHTML( 'drafts-view-discard' );

			$htmlDraftList = <<<END
				<table cellpadding="5" cellspacing="0" width="100%" border="0">
					<tr>
						<th align="left" width="75%" nowrap="nowrap">{$msgArticle}</th>
						<th align="left" nowrap="nowrap">{$msgSaved}</th>
						<th></th>
					</tr>
END;

			// Show list of drafts
			$count = 0;
			while ( $row = $db->fetchRow( $result ) )
			{
				// Article
				$title = Title::newFromDBKey( $row['draft_title'] ) ;
				$urlLoad = wfExpandURL( $title->getEditURL() ) . "&draft={$row['draft_id']}";
				$htmlTitle = $title->getEscapedText();
				
				// Drafts
				$urlDiscard = $wgTitle->getFullUrl() . "?discard={$row['draft_id']}";
				
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
						<td align="left" nowrap="nowrap"><a href="{$urlLoad}">{$htmlTitle}</a></td>
						<td align="left" nowrap="nowrap">{$htmlSaved}</td>
						<td align="right"><a href="{$urlDiscard}">{$msgDiscard}</a></td>
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
