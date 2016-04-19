<?php
require_once(__DIR__.'/Model.php');

class Venue extends Model {
	public $id;
	public $name;
	public $location;
	public $borough;
	public $image;
	public $website;
	public $facebook;
	public $twitter;
	public $foursquare;
	public $lat;
	public $lng;
	public $coords;

	public function __construct(array $values){
		parent::__construct($values);

		$this->coords	= $this->lat.','.$this->lng;
	}
}
