<?php

namespace GtwlSlurp;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Stichoza\GoogleTranslate\TranslateClient;

class Slurper{

	private $mainArticle = 'Wikipedia:Umfragen/Technische_Wünsche_2015';
	private $domain = 'de.wikipedia.org';
	/**
	 * @var MediawikiFactory
	 */
	private $site;

	/**
	 * @var TranslateClient|null
	 */
	private $translate;

	/**
	 * @var int
	 */
	private $highlight = 0;

	public function __construct( $config ) {
		if( array_key_exists( 'translate', $config ) ) {
			$this->translate = new TranslateClient( 'de', $config['translate'] );
		}
		if( array_key_exists( 'highlight', $config ) ) {
			$this->highlight = $config['highlight'];
		}

		$api = new MediawikiApi( 'https://' . $this->domain . '/w/api.php' );
		$mw = new MediawikiFactory( $api );
		$this->site = $mw;
	}

	private function getSubArticle( $subPage ) {
		return $this->mainArticle . '/' . $subPage;
	}

	public function run() {
		$pageTitles = $this->getAllSubPageTitles();

		$data = array();

		foreach( $pageTitles as $pageTitle ) {
			foreach( $this->getDataForPage( $pageTitle ) as $wish => $wishData ) {
				$data[$wish] = $wishData;
			}
		}

		return $this->output( $data );
	}

	private function output( $result ) {
		asort( $result );
		$result = array_reverse( $result, true );

		$tableRows = array();

		$total = 0;
		$wishCounter = 0;
		foreach( $result as $wishName => $data ) {
			$wishCounter += 1;
			if( $this->translate ) {
				$wishName = $this->translate->translate( $wishName );
			}
			$link = '[[' . $data['link'] . '|' . $wishName . ']]';
			$voteCount = $data['votes'];
			if( $this->highlight >= $wishCounter ) {
				$link = "'''" . $link . "'''";
				$voteCount = "'''" . $voteCount . "'''";
			}
			$tableRows[] = array( $voteCount, $link );
			$total += $data['votes'];
		}

		$table = new WikiTable(
			array( "Votes ($total)", 'Wish(' . count( $tableRows ) . ')' ),
			$tableRows,
			array( 'title' => 'GTWL Votes' )
		);

		return $table;
	}

	private function getDataForPage( $pageTitle ) {
		$page = $this->site->newPageGetter()->getFromTitle( $pageTitle );
		$text = $page->getRevisions()->getLatest()->getContent()->getData();

		preg_match_all( '/====([^(====)]+)====/i', $text, $headings );
		$split = preg_split( '/====[^(====)]+====/i', $text );
		array_shift( $split );
		$headings = $headings[1];

		if( count( $headings ) !== count( $split ) ){
			echo "Something wrong on $pageTitle\n";
			echo "Got a different number of headings and sections\n";
			die();
		}

		$data = array();
		foreach( $split as $key => $wishSection ) {
			$wishName = trim($headings[$key]);
			$wishSectionSplit = preg_split( '/\n;Unterstützung/i', $wishSection );
			$voteSection = $wishSectionSplit[1];

			$data[$wishName]['users'] = array();
			$data[$wishName]['votes'] = 0;
			$data[$wishName]['link'] = $pageTitle . '#' . $wishName;

			foreach( explode( "\n", $voteSection ) as $line ) {
				if(
					// Require the {{pro}} template
					preg_match( $this->getProTemplateRegex(), $line ) &&
					// Require a user / user talk link
					preg_match( $this->getUserLinkRegex(), $line, $userMatches )
				) {
					$data[$wishName]['users'][] = $userMatches[2];
					$data[$wishName]['votes'] += 1;
				}
			}
		}

		return $data;
	}

	private function getProTemplateRegex() {
		$namespaces = array( 'Template', 'Vorlage' );
		$titles = array( 'Pro', 'Dafür' );

		$nsPart = '((' . implode( '|', $namespaces ) . '):)?';
		$titlePart = '(' . implode( '|', $titles ) . ')';

		return '/\{\{' . $nsPart . $titlePart . '\}\}/i';
	}

	private function getUserLinkRegex() {
		$namespaces = array( 'User', 'Benutzer', 'Benutzerin', 'Benutzer Diskussion', 'Benutzerin Diskussion', 'User Talk' );

		$nsPart = '(' . implode( '|', $namespaces ) . '):';

		return '/\[\[' . $nsPart . '([^\]\|]+)' . '(\]\]|\|)/i';
	}

	private function getAllSubPageTitles() {
		$allPage = $this->site->newPageGetter()->getFromTitle( $this->getSubArticle( 'Alle' ) );
		$allText = $allPage->getRevisions()->getLatest()->getContent()->getData();

		preg_match_all( '/\{\{\.\.\/(.+)\}\}/i', $allText, $matches );

		$pageSubTitles = array();
		foreach( $matches[1] as $match ) {
			$pageSubTitles[] = $this->getSubArticle( trim( $match ) );
		}

		return $pageSubTitles;
	}

}
