<?php

/* Classes */

// Draft Class
class Draft {
	/* Fields */

	private $mDb;
	private $mExists = false;
	private $mId;
	private $mToken;
	private $mUserId;
	private $mTitle;
	private $mSection;
	private $mStartTime;
	private $mEditTime;
	private $mSaveTime;
	private $mScrollTop ;
	private $mText;
	private $mSummary;
	private $mMinorEdit;

	/* Functions */

	public function __construct( $id = null, $autoload = true ) {
		// If an ID is a number the existence is actually checked on load
		// If an ID is false the existance is always false durring load
		$this->mId = $id;

		# Load automatically
		if ( $autoload ) {
			$this->load();
		}
	}

	private function load() {
		global $wgUser;
		
		// Verify the ID has been set
		if ( $this->mId === null ) {
			return;
		}
		
		// Get db connection
		$this->getDB();

		// Select drafts from the database matching ID - can be 0 or 1 results
		$result = $this->mDb->select( 'drafts',
			array( '*' ),
			array(
				'draftmId' => (int) $this->_id,
				'draft_user' => (int) $wgUser->getID()
			),
			__METHOD__
		);
		if ( $result === false ) {
			return;
		}

		// Get the row
		$row = $this->mDb->fetchRow( $result );
		if ( !is_array( $row ) || count( $row ) == 0 ) {
			return;
		}

		// Synchronize data
		$this->mToken = $row['draft_token'];
		$this->mTitle = Title::makeTitle( $row['draft_namespace'], $row['draft_title'] );
		$this->mSection = $row['draft_section'];
		$this->mStartTime = $row['draft_starttime'];
		$this->mEditTime = $row['draft_edittime'];
		$this->mSaveTime = $row['draft_savetime'];
		$this->mScrollTop = $row['draft_scrolltop'];
		$this->mText = $row['draft_text'];
		$this->mSummary = $row['draft_summary'];
		$this->mMinorEdit = $row['draft_minoredit'];

		// Update state
		$this->mExists = true;

		return;
	}

	public function save() {
		global $wgUser, $wgRequest;
	
		// Get db connection
		$this->getDB();
		$this->mDb->begin();
		
		// Build data
		$data = array(
			'draftmToken' => (int) $this->getToken(),
			'draft_user' => (int) $wgUser->getID(),
			'draft_namespace' => (int) $this->mTitle->getNamespace(),
			'draftmTitle' => (string) $this->_title->getDBKey(),
			'draft_page' => (int) $this->mTitle->getArticleId(),
			'draftmSection' => $this->_section == '' ? null : (int) $this->_section,
			'draftmStartTime' => $this->mDb->timestamp( $this->_starttime ),
			'draftmEditTime' => $this->mDb->timestamp( $this->_edittime ),
			'draftmSaveTime' => $this->mDb->timestamp( $this->_savetime ),
			'draftmScrollTop' => (int) $this->_scrolltop,
			'draftmText' => (string) $this->_text,
			'draftmSummary' => (string) $this->_summary,
			'draftmMinorEdit' => (int) $this->_minoredit
		);

		// Save data
		if ( $this->mExists === true ) {
			$this->mDb->update( 'drafts',
				$data,
				array(
					'draftmId' => (int) $this->_id,
					'draft_user' => (int) $wgUser->getID()
				),
				__METHOD__
			);
		} else {
			// Before creating a new draft record, lets check if we have already
			$token = $wgRequest->getIntOrNull( 'wpDraftToken' );
			if ( $token !== null ) {
				// FIXME: clean up this code style :)
				// Check if token has been used already for this article
				if ( $this->mDb->selectField( 'drafts', 'draftmToken',
					array(
						'draft_namespace' => $data['draft_namespace'],
						'draftmTitle' => $data['draft_title'],
						'draft_user' => $data['draft_user'],
						'draftmToken' => $data['draft_token']
					),
					__METHOD__
				) === false ) {
					$this->mDb->insert( 'drafts', $data, __METHOD__ );
					$this->mId = $this->mDb->insertId();
					// Update state
					$this->mExists = true;
				}
			}
		}
		
		$this->mDb->commit();
		
		// Return success
		return true;
	}

	public function discard() {
		global $wgUser;
		
		// Get db connection
		$this->getDB();

		// Delete data
		$this->mDb->delete( 'drafts',
			array(
				'draftmId' => $this->_id,
				// FIXME: ID is already a primary key
				'draft_user' =>  $wgUser->getID()
			),
			__METHOD__
		);

		$this->mExists = false;
	}
	
	public static function newFromID( $id, $autoload = true ) {
		return new Draft( $id, $autoload );
	}
	
