/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * solarstorm.js
 *
 * SolarStorm user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

// Utility : first element matching selector
function $first(selector) {
	return document.querySelectorAll(selector)[0]
}

define([
	'dojo',
	'dojo/_base/declare',
	'ebg/core/gamegui',
	'ebg/counter',
	'ebg/stock'
], function(dojo, declare) {
	return declare('bgagame.solarstorm', ebg.core.gamegui, {
		constructor: function() {
			this.rooms = new SSRooms()
			this.players = new SSPlayers()
			this.resourceTypes = []
			this.playAreaEl = null
			this.damageDeck = null
			this.resourceDeck = null
		},

		setup: function(gamedatas) {
			this.resourceTypes = gamedatas.resourceTypes
			this.initializePlayArea()
			this.initializePlayersArea()
			this.initializeDamageDeck()
			this.initializeResourceDeck()
			this.setupNotifications()
		},

		initializePlayArea() {
			// Initialize rooms
			this.gamedatas.rooms.forEach(roomData => {
				const room = new SSRoom(this, roomData)
				this.rooms.addRoom(room)
			})

			this.playAreaEl = $first('.ss-play-area')
			// Global area on click
			this.playAreaEl.addEventListener('click', this.onPlayAreaClick.bind(this))
		},

		initializePlayersArea() {
			const playersData = this.gamedatas.ssPlayers.sort(
				(p1, p2) => p1.order - p2.order
			)
			console.dir(playersData)
			playersData.forEach(data => {
				const player = new SSPlayer(
					this,
					data.id,
					data.name,
					data.color,
					data.order,
					data.position
				)
				this.players.addPlayer(player)
			})

			for (const [playerId, playerCards] of Object.entries(
				this.gamedatas.resourceCards
			)) {
				playerCards.forEach(resourceCard => {
					this.players
						.getPlayerById(playerId)
						.stock.addToStockWithId(resourceCard.type, resourceCard.id)
				})
			}
		},

		initializeDamageDeck() {
			this.damageDeck = new ebg.stock()
			const damageDeckEl = $first('.ss-damage-deck')
			this.damageDeck.create(this, damageDeckEl, 160, 117)
			this.damageDeck.setSelectionMode(0)
			this.damageDeck.extraClasses = 'ss-damage-card'
			this.damageDeck.setOverlap(0.01, 0)
			for (let i = 0; i < 24; i++) {
				this.damageDeck.addItemType(
					i,
					1,
					g_gamethemeurl + 'img/damages.jpg',
					i + 1
				)
			}
			for (let card of Object.values(this.gamedatas.damageCardsDiscarded)) {
				this.damageDeck.addToStock(card.type)
			}
		},

		initializeResourceDeck() {
			const resourceDeckEl = $first('.ss-resource-deck__table')
			this.resourceDeck = new ebg.stock()
			this.resourceDeck.create(this, resourceDeckEl, 87, 120)
			this.resourceDeck.setSelectionMode(1)
			this.resourceDeck.extraClasses = 'ss-resource-card'
			this.resourceDeck.setSelectionAppearance('class')
			this.resourceTypes.forEach((type, index) => {
				this.resourceDeck.addItemType(
					type.id,
					index,
					g_gamethemeurl + 'img/resources.jpg',
					index
				)
			})
			dojo.connect(
				this.resourceDeck,
				'onChangeSelection',
				this,
				'onResourceDeckSelection'
			)
			for (let card of Object.values(this.gamedatas.resourceCardsOnTable)) {
				this.resourceDeck.addToStockWithId(card.type, card.id)
			}
		},

		highlightResourceDeck(which = []) {
			const sources = ['deck', 'table']
			sources.forEach(source => {
				const valid = which.includes(source)
				$first(`.ss-resource-deck__${source}`).classList[
					valid ? 'add' : 'remove'
				]('ss-resource-deck__source--highlight')
			})
		},

		onScreenWidthChange(arguments) {
			this.players.assertPositions()
		},

		///////////////////////////////////////////////////
		//// Game & client states

		// onEnteringState: this method is called each time we are entering into a new game state.
		//                  You can use this method to perform some user interface changes at this moment.
		//
		onEnteringState: function(stateName, args) {
			console.log('Entering state: ' + stateName, args)

			switch (stateName) {
				case 'playerTurn':
					const leftStr = dojo.string.substitute(_('(${n} left)'), {
						n: args.args.actions
					})
					this.gamedatas.gamestate.descriptionmyturn += ' ' + leftStr
					this.gamedatas.gamestate.description += ' ' + leftStr
					this.updatePageTitle()

					break
				case 'playerMove':
					this.rooms.highlight(args.args.possibleDestinations)
					break
				case 'playerScavengePickCards':
				case 'pickResources':
					this.highlightResourceDeck(args.args.possibleSources)
					break
				case 'playerShare':
				case 'playerShareChoosePlayer':
					this.players.highlightHands(args.args.possiblePlayers)
					break
				case 'playerDiscardResources':
				case 'playerRepair':
					this.players.highlightHands([+this.getActivePlayerId()])
					break
			}
		},

		// onLeavingState: this method is called each time we are leaving a game state.
		//                 You can use this method to perform some user interface changes at this moment.
		//
		onLeavingState: function(stateName) {
			console.log('Leaving state: ' + stateName)

			switch (stateName) {
				case 'playerMove':
					this.rooms.highlight(null)
					break
				case 'playerScavengePickCards':
				case 'pickResources':
					this.highlightResourceDeck([])
					break
				case 'playerShare':
				case 'playerShareChoosePlayer':
					this.players.highlightHands(null)
					break
				case 'playerDiscardResources':
				case 'playerRepair':
					this.players.highlightHands(null)
			}
		},

		// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
		//                        action status bar (ie: the HTML links in the status bar).
		//
		onUpdateActionButtons: function(stateName, args) {
			console.log('onUpdateActionButtons: ' + stateName, args)

			if (this.isCurrentPlayerActive()) {
				switch (stateName) {
					case 'playerTurn':
						this.addActionButton('buttonMove', _('Move'), evt => {
							this.onPlayerChooseAction(evt, 'move')
						})
						this.addActionButton('buttonScavenge', _('Scavenge'), evt => {
							this.onPlayerChooseAction(evt, 'scavenge')
						})
						this.addActionButton('buttonShare', _('Share'), evt => {
							this.onPlayerChooseAction(evt, 'share')
						})
						this.addActionButton('buttonRepair', _('Repair'), evt => {
							this.onPlayerChooseAction(evt, 'repair')
						})
						break
					case 'playerMove':
						this.addActionCancelButton()
						break
					case 'playerShare':
						this.addActionCancelButton()
						break
					case 'playerShareChoosePlayer':
						this.addActionCancelButton()
						break
					case 'playerRepair':
						this.addActionCancelButton()
						break
					case 'playerScavenge':
						this.addActionButton('buttonRollDice', _('Roll dice'), evt => {
							this.ajaxAction('rollDice', { lock: true })
						})
						this.addActionCancelButton()
						break
				}
			}
		},

		addActionCancelButton() {
			this.addActionButton(
				'buttonCancel',
				_('Cancel'),
				evt => {
					this.ajaxAction('cancel', { lock: true })
				},
				null,
				null,
				'gray'
			)
		},

		///////////////////////////////////////////////////
		//// Utility methods

		///////////////////////////////////////////////////
		//// Player's action

		ajaxAction(action, args, check = true) {
			console.log('ajaxAction', action, args, check)
			if (check & !this.checkAction(action)) {
				return
			}
			return new Promise((resolve, reject) => {
				this.ajaxcall(
					`/solarstorm/solarstorm/${action}.html`,
					args,
					this,
					function(result) {
						resolve(result)
					},
					function(is_error) {
						reject(is_error)
					}
				)
			})
		},

		onPlayerChooseAction(evt, action) {
			console.log('onPlayerChooseAction', evt, action)
			dojo.stopEvent(evt)
			this.ajaxAction('choose', { lock: true, actionName: action })
		},

		onPlayAreaClick(evt) {
			const el = evt.target

			// Clicked on a room
			if (el.classList.contains('ss-room')) {
				dojo.stopEvent(evt)
				const room = this.rooms.getByEl(el)
				this.onRoomClick(room)
				return
			}

			// Clicked on resourceDeck
			if (el.classList.contains('ss-resource-deck__deck')) {
				dojo.stopEvent(evt)
				this.onResourceDeckClick()
				return
			}
		},

		onRoomClick(room) {
			if (this.last_server_state.name === 'playerMove') {
				this.ajaxAction('move', { lock: true, position: room.position })
			}
		},

		// Clicked on resource deck (hidden)
		onResourceDeckClick() {
			this.resourceDeck.unselectAll()
			this.ajaxAction('pickResource', { lock: true, cardId: 9999 })
		},

		// Clicked on resource deck (visible)
		onResourceDeckSelection() {
			var card = this.resourceDeck.getSelectedItems()[0]
			if (!card) {
				return
			}
			this.resourceDeck.unselectAll()
			this.ajaxAction('pickResource', { lock: true, cardId: card.id })
		},

		// Click on resource player hand
		onPlayerResourceClick(player, card) {
			if (
				this.last_server_state.name === 'playerDiscardResources' &&
				player.isCurrentActive()
			) {
				this.ajaxAction('discardResource', { lock: true, cardId: card.id })
				return
			}

			if (this.last_server_state.name === 'playerShare') {
				this.ajaxAction('shareResource', { lock: true, cardId: card.id })
				return
			}

			if (this.last_server_state.name === 'playerShareChoosePlayer') {
				this.ajaxAction('giveResource', { lock: true, playerId: player.id })
				return
			}

			if (this.last_server_state.name === 'playerRepair') {
				this.ajaxAction('selectResourceForRepair', {
					lock: true,
					cardId: card.id
				})
				return
			}
		},

		///////////////////////////////////////////////////
		//// Reaction to cometD notifications

		/*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your solarstorm.game.php file.
        
        */
		setupNotifications: function() {
			dojo.subscribe('updateRooms', this, 'notif_updateRooms')
			dojo.subscribe('updateDamageDiscard', this, 'notif_updateDamageDiscard')
			dojo.subscribe(
				'addResourcesCardsOnTable',
				this,
				'notif_addResourcesCardsOnTable'
			)
			dojo.subscribe('updatePlayerData', this, 'notif_updatePlayerData')
			dojo.subscribe('playerPickResource', this, 'notif_playerPickResource')
			dojo.subscribe(
				'playerDiscardResource',
				this,
				'notif_playerDiscardResource'
			)
			dojo.subscribe('playerShareResource', this, 'notif_playerShareResource')
		},

		notif_updateRooms(notif) {
			console.log('notif_updateRooms', notif)
			notif.args.rooms.forEach(roomData => {
				const room = this.rooms.getBySlug(roomData.slug)
				room.setDamage(roomData.damage)
				room.setDiverted(roomData.diverted)
			})
		},

		notif_updateDamageDiscard(notif) {
			console.log('notif_updateDamageDiscard', notif)
			notif.args.cards.forEach(cardData => {
				this.damageDeck.addToStock(cardData.type)
			})
			// TODO update damageCardsNbr
		},

		notif_addResourcesCardsOnTable(notif) {
			console.log('notif_addResourcesCardsOnTable', notif)
			notif.args.cards.forEach(cardData => {
				this.resourceDeck.addToStockWithId(cardData.type, cardData.id)
			})
			// TODO update damageCardsNbr
		},

		notif_playerPickResource(notif) {
			console.log('notif_playerPickResource', notif)
			const card = notif.args.card
			this.resourceDeck.removeFromStockById(card.id)
			const player = this.players.getPlayerById(notif.args.player_id)
			player.stock.addToStockWithId(card.type, card.id)
			// TODO animation
		},

		notif_playerDiscardResource(notif) {
			console.log('notif_playerDiscardResource', notif)
			const card = notif.args.card
			const player = this.players.getPlayerById(notif.args.player_id)
			player.stock.removeFromStockById(card.id)
			// TODO animation
		},

		notif_playerShareResource(notif) {
			console.log('notif_playerShareResource', notif)
			const card = notif.args.card
			const player = this.players.getPlayerById(notif.args.player_id)
			if (notif.args.shareAction === 'take') {
				const fromPlayer = this.players.getPlayerById(notif.args.from_player_id)
				fromPlayer.stock.removeFromStockById(card.id)
				player.stock.addToStockWithId(card.type, card.id)
			} else {
				const toPlayer = this.players.getPlayerById(notif.args.to_player_id)
				player.stock.removeFromStockById(card.id)
				toPlayer.stock.addToStockWithId(card.type, card.id)
			}
			// TODO animation
		},

		notif_updatePlayerData(notif) {
			console.log('notif_updatePlayerData', notif)
			this.players
				.getPlayerById(notif.args.player_id)
				.setRoomPosition(notif.args.position)
		}
	})
})

