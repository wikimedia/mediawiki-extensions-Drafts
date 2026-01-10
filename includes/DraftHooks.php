<?php
/**
 * Hooks for Drafts extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\Config\Config;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Specials\SpecialMovePage;
use MediaWiki\Storage\EditResult;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

class DraftHooks implements
	\MediaWiki\Hook\EditFilterHook,
	\MediaWiki\Hook\EditPageBeforeEditButtonsHook,
	\MediaWiki\Hook\EditPage__attemptSave_afterHook,
	\MediaWiki\Hook\EditPage__showEditForm_initialHook,
	\MediaWiki\Hook\SpecialMovepageAfterMoveHook,
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook,
	\MediaWiki\Page\Hook\PageUndeleteCompleteHook,
	\MediaWiki\Preferences\Hook\GetPreferencesHook,
	\MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook,
	\MediaWiki\Storage\Hook\PageSaveCompleteHook,
	\MediaWiki\User\Hook\UserGetDefaultOptionsHook
{
	/**
	 * Enable the Drafts preference by default for new user accounts (as well as
	 * old ones that haven't explicitly disabled Drafts).
	 *
	 * @param array &$defaultOptions
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions['extensionDrafts_enable'] = true;
	}

	/**
	 * Register the preference to enable/disable Drafts on a per-user basis.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['extensionDrafts_enable'] = [
			'type' => 'toggle',
			'label-message' => 'drafts-enable',
			'section' => 'editing/extension-drafts'
		];
	}

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

	/**
	 * SpecialMovepageAfterMove hook
	 *
	 * @param SpecialMovePage $mp
	 * @param Title $ot
	 * @param Title $nt
	 */
	public function onSpecialMovepageAfterMove( $mp, $ot, $nt ) {
		// Update all drafts of old article to new article for all users
		Drafts::move( $ot, $nt );
	}

	/**
	 * PageSaveComplete hook - does two things:
	 * 1) If the save occurred from a draft, discards the draft
	 * 2) Updates DB entries in the drafts table on page creation (T21837)
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 * @return bool|void
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult,
	) {
		global $wgRequest;

		// Check if the save occurred from a draft
		$draft = Draft::newFromID( $wgRequest->getInt( 'wpDraftID', 0 ) );
		if ( $draft->exists() ) {
			// Discard the draft
			$draft->discard( $user );
		}

		// When a page is created, associate the page ID with any drafts that might exist
		$title = $wikiPage->getTitle();
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->update(
			'drafts',
			[ 'draft_page' => $title->getArticleID() ],
			[
				'draft_namespace' => $title->getNamespace(),
				'draft_title' => $title->getDBkey()
			],
			__METHOD__
		);
	}

	/**
	 * EditPage::showEditForm:initial hook
	 * Load draft...
	 *
	 * @param EditPage $editpage
	 * @param OutputPage $out
	 * @return void
	 */
	public function onEditPage__showEditForm_initial( $editpage, $out ) {
		$context = $editpage->getArticle()->getContext();
		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$user = $context->getUser();

		if ( !$userOptionsManager->getOption( $user, 'extensionDrafts_enable', 'true' ) ) {
			return;
		}

		// Check permissions
		$request = $context->getRequest();
		if ( $user->isAllowed( 'edit' ) && $user->isRegistered() ) {
			// Get draft
			$draft = Draft::newFromID( $request->getInt( 'draft', 0 ) );
			// Load form values
			if ( $draft->exists() ) {
				// Override initial values in the form with draft data
				$editpage->textbox1 = $draft->getText();
				$editpage->summary = $draft->getSummary();

				// $editpage->scrolltop = $draft->getScrollTop();
				// $editpage->minoredit = $draft->getMinorEdit() ? true : false;

				// EditPage::$scrolltop & EditPage::$minoredit made private in MW 1.38 :-(
				$request->setVal( 'wpScrolltop', $draft->getScrollTop() );
				$request->setVal( 'wpMinoredit', $draft->getMinorEdit() ? true : false );
			}

			$draftTitle = $request->getRawVal( 'wpDraftTitle' );
			// Try harder.
			if ( $draftTitle === null ) {
				$draftTitle = $request->getRawVal( 'title' );
			}

			// OK, now would be a *great* time to panic!
			if ( !$draftTitle ) {
				throw new MWException(
					"Can't get a Title, neither from 'wpDraftTitle' nor from 'title'!"
				);
			}

			// Save draft on non-save submission
			// (I guess this means the "Show changes" button? And also preview, apparently.)
			if (
				$request->getRawVal( 'action' ) === 'submit' &&
				$user->matchEditToken( $request->getText( 'wpEditToken' ) ) &&
				( $request->getRawVal( 'wpPreview' ) || $request->getRawVal( 'wpDiff' ) )
			) {
				$text = $request->getText( 'wpTextbox1' );
				// If the draft wasn't specified in the url, try using a
				// form-submitted one
				if ( !$draft->exists() ) {
					$draft = Draft::newFromID(
						$request->getInt( 'wpDraftID', 0 )
					);
				}
				// Load draft with info
				// @todo FIXME: newFromText() *can* still return null and make Draft#save barf!
				$draft->setTitle( Title::newFromText( $draftTitle ) );
				$draft->setSection( $request->getInt( 'wpSection' ) );
				$draft->setStartTime( $request->getText( 'wpStarttime' ) );
				$draft->setEditTime( $request->getText( 'wpEdittime' ) );
				$draft->setSaveTime( wfTimestampNow() );
				$draft->setScrollTop( $request->getInt( 'wpScrolltop' ) );
				$draft->setText( $text );
				$draft->setSummary( $request->getText( 'wpSummary' ) );
				$draft->setMinorEdit( $request->getBool( 'wpMinoredit' ) );
				// Save draft (but only if it makes sense -- T21737)
				if ( $text ) {
					$draft->save();
					// Use the new draft id
					$request->setVal( 'draft', $draft->getID() );
				}
			}
		}

		$out = $context->getOutput();

		$numDrafts = Drafts::num( $context->getTitle() );
		// Show list of drafts
		if ( $numDrafts > 0 ) {
			if ( $request->getRawVal( 'action' ) !== 'submit' ) {
				$out->addHTML( Html::openElement(
					'div', [ 'id' => 'drafts-list-box' ] )
				);
				$out->addHTML( Html::element(
					'h3', [], $context->msg( 'drafts-view-existing' )->text() )
				);
				$out->addHTML( Drafts::display( $context->getTitle() ) );
				$out->addHTML( Html::closeElement( 'div' ) );
			} else {
				$link = Html::element( 'a',
					[
						'href' => $context->getTitle()->getFullURL( 'action=edit' ),
						'class' => 'mw-discard-draft-link'
					],
					$context->msg( 'drafts-view-notice-link' )->numParams( $numDrafts )->text()
				);
				$out->addHTML( $context->msg( 'drafts-view-notice' )->rawParams( $link )->escaped() );
			}
		}
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave:after' hook.
	 * This method handles clicks on the "Save draft" button when the user has
	 * JavaScript disabled.
	 *
	 * @todo FIXME: The guts of this method kinda duplicate the onEditPage__showEditForm_initial() method's
	 * internals, but such is life.
	 *
	 * @param EditPage $editPage
	 * @param Status $status
	 * @param array $resultDetails
	 */
	// phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	public function onEditPage__attemptSave_after( $editPage, $status, $resultDetails ) {
		$article = $editPage->getArticle();
		$ctx = $article->getContext();
		$user = $ctx->getUser();
		$request = $ctx->getRequest();

		if ( !$user->isRegistered() ) {
			return;
		}

		// This is a no-JS endpoint, no need to do anything here for users w/
		// JS enabled.
		if ( $request->getBool( 'wpDraftJSEnabled' ) ) {
			return;
		}

		// We could check if the user preference is enabled for the user but that's
		// not strictly needed IMHO isnce this is basically an internal endpoint
		// that's normally accessible only if the user has JS disabled and they
		// hit the "Save Draft" button, and said button is shown only if they have the
		// preference option enabled, sooooo...

		$draftTitle = $request->getRawVal( 'wpDraftTitle' );
		// Try harder.
		if ( $draftTitle === null ) {
			$draftTitle = $request->getRawVal( 'title' );
		}

		$text = $request->getText( 'wpTextbox1' );

		$draft = Draft::newFromID( $request->getInt( 'wpDraftID', 0 ) );
		$draft->setToken( $request->getRawVal( 'wpDraftToken' ) ?? '' );
		// @todo FIXME: newFromText() *can* still return null and make Draft#save barf!
		$draft->setTitle( Title::newFromText( $draftTitle ) );
		$draft->setSection( $request->getInt( 'wpSection' ) );
		$draft->setStartTime( $request->getText( 'wpStarttime' ) );
		$draft->setEditTime( $request->getText( 'wpEdittime' ) );
		$draft->setSaveTime( wfTimestampNow() );
		$draft->setScrollTop( $request->getInt( 'wpScrolltop' ) );
		$draft->setText( $text );
		$draft->setSummary( $request->getText( 'wpSummary' ) );
		$draft->setMinorEdit( $request->getBool( 'wpMinoredit' ) );

		// Save draft (but only if it makes sense -- T21737)
		if ( $text !== '' ) {
			$draft->save();
		}
	}

	/**
	 * EditFilter hook
	 * Intercept the saving of an article to detect if the submission was from
	 * the non-JavaScript save draft button
	 *
	 * @param EditPage $editor
	 * @param string $text
	 * @param string $section
	 * @param string &$error
	 * @param string $summary
	 */
	public function onEditFilter( $editor, $text, $section, &$error, $summary ) {
		// Don't save if the save draft button caused the submit
		if ( $editor->getArticle()->getContext()->getRequest()->getText( 'wpDraftSave' ) !== '' ) {
			// Modify the error so it's clear we want to remain in edit mode
			$error = ' ';
		}
	}

	/**
	 * EditPageBeforeEditButtons hook
	 * Add draft saving controls
	 *
	 * @param EditPage $editpage
	 * @param array &$buttons
	 * @param int &$tabindex
	 */
	public function onEditPageBeforeEditButtons( $editpage, &$buttons, &$tabindex ) {
		$context = $editpage->getArticle()->getContext();
		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$user = $context->getUser();

		if ( !$userOptionsManager->getOption( $user, 'extensionDrafts_enable', 'true' ) ) {
			return;
		}

		// Check permissions
		if ( $user->isAllowed( 'edit' ) && $user->isRegistered() ) {
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
					// Do NOT change this to true! This HAS to be false
					// so that no-JS users can save drafts manually.
					'disabled' => false
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
			// Will be used by the onEditPage__attemptSave_after hook handler to
			// avoid unnecessary processing. The main JS file flips this to true
			// for people who have JS enabled.
			$buttons['savedraft'] .= new OOUI\HiddenInputWidget(
				[
					'name' => 'wpDraftJSEnabled',
					'value' => false
				]
			);
		}
	}

	/**
	 * Hook for ResourceLoaderGetConfigVars
	 *
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		global $egDraftsAutoSaveWait, $egDraftsAutoSaveTimeout,
			   $egDraftsAutoSaveInputBased;
		$vars['wgDraftAutoSaveWait'] = $egDraftsAutoSaveWait;
		$vars['wgDraftAutoSaveTimeout'] = $egDraftsAutoSaveTimeout;
		$vars['wgDraftAutoSaveInputBased'] = $egDraftsAutoSaveInputBased;
	}

	/**
	 * PageUndeleteComplete hook
	 *
	 * If an article is undeleted, update the page ID we have stored internally
	 *
	 * @see https://phabricator.wikimedia.org/T21734
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $restorer
	 * @param string $reason Undeletion reason
	 * @param RevisionRecord $restoredRev
	 * @param ManualLogEntry $logEntry
	 * @param int $restoredRevisionCount
	 * @param bool $created
	 * @param array $restoredPageIds
	 * @return void
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		if ( !$created ) {
			// Only for restored pages
			return;
		}

		$services = MediaWikiServices::getInstance();
		$title = $services->getTitleFactory()->newFromPageIdentity( $page );

		$dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->update(
			'drafts',
			[ 'draft_page' => $title->getArticleID() ],
			[
				'draft_namespace' => $title->getNamespace(),
				'draft_title' => $title->getDBkey()
			],
			__METHOD__
		);
	}
}
