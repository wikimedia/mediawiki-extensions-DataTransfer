<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Revision\SlotRecord;

/**
 * Background job to import a page into the wiki, for use by Data Transfer
 *
 * @author Yaron Koren
 */
class DTImportJob extends Job {

	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'dtImport', $title, $params, $id );
	}

	/**
	 * Run a dtImport job
	 * @return bool success
	 */
	function run() {
		if ( $this->title === null ) {
			$this->error = "dtImport: Invalid title";
			return false;
		}

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->title );
		if ( !$wikiPage ) {
			$this->error = 'dtImport: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$for_pages_that_exist = $this->params['for_pages_that_exist'];
		if ( $for_pages_that_exist == 'skip' && $this->title->exists() ) {
			return true;
		}

		if ( isset( $this->params['slot'] ) ) {
			$slotRole = $this->params['slot'];
			$slotRoleRegistry = MediaWikiServices::getInstance()->getSlotRoleRegistry();

			if ( !$slotRoleRegistry->isKnownRole( $slotRole ) ) {
				$this->error = 'dtImport: Slot role not found "' . $slotRole . '"';
				return false;
			}
		} else {
			$slotRole = SlotRecord::MAIN;
		}

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->params['user_id'] );
		// Exit if this is a blocked user (must have been blocked very recently, i.e.
		// after the job was created).
		if ( !$user->definitelyCan( 'edit', $this->title ) ) {
			return true;
		}

		$text = $this->params['text'];
		if ( $this->title->exists() ) {
			if ( $for_pages_that_exist == 'append' ) {
				$pageContent = $wikiPage->getContent();
				if ( $pageContent !== null && $pageContent instanceof TextContent ) {
					$text = $pageContent->getText() . "\n" . $text;
				}
			} elseif ( $for_pages_that_exist == 'merge' ) {
				$existingPageStructure = DTPageStructure::newFromTitle( $this->title, false );
				$newPageStructure = new DTPageStructure;
				$newPageStructure->parsePageContents( $text );
				$existingPageStructure->mergeInPageStructure( $newPageStructure );
				$text = $existingPageStructure->toWikitext();
			}
			// otherwise, $for_pages_that_exist == 'overwrite'
		}
		$edit_summary = CommentStoreComment::newUnsavedComment( $this->params['edit_summary'] );
		$new_content = new WikitextContent( $text );
		// It's strange that saveRevision() doesn't
		// automatically attach the 'bot' flag when the user
		// is a bot...
		if ( $user->isAllowed( 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		} else {
			$flags = 0;
		}

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( $slotRole, $new_content );

		if ( $slotRole !== SlotRecord::MAIN && !$wikiPage->exists() ) {
			// The 'main' content slot must be set when creating a new page
			$emptyContent = ContentHandler::makeContent( "", $wikiPage->getTitle() );
			$pageUpdater->setContent( SlotRecord::MAIN, $emptyContent );
		}

		$pageUpdater->saveRevision( $edit_summary, $flags );

		return true;
	}
}
