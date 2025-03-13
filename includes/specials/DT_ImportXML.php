<?php

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Title\Title;

/**
 * Lets the user import an XML file to turn into wiki pages
 *
 * @author Yaron Koren
 */

class DTImportXML extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct( $name = 'ImportXML' ) {
		parent::__construct( $name, 'datatransferimport' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $query ) {
		$this->setHeaders();

		// We call isDefinitelyAllowed() here because, unlike other
		// permission checks, this one also checks whether the user is
		// currently blocked.
		if ( method_exists( $this->getAuthority(), 'isDefinitelyAllowed' ) ) {
			// MW 1.41+
			$status = PermissionStatus::newEmpty();
			$this->getAuthority()->isDefinitelyAllowed( 'datatransferimport', $status );
			if ( !$status->isGood() ) {
				throw new PermissionsError( 'datatransferimport' );
			}
		} else {
			if ( !$this->getUser()->isAllowed( 'datatransferimport' ) ) {
				throw new PermissionsError( 'datatransferimport' );
			}
		}

		$out = $this->getOutput();
		$out->enableOOUI();

		$request = $this->getRequest();
		if ( $request->wasPosted() && $request->getCheck( 'import_file' ) ) {
			$editToken = $request->getVal( 'wpEditToken' );
			if ( !$this->getContext()->getCsrfTokenSet()->matchToken( $editToken ) ) {
				// @todo - ideally, this should output a prefilled form with
				// a new edit token ready to go for more convenient resubmitting.
				// This would be best done by outputting the form using Codex,
				// OOUI or HTMLForm.
				$text = $this->msg( 'import-token-mismatch' )->parse();
				$out->addHTML( $text );
				return;
			}
			$text = DTUtils::printImportingMessage();
			$uploadResult = ImportStreamSource::newFromUpload( "file_name" );
			$source = $uploadResult->value;
			$importSummary = $request->getVal( 'import_summary' );
			$forPagesThatExist = $request->getVal( 'pagesThatExist' );
			$text .= self::modifyPages( $source, $importSummary, $forPagesThatExist );
		} else {
			$formText = DTUtils::printFileSelector( $this->msg( 'dt_filetype_xml' )->text() );
			$formText .= DTUtils::printExistingPagesHandling();
			$formText .= DTUtils::printImportSummaryInput( $this->msg( 'dt_filetype_xml' )->text() );
			$formText .= DTUtils::printEditTokenInput( $this->getContext()->getCsrfTokenSet() );
			$formText .= DTUtils::printSubmitButton();
			$text = "\t" . Xml::tags( 'form',
				[
					'enctype' => 'multipart/form-data',
					'action' => '',
					'method' => 'post'
				], $formText ) . "\n";
		}

		$out->addHTML( $text );
	}

	function modifyPages( $source, $editSummary, $forPagesThatExist ) {
		if ( $source == null ) {
			return Html::element( 'div', [ 'class' => 'error' ], wfMessage( 'importnofile' )->text() );
		}

		$text = "";
		$xml_parser = new DTXMLParser( $source );
		$xml_parser->doParse();
		$jobs = [];
		$job_params = [];
		$job_params['user_id'] = $this->getUser()->getId();
		$job_params['edit_summary'] = $editSummary;
		$job_params['for_pages_that_exist'] = $forPagesThatExist;

		foreach ( $xml_parser->getPages() as $page ) {
			$title = Title::newFromText( $page->getName() );
			$job_params['text'] = $page->createText();
			$job_params['slot'] = $page->getSlot();
			$jobs[] = new DTImportJob( $title, $job_params );
		}
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );

		$text .= $this->msg( 'dt_import_success' )->numParams( count( $jobs ) )->params( 'XML' )
			->parseAsBlock();
		return $text;
	}
}
