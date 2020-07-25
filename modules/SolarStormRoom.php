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

	/** @var int */
	private $damage = null;

	/** @var bool */
	private $diverted = null;

	public function __construct(SolarStorm $table, array $roomData) {
		$this->table = $table;

		$this->roomId = (int) $roomData['room'];
		$this->position = (int) $roomData['position'];
		$this->damage = (int) $roomData['damage'];
		$this->diverted = $roomData['diverted'] == 1;

		$roomInfo = $this->table->roomInfos[$this->roomId];
		$this->slug = $roomInfo['slug'];
		$this->name = $roomInfo['name'];
		$this->description = $roomInfo['description'];
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

	public function getDamage(): int {
		return $this->damage;
	}

	public function isDiverted(): bool {
		return $this->diverted;
	}

	public function setDamage(int $damage): void {
		$this->damage = $damage;
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
		$damage = $this->getDamage();
		$diverted = $this->getDamage() ? '1' : '0';
		$sql = "UPDATE rooms SET damage = $damage, diverted = $diverted WHERE room = $roomId";
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