class SSRooms {
	rooms = []

	addRoom(room) {
		this.rooms.push(room)
	}

	getByEl(el) {
		return this.rooms.find(r => r.el === el)
	}

	getBySlug(slug) {
		return this.rooms.find(r => r.slug === slug)
	}

	getByPosition(position) {
		return this.rooms.find(r => r.position === position)
	}

	highlight(positions) {
		this.rooms.forEach(room => {
			room.highlight(positions && positions.includes(room.position))
		})
	}
}

class SSRoom {
	gameObject = null
	id = null
	slug = null
	name = null
	description = null
	position = null
	damage = [false, false, false]
	diverted = false

	el = null
	divertedTokenEl = null

	constructor(gameObject, data) {
		this.gameObject = gameObject
		this.id = data.id
		this.slug = data.slug
		this.name = data.name
		this.description = data.description
		this.position = data.position
		this.assertEl()
		this.setDamage(data.damage)
		this.setDiverted(data.diverted)
	}

	assertEl() {
		let el = $first(`.ss-room--${this.id}`)
		if (el) {
			this.el = el
			return
		}
		const roomsEl = $first('.ss-rooms')
		el = dojo.create(
			'div',
			{
				id: `ss-room--${this.id}`,
				class: `ss-room ss-room--pos-${this.position} ss-room--${this.id}`
			},
			roomsEl
		)
		this.gameObject.addTooltipHtml(
			el.id,
			`<div class="ss-room ss-room-tooltip ss-room--${this.id}"></div><b>${this.name}</b><hr/>${this.description}`,
			1000
		)
		if (this.id !== 0) {
			for (let i = 0; i < 3; i++) {
				dojo.create(
					'div',
					{ class: `ss-room__damage ss-room__damage--${i}` },
					el
				)
			}
			this.divertedTokenEl = dojo.create(
				'div',
				{ class: 'ss-room__diverted-token' },
				el
			)
		}
		this.el = el
	}

