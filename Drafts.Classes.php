<?php

/* Classes */

// Draft Class
class Draft
{
	/* Fields */

	private $_db = false;
	private $_exists = false;
	private $_id = false;
	private $_user;
	private $_namespace;
	private $_title;
	private $_section;
	private $_starttime;
	private $_edittime;
	private $_savetime;
	private $_scrolltop;
	private $_text;
	private $_summary;
	private $_minoredit;

	/* Functions */

	public function Draft( $id = false ) {
		// If an ID is a number the existence is actually checked on load
		// If an ID is false the existance is always false durring load
		$this->_id = $id;

		# Load automatically
		$this->load();
	}

	private function load() {
		global $wgUser;

		// Verify the ID has been set
		if ( $this->_id === false ) {
			return;
		}

		// Get a connection if we don't have one already
		if ( $this->_db === false ) {
			$this->_db = wfGetDB( DB_MASTER );
		}

		// Select drafts from the database matching ID - can be 0 or 1 results
		$result = $this->_db->select( 'drafts', array( '*' ),
			array(
				'draft_id' => (int) $this->_id,
				'draft_user' => (int) $wgUser->getID()
			)
		);
		if ( $result === false ) {
			return;
		}

		// Get the row
		$row = $this->_db->fetchRow( $result );
		if ( !is_array( $row ) || count( $row ) == 0 ) {
			return;
		}

		// Synchronize data
		$this->_namespace = $row['draft_namespace'];
		$this->_title = $row['draft_title'];
		$this->_section = $row['draft_section'];
		$this->_starttime = $row['draft_starttime'];
		$this->_edittime = $row['draft_edittime'];
		$this->_savetime = $row['draft_savetime'];
		$this->_scrolltop = $row['draft_scrolltop'];
		$this->_text = $row['draft_text'];
		$this->_summary = $row['draft_summary'];
		$this->_minoredit = $row['draft_minoredit'];

		// Update state
		$this->_exists = true;

		return;
	}

	public function save() {
		global $wgUser;

		// Get a connection if we don't have one already
		if ( $this->_db === false ) {
			$this->_db = wfGetDB( DB_MASTER );
		}

		// Get title object from text
		$title = Title::newFromText( $this->_title );

		// Build data
		$data = array(
			'draft_user' => (int) $wgUser->getID(),
			'draft_namespace' => (int) $this->_namespace,
			'draft_title' => (string) $title->getDBKey(),
			'draft_section' => (int) $this->_section,
			'draft_starttime' => $this->_db->timestamp( $this->_starttime ),
			'draft_edittime' => $this->_db->timestamp( $this->_edittime ),
			'draft_savetime' => $this->_db->timestamp( $this->_savetime ),
			'draft_scrolltop' => (int) $this->_scrolltop,
			'draft_text' => (string) $this->_text,
			'draft_summary' => (string) $this->_summary,
			'draft_minoredit' => (int) $this->_minoredit
		);

		// Save data
		if ( $this->_exists === true ) {
			$this->_db->update( 'drafts', $data,
				array(
					'draft_id' => (int) $this->_id,
					'draft_user' => (int) $wgUser->getID()
				)
			);
		}
		else {
			$this->_db->insert( 'drafts', $data );
			$this->_id = $this->_db->insertId();
			// Update state
			$this->_exists = true;
		}

		// Return success
		return true;
	}

	public function discard()
	{
		global $wgUser;

		// Delete data
		$this->_db->query(
			sprintf( 'DELETE FROM drafts WHERE draft_id = %d AND draft_user = %d',
				$this->_id,
				$wgUser->getID()
			), __METHOD__ );

		$this->_exists = false;
	}

	/* States */

	public function exists()
	{
		return $this->_exists;
	}

	/* Properties */

	public function getID() {
		return $this->_id;
	}

	public function getUser( $user ) {
		$this->_user = $user;
	}
	public function setUser() {
		return $this->_user;
	}

	public function getNamespace() {
		return $this->_namespace;
	}
	public function setNamespace( $namespace ) {
		$this->_namespace = $namespace;
	}

	public function getTitle() {
		return $this->_title;
	}
	public function setTitle( $title ) {
		$this->_title = $title;
	}

	public function getSection() {
		return $this->_section;
	}
	public function setSection( $section ) {
		$this->_section = $section;
	}

	public function getStartTime() {
		return $this->_starttime;
	}
	public function setStartTime( $starttime ) {
		$this->_starttime = $starttime;
	}

	public function getEditTime() {
		return $this->_edittime;
	}
	public function setEditTime( $edittime ) {
		$this->_edittime = $edittime;
	}

	public function getSaveTime() {
		return $this->_savetime;
	}
	public function setSaveTime( $savetime ) {
		$this->_savetime = $savetime;
	}

	public function getScrollTop() {
		return $this->_scrolltop;
	}
	public function setScrollTop( $scrolltop ) {
		$this->_scrolltop = $scrolltop;
	}

	public function getText() {
		return $this->_text;
	}
	public function setText( $text ) {
		$this->_text = $text;
	}

	public function getSummary() {
		return $this->_summary;
	}
	public function setSummary( $summary ) {
		$this->_summary = $summary;
	}

	public function getMinorEdit() {
		return $this->_minoredit;
	}
	public function setMinorEdit( $minoredit ) {
		$this->_minoredit = $minoredit;
	}
}
