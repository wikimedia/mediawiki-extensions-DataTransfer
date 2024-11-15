<?php
/**
 * Class for parsing XML representing wiki pages and their template calls
 *
 * @author Yaron Koren
 * @ingroup DataTransfer
 */

class DTXMLParser {
	private $mDebug = false;
	private $mSource = null;
	private $mCurFieldName = null;
	private $mCurFieldValue = '';
	private $mCurTemplate = null;
	private $mCurPage = null; // new DTWikiPage();
	private $mPages = [];

	function __construct( $source ) {
		$this->mSource = $source;
	}

	function throwXMLerror( $text ) {
		print htmlspecialchars( $text );
	}

	function doParse() {
		$parser = xml_parser_create( "UTF-8" );

		# case folding violates XML standard, turn it off
		xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, false );

		xml_set_object( $parser, $this );
		xml_set_element_handler( $parser, "in_start", "" );

		$offset = 0; // for context extraction on error reporting
		do {
			$chunk = $this->mSource->readChunk();
			if ( !xml_parse( $parser, $chunk, $this->mSource->atEnd() ) ) {
				wfDebug( "WikiImporter::doImport encountered XML parsing error\n" );
				// return new WikiXmlError( $parser, wfMessage( 'import-parse-failure' )->escaped(), $chunk, $offset );
			}
			$offset += strlen( $chunk );
		} while ( $chunk !== false && !$this->mSource->atEnd() );

