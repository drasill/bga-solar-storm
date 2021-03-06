<?php

declare(strict_types=1);

class SolarStormRooms extends APP_GameClass {
	/** @var SolarStormRoom[] */
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
			$sql = "INSERT INTO rooms (position, room, damage1, damage2, damage3, diverted, destroyed) VALUES ('$position', '$room', '$damage', '$damage', '$damage', false, false)";
			self::DbQuery($sql);
		}
		$this->load();
	}

	public function load() {
		$this->rooms = [];
		$sql =
			'SELECT id, position, room, damage1, damage2, damage3, diverted, destroyed, protection1, protection2, protection3, protection4 FROM rooms';
		$roomsData = self::getCollectionFromDb($sql);
		foreach ($roomsData as $roomData) {
			$room = new SolarStormRoom($this->table, $roomData);
			$this->rooms[] = $room;
		}
	}

	public function getRoom(int $roomId): SolarStormRoom {
		foreach ($this->rooms as $room) {
			if ($room->getRoomId() === $roomId) {
				return $room;
			}
		}
		throw new \Exception("Room id '$roomId' not found");
	}

	/**
	 * @return SolarStormRoom[]
	 */
	public function getRooms(): array {
		return $this->rooms;
	}

	public function getRoomBySlug(string $slug): SolarStormRoom {
		foreach ($this->rooms as $room) {
			if ($room->getSlug() === $slug) {
				return $room;
			}
		}
		throw new \Exception("Room '$slug' not found");
	}

	public function getRoomByPosition(int $position): SolarStormRoom {
		foreach ($this->rooms as $room) {
			if ($room->getPosition() === $position) {
				return $room;
			}
		}
		throw new \Exception("Room @ '$position' not found");
	}

	public function countTotalProtectionTokens(): int {
		$total = 0;
		foreach ($this->rooms as $room) {
			$total += count($room->getProtection());
		}
		return $total;
	}

	public function countTotalDiverted(): int {
		return count(
			array_filter($this->rooms, function ($room) {
				return $room->isDiverted();
			})
		);
	}

	public function toArray() {
		return array_map(function ($r) {
			return $r->toArray();
		}, $this->rooms);
	}
}