	setDamage(damage) {
		if (this.id === 0) {
			return
		}
		damage.forEach((dmg, index) => {
			this.el.classList[dmg ? 'add' : 'remove'](`ss-room--damaged-${index}`)
		})
		this.damage = damage
	}

	setDiverted(diverted) {
		if (this.id === 0) {
			return
		}
		this.diverted = diverted
		this.divertedTokenEl.classList[diverted ? 'add' : 'remove'](
			'ss-room__diverted-token--visible'
		)
	}

	highlight(value) {
		this.el.classList[value ? 'add' : 'remove']('ss-room-highlight')
	}
}

class SSPlayers {
	players = []

	addPlayer(player) {
		this.players.push(player)
	}

	getPlayerById(id) {
		return this.players.find(p => p.id === +id)
	}

	getAtPosition(position) {
		return this.players.filter(p => p.position === +position)
	}

	assertPositions() {
		this.players.forEach(player => {
			player.setRoomPosition(player.position, true, false)
		})
	}

	highlightHands(ids) {
		this.players.forEach(player => {
			player.highlightHand(ids && ids.includes(player.id))
		})
	}
}

class SSPlayer {
	gameObject = null
	id = null
	name = null
	color = null
	boardEl = null
	stock = null
	meepleEl = null
	order = null
	position = null

