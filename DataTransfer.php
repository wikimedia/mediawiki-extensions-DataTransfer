<?php
/**
 * Global functions and constants for the Data Transfer extension.
 *
 * @author Yaron Koren
 * @ingroup DataTransfer
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

define( 'DATA_TRANSFER_VERSION', '1.0.1' );

$wgExtensionCredits['specialpage'][] = array(
	'path'           => __FILE__,
	'name'           => 'Data Transfer',
	'version'        => DATA_TRANSFER_VERSION,
	'author'         => 'Yaron Koren',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Data_Transfer',
	'descriptionmsg' => 'datatransfer-desc',
	'license-name'   => 'GPL-2.0-or-later'
);

###
# This is the path to your installation of DataTransfer as
# seen on your local filesystem. Used against some PHP file path
# issues.
##
$dtgIP = dirname( __FILE__ );
##

$wgResourceModules[ 'ext.datatransfer' ] = array(
    'localBasePath' => $dtgIP,
    'styles' => 'styles/DataTransfer.css',
    'position' => 'bottom',
);

// Register all special pages and other classes.
$wgAutoloadClasses['DTPageStructure'] = $dtgIP . '/includes/DT_PageStructure.php';
$wgAutoloadClasses['DTPageComponent'] = $dtgIP . '/includes/DT_PageComponent.php';
$wgAutoloadClasses['DTPage'] = $dtgIP . '/includes/DT_Page.php';
$wgAutoloadClasses['DTUtils'] = $dtgIP . '/includes/DT_Utils.php';
$wgSpecialPages['ViewXML'] = 'DTViewXML';
$wgAutoloadClasses['DTViewXML'] = $dtgIP . '/specials/DT_ViewXML.php';
$wgSpecialPages['ImportXML'] = 'DTImportXML';
$wgAutoloadClasses['DTImportXML'] = $dtgIP . '/specials/DT_ImportXML.php';
$wgSpecialPages['ImportCSV'] = 'DTImportCSV';
$wgAutoloadClasses['DTImportCSV'] = $dtgIP . '/specials/DT_ImportCSV.php';
$wgSpecialPages['ImportSpreadsheet'] = 'DTImportSpreadsheet';
$wgAutoloadClasses['DTImportSpreadsheet'] = $dtgIP . '/specials/DT_ImportSpreadsheet.php';
$wgJobClasses['dtImport'] = 'DTImportJob';
$wgAutoloadClasses['DTImportJob'] = $dtgIP . '/includes/DT_ImportJob.php';
$wgAutoloadClasses['DTXMLParser'] = $dtgIP . '/includes/DT_XMLParser.php';
$wgAutoloadClasses['DTWikiTemplate'] = $dtgIP . '/includes/DT_WikiTemplate.php';
$wgAutoloadClasses['DTWikiPage'] = $dtgIP . '/includes/DT_WikiPage.php';
$wgAutoloadClasses['DTHooks'] = $dtgIP . '/includes/DT_Hooks.php';

$wgHooks['AdminLinks'][] = 'DTHooks::addToAdminLinks';

###
# Permission to import files
###
$wgGroupPermissions['sysop']['datatransferimport'] = true;
$wgAvailableRights[] = 'datatransferimport';

// Global settings
$wgDataTransferViewXMLParseFields = false;
$wgDataTransferViewXMLParseFreeText = true;

$wgMessagesDirs['DataTransfer'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['DataTransferAlias'] = $dtgIP . '/DataTransfer.i18n.alias.php';