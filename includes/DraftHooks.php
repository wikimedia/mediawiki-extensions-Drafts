<?php
/**
 * Hooks for Drafts extension
 *
 * @file
 * @ingroup Extensions
 */

class DraftHooks {
	/**
	 * @param array &$defaultOptions
	 */
	public static function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions['extensionDrafts_enable'] = true;
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['extensionDrafts_enable'] = [
			'type' => 'toggle',
			'label-message' => 'drafts-enable',
			'section' => 'editing/extension-drafts'
		];
		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function schema( $updater ) {
		$updater->addExtensionTable( 'drafts', dirname( __DIR__ ) . '/sql/Drafts.sql' );
		if ( $updater->getDb()->getType() != 'sqlite' ) {
			$updater->modifyExtensionField( 'drafts', 'draft_token',
				dirname( __DIR__ ) . '/sql/patch-draft_token.sql' );
		}
	}

	/**
	 * SpecialMovepageAfterMove hook
	 * @param MovePageForm $mp
	 * @param Title $ot
	 * @param Title $nt
	 */
	public static function onSpecialMovepageAfterMove( $mp, $ot, $nt ) {
		// Update all drafts of old article to new article for all users
		Drafts::move( $ot, $nt );
	}

	/**
	 * PageContentSaveComplete hook
	 *
	 * @param WikiPage $article
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param null $isWatch
	 * @param null $section
	 * @param int $flags
	 * @param Revision $revision
	 * @param Status $status
	 * @param int|bool $baseRevId
	 */
	public static function onPageContentSaveComplete( WikiPage $article, $user, $content, $summary, $isMinor,
		$isWatch, $section, $flags, $revision, $status, $baseRevId
	) {
		global $wgRequest;
		// Check if the save occurred from a draft
		$draft = Draft::newFromID( $wgRequest->getIntOrNull( 'wpDraftID' ) );
		if ( $draft->exists() ) {
			// Discard the draft
			$draft->discard( $user );
		}
	}

	/**
	 * EditPage::showEditForm:initial hook
	 * Load draft...
	 * @param EditPage $editpage
	 * @return bool
	 */
	public static function loadForm( EditPage $editpage ) {
		$context = $editpage->getArticle()->getContext();
		$user = $context->getUser();

		if ( !$user->getOption( 'extensionDrafts_enable', 'true' ) ) {
			return true;
		}

		// Check permissions
		$request = $context->getRequest();
		if ( $user->isAllowed( 'edit' ) && $user->isLoggedIn() ) {
			// Get draft
			$draft = Draft::newFromID( $request->getIntOrNull( 'draft' ) );
			// Load form values
			if ( $draft->exists() ) {
				// Override initial values in the form with draft data
				$editpage->textbox1 = $draft->getText();
				$editpage->summary = $draft->getSummary();
				$editpage->scrolltop = $draft->getScrollTop();
				$editpage->minoredit = $draft->getMinorEdit() ? true : false;
			}

			// Save draft on non-save submission
			if ( $request->getVal( 'action' ) == 'submit' &&
				$user->matchEditToken( $request->getText( 'wpEditToken' ) ) &&
				$request->getText( 'wpDraftTitle' ) === null ) {
				// If the draft wasn't specified in the url, try using a
				// form-submitted one
				if ( !$draft->exists() ) {
					$draft = Draft::newFromID(
						$request->getIntOrNull( 'wpDraftID' )
					);
				}
				// Load draft with info
				$draft->setTitle( Title::newFromText(
						$request->getText( 'wpDraftTitle' ) )
				);
				$draft->setSection( $request->getInt( 'wpSection' ) );
				$draft->setStartTime( $request->getText( 'wpStarttime' ) );
				$draft->setEditTime( $request->getText( 'wpEdittime' ) );
				$draft->setSaveTime( wfTimestampNow() );
				$draft->setScrollTop( $request->getInt( 'wpScrolltop' ) );
				$draft->setText( $request->getText( 'wpTextbox1' ) );
				$draft->setSummary( $request->getText( 'wpSummary' ) );
				$draft->setMinorEdit( $request->getBool( 'wpMinoredit' ) );
				// Save draft
				$draft->save();
				// Use the new draft id
				$request->setVal( 'draft', $draft->getID() );
			}
		}

		$out = $context->getOutput();

		$numDrafts = Drafts::num( $context->getTitle() );
		// Show list of drafts
		if ( $numDrafts > 0 ) {
			if ( $request->getText( 'action' ) !== 'submit' ) {
				$out->addHTML( Xml::openElement(
					'div', [ 'id' => 'drafts-list-box' ] )
				);
				$out->addHTML( Xml::element(
					'h3', null, $context->msg( 'drafts-view-existing' )->text() )
				);
				$out->addHTML( Drafts::display( $context->getTitle() ) );
				$out->addHTML( Xml::closeElement( 'div' ) );
			} else {
				$jsWarn = "if( !wgAjaxSaveDraft.insync ) return confirm(" .
					Xml::encodeJsVar( $context->msg( 'drafts-view-warn' )->text() ) .
					")";
				$link = Xml::element( 'a',
					[
						'href' => $context->getTitle()->getFullURL( 'action=edit' ),
						'onclick' => $jsWarn
					],
					$context->msg( 'drafts-view-notice-link' )->numParams( $numDrafts )->text()
				);
				$out->addHTML( $context->msg( 'drafts-view-notice' )->rawParams( $link )->escaped() );
			}
		}
	}