	constructor(gameObject, id, name, color, order, position) {
		this.gameObject = gameObject
		this.id = +id
		this.name = name
		this.color = color
		this.order = order
		this.assertBoardEl()
		this.assertMeepleEl()
		this.createStock()
		this.setRoomPosition(position, false, true)
	}

	isCurrentActive() {
		return (
			this.gameObject.player_id == this.id &&
			this.gameObject.getActivePlayerId() == this.id
		)
	}

	assertBoardEl() {
		let boardEl = $first(`.ss-players-board--id-${this.id}`)
		if (boardEl) {
			this.boardEl = boardEl
			return
		}
		const playersHandsEl = $first('.ss-players-hands')
		boardEl = dojo.create(
			'div',
			{
				class: `ss-player-board ss-players-board--id-${this.id}`
			},
			playersHandsEl
		)
		const handEl = dojo.create(
			'div',
			{
				class: 'ss-player-board__name',
				style: {
					backgroundColor: '#' + this.color
				},
				innerHTML: `Hand of ${this.name}`
			},
			boardEl
		)
		this.boardEl = boardEl
	}

	assertMeepleEl() {
		let meepleEl = $first(`.ss-player-meeple--id-${this.id}`)
		if (meepleEl) {
			this.meepleEl = meepleEl
			return
		}
		const playersArea = $first('.ss-play-area')
		meepleEl = dojo.create(
			'div',
			{
				id: `ss-player-meeple--id-${this.id}`,
				class: `ss-player-meeple ss-player-meeple--order-${this.order} ss-player-meeple--id-${this.id}`
			},
			playersArea
		)
		this.gameObject.addTooltipHtml(meepleEl.id, _(`Player ${this.name}`), 250)
		this.meepleEl = meepleEl
	}

