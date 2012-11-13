<?php
/**
 * Lets the user import a CSV file to turn into wiki pages
 *
 * @author Yaron Koren
 */

class DTPage {
	var $mName;
	var $mTemplates;
	var $mFreeText;

	public function DTPage() {
		$this->mTemplates = array();
	}

	function setName( $name ) {
		$this->mName = $name;
	}

	function getName() {
		return $this->mName;
	}

	function addTemplateField( $template_name, $field_name, $value ) {
		if ( ! array_key_exists( $template_name, $this->mTemplates ) ) {
			$this->mTemplates[$template_name] = array();
		}
		$this->mTemplates[$template_name][$field_name] = $value;
	}

	function setFreeText( $free_text ) {
		$this->mFreeText = $free_text;
	}

	function createText() {
		$text = "";
		foreach ( $this->mTemplates as $template_name => $fields ) {
			$text .= '{{' . $template_name . "\n";
			foreach ( $fields as $field_name => $val ) {
				$text .= "|$field_name=$val\n";
			}
			$text .= '}}' . "\n";
		}
		$text .= $this->mFreeText;
		return $text;
	}
}

class DTImportCSV extends SpecialPage {

	/**
	 * Constructor
	 */
	public function DTImportCSV() {
		parent::__construct( 'ImportCSV' );
	}

	function execute( $query ) {
		$this->setHeaders();

		if ( ! $this->getUser()->isAllowed( 'datatransferimport' ) ) {
			throw new PermissionsError( 'datatransferimport' );
		}

		$out = $this->getOutput();
		$request = $this->getRequest();
		if ( $request->getCheck( 'import_file' ) ) {
			$text = DTUtils::printImportingMessage();
			$uploadResult = ImportStreamSource::newFromUpload( "file_name" );
			if ( !$uploadResult->isOK() ) {
				$uploadError = $out->parse( $uploadResult->getWikiText() );
				$text .= $uploadError;
				$out->addHTML( $text );
				return;
			}

			$source = $uploadResult->value;

			$encoding = $request->getVal( 'encoding' );
			$pages = array();
			$error_msg = self::getCSVData( $source->mHandle, $encoding, $pages );
			if ( ! is_null( $error_msg ) ) {
				$text .= $error_msg;
				$out->addHTML( $text );
				return;
			}

			$importSummary = $request->getVal( 'import_summary' );
			$forPagesThatExist = $request->getVal( 'pagesThatExist' );
			$text .= self::modifyPages( $pages, $importSummary, $forPagesThatExist );
		} else {
			$formText = DTUtils::printFileSelector( 'CSV' );
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
			$formText .= DTUtils::printImportSummaryInput( 'CSV' );
			$formText .= DTUtils::printSubmitButton();
			$text = "\t" . Xml::tags( 'form',
				array(
					'enctype' => 'multipart/form-data',
					'action' => '',
					'method' => 'post'
				), $formText ) . "\n";
		}

		$out->addHTML( $text );
	}

	static function getCSVData( $csv_file, $encoding, &$pages ) {
		if ( is_null( $csv_file ) ) {
			return wfMessage( 'emptyfile' )->text();
		}

		$table = array();
		if ( $encoding == 'utf16' ) {
			// Change encoding to UTF-8.
			// Starting with PHP 5.3 we could use str_getcsv(),
			// which would save the tempfile hassle.
			$tempfile = tmpfile();
			$csv_string = '';
			while ( !feof( $csv_file ) ) {
				$csv_string .= fgets( $csv_file, 65535 );
 			}
			fwrite( $tempfile, iconv( 'UTF-16', 'UTF-8', $csv_string ) );
			fseek( $tempfile, 0 );
			while ( $line = fgetcsv( $tempfile ) ) {
				array_push( $table, $line );
			}
			fclose( $tempfile );
		} else {
			while ( $line = fgetcsv( $csv_file ) ) {
				array_push( $table, $line );
			}
		}
		fclose( $csv_file );

		// Get rid of the "byte order mark", if it's there - this is
		// a three-character string sometimes put at the beginning
		// of files to indicate its encoding.
		// Code copied from:
		// http://www.dotvoid.com/2010/04/detecting-utf-bom-byte-order-mark/
		$byteOrderMark = pack( "CCC", 0xef, 0xbb, 0xbf );
		if ( 0 == strncmp( $table[0][0], $byteOrderMark, 3 ) ) {
			$table[0][0] = substr( $table[0][0], 3 );
			// If there were quotation marks around this value,
			// they didn't get removed, so remove them now.
			$table[0][0] = trim( $table[0][0], '"' );
		}

		// check header line to make sure every term is in the
		// correct format
		$title_label = wfMessage( 'dt_xml_title' )->inContentLanguage()->text();
		$free_text_label = wfMessage( 'dt_xml_freetext' )->inContentLanguage()->text();
		foreach ( $table[0] as $i => $header_val ) {
			if ( $header_val !== $title_label && $header_val !== $free_text_label &&
				! preg_match( '/^[^\[\]]+\[[^\[\]]+]$/', $header_val ) ) {
				$error_msg = wfMessage( 'dt_importcsv_badheader', $i, $header_val, $title_label, $free_text_label )->text();
				return $error_msg;
			}
		}
		foreach ( $table as $i => $line ) {
			if ( $i == 0 ) continue;
			$page = new DTPage();
			foreach ( $line as $j => $val ) {
				if ( $val == '' ) continue;
				if ( $table[0][$j] == $title_label ) {
					$page->setName( $val );
				} elseif ( $table[0][$j] == $free_text_label ) {
					$page->setFreeText( $val );
				} else {
					list( $template_name, $field_name ) = explode( '[', str_replace( ']', '', $table[0][$j] ) );
					$page->addTemplateField( $template_name, $field_name, $val );
				}
			}
			$pages[] = $page;
		}

		return null;
	}

	function modifyPages( $pages, $editSummary, $forPagesThatExist ) {
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
		// MW 1.21+
		if ( class_exists( 'JobQueueGroup' ) ) {
			JobQueueGroup::singleton()->push( $jobs );
		} else {
			Job::batchInsert( $jobs );
		}
		$text .= $this->msg( 'dt_import_success' )->numParams( count( $jobs ) )->params( 'CSV' )->parseAsBlock();

		return $text;
	}
}
