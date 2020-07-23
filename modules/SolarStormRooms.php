<?php

declare(strict_types=1);

class SolarStormRooms extends APP_GameClass {

	/** @var array */
	private $rooms;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->load();
	}

	public function generateRooms() {
		$rooms = [1, 2, 3, 4, 5, 6, 7, 8];
		shuffle($rooms);
		// Position 4 (center) is always room 0 (Energy Core)
		array_splice($rooms, 4, 0, 0);
		foreach ($rooms as $position => $room) {
			$damage = rand(0,2);
			$sql = "INSERT INTO rooms (position, room, damage, diverted) VALUES ('$position', '$room', '$damage', false)";
			self::DbQuery($sql);
		}
		$this->load();
	}

	public function load() {
		$sql = 'SELECT id, position, room, damage, diverted FROM rooms';
		$this->rooms = self::getCollectionFromDb($sql);
	}

	public function toArray() {
		return array_values($this->rooms);
	}
}
