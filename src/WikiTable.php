<?php

namespace GtwlSlurp;

class WikiTable {

	/**
	 * @var array
	 */
	private $headings;

	/**
	 * @var array
	 */
	private $rows;

	/**
	 * @var array
	 */
	private $config;

	/**
	 * @param string[] $headings
	 * @param array[] $rows
	 * @param array $config
	 *   string title (optional)
	 */
	public function __construct( $headings, $rows, $config ) {
		$this->headings = $headings;
		$this->rows = $rows;
		$this->config = $config;
	}

	public function __toString() {
		return $this->getTableStart() .
			$this->getTableHeadings() .
			$this->getTableRows() .
			$this->getTableEnd();
	}

	private function getTableStart() {
		$string = '{| class="wikitable" style="text-align: center;"' . "\n";
		if ( array_key_exists( 'title', $this->config ) ) {
			$string .= '|+ ' . $this->config['title'] . "\n";
		}
		return $string;
	}

	private function getTableHeadings() {
		$headings = '';
		foreach( $this->headings as $heading ) {
			$headings .= '! ' . $heading . "\n";
		}
		return $headings;
	}

	private function getTableRows() {
		$rowsString = '';
		foreach( $this->rows as $values ) {
			$rowsString .= "|-\n";
			foreach( $values as $value ) {
				$rowsString .= "| $value\n";
			}
		}
		return $rowsString;
	}

	private function getTableEnd() {
		return '|}';
	}

}
