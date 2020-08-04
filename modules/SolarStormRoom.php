<?php
// vim: tw=120:

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

	/** @var string */
	private $color;

	/** @var int */
	private $position = null;

	/** @var bool[] */
	private $damage = [false, false, false];

	/** @var int[] */
	private $protection = [];

	/** @var string[] */
	private $resources = [];

	/** @var string[] */
	private $divertResources = [];

	/** @var bool */
	private $diverted = null;

	/** @var bool */
	private $destroyed = false;

	public function __construct(SolarStorm $table, array $roomData) {
		$this->table = $table;

		$this->roomId = (int) $roomData['room'];
		$this->position = (int) $roomData['position'];
		$this->damage = [$roomData['damage1'] == 1, $roomData['damage2'] == 1, $roomData['damage3'] == 1];
		$this->protection = array_values(
			array_filter([
				(int) $roomData['protection1'],
				(int) $roomData['protection2'],
				(int) $roomData['protection3'],
				(int) $roomData['protection4'],
			])
		);
		$this->diverted = $roomData['diverted'] == 1;
		$this->destroyed = $roomData['destroyed'] == 1;

		$roomInfo = $this->table->roomInfos[$this->roomId];
		$this->slug = $roomInfo['slug'];
		$this->name = $roomInfo['name'];
		$this->color = $roomInfo['color'];
		$this->description = $roomInfo['description'];
		$this->resources = $roomInfo['resources'];
		$this->divertResources = $roomInfo['divertResources'];
	}

	public function getRoomId(): int {
		return $this->roomId;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getColor(): string {
		return $this->color;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getSlug(): string {
		return $this->slug;
	}

	public function getResources(): array {
		return $this->resources;
	}

	public function getDivertResources(): array {
		return $this->divertResources;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function getDamage(): array {
		return $this->damage;
	}

	public function getProtection(): array {
		return $this->protection;
	}

	public function isProtected(): bool {
		return !empty($this->protection);
	}

	public function setProtection(array $protection): void {
		$this->protection = $protection;
	}

	/**
	 * @return int playerId owning the protection token
	 */
	public function removeOldestProtectionToken(): int {
		if (!$this->isProtected()) {
			throw new \Exception('Cannot remove protection token');
		}
		return array_shift($this->protection);
	}

	public function isDiverted(): bool {
		return $this->diverted;
	}

	public function getDamageCount(): int {
		return count(array_filter($this->damage));
	}

	public function setDamage(array $damage) {
		$this->damage = $damage;
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

	public function setDiverted(bool $diverted): void {
		if ($this->slug === 'energy-core') {
			throw new BgaVisibleSystemException('Cannot divert power in this room');
		}
		$this->diverted = $diverted;
	}

	public function setDestroyed(bool $destroyed): void {
		$this->destroyed = $destroyed;
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

	public function addProtection(SolarStormPlayer $player) {
		$this->protection[] = $player->getId();
	}

	public function save() {
		$roomId = $this->getRoomId();

		$data = [
			'damage1' => $this->damage[0] ? '1' : '0',
			'damage2' => $this->damage[1] ? '1' : '0',
			'damage3' => $this->damage[2] ? '1' : '0',
			'diverted' => $this->diverted ? '1' : '0',
			'destroyed' => $this->destroyed ? '1' : '0',
			'protection1' => $this->protection[0] ?? 'NULL',
			'protection2' => $this->protection[1] ?? 'NULL',
			'protection3' => $this->protection[2] ?? 'NULL',
			'protection4' => $this->protection[3] ?? 'NULL',
		];

		$updStr = [];
		foreach ($data as $key => $value) {
			$updStr[] = "$key = $value";
		}
		$sql = 'UPDATE rooms SET ' . join(', ', $updStr) . " WHERE room = $roomId";
		self::DbQuery($sql);
	}

	public function toArray(): array {
		return [
			'id' => $this->roomId,
			'slug' => $this->slug,
			'name' => $this->name,
			'color' => $this->color,
			'description' => $this->description,
			'position' => $this->position,
			'diverted' => $this->diverted,
			'destroyed' => $this->destroyed,
			'damage' => $this->damage,
			'protection' => $this->protection,
		];
	}
}
