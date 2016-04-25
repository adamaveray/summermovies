<?php
require_once(__DIR__.'/Model.php');

class Movie extends Model {
	public $id;
	/** @var Venue $venue */
	public $venue;
	/** @var DateTimeInterface $date */
	public $date;
	public $title;
	public $year;
	public $synopsis;
	public $rating;
	public $url;
	public $poster;
	public $posterSource;

	public $cost;

	public $isPending	= false;

	public function __construct(array $values){
		parent::__construct($values);

		$this->id	= $this->generateID();

		if(substr($this->title, 0, 1) === '(' && substr($this->title, -1) === ')'){
			// Unconfirmed
			$this->isPending	= true;
			$this->title		= substr($this->title, 1, -1);
		}
	}

	private function generateID(){
		return slugify($this->date->format('M-j').'-'.$this->title.'-'.$this->year.'-'.$this->venue->id);
	}
}
