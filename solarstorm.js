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

			this.playAreaEl = document.getElementsByClassName('ss-play-area')[0]
			// Global area on click
			this.playAreaEl.addEventListener('click', this.onPlayAreaClick.bind(this))
		},

		initializePlayersArea() {
			for (let playerId in this.gamedatas.players) {
				const data = this.gamedatas.players[playerId]
				const order = this.gamedatas.playerorder.findIndex(p => p == playerId)
				const player = new SSPlayer(
					this,
					+playerId,
					data.name,
					data.color,
					order
				)
				this.players.addPlayer(player)
			}

			this.players.players.forEach(player => {
				player.setRoomPosition(
					this.gamedatas.ssPlayers[player.id].position,
					false,
					true
				)
			})

			for (const [playerId, playerCards] of Object.entries(
				this.gamedatas.resourceCards
			)) {
				playerCards.forEach(resourceCard => {
					this.players
						.getPlayerById(playerId)
						.stock.addToStock(resourceCard.type)
				})
			}
		},

		initializeDamageDeck() {
			this.damageDeck = new ebg.stock()
			const damageDeckEl = document.getElementsByClassName('ss-damage-deck')[0]
			this.damageDeck.create(this, damageDeckEl, 160, 117)
			this.damageDeck.setSelectionMode(0)
			this.damageDeck.extraClasses = 'ss-damage-card'
			this.damageDeck.setOverlap(20, 0)
			for (let i = 0; i < 24; i++) {
				this.damageDeck.addItemType(
					i,
					1,
					g_gamethemeurl + 'img/damages.jpg',
					i + 1
				)
			}
			for (let card of Object.values(this.gamedatas.damageCardsDiscarded)) {
				console.log(card)
				this.damageDeck.addToStock(card.type)
			}
		},

		initializeResourceDeck() {
			const resourceDeckEl = document.getElementsByClassName('ss-resource-deck')[0]

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
			for (let card of Object.values(this.gamedatas.resourceCardsOnTable)) {
				console.log(card)
				this.resourceDeck.addToStock(card.type)
			}

		},

		onScreenWidthChange(arguments) {
			this.players.assertPositions()
		},

		onPlayAreaClick(evt) {
			const el = evt.target

			// DEBUG test
			if (el.classList.contains('ss-room')) {
				dojo.stopEvent(evt)

				const room = this.rooms.getByEl(el)
				room.setDamage((room.damage + 1) % 4)

				room.setDiverted(!room.diverted)

				const playerIndex = Math.trunc(
					Math.random() * this.players.players.length
				)
				const player = this.players.players[playerIndex]
				player.setRoomPosition(room.position)
			}
		},

		///////////////////////////////////////////////////
		//// Game & client states

		// onEnteringState: this method is called each time we are entering into a new game state.
		//                  You can use this method to perform some user interface changes at this moment.
		//
		onEnteringState: function(stateName, args) {
			console.log('Entering state: ' + stateName)

			switch (stateName) {
				/* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */

				case 'dummmy':
					break
			}
		},

		// onLeavingState: this method is called each time we are leaving a game state.
		//                 You can use this method to perform some user interface changes at this moment.
		//
		onLeavingState: function(stateName) {
			console.log('Leaving state: ' + stateName)

			switch (stateName) {
				/* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */

				case 'dummmy':
					break
			}
		},

		// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
		//                        action status bar (ie: the HTML links in the status bar).
		//
		onUpdateActionButtons: function(stateName, args) {
			console.log('onUpdateActionButtons: ' + stateName)

			if (this.isCurrentPlayerActive()) {
				switch (
					stateName
					/*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
				) {
				}
			}
		},

		///////////////////////////////////////////////////
		//// Utility methods

		/*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

		///////////////////////////////////////////////////
		//// Player's action

		/*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

		/* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/solarstorm/solarstorm/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

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
				this.resourceDeck.addToStock(cardData.type)
			})
			// TODO update damageCardsNbr
		}

		// TODO: from this point and below, you can write your game notifications handling methods

		/*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
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
}

class SSRoom {
	gameObject = null
	id = null
	slug = null
	name = null
	description = null
	position = null
	damage = null
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
		let el = document.getElementsByClassName(`ss-room--${this.id}`)[0]
		if (el) {
			this.el = el
			return
		}
		const roomsEl = document.getElementsByClassName('ss-rooms')[0]
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
		this.el.classList.remove('ss-room--damaged-0')
		this.el.classList.remove('ss-room--damaged-1')
		this.el.classList.remove('ss-room--damaged-2')
		this.el.classList.remove('ss-room--damaged-3')
		this.damage = damage
		this.el.classList.add(`ss-room--damaged-${damage}`)
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

	constructor(gameObject, id, name, color, order) {
		this.gameObject = gameObject
		this.id = +id
		this.name = name
		this.color = color
		this.order = order
		this.assertBoardEl()
		this.assertMeepleEl()
		this.createStock()
		this.setRoomPosition(null)
	}

	assertBoardEl() {
		let boardEl = document.getElementsByClassName(
			`ss-players-board--id-${this.id}`
		)[0]
		if (boardEl) {
			this.boardEl = boardEl
			return
		}
		const playersArea = document.getElementsByClassName('ss-players-area')[0]
		boardEl = dojo.create(
			'div',
			{
				class: `ss-player-board ss-players-board--id-${this.id}`
			},
			playersArea
		)
		const handEl = dojo.create(
			'div',
			{
				class: 'ss-player-board__name',
				style: {
					backgroundColor: '#' + this.color
				},
				innerHTML: `Hand for ${this.name} #${this.id}`
			},
			boardEl
		)
		this.boardEl = boardEl
	}

	assertMeepleEl() {
		let meepleEl = document.getElementsByClassName(
			`ss-player-meeple--id-${this.id}`
		)[0]
		if (meepleEl) {
			this.meepleEl = meepleEl
			return
		}
		const playersArea = document.getElementsByClassName('ss-play-area')[0]
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

		const index = this.gameObject.players
			.getAtPosition(position)
			.sort(p => p.order)
			.findIndex(p => p.id === this.id)
		const offsetX = index * 20 + roomPos.w * 0.2
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
}
