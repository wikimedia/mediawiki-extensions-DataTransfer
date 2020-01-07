<?php
/**
 * Lets the user import a CSV file to turn into wiki pages
 *
 * @author Yaron Koren
 */

class DTImportCSV extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct( $name='ImportCSV' ) {
		parent::__construct( $name );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $query ) {
		$this->setHeaders();

		if ( ! $this->getUser()->isAllowed( 'datatransferimport' ) ) {
			throw new PermissionsError( 'datatransferimport' );
		}

		if ( $this->getRequest()->getCheck( 'import_file' ) ) {
			$text = $this->importFromUploadAndModifyPages();
		} else {
			$text = $this->printForm();
		}

		$this->getOutput()->addModuleStyles( 'ext.datatransfer');
		$this->getOutput()->addHTML( $text );
	}

	protected function importFromUploadAndModifyPages () {

		$text = DTUtils::printImportingMessage();
		$uploadResult = ImportStreamSource::newFromUpload( "file_name" );

		if ( !$uploadResult->isOK() ) {
			$uploadError = $this->getOutput()->parse( $uploadResult->getWikiText() );
			$text .= $uploadError;
			return $text;
		}

		$source = $uploadResult->value;

		$encoding = $this->getRequest()->getVal( 'encoding' );
		$pages = array();

		$error_msg = $this->importFromFile( $source, $encoding, $pages );

		if ( ! is_null( $error_msg ) ) {
			$text .= $error_msg;
			return $text;
		}

		$importSummary = $this->getRequest()->getVal( 'import_summary' );
		$forPagesThatExist = $this->getRequest()->getVal( 'pagesThatExist' );

		$text .= self::modifyPages( $pages, $importSummary, $forPagesThatExist );

		return $text;
	}

	protected function printForm() {
		$formText = DTUtils::printFileSelector( $this->getFiletype() );
		$utf8OptionText = "\t" . Xml::element( 'option',
				array(
					'selected' => 'selected',
					'value' => 'utf8'
				), 'UTF-8' ) . "\n";
		$utf16OptionText = "\t" . Xml::element( 'option',
				array(
					'value' => 'utf16'
				), 'UTF-16' ) . "\n";
		$encodingSelectText = Xml::tags( 'select',
				array( 'name' => 'encoding' ),
				"\n" . $utf8OptionText . $utf16OptionText. "\t" ) . "\n\t";
		$formText .= "\t" . Xml::tags( 'p', null, $this->msg( 'dt_import_encodingtype', 'CSV' )->text() . " " . $encodingSelectText ) . "\n";
		$formText .= "\t" . '<hr style="margin: 10px 0 10px 0" />' . "\n";
		$formText .= DTUtils::printExistingPagesHandling();
		$formText .= DTUtils::printImportSummaryInput( $this->getFiletype() );
		$formText .= DTUtils::printSubmitButton();
		$text = "\t" . Xml::tags( 'form',
				array(
					'enctype' => 'multipart/form-data',
					'action' => '',
					'method' => 'post'
				), $formText ) . "\n";
		return $text;
	}

	protected function importFromFile( ImportStreamSource $csvFileStream, $encoding, &$pages ) {
		$encodingCode = ( $encoding == 'utf16' ) ? 'UTF-16' : 'ASCII';

		$csvString = '';
		while ( ! $csvFileStream->atEnd() ) {
			$csvString .= $csvFileStream->readChunk();
		}

		if ( $csvString == '' ) {
			return wfMessage( 'emptyfile' )->text();
		}

		// Get rid of the "byte order mark", if it's there - this is
		// a two- or three-character string sometimes put at the beginning
		// of files to indicate its encoding.
		$byteOrderMarks = array(
			pack( "CCC", 0xef, 0xbb, 0xbf ), // UTF8
			pack( "CC", 0xfe, 0xff ), // UTF16 big-endian
			pack( "CC", 0xff, 0xfe ) // UTF16 little-endian
		);
		foreach ( $byteOrderMarks as $i => $bom ) {
			if ( strncmp( $csvString, $bom, strlen( $bom ) ) === 0 ) {
				if ( $i == 2 ) {
					// This is really just here as a
					// placeholder - unfortunately, the
					// encoding for UTF-16LE still doesn't
					// work, so trying to import CSV from
					// such a file will result in an
					// error message.
					$encodingCode = 'UTF-16LE';
				}
				$csvString = substr( $csvString, strlen( $bom ) );
				break;
			}
		}

		$table = str_getcsv( $csvString, "\n" );
		foreach ( $table as &$row ) {
			$row = str_getcsv( $row );
			foreach ( $row as &$value ) {
				$value = mb_convert_encoding( $value, 'UTF-8', $encodingCode );
				$value = trim( $value );
			}
		}

		return $this->importFromArray( $table, $pages );

	}

	protected function importFromArray( $table, &$pages ) {
		// Check header line to make sure every term is in the
		// correct format.
		$titleLabels = array( wfMessage( 'dt_xml_title' )->inContentLanguage()->text() );
		$freeTextLabels = array( wfMessage( 'dt_xml_freetext' )->inContentLanguage()->text() );
		// Add the English-language values as well, if this isn't an
		// English-language wiki.
		if ( $this->getLanguage()->getCode() !== 'en' ) {
			$titleLabels[] = wfMessage( 'dt_xml_title' )->inLanguage( 'en' )->text();
			$freeTextLabels[] = wfMessage( 'dt_xml_freetext' )->inLanguage( 'en' )->text();
		}
		foreach ( $table[0] as $i => $headerVal ) {
			if ( !in_array( $headerVal, $titleLabels )
			&& !in_array( $headerVal, $freeTextLabels )
			&& $headerVal !== ''
			&& !preg_match( '/^[^\[\]]+\[[^\[\]]+]$/', $headerVal ) ) {
				$errorMsg = wfMessage( 'dt_importcsv_badheader', $i, $headerVal, $titleLabels[0], $freeTextLabels[0] )->text();
				return $errorMsg;
			}
		}
		foreach ( $table as $i => $line ) {
			if ( $i == 0 ) continue;
			$page = new DTPage();
			foreach ( $line as $j => $val ) {
				if ( $table[0][$j] === '' ) {
					continue;
				}
				if ( in_array( $table[0][$j], $titleLabels ) ) {
					$page->setName( $val );
				} elseif ( in_array( $table[0][$j], $freeTextLabels ) ) {
					$page->setFreeText( $val );
				} else {
					list( $templateName, $fieldName ) = explode( '[', str_replace( ']', '', $table[0][$j] ) );
					$page->addTemplateField( $templateName, $fieldName, $val );
				}
			}
			$pages[] = $page;
		}

		return null;
	}

	protected function modifyPages( $pages, $editSummary, $forPagesThatExist ) {
		$text = "";
		$jobs = array();
		$jobParams = array();
		$jobParams['user_id'] = $this->getUser()->getId();
		$jobParams['edit_summary'] = $editSummary;
		$jobParams['for_pages_that_exist'] = $forPagesThatExist;
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page->getName() );
			if ( is_null( $title ) ) {
				$text .= '<p>' . $this->msg( 'img-auth-badtitle', $page->getName() )->text() . "</p>\n";
				continue;
			}
			$jobParams['text'] = $page->createText();
			$jobs[] = new DTImportJob( $title, $jobParams );
		}
		JobQueueGroup::singleton()->push( $jobs );

		$text .= $this->msg( 'dt_import_success' )->numParams( count( $jobs ) )->params( $this->getFiletype() )->parseAsBlock();

		return $text;
	}

	protected function getFiletype() {
		return wfMessage( 'dt_filetype_csv' )->text();
	}
}
