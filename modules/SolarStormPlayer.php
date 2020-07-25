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
	private $order = null;

	/** @var int */
	private $position = null;

	/** @var int */
	private $actions = null;

	/** @var int */
	private $actionsTokens = null;

	public function __construct(array $bgaPlayerData) {
		$this->id = (int) $bgaPlayerData['player_id'];
		$this->name = $bgaPlayerData['player_name'];
		$this->color = $bgaPlayerData['player_color'];
		$this->order = (int)$bgaPlayerData['player_no'];
		$this->load();
	}

	private function load(): void {
		$data = self::getObjectFromDB(
			"SELECT position, actions, actions_tokens FROM player_data WHERE player_id = {$this->id}"
		);
		if ($data === null) {
			$data = [
				'position' => 4,
				'actions' => 3,
				'actions_tokens' => 0,
			];
			$sql = "INSERT INTO player_data (player_id, position, actions, actions_tokens) VALUES ({$this->id}, {$data['position']}, {$data['actions']}, {$data['actions_tokens']})";
			self::DbQuery($sql);
		}
		$this->position = (int) $data['position'];
		$this->actions = (int) $data['actions'];
		$this->actionsTokens = (int) $data['actions_tokens'];
	}

	public function getId(): int {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getOrder(): int {
		return $this->order;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function getActions(): int {
		return $this->actions;
	}

	public function getActionsTokens(): int {
		return $this->actionsTokens;
	}

	public function setPosition(int $position): void {
		if ($position < 0 || $position > 8) {
			throw new \Exception('Invalid position');
		}
		$this->position = $position;
	}

	public function setActions(int $actions): void {
		if ($actions < 0 || $actions > 10) {
			throw new \Exception('Invalid actions');
		}
		$this->actions = $actions;
	}

	public function incrementActions(int $diff): void {
		$this->setActions($this->actions + $diff);
	}

	public function setActionsTokens(int $actionsTokens): void {
		if ($actionsTokens < 0 || $actionsTokens > 8) {
			throw new \Exception('Invalid actionsTokens');
		}
		$this->actionsTokens = $actionsTokens;
	}

	public function save() {
		$playerId = $this->getId();
		$position = $this->getPosition();
		$actions = $this->getActions();
		$actionsTokens = $this->getActionsTokens();
		$sql = "UPDATE player_data SET position = $position, actions = $actions, actions_tokens = $actionsTokens WHERE player_id = $playerId";
		self::DbQuery($sql);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'color' => $this->color,
			'order' => $this->order,
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
