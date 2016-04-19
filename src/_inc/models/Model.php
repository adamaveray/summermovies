<?php
abstract class Model {
	public function __construct(array $values){
		foreach($values as $key => $value){
			$this->{$key}	= $value;
		}
	}
}
