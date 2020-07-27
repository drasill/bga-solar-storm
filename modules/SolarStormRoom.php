<?php

declare(strict_types=1);

class SolarStormRoom extends APP_GameClass {
	/** @var SolarStorm */
	private $table;

	/** @var int */
	private $roomId;

	/** @var string */
	private $slug;

	/** @var string */
	private $name;

	/** @var string */
	private $description;

	/** @var int */
	private $position = null;

	/** @var bool[] */
	private $damage = [false, false, false];

	/** @var string[] */
	private $resources = [];

	/** @var bool */
	private $diverted = null;

	public function __construct(SolarStorm $table, array $roomData) {
		$this->table = $table;

		$this->roomId = (int) $roomData['room'];
		$this->position = (int) $roomData['position'];
		$this->damage = [
			$roomData['damage1'] == 1,
			$roomData['damage2'] == 1,
			$roomData['damage3'] == 1,
		];
		$this->diverted = $roomData['diverted'] == 1;

		$roomInfo = $this->table->roomInfos[$this->roomId];
		$this->slug = $roomInfo['slug'];
		$this->name = $roomInfo['name'];
		$this->description = $roomInfo['description'];
		$this->resources = $roomInfo['resources'];
	}

	public function getRoomId(): int {
		return $this->roomId;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getSlug(): string {
		return $this->slug;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function isDiverted(): bool {
		return $this->diverted;
	}

	public function doDamage(): void {
		foreach ($this->damage as $dmgIndex => $dmgValue) {
			if ($dmgValue) {
				continue;
			}
			$this->damage[$dmgIndex] = true;
			return;
		}
		throw new \Exception('Cannot damage this room more');
	}

	public function repairWithResource(string $resourceType): void {
		$resourceInfo = $this->table->resourceTypes[$resourceType];
		foreach ($this->resources as $resIndex => $resType) {
			if ($resType !== $resourceType) {
				continue;
			}
			if (!$this->damage[$resIndex]) {
				throw new BgaUserException(
					sprintf('This room is not damaged resource %s', $resourceInfo['nametr'])
				);
			}
			$this->damage[$resIndex] = false;
			return;
		}
		throw new BgaUserException(
			sprintf('Cannot repair this room with the resource %s', $resourceInfo['nametr'])
		);
	}

	public function setDiverted(bool $diverted): void {
		$this->diverted = $diverted;
	}

	/**
	 * Return list of positions a player can go from this room
	 * @param int[]
	 */
	public function getPossibleDestinations(): array {
		$positionMap = [
			0 => [1, 3],
			1 => [0, 2, 4],
			2 => [1, 5],
			3 => [0, 4, 6],
			4 => [1, 3, 5, 7],
			5 => [2, 4, 8],
			6 => [3, 7],
			7 => [6, 4, 8],
			8 => [7, 5],
		];
		return $positionMap[$this->position];
	}

	public function save() {
		$roomId = $this->getRoomId();
		$damage1 = $this->damage[0] ? '1' : '0';
		$damage2 = $this->damage[1] ? '1' : '0';
		$damage3 = $this->damage[2] ? '1' : '0';
		$diverted = $this->diverted ? '1' : '0';
		$sql = "UPDATE rooms SET damage1 = $damage1, damage2 = $damage2, damage3 = $damage3, diverted = $diverted WHERE room = $roomId";
		self::DbQuery($sql);
	}

	public function toArray(): array {
		return [
			'id' => $this->roomId,
			'slug' => $this->slug,
			'name' => $this->name,
			'description' => $this->description,
			'position' => $this->position,
			'diverted' => $this->diverted,
			'damage' => $this->damage,
		];
	}
}