	/**
	 * EditFilter hook
	 * Intercept the saving of an article to detect if the submission was from
	 * the non-javascript save draft button
	 * @param EditPage $editor
	 * @param string $text
	 * @param string $section
	 * @param string $error
	 */
	public static function onEditFilter( EditPage $editor, $text, $section, $error ) {
		// Don't save if the save draft button caused the submit
		if ( $editor->getArticle()->getContext()->getRequest()->getText( 'wpDraftSave' ) !== '' ) {
			// Modify the error so it's clear we want to remain in edit mode
			$error = ' ';
		}
	}

	/**
	 * EditPageBeforeEditButtons hook
	 * Add draft saving controls
	 * @param EditPage $editpage
	 * @param array &$buttons
	 * @param int &$tabindex
	 */
	public static function onEditPageBeforeEditButtons( EditPage $editpage, &$buttons, &$tabindex ) {
		$context = $editpage->getArticle()->getContext();
		$user = $context->getUser();

		if ( !$user->getOption( 'extensionDrafts_enable', 'true' ) ) {
			return;
		}
		// Check permissions
		if ( $user->isAllowed( 'edit' ) && $user->isLoggedIn() ) {
			$request = $context->getRequest();
			$context->getOutput()->addModules( 'ext.Drafts' );

			$buttons['savedraft'] = new OOUI\ButtonInputWidget(
				[
					'name' => 'wpDraftSave',
					'tabIndex' => ++$tabindex,
					'id' => 'wpDraftWidget',
					'inputId' => 'wpDraftSave',
					'useInputTag' => true,
					'flags' => [ 'progressive' ],
					'label' => $context->msg( 'drafts-save-save' )->text(),
					'infusable' => true,
					'type' => 'submit',
					'title' => Linker::titleAttrib( 'drafts-save' ),
					'accessKey' => Linker::accesskey( 'drafts-save' ),
					'disabled' => true
				]
			);
			$buttons['savedraft'] .= new OOUI\HiddenInputWidget(
				[
					'name' => 'wpDraftToken',
					'value' => MWCryptRand::generateHex( 32 )
				]
			);
			$buttons['savedraft'] .= new OOUI\HiddenInputWidget(
				[
					'name' => 'wpDraftID',
					'value' => $request->getInt( 'draft' )
				]
			);
			$buttons['savedraft'] .= new OOUI\HiddenInputWidget(
				[
					'name' => 'wpDraftTitle',
					'value' => $context->getTitle()->getPrefixedText()
				]
			);
		}
	}

	/**
	 * Hook for ResourceLoaderGetConfigVars
	 *
	 * @param array &$vars
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $egDraftsAutoSaveWait, $egDraftsAutoSaveTimeout,
			   $egDraftsAutoSaveInputBased;
		$vars['wgDraftAutoSaveWait'] = $egDraftsAutoSaveWait;
		$vars['wgDraftAutoSaveTimeout'] = $egDraftsAutoSaveTimeout;
		$vars['wgDraftAutoSaveInputBased'] = $egDraftsAutoSaveInputBased;
	}

}
