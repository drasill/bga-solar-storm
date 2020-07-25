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
	private $position;

	public function __construct(array $bgaPlayerData, int $position) {
		$this->id = (int) $bgaPlayerData['player_id'];
		$this->name = $bgaPlayerData['player_name'];
		$this->color = $bgaPlayerData['player_color'];
		$this->position = $position;
	}

	public function getId(): int {
		return $this->id;
	}
	public function getPosition(): int {
		return $this->position;
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'color' => $this->color,
			'position' => $this->position,
		];
	}
}
