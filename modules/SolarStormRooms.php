<?php

declare(strict_types=1);

class SolarStormRooms extends APP_GameClass {
	/** @var array */
	private $rooms;

	/** @var SolarStorm */
	private $table;

	/**
	 * Constructor
	 */
	public function __construct(SolarStorm $table) {
		$this->table = $table;
		$this->load();
	}

	public function generateRooms() {
		$rooms = [1, 2, 3, 4, 5, 6, 7, 8];
		shuffle($rooms);
		// Position 4 (center) is always room 0 (Energy Core)
		array_splice($rooms, 4, 0, 0);
		foreach ($rooms as $position => $room) {
			$damage = rand(0, 2);
			$sql = "INSERT INTO rooms (position, room, damage, diverted) VALUES ('$position', '$room', '$damage', false)";
			self::DbQuery($sql);
		}
		$this->load();
	}

	public function load() {
		$this->rooms = [];
		$sql = 'SELECT id, position, room, damage, diverted FROM rooms';
		$roomsData = self::getCollectionFromDb($sql);
		foreach ($roomsData as $roomData) {
			$roomId = (int) $roomData['room'];
			$roomInfo = $this->table->roomInfos[$roomId];
			$room = [
				'id' => $roomId,
				'slug'=> $roomInfo['slug'],
				'name'=> $roomInfo['name'],
				'description'=> $roomInfo['description'],
				'position' => (int) $roomData['position'],
				'damage' => (int) $roomData['damage'],
				'diverted' => $roomData['diverted'] == 1,
			];
			$this->rooms[] = $room;
		}
	}

	public function toArray() {
		return array_values($this->rooms);
	}
}
