<?php

/**
 * German Technical Wish List Vote Slurper
 *
 *  - Votes are only counted if the vote line contains the {{pro}} template (or a redirect to it)
 *    as well as a link to a user or user talk page.
 *  - All wish lists are retrieve from Wikipedia:Umfragen/Technische_Wünsche_2015/Alle
 *
 *  - Each wish list MUST then have each wish as a level 4 heading '==== Wish Name ===='
 *  - Each wish MUST then have a vote section ';Unterstützung'
 *  - Each wish list is expected to have a header before the first level 4 heading that is discarded
 *
 * @licence GPLv2+
 * @author Addshore
 *
 * @todo output as a wikitable or something like that?
 * @todo CLI option to translate to english?
 */

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Stichoza\GoogleTranslate\TranslateClient;

// Loading

require_once( __DIR__ . '/vendor/autoload.php' );

// Config

$article = 'Wikipedia:Umfragen/Technische_Wünsche_2015/Alle';

// Runtime

$slurper = new Slurper( array(
	// TODO cli var?
	// Note: Uncomment to translate the output to english! :D
	//'translate' => 'en',
) );
$slurper->run();

// Class

class Slurper{

	private $mainArticle = 'Wikipedia:Umfragen/Technische_Wünsche_2015';
	private $domain = 'de.wikipedia.org';
	/**
	 * @var MediawikiFactory
	 */
	private $site;

	/**
	 * @var TranslateClient
	 */
	private $translate;

	public function __construct( $config ) {
		if( array_key_exists( 'translate', $config ) ) {
			$this->translate = new TranslateClient( 'de', $config['translate'] );
		}

		$api = new MediawikiApi( 'https://' . $this->domain . '/w/api.php' );
		$mw = new MediawikiFactory( $api );
		$this->site = $mw;
	}

	private function getSubArticle( $subPage ) {
		return $this->mainArticle . '/' . $subPage;
	}

	public function run() {
		echo "Running\n";
		$pageTitles = $this->getAllSubPageTitles();

		$results = array();

		foreach( $pageTitles as $pageTitle ) {
			foreach( $this->getVotesForPage( $pageTitle ) as $wish => $voters ) {
				$results[$wish] = count( $voters );
			}
		}

		$this->output( $results );
	}

	private function output( $result ) {
		asort( $result );
		$result = array_reverse( $result, true );

		$total = 0;
		foreach( $result as $wishName => $voters ) {
			if( $this->translate ) {
				//$wishName = $this->translate->translate( $wishName );
			}
			echo "$voters - $wishName\n";
			$total += $voters;
		}
		echo "Total Votes: $total\n";
	}

	private function getVotesForPage( $pageTitle ) {
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

		$votes = array();
		foreach( $split as $key => $wishSection ) {
			$wishName = $headings[$key];
			$wishSectionSplit = preg_split( '/\n;Unterstützung/i', $wishSection );
			$voteSection = $wishSectionSplit[1];
			foreach( explode( "\n", $voteSection ) as $line ) {
				if(
					// Require the {{pro}} template
					preg_match( $this->getProTemplateRegex(), $line ) &&
					// Require a user / user talk link
					preg_match( $this->getUserLinkRegex(), $line, $userMatches )
				) {
					$votes[trim($wishName)][] = $userMatches[2];
				}
			}
		}

		return $votes;
	}

	private function getProTemplateRegex() {
		$namespaces = array( 'Template', 'Vorlage' );
		$titles = array( 'Pro', 'Dafür' );

		$nsPart = '((' . implode( '|', $namespaces ) . '):)?';
		$titlePart = '(' . implode( '|', $titles ) . ')';

		return '/\{\{' . $nsPart . $titlePart . '\}\}/i';
	}

	private function getUserLinkRegex() {
		$namespaces = array( 'User', 'Benutzer', 'Benutzer Diskussion', 'User Talk' );

		$nsPart = '(' . implode( '|', $namespaces ) . '):';

		return '/\[\[' . $nsPart . '([^\]\|]+)' . '(\]\]|\|)/i';
	}

	private function getAllSubPageTitles() {
		$allPage = $this->site->newPageGetter()->getFromTitle( $this->getSubArticle( 'Alle' ) );
		$allText = $allPage->getRevisions()->getLatest()->getContent()->getData();

		preg_match_all( '/\{\{\.\.\/(.+)\}\}/i', $allText, $matches );

		$pageSubTitles = array();
		foreach( $matches[1] as $match ) {
			$pageSubTitles[] = $this->getSubArticle( $match );
		}

		return $pageSubTitles;
	}

}
