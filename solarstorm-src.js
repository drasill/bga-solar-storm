// vim: tw=120:
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

define(['dojo', 'dojo/_base/declare', 'ebg/core/gamegui', 'ebg/counter', 'ebg/stock'], function(dojo, declare) {
	return declare('bgagame.solarstorm', ebg.core.gamegui, {
		constructor: function() {
			this.rooms = new SSRooms()
			this.players = new SSPlayers(this)
			this.resourceTypes = []
			this.playAreaEl = null
			this.damageDeck = null
			this.resourceDeck = null
			this.reorderResourceDeck = null
			this.reorderDamageDeck = null
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
			const playersData = this.gamedatas.ssPlayers.sort((p1, p2) => p1.order - p2.order)
			playersData.forEach(data => {
				const player = new SSPlayer(this, data.id, data.name, data.color, data.order, data.position)
				this.players.addPlayer(player)
			})

			for (const [playerId, playerCards] of Object.entries(this.gamedatas.resourceCards)) {
				playerCards.forEach(resourceCard => {
					this.players.getPlayerById(playerId).stock.addToStockWithId(resourceCard.type, resourceCard.id)
				})
			}
		},

		initializeDamageDeck() {
			const damageDeckEl = $first('.ss-damage-deck')
			this.damageDeck = this.createDamageStock(damageDeckEl)
			this.damageDeck.setOverlap(0.01)
			this.damageDeck.setSelectionMode(0)
			for (let card of Object.values(this.gamedatas.damageCardsDiscarded)) {
				this.damageDeck.addToStock(card.type)
			}

			const reorderDamageDeckEl = $first('.ss-damage-reorder-deck')
			this.reorderDamageDeck = this.createDamageStock(reorderDamageDeckEl, () => this.onReorderDamageDeckSelection())
		},

		initializeResourceDeck() {
			const resourceDeckEl = $first('.ss-resource-deck__table')
			this.resourceDeck = this.createResourceStock(resourceDeckEl, () => this.onResourceDeckSelection())
			for (let card of Object.values(this.gamedatas.resourceCardsOnTable)) {
				this.resourceDeck.addToStockWithId(card.type, card.id)
			}

			const reorderResourceDeckEl = $first('.ss-resource-reorder-deck')
			this.reorderResourceDeck = this.createResourceStock(reorderResourceDeckEl)
		},

		highlightResourceDeck(which = []) {
			const sources = ['deck', 'table']
			sources.forEach(source => {
				const valid = which.includes(source)
				$first(`.ss-resource-deck__${source}`).classList[valid ? 'add' : 'remove'](
					'ss-resource-deck__source--highlight'
				)
			})
		},

		onScreenWidthChange() {
			this.players.assertPositions()
		},

		showResourceCardsToPutInDeck(resourceCards) {
			$first('.ss-resource-reorder-dialog__title').innerHTML =
				_('Put back the cards in the deck.') + '<br/>' + _('The last one will be on top.')
			$first('.ss-resource-reorder-dialog').classList.add('ss-resource-reorder-dialog--visible')
			for (let card of Object.values(resourceCards)) {
				this.reorderResourceDeck.addToStockWithId(card.type, card.id)
			}
		},

		hideResourceCardsToPutInDeck() {
			$first('.ss-resource-reorder-dialog').classList.remove('ss-resource-reorder-dialog--visible')
		},

		showDamageCardsToPutInDeck(damageCards) {
			$first('.ss-damage-reorder-dialog__title').innerHTML =
				_('Put back the cards in the deck.') + '<br/>' + _('The last one will be on top.')
			$first('.ss-damage-reorder-dialog').classList.add('ss-damage-reorder-dialog--visible')
			for (let card of Object.values(damageCards)) {
				this.reorderDamageDeck.addToStockWithId(card.type, card.id)
			}
		},

		hideDamageCardsToPutInDeck() {
			$first('.ss-damage-reorder-dialog').classList.remove('ss-damage-reorder-dialog--visible')
		},

		///////////////////////////////////////////////////
		//// Game & client states

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
					this.doPlayerActionMove(args.args.possibleDestinations)
					break
				case 'playerScavengePickCards':
				case 'pickResources':
					this.highlightResourceDeck(args.args.possibleSources)
					break
				case 'playerDiscardResources':
					this.doPlayerActionDiscardResource()
					break
				case 'playerRepair':
					this.doPlayerActionRepair()
					break
				case 'playerRoomCrewQuarter':
					this.doPlayerRoomCrewQuarter()
					break
				case 'playerRoomCargoHold':
					this.doPlayerRoomCargoHold(Object.values(args.args._private.resourceCards))
					break
				case 'playerRoomBridge':
					this.showDamageCardsToPutInDeck(args.args._private.damageCards)
					break
				case 'playerRoomEngineRoom':
					this.doPlayerRoomEngineRoom(args.args.resourceCards)
					break
			}
		},

		onLeavingState: function(stateName) {
			console.log('Leaving state: ' + stateName)

			switch (stateName) {
				case 'playerScavengePickCards':
				case 'pickResources':
					this.highlightResourceDeck([])
					break
				case 'playerRoomCargoHold':
					this.hideResourceCardsToPutInDeck()
					break
				case 'playerRoomBridge':
					this.hideDamageCardsToPutInDeck()
					break
			}
		},

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
						this.addActionButton('buttonRoom', _('Room action'), evt => {
							this.onPlayerChooseAction(evt, 'room')
						})
						break
					case 'playerShare':
						this.addActionButton('shareGive', _('Give a card'), evt => {
							this.doPlayerActionGiveResource(true)
						})
						this.addActionButton('shareTake', _('Take a card'), evt => {
							this.doPlayerActionTakeResource(true)
						})
						break
					case 'playerScavenge':
						this.addActionButton('buttonRollDice', _('Roll dice'), evt => {
							this.ajaxAction('rollDice', { lock: true })
						})
						this.showActionCancelButton(() => {
							this.ajaxAction('cancel', { lock: true })
						})
						break
					case 'playerRoomMessHall':
						this.addActionButton('messHallGive', _('Give a card'), evt => {
							this.doPlayerActionGiveResource(false)
						})
						this.addActionButton('messHallTake', _('Take a card'), evt => {
							this.doPlayerActionTakeResource(false)
						})
						this.addActionButton('messHallSwap', _('Swap a card'), evt => {
							this.doPlayerActionSwapResource()
						})
						break
				}
			}
		},

		showActionCancelButton(callback) {
			this.removeActionCancelButton()
			this.addActionButton('actionCancelButton', _('Cancel'), callback, null, null, 'gray')
		},

		removeActionCancelButton() {
			const el = $('actionCancelButton')
			if (el) {
				el.remove()
			}
		},

		///////////////////////////////////////////////////
		//// Utility methods

		createResourceStock(el, onClick = null) {
			const stock = new ebg.stock()
			stock.create(this, el, 87, 120)
			stock.setSelectionMode(1)
			stock.extraClasses = 'ss-resource-card'
			stock.setSelectionAppearance('class')
			this.resourceTypes.forEach((type, index) => {
				stock.addItemType(type.id, index, g_gamethemeurl + 'img/resources.jpg', index)
			})
			if (onClick !== null) {
				dojo.connect(stock, 'onChangeSelection', onClick)
			}
			return stock
		},

		createDamageStock(el, onClick = null) {
			const stock = new ebg.stock()
			stock.create(this, el, 160, 117)
			stock.setSelectionMode(1)
			stock.extraClasses = 'ss-damage-card'
			stock.setSelectionAppearance('class')
			for (let i = 0; i < 24; i++) {
				stock.addItemType(i, 1, g_gamethemeurl + 'img/damages.jpg', i + 1)
			}
			if (onClick !== null) {
				dojo.connect(stock, 'onChangeSelection', onClick)
			}
			return stock
		},

		waitForResourceCardClick(players, options = {}) {
			const ids = players.map(p => p.id)
			this.players.highlightHands(ids)

			return new Promise((resolve, reject) => {
				const handles = []
				const cleanAll = () => {
					this.players.highlightHands(null)
					handles.forEach(handle => dojo.disconnect(handle))
					if (options.cancel) {
						this.removeActionCancelButton()
					}
				}

				if (options.cancel) {
					this.showActionCancelButton(() => {
						cleanAll()
						reject('CANCEL BTN')
					})
				}

				players.forEach(player => {
					handles.push(
						dojo.connect(player.stock, 'onChangeSelection', () => {
							const cards = player.stock.getSelectedItems()
							const card = cards[0]
							cleanAll()
							if (!card) {
								reject('NO CARD')
							} else {
								player.stock.unselectAll()
								resolve({ card, player })
							}
						})
					)
				})
			})
		},

		waitForRoomClick(rooms, options = {}) {
			const positions = rooms.map(r => r.position)
			this.rooms.highlightPositions(positions)

			return new Promise((resolve, reject) => {
				const handles = []
				const cleanAll = () => {
					handles.forEach(handle => dojo.disconnect(handle))
					if (options.cancel) {
						this.removeActionCancelButton()
					}
				}

				if (options.cancel) {
					this.showActionCancelButton(() => {
						cleanAll()
						reject('CANCEL BTN')
						this.rooms.highlightPositions(null)
					})
				}

				rooms.forEach(room => {
					handles.push(
						dojo.connect(room.el, 'onclick', () => {
							this.rooms.highlightPositions(null)
							cleanAll()
							resolve(room)
						})
					)
				})
			})
		},

		waitForPlayerMeepleClick(players, options = {}) {
			const ids = players.map(p => p.id)
			this.players.highlightMeeples(ids)

			return new Promise((resolve, reject) => {
				const handles = []
				const cleanAll = () => {
					handles.forEach(handle => dojo.disconnect(handle))
					this.removeActionCancelButton()
					this.players.highlightMeeples(null)
				}

				if (options.cancel) {
					this.showActionCancelButton(() => {
						cleanAll()
						reject('CANCEL BTN')
					})
				}

				players.forEach(player => {
					handles.push(
						dojo.connect(player.meepleEl, 'onclick', () => {
							cleanAll()
							resolve(player)
						})
					)
				})
			})
		},

		waitForResourceCardFromDialog(cards, options = {}) {
			return new Promise((resolve, reject) => {
				const handles = []
				const dialogEl = $first('.ss-resource-reorder-dialog')
				const cleanAll = () => {
					handles.forEach(handle => dojo.disconnect(handle))
					dialogEl.classList.remove('ss-resource-reorder-dialog--visible')
					this.removeActionCancelButton()
				}

				if (options.cancel) {
					this.showActionCancelButton(() => {
						cleanAll()
						reject('CANCEL BTN')
					})
				}

				this.reorderResourceDeck.unselectAll()
				this.reorderResourceDeck.removeAll()

				$first('.ss-resource-reorder-dialog__title').innerHTML = options.title || ''
				dialogEl.classList.add('ss-resource-reorder-dialog--visible')
				for (let card of Object.values(cards)) {
					this.reorderResourceDeck.addToStockWithId(card.type, card.id)
				}

				this.reorderResourceDeck.setSelectionMode(1)
				handles.push(
					dojo.connect(this.reorderResourceDeck, 'onChangeSelection', () => {
						const cards = this.reorderResourceDeck.getSelectedItems()
						const card = cards[0]
						cleanAll()
						if (!card) {
							reject('NO CARD')
						} else {
							resolve(card)
						}
					})
				)
			})
		},

		waitForResourceCardOrderFromDialog(cards, options = {}) {
			return new Promise((resolve, reject) => {
				let selectedCards = []
				const handles = []
				const dialogEl = $first('.ss-resource-reorder-dialog')
				if (!options.count) {
					options.count = cards.length
				}
				const cleanAll = () => {
					handles.forEach(handle => dojo.disconnect(handle))
					dialogEl.classList.remove('ss-resource-reorder-dialog--visible')
					this.removeActionCancelButton()
					$('buttonAccept').remove()
					$('buttonReset').remove()
				}

				if (options.cancel) {
					this.showActionCancelButton(() => {
						cleanAll()
						reject('CANCEL BTN')
					})
				}

				this.reorderResourceDeck.unselectAll()
				this.reorderResourceDeck.removeAll()

				$first('.ss-resource-reorder-dialog__title').innerHTML = options.title || ''
				dialogEl.classList.add('ss-resource-reorder-dialog--visible')
				for (let card of Object.values(cards)) {
					this.reorderResourceDeck.addToStockWithId(card.type, card.id)
				}

				this.reorderResourceDeck.setSelectionMode(2)
				this.addActionButton('buttonReset', _('Restart selection'), () => {
					selectedCards.forEach(card => {
						this.reorderResourceDeck.addToStockWithId(card.type, card.id)
					})
					selectedCards = []
				})
				this.addActionButton('buttonAccept', _('Accept'), () => {
					if (selectedCards.length !== options.count) {
						gameui.showMessage(_(`You must select ${options.count} cards`), 'error')
						return
					}
					cleanAll()
					resolve(selectedCards)
				})
				this.reorderResourceDeck.setSelectionMode(1)
				handles.push(
					dojo.connect(this.reorderResourceDeck, 'onChangeSelection', () => {
						const cards = this.reorderResourceDeck.getSelectedItems()
						const card = cards[0]
						if (card) {
							selectedCards.push(card)
							this.reorderResourceDeck.removeFromStockById(card.id)
						}
					})
				)
			})
		},

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
			dojo.stopEvent(evt)
			this.ajaxAction('choose', { lock: true, actionName: action })
		},

		onPlayAreaClick(evt) {
			const el = evt.target

			// Clicked on resourceDeck
			if (el.classList.contains('ss-resource-deck__deck')) {
				dojo.stopEvent(evt)
				this.onResourceDeckClick()
				return
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

		onReorderResourceDeckSelection() {
			var card = this.reorderResourceDeck.getSelectedItems()[0]
			if (!card) {
				return
			}
			this.reorderResourceDeck.unselectAll()
			this.ajaxAction('putBackResourceCardInDeck', {
				lock: true,
				cardId: card.id
			})
		},

		onReorderDamageDeckSelection() {
			var card = this.reorderDamageDeck.getSelectedItems()[0]
			if (!card) {
				return
			}
			this.reorderDamageDeck.unselectAll()
			this.ajaxAction('putBackDamageCardInDeck', {
				lock: true,
				cardId: card.id
			})
		},

		async doPlayerActionMove(possibleDestinations) {
			try {
				const rooms = possibleDestinations.map(p => this.rooms.getByPosition(p))
				const room = await this.waitForRoomClick(rooms, { cancel: true })
				this.ajaxAction('move', { lock: true, position: room.position })
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
			}
		},

		async doPlayerActionDiscardResource() {
			const card = (await this.waitForResourceCardClick([this.players.getActive()])).card
			this.ajaxAction('discardResource', { lock: true, cardId: card.id })
		},

		async doPlayerActionGiveResource(sameRoomOnly = true) {
			this.gamedatas.gamestate.descriptionmyturn = _('You mush choose a card to give')
			this.updatePageTitle()
			this.removeActionButtons()
			try {
				const activePlayer = this.players.getActive()
				const card = (await this.waitForResourceCardClick([activePlayer], { cancel: true })).card
				this.gamedatas.gamestate.descriptionmyturn = _('You mush choose a player to give the card to')
				this.updatePageTitle()
				this.removeActionButtons()
				const targetPlayers = this.players
					.getInactive()
					.filter(p => !sameRoomOnly || p.position === activePlayer.position)
				const player = (await this.waitForResourceCardClick(targetPlayers, { cancel: true })).player
				await this.ajaxAction('giveResourceToAnotherPlayer', {
					lock: true,
					cardId: card.id,
					playerId: player.id
				})
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
			}
		},

		async doPlayerActionTakeResource(sameRoomOnly = true) {
			this.gamedatas.gamestate.descriptionmyturn = _('You mush choose a card to take')
			this.updatePageTitle()
			this.removeActionButtons()
			try {
				const activePlayer = this.players.getActive()
				const targetPlayers = this.players
					.getInactive()
					.filter(p => !sameRoomOnly || p.position === activePlayer.position)
				const card = (await this.waitForResourceCardClick(targetPlayers, { cancel: true })).card
				await this.ajaxAction('pickResourceFromAnotherPlayer', {
					lock: true,
					cardId: card.id
				})
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
			}
		},

		async doPlayerActionSwapResource() {
			this.gamedatas.gamestate.descriptionmyturn = _('Swap cards : You mush choose a card to give')
			this.updatePageTitle()
			this.removeActionButtons()
			try {
				const card1 = (await this.waitForResourceCardClick([this.players.getActive()], { cancel: true })).card
				this.gamedatas.gamestate.descriptionmyturn = _('Swap cards : You mush choose a card to exchange')
				this.updatePageTitle()
				this.removeActionButtons()
				const card2 = (await this.waitForResourceCardClick(this.players.getInactive(), { cancel: true })).card
				await this.ajaxAction('swapResourceWithAnotherPlayer', {
					lock: true,
					cardId: card1.id,
					card2Id: card2.id
				})
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
			}
		},

		async doPlayerActionRepair() {
			try {
				const card = (await this.waitForResourceCardClick([this.players.getActive()], { cancel: true })).card
				await this.ajaxAction('selectResourceForRepair', {
					lock: true,
					cardId: card.id
				})
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
			}
		},

		async doPlayerRoomCargoHold(cards) {
			const selectedCards = await this.waitForResourceCardOrderFromDialog(cards, {
				title: _('Select a card from the discard, to be swap with one from your hand'),
				cancel: false
			})
			this.ajaxAction('putBackResourceCardsInDeck', {
				lock: true,
				cardIds: selectedCards.map(c => c.id).join(',')
			})
		},

		async doPlayerRoomCrewQuarter() {
			try {
				const player = await this.waitForPlayerMeepleClick(this.players.players, { cancel: true })
				this.gamedatas.gamestate.descriptionmyturn = _('Crew Quarters: You mush choose a destination')
				this.updatePageTitle()
				this.removeActionButtons()
				// Valid rooms are where there are meeples
				const validRooms = this.players.players.map(p => p.position).map(p => this.rooms.getByPosition(p))
				const room = await this.waitForRoomClick(validRooms, { cancel: true })
				this.ajaxAction('moveMeepleToRoom', {
					lock: true,
					playerId: player.id,
					position: room.position
				})
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
			}
		},

		// TODO check server side discard/hand are not empty
		async doPlayerRoomEngineRoom(cards) {
			try {
				const card = await this.waitForResourceCardFromDialog(cards, {
					title: _('Select a card from the discard, to be swap with one from your hand'),
					cancel: true
				})
				const card2 = (await this.waitForResourceCardClick([this.players.getActive()], { cancel: true })).card
				this.ajaxAction('swapResourceFromDiscard', {
					lock: true,
					cardId: card.id,
					card2Id: card2.id
				})
			} catch (e) {
				this.ajaxAction('cancel', { lock: true })
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
			dojo.subscribe('addResourcesCardsOnTable', this, 'notif_addResourcesCardsOnTable')
			dojo.subscribe('updatePlayerData', this, 'notif_updatePlayerData')
			dojo.subscribe('playerPickResource', this, 'notif_playerPickResource')
			dojo.subscribe('playerDiscardResource', this, 'notif_playerDiscardResource')
			dojo.subscribe('playerShareResource', this, 'notif_playerShareResource')
			dojo.subscribe('putBackResourceCardInDeck', this, 'notif_putBackResourceCardInDeck')
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

		notif_putBackResourceCardInDeck(notif) {
			console.log('notif_putBackResourceCardInDeck', notif)
			const card = notif.args.card
			this.reorderResourceDeck.removeFromStockById(card.id, $first('.ss-resource-deck__deck'))
		},

		notif_updatePlayerData(notif) {
			console.log('notif_updatePlayerData', notif)
			this.players.getPlayerById(notif.args.player_id).setRoomPosition(notif.args.position)
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

	highlightPositions(positions) {
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
				dojo.create('div', { class: `ss-room__damage ss-room__damage--${i}` }, el)
			}
			this.divertedTokenEl = dojo.create('div', { class: 'ss-room__diverted-token' }, el)
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
		this.divertedTokenEl.classList[diverted ? 'add' : 'remove']('ss-room__diverted-token--visible')
	}

	highlight(value) {
		this.el.classList[value ? 'add' : 'remove']('ss-room-highlight')
	}
}

class SSPlayers {
	gameObject = null
	players = []

	constructor(gameObject) {
		this.gameObject = gameObject
	}

	addPlayer(player) {
		this.players.push(player)
	}

	getPlayerById(id) {
		return this.players.find(p => p.id === +id)
	}

	// Return active player
	getActive() {
		return this.getPlayerById(+this.gameObject.getActivePlayerId())
	}

	// Return inactive players (array)
	getInactive() {
		return this.players.filter(p => p.id !== +this.gameObject.getActivePlayerId())
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

	highlightMeeples(ids) {
		this.players.forEach(player => {
			player.highlightMeeple(ids === 'all' || (ids && ids.includes(player.id)))
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
		return this.gameObject.player_id == this.id && this.gameObject.getActivePlayerId() == this.id
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
		this.stock = this.gameObject.createResourceStock(handEl, null)
		this.stock.setOverlap(30, 5)
		this.gameObject.resourceTypes.forEach((type, index) => {
			this.stock.addItemType(type.id, index, g_gamethemeurl + 'img/resources.jpg', index)
		})
	}

	setRoomPosition(position, instant = false, moveOthers = true) {
		const previousPosition = this.position
		this.position = position
		if (position === null) {
			// this.meepleEl.style.display = 'none'
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

		this.gameObject.slideToObjectPos(this.meepleEl, roomEl, offsetX, offsetY, duration, 0).play()

		if (moveOthers) {
			this.gameObject.players.getAtPosition(position).forEach(p => p.setRoomPosition(position, false, false))
			if (previousPosition !== position) {
				this.gameObject.players
					.getAtPosition(previousPosition)
					.forEach(p => p.setRoomPosition(previousPosition, false, false))
			}
		}
	}

	highlightHand(value) {
		this.boardEl.classList[value ? 'add' : 'remove']('ss-player-board--highlight')
	}

	highlightMeeple(value) {
		this.meepleEl.classList[value ? 'add' : 'remove']('ss-player-meeple--highlight')
	}
}
