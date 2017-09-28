<?php include("DT_ImportCSVUtils.php"); ?>
<?php

class ApiUploadCSV extends ApiBase {

	public function execute() {
		
		//User Permission
		if ( ! $this->getUser()->isAllowed( 'datatransferimport' ) ) {
			error_log("utente non autorizzato");
			error_log(print_r($this->getRequest(),true));
			throw new PermissionsError( 'datatransferimport' );
		}
		
		//Call Import CSV method 
		$utilsImportCsv = new DT_ImportCSVUtils();
		$message = $utilsImportCsv->importCsvFile();

		//Get Results
		$result = $this->getResult();
		$result->addValue( $this->getModuleName(), 'result', print_r($message,true));
		
		return true;
	}

	public function getAllowedParams() {
		return array(
			'object' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'encoding' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'importType' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}

	public function getExamplesMessages() {
		return array(
				'api.php?action=uploadcsv&object=ImportCSV&importType=overwrite&encoding=UTF8&format=json'
				=> 'apihelp-uploadcsv-example-1'
		);
	}
}
