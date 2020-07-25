<?php

declare(strict_types=1);

class SolarStormPlayer extends APP_GameClass {
	/** @var int */
	private $id;

	/** @var string */
	private $name;

	/** @var string */
	private $color;

	/** @var int */
	private $position = null;

	public function __construct(array $bgaPlayerData) {
		$this->id = (int) $bgaPlayerData['player_id'];
		$this->name = $bgaPlayerData['player_name'];
		$this->color = $bgaPlayerData['player_color'];
		$this->loadPosition();
	}

	private function loadPosition(): void {
		$position = self::getUniqueValueFromDB(
			"SELECT  position FROM player_positions WHERE player_id = {$this->id}"
		);
		if ($position === null) {
			$position = 4;
			$sql = "INSERT INTO player_positions (player_id, position) VALUES ({$this->id}, {$position})";
			self::DbQuery($sql);
		}
		$this->position = (int) $position;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function setPosition(int $position): void {
		if ($position < 0 || $position > 8) {
			throw new \Exception('Invalid position');
		}
		$this->position = $position;
		$sql = "UPDATE player_positions SET position = $position WHERE player_id = $this->id";
		self::DbQuery($sql);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'color' => $this->color,
			'position' => $this->position,
		];
	}

	public function getNotificationArgs() {
		return [
			'player_id' => $this->getId(),
			'player_name' => $this->getName(),
		];
	}
}