	createStock() {
		this.stock = new ebg.stock()
		const handEl = dojo.create(
			'div',
			{
				class: 'ss-player-hand',
				id: `ss-player-hand--${this.id}`
			},
			this.boardEl
		)
		this.stock.create(this.gameObject, handEl, 87, 120)
		this.stock.setSelectionMode(1)
		this.stock.extraClasses = 'ss-resource-card'
		this.stock.setSelectionAppearance('class')
		this.stock.setOverlap(30, 5)
		dojo.connect(this.stock, 'onChangeSelection', () => {
			var card = this.stock.getSelectedItems()[0]
			if (!card) {
				return
			}
			this.stock.unselectAll()
			this.gameObject.onPlayerResourceClick(this, card)
		})
		this.gameObject.resourceTypes.forEach((type, index) => {
			this.stock.addItemType(
				type.id,
				index,
				g_gamethemeurl + 'img/resources.jpg',
				index
			)
		})
	}

	setRoomPosition(position, instant = false, moveOthers = true) {
		const previousPosition = this.position
		this.position = position
		if (position === null) {
			this.meepleEl.style.display = 'none'
			return
		}
		this.meepleEl.style.display = 'block'

		const roomEl = this.gameObject.rooms.getByPosition(position).el
		const duration = instant ? 0 : 750
		const roomPos = dojo.position(roomEl)

		// const index = this.gameObject.players
		// .getAtPosition(position)
		// .sort(p => p.order)
		// .findIndex(p => p.id === this.id)
		const index = this.order
		const offsetX = index * 30
		const offsetY = roomPos.h * 0.2

		this.gameObject
			.slideToObjectPos(this.meepleEl, roomEl, offsetX, offsetY, duration, 0)
			.play()

		if (moveOthers) {
			this.gameObject.players
				.getAtPosition(position)
				.forEach(p => p.setRoomPosition(position, false, false))
			if (previousPosition !== position) {
				this.gameObject.players
					.getAtPosition(previousPosition)
					.forEach(p => p.setRoomPosition(previousPosition, false, false))
			}
		}
	}

	highlightHand(value) {
		this.boardEl.classList[value ? 'add' : 'remove'](
			'ss-player-board--highlight'
		)
	}
}