	public static function newFromRow( $row ) {
		$draft = new Draft( $row['draftmId'], false );
		$draft->setToken( $row['draftmToken'] );
		$draft->setTitle( Title::makeTitle( $row['draft_namespace'], $row['draftmTitle'] ) );
		$draft->setSection( $row['draftmSection'] );
		$draft->setStartTime( $row['draftmStartTime'] );
		$draft->setEditTime( $row['draftmEditTime'] );
		$draft->setSaveTime( $row['draftmSaveTime'] );
		$draft->setScrollTop( $row['draftmScrollTop'] );
		$draft->setText( $row['draftmText'] );
		$draft->setSummary( $row['draftmSummary'] );
		$draft->setMinorEdit( $row['draftmMinorEdit'] );
		return $draft;
	}
	
	public static function countDrafts( &$title = null, $userID = null ) {
		global $wgUser;
		
		Draft::cleanDrafts();
		
		// Get db connection
		$db = wfGetDB( DB_SLAVE );
		
		// Build where clause
		$where = array();
		if ( $title !== null ) {
			$where['draft_namespace'] = $title->getNamespace();
			$where['draftmTitle'] = $title->getDBKey();
		}
		if ( $userID !== null ) {
			$where['draft_user'] = $userID;
		} else {
			$where['draft_user'] = $wgUser->getID();
		}
		
		// Get a list of matching drafts
		return $db->selectField( 'drafts', 'count(*)', $where, __METHOD__ );
	}
	
	public static function cleanDrafts() {
		global $wgDraftsLifeSpan;
		
		// Get db connection
		$db = wfGetDB( DB_MASTER );
		
		// Remove drafts that are more than $wgDraftsLifeSpan days old
		$cutoff = wfTimestamp( TS_UNIX ) - ( $wgDraftsLifeSpan * 60 * 60 * 24 );
		$db->delete( 'drafts',
			array(
				'draftmSaveTime < ' . $db->addQuotes( $db->timestamp( $cutoff ) )
			),
			__METHOD__
		);
	}
	
	public static function getDrafts( $title = null, $userID = null ) {
		global $wgUser;
		
		Draft::cleanDrafts();
		
		// Get db connection
		$db = wfGetDB( DB_MASTER );
		
		// Build where clause
		$where = array();
		if ( $title !== null ) {
			$pageId = $title->getArticleId();
			if ( $pageId ) {
				$where['draft_page'] = $pageId;
			} else {
				$where['draft_page'] = 0; // page not created yet
				$where['draft_namespace'] = $title->getNamespace();
				$where['draftmTitle'] = $title->getDBKey();
			}
		}
		if ( $userID !== null ) {
			$where['draft_user'] = $userID;
		} else {
			$where['draft_user'] = $wgUser->getID();
		}
		
		// Create an array of matching drafts
		$drafts = array();
		$result = $db->select( 'drafts', '*', $where, __METHOD__ );
		if ( $result ) {
			while ( $row = $db->fetchRow( $result ) ) {
				// Add a new draft to the list from the row
				$drafts[] = Draft::newFromRow( $row );
			}
		}
		
		// Return array of matching drafts
		return count( $drafts ) ? $drafts : null;
	}
	