		xml_parser_free( $parser );
	}

	function donothing( $parser, $x, $y = "" ) {
		// Do nothing.
	}

	function in_start( $parser, $name, $attribs ) {
		$pages_str = str_replace( ' ', '_', wfMessage( 'dt_xml_pages' )->inContentLanguage()->text() );
		if ( $name != $pages_str ) {
			$this->throwXMLerror( "Expected '$pages_str', got '$name'" );
		}
		xml_set_element_handler( $parser, "in_pages", "out_pages" );
	}

	function in_pages( $parser, $name, $attribs ) {
		$page_str = str_replace( ' ', '_', wfMessage( 'dt_xml_page' )->inContentLanguage()->text() );
		$slot_str = str_replace( ' ', '_', wfMessage( 'dt_xml_slot' )->inContentLanguage()->text() );
		if ( $name == $page_str ) {
			$title_str = str_replace( ' ', '_', wfMessage( 'dt_xml_title' )->inContentLanguage()->text() );
			if ( array_key_exists( $title_str, $attribs ) ) {
				$this->mCurPage = new DTWikiPage( $attribs[$title_str] );
				xml_set_element_handler( $parser, "in_page", "out_page" );
			} else {
				$this->throwXMLerror( "'$title_str' attribute missing for page" );
				return;
			}

			if ( array_key_exists( $slot_str, $attribs ) ) {
				$this->mCurPage->setSlot( $attribs[$slot_str] );
			}
		} else {
			$this->throwXMLerror( "Expected <$page_str>, got <$name>" );
		}

		return;
	}

	function out_pages( $parser, $name ) {
		xml_set_element_handler( $parser, "donothing", "donothing" );
	}

	function in_category( $parser, $name, $attribs ) {
		$page_str = str_replace( ' ', '_', wfMessage( 'dt_xml_page' )->inContentLanguage()->text() );
		if ( $name == $page_str ) {
			if ( array_key_exists( $title_str, $attribs ) ) {
				$this->mCurPage = new DTWikiPage( $attribs[$title_str] );
				xml_set_element_handler( $parser, "in_page", "out_page" );
			} else {
				$this->throwXMLerror( "'$title_str' attribute missing for page" );
				return;
			}
		} else {
			$this->throwXMLerror( "Expected <$page_str>, got <$name>" );
			return;
		}
	}

	function out_category( $parser, $name ) {
		if ( $name != "category" ) {
			$this->throwXMLerror( "Expected </category>, got </$name>" );
			return;
		}
		xml_set_element_handler( $parser, "donothing", "donothing" );
	}

	function in_page( $parser, $name, $attribs ) {
		$template_str = str_replace( ' ', '_', wfMessage( 'dt_xml_template' )->inContentLanguage()->text() );
		$name_str = str_replace( ' ', '_', wfMessage( 'dt_xml_name' )->inContentLanguage()->text() );
		$free_text_str = str_replace( ' ', '_', wfMessage( 'dt_xml_freetext' )->inContentLanguage()->text() );
		if ( $name == $template_str ) {
			if ( array_key_exists( $name_str, $attribs ) ) {
				$this->mCurTemplate = new DTWikiTemplate( $attribs[$name_str] );
				xml_set_element_handler( $parser, "in_template", "out_template" );
			} else {
				$this->throwXMLerror( "'$name_str' attribute missing for template" );
				return;
			}
		} elseif ( $name == $free_text_str ) {
			xml_set_element_handler( $parser, "in_freetext", "out_freetext" );
			xml_set_character_data_handler( $parser, "freetext_value" );
		} else {
			$this->throwXMLerror( "Expected <$template_str>, got <$name>" );
			return;
		}
	}

	function out_page( $parser, $name ) {
		$page_str = str_replace( ' ', '_', wfMessage( 'dt_xml_page' )->inContentLanguage()->text() );
		if ( $name != $page_str ) {
			$this->throwXMLerror( "Expected </$page_str>, got </$name>" );
			return;
		}
		$this->mPages[] = $this->mCurPage;
		xml_set_element_handler( $parser, "in_pages", "out_pages" );
	}

	function in_template( $parser, $name, $attribs ) {
		$field_str = str_replace( ' ', '_', wfMessage( 'dt_xml_field' )->inContentLanguage()->text() );
		if ( $name == $field_str ) {
			$name_str = str_replace( ' ', '_', wfMessage( 'dt_xml_name' )->inContentLanguage()->text() );
			if ( array_key_exists( $name_str, $attribs ) ) {
				$this->mCurFieldName = $attribs[$name_str];
				xml_set_element_handler( $parser, "in_field", "out_field" );
				xml_set_character_data_handler( $parser, "field_value" );
			} else {
				$this->throwXMLerror( "'$name_str' attribute missing for field" );
				return;
			}
		} else {
			$this->throwXMLerror( "Expected <$field_str>, got <$name>" );
			return;
		}
	}

	function out_template( $parser, $name ) {
		$template_str = str_replace( ' ', '_', wfMessage( 'dt_xml_template' )->inContentLanguage()->text() );
		if ( $name != $template_str ) {
			$this->throwXMLerror( "Expected </$template_str>, got </$name>" );
			return;
		}
		$this->mCurPage->addTemplate( $this->mCurTemplate );
		xml_set_element_handler( $parser, "in_page", "out_page" );
	}

	function in_field( $parser, $name, $attribs ) {
		// xml_set_element_handler( $parser, "donothing", "donothing" );
	}

	function out_field( $parser, $name ) {
		$field_str = str_replace( ' ', '_', wfMessage( 'dt_xml_field' )->inContentLanguage()->text() );
		if ( $name == $field_str ) {
			$this->mCurTemplate->addField( $this->mCurFieldName, $this->mCurFieldValue );
			$this->mCurFieldValue = '';
		} else {
			$this->throwXMLerror( "Expected </$field_str>, got </$name>" );
			return;
		}
		xml_set_element_handler( $parser, "in_template", "out_template" );
	}

	function field_value( $parser, $data ) {
		$this->mCurFieldValue .= $data;
	}

	function in_freetext( $parser, $name, $attribs ) {
		// xml_set_element_handler( $parser, "donothing", "donothing" );
	}

	function out_freetext( $parser, $name ) {
		xml_set_element_handler( $parser, "in_page", "out_page" );
	}

	function freetext_value( $parser, $data ) {
		$this->mCurPage->addFreeText( $data );
	}

	public function getPages() {
		return $this->mPages;
	}
}
