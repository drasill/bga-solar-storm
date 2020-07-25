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
			$damage = 0;
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
				'slug' => $roomInfo['slug'],
				'name' => $roomInfo['name'],
				'description' => $roomInfo['description'],
				'position' => (int) $roomData['position'],
				'damage' => (int) $roomData['damage'],
				'diverted' => $roomData['diverted'] == 1,
			];
			$this->rooms[] = $room;
		}
	}

	public function getRoomById(int $id): array {
	}

	public function getRoomBySlug(string $slug): array {
		foreach ($this->rooms as $room) {
			if ($room['slug'] === $slug) {
				return $room;
			}
		}
		throw new \Exception("Room '$slug' not found");
	}

	public function getRoomByPosition(int $position): array {
		foreach ($this->rooms as $room) {
			if ($room['position'] === $position) {
				return $room;
			}
		}
		throw new \Exception("Room @ '$position' not found");
	}

	public function updateRoom(array $newRoom): void {

		$found = true;
		foreach ($this->rooms as &$room) {
			if ($room['id'] === $newRoom['id']) {
				$found = true;
				break;
			}
		}
		if (!$found)
		throw new \Exception("Room id '{$newRoom['id']} not found");

		$damage = (int) $newRoom['damage'];
		$diverted = $newRoom['diverted'] ? 1 : 0;
		$roomId = (int) $newRoom['id'];
		$sql = "UPDATE rooms SET damage = $damage, diverted = $diverted WHERE room = $roomId";
		self::DbQuery($sql);

		$room['damage'] = $newRoom['damage'];
		$room['diverted'] = $newRoom['diverted'];
	}

	public function toArray() {
		return array_values($this->rooms);
	}
}