	public static function listDrafts( &$title = null, $user = null ) {
		global $wgOut, $wgRequest, $wgUser, $wgLang;
		
		// Get draftID
		$currentDraft = Draft::newFromID( $wgRequest->getIntOrNull( 'draft' ) );
		
		// Output HTML for list of drafts
		$drafts = Draft::getDrafts( $title, $user );
		if ( count( $drafts ) > 0 )	{
			// Internationalization
			wfLoadExtensionMessages( 'Drafts' );
			
			// Build XML
			$wgOut->addHTML(
				Xml::openElement( 'table',
					array(
						'cellpadding' => 5,
						'cellspacing' => 0,
						'width' => '100%',
						'border' => 0,
						'style' => 'margin-left:-5px;margin-right:-5px'
					)
				)
			);
			$wgOut->addHTML( Xml::openElement( 'tr' ) );
			$wgOut->addHTML(
				Xml::element( 'th',
					array(
						'align' => 'left',
						'width' => '75%',
						'nowrap' => 'nowrap'
					),
					wfMsg( 'drafts-view-article' )
				)
			);
			$wgOut->addHTML(
				Xml::element( 'th',
					array(
						'align' => 'left',
						'nowrap' => 'nowrap'
					),
					wfMsg( 'drafts-view-saved' )
				)
			);
			$wgOut->addHTML( Xml::element( 'th' ) );
			$wgOut->addHTML( Xml::closeElement( 'tr' ) );
			
			// Add existing drafts for this page and user
			foreach ( $drafts as $draft ) {
				// Get article title text
				$htmlTitle = $draft->getTitle()->getEscapedText();
				
				// Build Article Load link
				$urlLoad = $draft->getTitle()->getFullUrl( 'action=edit&draft=' . urlencode( $draft->getID() ) );
				
				// Build discard link
				$urlDiscard = sprintf( '%s?discard=%s&token=%s',
					SpecialPage::getTitleFor( 'Drafts' )->getFullUrl(),
					urlencode( $draft->getID() ),
					urlencode( $wgUser->editToken() )
				);
				// If in edit mode, return to editor
				if ( $wgRequest->getText( 'action' ) == 'edit' || $wgRequest->getText( 'action' ) == 'submit' ) {
					$urlDiscard .= '&returnto=' . urlencode( 'edit' );
				}
				
				// Append section to titles and links
				if ( $draft->getSection() !== null ) {
					// Detect section name
					$lines = explode( "\n", $draft->getText() );
					
					// If there is any content in the section
					if ( count( $lines ) > 0 ) {
						$htmlTitle .= '#' . htmlspecialchars(
							trim( trim( substr( $lines[0], 0, 255 ), '=' ) )
						);
					}
					
					// Modify article link and title
					$urlLoad .= '&section=' . urlencode( $draft->getSection() );
					$urlDiscard .= '&section=' . urlencode( $draft->getSection() );
				}
				
				// Build XML
				$wgOut->addHTML( Xml::openElement( 'tr' ) );
				$wgOut->addHTML(
					Xml::openElement( 'td',
						array(
							'align' => 'left',
							'nowrap' => 'nowrap'
						)
					)
				);
				$wgOut->addHTML(
					Xml::element( 'a',
						array(
							'href' => $urlLoad,
							'style' => 'font-weight:' . ( $currentDraft->getID() == $draft->getID() ? 'bold' : 'normal' )
						),
						$htmlTitle
					)
				);
				$wgOut->addHTML( Xml::closeElement( 'td' ) );
				$wgOut->addHTML(
					Xml::element( 'td',
						array(
							'align' => 'left',
							'nowrap' => 'nowrap'
						),
						$wgLang->timeanddate( $draft->getSaveTime() )
					)
				);
				$wgOut->addHTML(
					Xml::openElement( 'td',
						array(
							'align' => 'left',
							'nowrap' => 'nowrap'
						)
					)
				);
				$wgOut->addHTML(
					Xml::element( 'a',
						array(
							'href' => $urlDiscard,
							'onclick' => "if( !wgAjaxSaveDraft.insync ) return confirm('" . Xml::escapeJsString( wfMsgHTML( 'drafts-view-warn' ) ) . "')"
						),
						wfMsg( 'drafts-view-discard' )
					)
				);
				$wgOut->addHTML( Xml::closeElement( 'td' ) );
				$wgOut->addHTML( Xml::closeElement( 'tr' ) );
			}
			$wgOut->addHTML( Xml::closeElement( 'table' ) );

			// Return number of drafts
			return count( $drafts );
		}
		return 0;
	}
	
	public static function newToken() {
		return time();
	}
	
	// FIXME: load balancer knows how to not re-fetch connections
	private function getDB() {
		// Get database connection if we don't already have one
		if ( $this->mDb === null ) {
			$this->mDb = wfGetDB( DB_MASTER );
		}
	}

	/* States */
	public function exists() {
		return $this->mExists;
	}

	/* Properties */

	public function getID() {
		return $this->mId;
	}

	public function setToken( $token ) {
		$this->mToken = $token;
	}
	public function getToken() {
		return $this->mToken;
	}

	public function getUserID( $userID ) {
		$this->mUserId = $userID;
	}
	public function setUserID() {
		return $this->mUserId;
	}

	public function getTitle() {
		return $this->mTitle;
	}
	public function setTitle( $title ) {
		$this->mTitle = $title;
	}

	public function getSection() {
		return $this->mSection;
	}
	public function setSection( $section ) {
		$this->mSection = $section;
	}

	public function getStartTime() {
		return $this->mStartTime;
	}
	public function setStartTime( $starttime ) {
		$this->mStartTime = $starttime;
	}

	public function getEditTime() {
		return $this->mEditTime;
	}
	public function setEditTime( $edittime ) {
		$this->mEditTime = $edittime;
	}

	public function getSaveTime() {
		return $this->mSaveTime;
	}
	public function setSaveTime( $savetime ) {
		$this->mSaveTime = $savetime;
	}

	public function getScrollTop() {
		return $this->mScrollTop;
	}
	public function setScrollTop( $scrolltop ) {
		$this->mScrollTop = $scrolltop;
	}

	public function getText() {
		return $this->mText;
	}
	public function setText( $text ) {
		$this->mText = $text;
	}

	public function getSummary() {
		return $this->mSummary;
	}
	public function setSummary( $summary ) {
		$this->mSummary = $summary;
	}

	public function getMinorEdit() {
		return $this->mMinorEdit;
	}
	public function setMinorEdit( $minoredit ) {
		$this->mMinorEdit = $minoredit;
	}
}
