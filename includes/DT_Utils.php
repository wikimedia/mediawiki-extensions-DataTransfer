<?php

/**
 * Utility functions for the Data Transfer extension.
 *
 * @author Yaron Koren
 */
class DTUtils {

	static function printImportingMessage() {
		return "\t" . Html::element( 'p', null, wfMessage( 'dt_import_importing' )->text() ) . "\n";
	}

	static function printFileSelector( $fileType ) {
		$text = "\n\t" . Html::element( 'p', null, wfMessage( 'dt_import_selectfile', $fileType )->text() ) . "\n";

		// Unfortunately, the PHP version of this widget contains no
		// custom formatting - it's identical to the browser's
		// standard file input. This may or may not ever get fixed.
		$text .= new OOUI\SelectFileInputWidget( [
			'name' => 'file_name'
		] );
		$text .= "\t" . '<hr style="margin: 10px 0 10px 0" />' . "\n";
		return $text;
	}

	static function printImportRadioButton( $option, $optionMsg, $isChecked = false ) {
		$radioButtonAttrs = [];
		if ( $isChecked ) {
			$radioButtonAttrs['checked'] = true;
		}
		$text = "\t" . Html::input( 'pagesThatExist', $option, 'radio', $radioButtonAttrs ) .
			"\t" . wfMessage( $optionMsg )->escaped() . "<br />" . "\n";
		$text = Html::rawElement( 'label', null, $text ) . "\n";
		return $text;
	}

	static function printExistingPagesHandling() {
		$text = "\t" . Html::element( 'p', null, wfMessage( 'dt_import_forexisting' )->text() ) . "\n";
		$existingPagesText =
			self::printImportRadioButton( 'overwrite', 'dt_import_overwriteexisting', true ) .
			self::printImportRadioButton( 'merge', 'dt_import_mergeintoexisting' ) .
			self::printImportRadioButton( 'skip', 'dt_import_skipexisting' ) .
			self::printImportRadioButton( 'append', 'dt_import_appendtoexisting' );
		$text .= "\t" . Html::rawElement( 'p', null, $existingPagesText ) . "\n";
		$text .= "\t" . '<hr style="margin: 10px 0 10px 0" />' . "\n";
		return $text;
	}

	static function printImportSummaryInput( $fileType ) {
		$importSummaryText = "\t" . Html::input(
			'import_summary',
			wfMessage( 'dt_import_editsummary', $fileType )->inContentLanguage()->text(),
			'text',
			[
				'id' => 'wpSummary', // ID is necessary for CSS formatting
				'class' => 'mw-summary',
			]
		) . "\n";
		return "\t" . Html::rawElement( 'p', null,
			wfMessage( 'dt_import_summarydesc' )->escaped() . "\n" .
			$importSummaryText ) . "\n";
	}

	static function printSubmitButton( $buttonMsg = null ) {
		if ( $buttonMsg == null ) {
			$buttonMsg = 'import-interwiki-submit';
		}
		$formSubmitButton = new OOUI\ButtonInputWidget( [
			'label' => wfMessage( $buttonMsg )->text(),
			'type' => 'submit',
			'name' => 'import_file',
			'id' => 'import_file',
			'flags' => [ 'primary', 'progressive' ]
		] );
		return "\t" . Html::rawElement( 'p', null, $formSubmitButton ) . "\n";
	}
}
