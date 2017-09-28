<?php include("DT_ImportCSV.php"); 
?>

<?php

/**
 * Lets the user import a CSV file with api into wiki pages
 *
 * @author Francesco Maria Sprotetto
 */

class DT_ImportCSVUtils extends DTImportCSV { 

	//Call protected Method of class DTImportCSV
	public function importCsvFile() {
		$dtiImportCsv = new DTImportCSV();

		$text = $dtiImportCsv->importFromUploadAndModifyPages();

		return $text;
	}
}