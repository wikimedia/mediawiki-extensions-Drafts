<?php
/**
 * API module to save Drafts
 *
 * @file
 * @ingroup API
 * @author Kunal Mehta
 */
class ApiSaveDrafts extends ApiBase {
	public function execute() {

		if ( $this->getUser()->isAnon() ) {
			$this->dieUsage( 'You must be logged in to save drafts.', 'notloggedin' );
		}

		$params = $this->extractRequestParams();

		$draft = Draft::newFromID( $params['id'] );
		$draft->setToken( $params['drafttoken'] );
		$draft->setTitle( Title::newFromText( $params['title'] ) );
		$draft->setSection( $params['section'] == '' ? null : $params['section'] );
		$draft->setStartTime( $params['starttime'] );
		$draft->setEditTime( $params['edittime'] );
		$draft->setSaveTime( wfTimestampNow() );
		$draft->setScrollTop( $params['scrolltop'] );
		$draft->setText( $params['text'] );
		$draft->setSummary( $params['summary'] );
		$draft->setMinorEdit( $params['minoredit'] );
		$draft->save();

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			array( 'id' => $draft->getID() )
		);

	}

	public function getDescription() {
		return 'Save a draft';
	}

	public function getAllowedParams() {
		return array(
			'id' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'drafttoken' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'section' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'starttime' => array(
				ApiBase::PARAM_REQUIRED => true,
			),
			'edittime' => array(
				ApiBase::PARAM_REQUIRED => true,
			),
			'scrolltop' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
			),
			'text' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'summary' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'minoredit' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_REQUIRED => true,
			),
			'token' => null,
		);
	}

	public function getPossibleErrors() {
		return array(
			array( 'notloggedin'),
		);
	}

	public function getResultProperties() {
		return array(
			'' => array(
				'id' => 'integer',
			)
		);
	}

	public function mustBePosted() {
		return true;
	}


	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

	public function isWriteMode() {
		return true;
	}

}
