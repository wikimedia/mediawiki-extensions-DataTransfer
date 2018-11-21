<?php
/**
 * Displays an interface to let the user export pages from the wiki in XML form
 *
 * @author Yaron Koren
 */

class DTViewXML extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct( $name = 'ViewXML' ) {
		parent::__construct( $name );
	}

	function execute( $query ) {
		$this->setHeaders();
		$this->doSpecialViewXML( $query );
	}

	static function getCategoriesList() {
		$dbr = wfGetDB( DB_SLAVE );
		$categorylinks = $dbr->tableName( 'categorylinks' );
		$res = $dbr->query( "SELECT DISTINCT cl_to FROM $categorylinks" );
		$categories = array();
		while ( $row = $dbr->fetchRow( $res ) ) {
			$categories[] = $row[0];
		}
		$dbr->freeResult( $res );
		sort( $categories );
		return $categories;
	}

	static function getNamespacesList() {
		$dbr = wfGetDB( DB_SLAVE );
		$page = $dbr->tableName( 'page' );
		$res = $dbr->query( "SELECT DISTINCT page_namespace FROM $page" );
		$namespaces = array();
		while ( $row = $dbr->fetchRow( $res ) ) {
			$namespaces[] = $row[0];
		}
		$dbr->freeResult( $res );
		return $namespaces;
	}

	/*
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based
	 * on SMW's SMWInlineQuery::includeSubcategories()
	 */
	static function getPagesForCategory( $top_category, $num_levels ) {
		if ( 0 == $num_levels ) return $top_category;

		$db = wfGetDB( DB_SLAVE );
		$fname = "getPagesForCategory";
		$categories = array( $top_category );
		$checkcategories = array( $top_category );
		$titles = array();
		for ( $level = $num_levels; $level > 0; $level-- ) {
			$newcategories = array();
			foreach ( $checkcategories as $category ) {
				$res = $db->select( // make the query
					array( 'categorylinks', 'page' ),
					array( 'page_id', 'page_title', 'page_namespace' ),
					array( 'cl_from = page_id',
						'cl_to = ' . $db->addQuotes( $category )
					),
					$fname
				);
				if ( $res ) {
					while ( $row = $db->fetchRow( $res ) ) {
						if ( array_key_exists( 'page_title', $row ) ) {
							$page_namespace = $row['page_namespace'];
							if ( $page_namespace == NS_CATEGORY ) {
								$new_category = $row[ 'page_title' ];
								if ( !in_array( $new_category, $categories ) ) {
									$newcategories[] = $new_category;
								}
							} else {
								$titles[] = Title::newFromID( $row['page_id'] );
							}
						}
					}
					$db->freeResult( $res );
				}
			}
			if ( count( $newcategories ) == 0 ) {
				return $titles;
			} else {
				$categories = array_merge( $categories, $newcategories );
			}
			$checkcategories = array_diff( $newcategories, array() );
		}
		return $titles;
	}

	static function getPagesForNamespace( $namespace ) {
		$dbr = wfGetDB( DB_SLAVE );
		$page = $dbr->tableName( 'page' );
		$res = $dbr->query( "SELECT page_id FROM $page WHERE page_namespace = '$namespace'" );
		$titles = array();
		while ( $row = $dbr->fetchRow( $res ) ) {
			$titles[] = Title::newFromID( $row[0] );
		}
		$dbr->freeResult( $res );
		return $titles;
	}

	/**
	 * Helper function for getXMLForPage()
	 */
	static function treeContainsElement( $tree, $element ) {
		// escape out if there's no tree (i.e., category)
		if ( $tree == null ) {
			return false;
		}

		foreach ( $tree as $node => $child_tree ) {
			if ( $node === $element ) {
				return true;
			} elseif ( count( $child_tree ) > 0 ) {
				if ( self::treeContainsElement( $child_tree, $element ) ) {
					return true;
				}
			}
		}
		// no match found
		return false;
	}


	static function getXMLForPage( $title, $simplified_format, $depth = 0 ) {
		if ( $depth > 5 ) { return ""; }

		$pageStructure = DTPageStructure::newFromTitle( $title );
		$text = $pageStructure->toXML( $simplified_format );

		// escape back the curly brackets that were escaped out at the beginning
		$text = str_replace( '&amp;#123;', '{', $text );
		$text = str_replace( '&amp;#125;', '}', $text );
		return $text;
	}

	function doSpecialViewXML() {
		global $wgContLang;

		$out = $this->getOutput();
		$request  = $this->getRequest();

		$namespace_labels = $wgContLang->getNamespaces();
		$category_label = $namespace_labels[NS_CATEGORY];
		$name_str = str_replace( ' ', '_', wfMessage( 'dt_xml_name' )->inContentLanguage()->text() );
		$namespace_str = str_replace( ' ', '_', wfMessage( 'dt_xml_namespace' )->text() );
		$pages_str = str_replace( ' ', '_', wfMessage( 'dt_xml_pages' )->inContentLanguage()->text() );

		$form_submitted = false;
		$cats = $request->getArray( 'categories' );
		$nses = $request->getArray( 'namespaces' );
		$requestedTitles = $request->getVal( 'titles' );
		if ( ! empty( $cats ) || ! empty( $nses ) || $requestedTitles != null ) {
			$form_submitted = true;
		}

		if ( $form_submitted ) {
			$out->disable();

			// Cancel output buffering and gzipping if set
			// This should provide safer streaming for pages with history
			wfResetOutputBuffers();
			header( "Content-type: application/xml; charset=utf-8" );

			$simplified_format = $request->getVal( 'simplified_format' );
			$text = "<$pages_str>";
			if ( $cats ) {
				foreach ( $cats as $cat => $val ) {
					if ( $simplified_format )
						$text .= '<' . str_replace( ' ', '_', $cat ) . ">\n";
					else
						$text .= "<$category_label $name_str=\"$cat\">\n";
					$titles = self::getPagesForCategory( $cat, 10 );
					foreach ( $titles as $title ) {
						$text .= self::getXMLForPage( $title, $simplified_format );
					}
					if ( $simplified_format ) {
						$text .= '</' . str_replace( ' ', '_', $cat ) . ">\n";
					} else {
						$text .= "</$category_label>\n";
					}
				}
			}

			if ( $nses ) {
				foreach ( $nses as $ns => $val ) {
			 		if ( $ns == 0 ) {
						$ns_name = "Main";
					} else {
						$ns_name = MWNamespace::getCanonicalName( $ns );
					}
					if ( $simplified_format ) {
						$text .= '<' . str_replace( ' ', '_', $ns_name ) . ">\n";
					} else {
						$text .= "<$namespace_str $name_str=\"$ns_name\">\n";
					}
					$titles = self::getPagesForNamespace( $ns );
					foreach ( $titles as $title ) {
						$text .= self::getXMLForPage( $title, $simplified_format );
					}
					if ( $simplified_format )
						$text .= '</' . str_replace( ' ', '_', $ns_name ) . ">\n";
					else
						$text .= "</$namespace_str>\n";
				}
			}

			// The user can specify a set of page names to view
			// the XML of, using a "titles=" parameter, separated
			// by "|", in the manner of the MediaWiki API.
			// Hm... perhaps all of Special:ViewXML should just
			// be replaced by an API action?
			if ( $requestedTitles ) {
				$pageNames = explode( '|', $requestedTitles );
				foreach ( $pageNames as $pageName ) {
					$title = Title::newFromText( $pageName );
					$text .= self::getXMLForPage( $title, $simplified_format );
				}
			}

			$text .= "</$pages_str>";
			print $text;
		} else {
			// set 'title' as hidden field, in case there's no URL niceness
			global $wgContLang;
			$mw_namespace_labels = $wgContLang->getNamespaces();
			$special_namespace = $mw_namespace_labels[NS_SPECIAL];
			$text = <<<END
	<form action="" method="get">
	<input type="hidden" name="title" value="$special_namespace:ViewXML">

END;
			$text .= "<p>" . wfMessage( 'dt_viewxml_docu' )->text() . "</p>\n";
			$text .= "<h2>" . wfMessage( 'dt_viewxml_categories' )->text() . "</h2>\n";
			$categories = self::getCategoriesList();
			foreach ( $categories as $category ) {
				$text .= Html::input( "categories[$category]", null, 'checkbox' );
				$title = Title::makeTitle( NS_CATEGORY, $category );
				$link = Linker::link( $title, htmlspecialchars( $title->getText() ) );
				$text .= " $link<br />\n";
			}
			$text .= "<h2>" . wfMessage( 'dt_viewxml_namespaces' )->text() . "</h2>\n";
			$namespaces = self::getNamespacesList();
			foreach ( $namespaces as $nsCode ) {
				if ( $nsCode === '0' ) {
					$nsName = wfMessage( 'blanknamespace' )->escaped();
				} else {
					$nsName = htmlspecialchars( $wgContLang->getFormattedNsText( $nsCode ) );
					if ( $nsName === '' ) continue;
				}
				$text .= Html::input( "namespaces[$nsCode]", null, 'checkbox' );
				$text .= ' ' . str_replace( '_', ' ', $nsName ) . "<br />\n";
			}
			$text .= "<br /><p><label><input type=\"checkbox\" name=\"simplified_format\" /> " . wfMessage( 'dt_viewxml_simplifiedformat' )->text() . "</label></p>\n";
			$text .= "<input type=\"submit\" value=\"" . wfMessage( 'viewxml' )->text() . "\">\n";
			$text .= "</form>\n";

			$out->addHTML( $text );
		}
	}
}
