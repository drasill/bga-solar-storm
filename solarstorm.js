function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

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
  return document.querySelectorAll(selector)[0];
}

define(['dojo', 'dojo/_base/declare', 'ebg/core/gamegui', 'ebg/counter', 'ebg/stock'], function (dojo, declare) {
  return declare('bgagame.solarstorm', ebg.core.gamegui, {
    constructor: function () {
      this.rooms = new SSRooms();
      this.players = new SSPlayers(this);
      this.resourceTypes = [];
      this.damageDeck = null;
      this.resourceDeck = null;
      this.reorderResourceDeck = null;
      this.reorderDamageDeck = null;
    },
    setup: function (gamedatas) {
      this.resourceTypes = gamedatas.resourceTypes;
      this.initializePlayArea();
      this.initializePlayersArea();
      this.initializeDamageDeck();
      this.initializeResourceDeck();
      this.setupNotifications();
    },

    initializePlayArea() {
      // Initialize rooms
      this.gamedatas.rooms.forEach(roomData => {
        const room = new SSRoom(this, roomData);
        this.rooms.addRoom(room);
      });
      document.addEventListener('mouseover', e => {
        if (e.target && e.target.classList && e.target.classList.contains('ss-room-name')) {
          const room = this.rooms.getBySlug(e.target.getAttribute('data-room'));
          room.highlightHover(true);
        }
      });
      document.addEventListener('mouseout', e => {
        if (e.target && e.target.classList && e.target.classList.contains('ss-room-name')) {
          const room = this.rooms.getBySlug(e.target.getAttribute('data-room'));
          room.highlightHover(false);
        }
      });
    },

    initializePlayersArea() {
      const playersData = this.gamedatas.ssPlayers.sort((p1, p2) => p1.order - p2.order);
      playersData.forEach(data => {
        const player = new SSPlayer(this, data.id, data.name, data.color, data.order, data.position, data.actionsTokens);
        this.players.addPlayer(player);
      });

      for (const [playerId, playerCards] of Object.entries(this.gamedatas.resourceCards)) {
        playerCards.forEach(resourceCard => {
          this.players.getPlayerById(playerId).stock.addToStockWithId(resourceCard.type, resourceCard.id);
        });
      }

      this.rooms.rooms.forEach(r => r.updateProtectionTokens());
    },

    initializeDamageDeck() {
      $first('.ss-damage-deck__title').innerHTML = '<span>' + _('Damage cards') + '</span>';
      const damageDeckEl = $first('.ss-damage-deck');
      this.damageDeck = this.createDamageStock(damageDeckEl);
      this.damageDeck.setOverlap(0.01);
      this.damageDeck.setSelectionMode(0);

      for (let card of Object.values(this.gamedatas.damageCardsDiscarded)) {
        this.damageDeck.addToStock(card.type);
      }

      this.addTooltipMarkdown(damageDeckEl, _("Damage deck.\n----\nAt then **end of turn** of each player, a new card is revealed, indicating rooms which are damaged.\nThe deck is ordered like this :\n+ 8 damage cards with 1 room\n+ 8 damage cards with 2 rooms\n+ 8 damage cards with 3 rooms.\nWhen the deck is empty, the ship' hull starts taking damage, and resources will be discarded from the deck, accelerating the end of the game !\n----\n+ Note: at the start of the game, two damage cards are revealed from the bottom (applying 6 damages).\n+ Note 2: if a room has a *protection* token, instead of taking damage, the protection token is removed."), {}, 1000);
      const reorderDamageDeckEl = $first('.ss-damage-reorder-deck');
      this.reorderDamageDeck = this.createDamageStock(reorderDamageDeckEl);
    },

    initializeResourceDeck() {
      $first('.ss-resource-deck__title').innerHTML = '<span>' + _('Resource cards') + '</span>';
      const resourceDeckEl = $first('.ss-resource-deck__table');
      this.resourceDeck = this.createResourceStock(resourceDeckEl);
      this.resourceDeck.setOverlap(50);
      this.resourceDeck.setSelectionMode(0);

      for (let card of Object.values(this.gamedatas.resourceCardsOnTable)) {
        this.resourceDeck.addToStockWithId(card.type, card.id);
      }

      const reorderResourceDeckEl = $first('.ss-resource-reorder-deck');
      this.reorderResourceDeck = this.createResourceStock(reorderResourceDeckEl);
      this.addTooltipMarkdown($first('.ss-resource-deck__deck'), _('Resource deck.\n----\nAt the **end of their turn**, a player can pick either:\n+ 2 cards from this deck (*face down*),\n+ or 1 card among the 2 revealed.\n----**Important :** When the resource deck is depleted, the game is instantly lost.\n----At the start, there was a total of ${num} resources cards in this deck.'), {
        num: this.gamedatas.resourceCardsNbrInitial
      }, 1000);
      this.updateResourceCardsNbr(this.gamedatas.resourceCardsNbr);
    },

    onScreenWidthChange() {
      this.players.assertPositions();
    },

    ///////////////////////////////////////////////////
    //// Game & client states
    onEnteringState: function (stateName, args) {
      console.log('Entering state: ' + stateName, args);

      if (stateName === 'playerTurn') {
        // Display for all players
        const leftStr = dojo.string.substitute(_('(${n} left)'), {
          n: args.args.actions
        });
        this.gamedatas.gamestate.descriptionmyturn += ' ' + leftStr;
        this.gamedatas.gamestate.description += ' ' + leftStr;
        this.updatePageTitle();
        return;
      } // Now, only for active player


      if (!this.isCurrentPlayerActive()) {
        return;
      }

      switch (stateName) {
        case 'playerMove':
          this.doPlayerActionMove(args.args.possibleDestinations);
          break;

        case 'playerScavengePickCards':
          this.doPlayerPickResources(args.args.possibleSources);
          break;

        case 'playerDiscardResources':
          this.doPlayerActionDiscardResource();
          break;

        case 'playerRepair':
          this.doPlayerActionRepair();
          break;

        case 'playerDivert':
          this.doPlayerActionDivert();
          break;

        case 'playerRoomCrewQuarter':
          this.doPlayerRoomCrewQuarter();
          break;

        case 'playerRoomCargoHold':
          this.doPlayerRoomCargoHold(Object.values(args.args._private.resourceCards));
          break;

        case 'playerRoomEngineRoom':
          this.doPlayerRoomEngineRoom(args.args.resourceCards);
          break;

        case 'playerRoomRepairCentre':
          this.doPlayerRoomRepairCentre();
          break;

        case 'playerRoomArmoury':
          const tokensLeft = args.args.tokensLeft;
          const tokensToPut = Math.min(2, tokensLeft);

          if (tokensLeft < 2) {
            this.multipleChoiceDialog(_('There is only one protection token left; are you sure you want to do this action ?'), [_('Yes'), _('Cancel')], choice => {
              if (choice == 0) {
                this.doPlayerRoomArmoury(tokensToPut);
              } else {
                this.ajaxAction('cancel', {
                  lock: true
                });
              }
            });
          } else {
            this.doPlayerRoomArmoury(tokensToPut);
          }

          break;

        case 'playerRoomBridge':
          this.doPlayerRoomBridge(Object.values(args.args._private.damageCards));
          break;

        case 'pickResources':
          this.doPlayerPickResources(args.args.possibleSources);
          break;
      }
    },
    onLeavingState: function (stateName) {
      console.log('Leaving state: ' + stateName);

      switch (stateName) {}
    },
    onUpdateActionButtons: function (stateName, args) {
      console.log('onUpdateActionButtons: ' + stateName, args);

      if (this.isCurrentPlayerActive()) {
        const player = this.players.getActive();

        switch (stateName) {
          case 'playerTurn':
            this.addActionButton('buttonMove', _('Move'), evt => {
              this.onPlayerChooseAction(evt, 'move');
            });
            this.addActionButton('buttonScavenge', _('Scavenge'), evt => {
              this.onPlayerChooseAction(evt, 'scavenge');
            });
            this.addActionButton('buttonShare', _('Share'), evt => {
              this.onPlayerChooseAction(evt, 'share');
            });
            this.addActionButton('buttonRepair', _('Repair'), evt => {
              this.onPlayerChooseAction(evt, 'repair');
            });
            this.addActionButton('buttonRoom', _('Room action'), evt => {
              this.onPlayerChooseAction(evt, 'room');
            });
            this.addActionButton('buttonDivert', _('Divert'), evt => {
              this.onPlayerChooseAction(evt, 'divert');
            });
            this.addActionButton('buttonToken', _('Take action token'), evt => {
              this.onPlayerChooseAction(evt, 'token');
            });

            if (player.actionsTokens > 0) {
              this.addActionButton('buttonUseToken', _('Use action token'), evt => {
                this.ajaxAction('useToken', {
                  lock: true
                });
              });
            }

            break;

          case 'playerShare':
            this.addActionButton('shareGive', _('Give a card'), evt => {
              this.doPlayerActionGiveResource(true);
            });
            this.addActionButton('shareTake', _('Take a card'), evt => {
              this.doPlayerActionTakeResource(true);
            });
            break;

          case 'playerScavenge':
            this.addActionButton('buttonRollDice', _('Roll dice'), evt => {
              this.ajaxAction('rollDice', {
                lock: true
              });
            });
            this.showActionCancelButton(() => {
              this.ajaxAction('cancel', {
                lock: true
              });
            });
            break;

          case 'playerRoomMessHall':
            this.addActionButton('messHallGive', _('Give a card'), evt => {
              this.doPlayerActionGiveResource(false);
            });
            this.addActionButton('messHallTake', _('Take a card'), evt => {
              this.doPlayerActionTakeResource(false);
            });
            this.addActionButton('messHallSwap', _('Swap a card'), evt => {
              this.doPlayerActionSwapResource();
            });
            break;

          case 'playerAskActionTokensPlay':
            this.addActionButton('buttonUseToken', _('Use action token'), evt => {
              this.ajaxAction('useToken', {
                lock: true
              });
            });
            this.addActionButton('buttonDontUseToken', _('End turn'), evt => {
              this.ajaxAction('dontUseToken', {
                lock: true
              });
            });
            break;
        }
      }
    },

    showActionCancelButton(callback) {
      this.removeActionCancelButton();
      this.addActionButton('actionCancelButton', _('Cancel'), callback, null, null, 'gray');
    },

    removeActionCancelButton() {
      const el = $('actionCancelButton');

      if (el) {
        el.remove();
      }
    },

    ///////////////////////////////////////////////////
    //// Utility methods
    createResourceStock(el) {
      const stock = new ebg.stock();
      stock.create(this, el, 72, 100);
      stock.setSelectionMode(1);
      stock.extraClasses = 'ss-resource-card';
      stock.setSelectionAppearance('class');

      stock.onItemCreate = (el, id) => {
        const type = this.resourceTypes.find(r => r.id === id);
        this.addTooltipMarkdown(el, _('Resource card of type: **${type}** ${detail}\n----\nUsed to **repair** or **divert** power in the rooms.\nMaximum 6 cards in the player hand (at the end of turn).'), {
          type: type.nametr,
          detail: id === 'universal' ? _('(can be used as any other resource)') : ''
        }, 250);
      };

      this.resourceTypes.forEach((type, index) => {
        stock.addItemType(type.id, index, g_gamethemeurl + 'img/resources.jpg', index);
      });
      return stock;
    },

    createDamageStock(el) {
      const stock = new ebg.stock();
      stock.create(this, el, 123, 90);
      stock.setSelectionMode(1);
      stock.extraClasses = 'ss-damage-card';
      stock.setSelectionAppearance('class');

      for (let i = 0; i < 24; i++) {
        stock.addItemType(i, 1, g_gamethemeurl + 'img/damages.jpg', i + 1);
      }

      return stock;
    },

    highlightEl(el, value, cls = 'ss-highlight') {
      if (typeof el === 'string') {
        el = $first(el);
      }

      el.classList[value ? 'add' : 'remove'](cls);
    },

    setVisibleEl(el, value) {
      if (typeof el === 'string') {
        el = $first(el);
      }

      el.classList[value ? 'add' : 'remove']('ss-visible');
    },

    connectStockCardClick(stock, callback) {
      return dojo.connect(stock, 'onChangeSelection', () => {
        const cards = stock.getSelectedItems();
        const card = cards[0];

        if (card) {
          stock.unselectAll();
          callback(card);
        }
      });
    },

    waitForResourceFromDeck(options = {}) {
      // Default options
      options = Object.assign({
        table: true,
        deck: true,
        cancel: false
      }, options);
      return new Promise((resolve, reject) => {
        const handles = [];
        const types = ['table', 'deck'];

        const cleanAll = () => {
          // De-Highlight
          types.forEach(type => {
            this.highlightEl(`.ss-resource-deck__${type}`, false);
          });
          handles.forEach(handle => dojo.disconnect(handle));
          this.resourceDeck.setSelectionMode(0);
          this.removeActionCancelButton();
        }; // Highlight


        types.forEach(type => {
          this.highlightEl(`.ss-resource-deck__${type}`, options[type]);
        });

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        } // Wait for click


        if (options.table) {
          this.resourceDeck.setSelectionMode(1);
          handles.push(this.connectStockCardClick(this.resourceDeck, card => {
            cleanAll();
            resolve(card);
          }));
        }

        if (options.deck) {
          handles.push(dojo.connect($first('.ss-resource-deck__source'), 'onclick', () => {
            cleanAll();
            resolve({
              id: 9999
            });
          }));
        }
      });
    },

    waitForPlayerResource(players, options = {}) {
      // Default options
      options = Object.assign({
        cancel: false
      }, options);
      const ids = players.map(p => p.id);
      this.players.highlightHands(ids);
      return new Promise((resolve, reject) => {
        const handles = [];

        const cleanAll = () => {
          this.players.highlightHands(null);
          handles.forEach(handle => dojo.disconnect(handle));
          players.forEach(player => {
            player.stock.setSelectionMode(0);
          });

          if (options.cancel) {
            this.removeActionCancelButton();
          }
        };

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }

        players.forEach(player => {
          player.stock.setSelectionMode(1);
          handles.push(this.connectStockCardClick(player.stock, card => {
            cleanAll();
            resolve({
              card,
              player
            });
          }));
        });
      });
    },

    waitForPlayerResources(options = {}) {
      // Default options
      options = Object.assign({
        count: 1,
        cancel: false
      }, options);
      return new Promise((resolve, reject) => {
        const handles = [];
        const player = this.players.getActive();
        player.highlightHand(true);

        const cleanAll = () => {
          player.highlightHand(false);
          player.stock.setSelectionMode(0);
          handles.forEach(handle => dojo.disconnect(handle));
          this.removeActionCancelButton();
          $('buttonAccept').remove();
        };

        player.stock.setSelectionMode(2);
        this.addActionButton('buttonAccept', _('Accept'), () => {
          const cards = player.stock.getSelectedItems();

          if (cards.length !== options.count) {
            gameui.showMessage(_(`You must select ${options.count} cards`), 'error');
            return;
          }

          cleanAll();
          resolve(cards);
        });

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }
      });
    },

    waitForRoomClick(rooms, options = {}) {
      // Default options
      options = Object.assign({
        cancel: false
      }, options);
      const positions = rooms.map(r => r.position);
      this.rooms.highlightPositions(positions);
      return new Promise((resolve, reject) => {
        const handles = [];

        const cleanAll = () => {
          handles.forEach(handle => dojo.disconnect(handle));

          if (options.cancel) {
            this.removeActionCancelButton();
          }
        };

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
            this.rooms.highlightPositions(null);
          });
        }

        rooms.forEach(room => {
          handles.push(dojo.connect(room.el, 'onclick', () => {
            this.rooms.highlightPositions(null);
            cleanAll();
            resolve(room);
          }));
        });
      });
    },

    waitForPlayerMeepleClick(players, options = {}) {
      // Default options
      options = Object.assign({
        cancel: false
      }, options);
      const ids = players.map(p => p.id);
      this.players.highlightMeeples(ids);
      return new Promise((resolve, reject) => {
        const handles = [];

        const cleanAll = () => {
          handles.forEach(handle => dojo.disconnect(handle));
          this.removeActionCancelButton();
          this.players.highlightMeeples(null);
        };

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }

        players.forEach(player => {
          handles.push(dojo.connect(player.meepleEl, 'onclick', () => {
            cleanAll();
            resolve(player);
          }));
        });
      });
    },

    waitForResourceCardFromDialog(cards, options = {}) {
      // Default options
      options = Object.assign({
        cancel: false,
        title: ''
      }, options);
      return new Promise((resolve, reject) => {
        const handles = [];
        const dialogEl = $first('.ss-resource-reorder-dialog');

        const cleanAll = () => {
          handles.forEach(handle => dojo.disconnect(handle));
          this.setVisibleEl(dialogEl, false);
          this.removeActionCancelButton();
        };

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }

        this.reorderResourceDeck.unselectAll();
        this.reorderResourceDeck.removeAll();
        $first('.ss-resource-reorder-dialog__title').innerHTML = options.title;
        this.setVisibleEl(dialogEl, true);

        for (let card of Object.values(cards)) {
          this.reorderResourceDeck.addToStockWithId(card.type, card.id);
        }

        this.reorderResourceDeck.setSelectionMode(1);
        handles.push(this.connectStockCardClick(this.reorderResourceDeck, card => {
          cleanAll();
          resolve(card);
        }));
      });
    },

    waitForResourceCardOrderFromDialog(cards, options = {}) {
      // Default options
      options = Object.assign({
        cancel: false,
        count: cards.length,
        title: ''
      }, options);
      return new Promise((resolve, reject) => {
        let selectedCards = [];
        const handles = [];
        const dialogEl = $first('.ss-resource-reorder-dialog');

        const cleanAll = () => {
          handles.forEach(handle => dojo.disconnect(handle));
          this.setVisibleEl(dialogEl, false);
          this.removeActionCancelButton();
          $('buttonAccept').remove();
          $('buttonReset').remove();
        };

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }

        this.reorderResourceDeck.unselectAll();
        this.reorderResourceDeck.removeAll();
        $first('.ss-resource-reorder-dialog__title').innerHTML = options.title;
        this.setVisibleEl(dialogEl, true);

        for (let card of Object.values(cards)) {
          this.reorderResourceDeck.addToStockWithId(card.type, card.id);
        }

        this.reorderResourceDeck.setSelectionMode(2);
        this.addActionButton('buttonReset', _('Restart selection'), () => {
          selectedCards.forEach(card => {
            this.reorderResourceDeck.addToStockWithId(card.type, card.id);
          });
          selectedCards = [];
        });
        this.addActionButton('buttonAccept', _('Accept'), () => {
          if (selectedCards.length !== options.count) {
            gameui.showMessage(_(`You must select ${options.count} cards`), 'error');
            return;
          }

          cleanAll();
          resolve(selectedCards);
        });
        this.reorderResourceDeck.setSelectionMode(1);
        handles.push(this.connectStockCardClick(this.reorderResourceDeck, card => {
          selectedCards.push(card);
          this.reorderResourceDeck.removeFromStockById(card.id);
        }));
      });
    },

    waitForDamageCardOrderFromDialog(cards, options = {}) {
      // Default options
      options = Object.assign({
        cancel: false,
        count: cards.length,
        title: ''
      }, options);
      return new Promise((resolve, reject) => {
        let selectedCards = [];
        const handles = [];
        const dialogEl = $first('.ss-damage-reorder-dialog');

        const cleanAll = () => {
          handles.forEach(handle => dojo.disconnect(handle));
          this.setVisibleEl(dialogEl, false);
          this.removeActionCancelButton();
          $('buttonAccept').remove();
          $('buttonReset').remove();
        };

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }

        this.reorderDamageDeck.unselectAll();
        this.reorderDamageDeck.removeAll();
        $first('.ss-damage-reorder-dialog__title').innerHTML = options.title;
        this.setVisibleEl(dialogEl, true);

        for (let card of Object.values(cards)) {
          this.reorderDamageDeck.addToStockWithId(card.type, card.id);
        }

        this.reorderDamageDeck.setSelectionMode(2);
        this.addActionButton('buttonReset', _('Restart selection'), () => {
          selectedCards.forEach(card => {
            this.reorderDamageDeck.addToStockWithId(card.type, card.id);
          });
          selectedCards = [];
        });
        this.addActionButton('buttonAccept', _('Accept'), () => {
          if (selectedCards.length !== options.count) {
            gameui.showMessage(_(`You must select ${options.count} cards`), 'error');
            return;
          }

          cleanAll();
          resolve(selectedCards);
        });
        this.reorderDamageDeck.setSelectionMode(1);
        handles.push(this.connectStockCardClick(this.reorderDamageDeck, card => {
          selectedCards.push(card);
          this.reorderDamageDeck.removeFromStockById(card.id);
        }));
      });
    },

    waitForResourceType(options = {}) {
      // Default options
      options = Object.assign({
        cancel: false
      }, options);

      const cleanAll = () => {
        this.removeActionButtons();
      };

      return new Promise((resolve, reject) => {
        this.resourceTypes.filter(r => r.id !== 'universal').forEach(resourceType => {
          this.addActionButton(`buttonResourceType__${resourceType.id}`, `<span class="ss-resource-card-icon ss-resource-card-icon--medium ss-resource-card-icon--${resourceType.id}"></span>${resourceType.name}`, () => {
            cleanAll();
            resolve(resourceType);
          });
        });

        if (options.cancel) {
          this.showActionCancelButton(() => {
            cleanAll();
            reject('CANCEL BTN');
          });
        }
      });
    },

    updateResourceCardsNbr(num) {
      $first('.ss-resource-deck__deck__number').innerHTML = num;
    },

    addTooltipMarkdown(el, text, args = {}, delay = 250) {
      const id = this.getElId(el);
      const content = '<div class="ss-tooltip-markdown">' + markdownSubstitute(text, args) + '</div>';
      this.addTooltipHtml(id, content, delay);
    },

    getElId: (() => {
      let idCounter = 0;
      return el => {
        if (el.id) {
          return el.id;
        }

        idCounter++;
        const id = 'el-' + idCounter;
        el.id = id;
        return id;
      };
    })(),

    ///////////////////////////////////////////////////
    //// Player's action
    ajaxAction(action, args, check = true) {
      console.log('ajaxAction', action, args, check);

      if (check & !this.checkAction(action)) {
        return;
      }

      return new Promise((resolve, reject) => {
        this.ajaxcall(`/solarstorm/solarstorm/${action}.html`, args, this, function (result) {
          resolve(result);
        }, function (is_error) {
          reject(is_error);
        });
      });
    },

    onPlayerChooseAction(evt, action) {
      dojo.stopEvent(evt);
      this.ajaxAction('choose', {
        lock: true,
        actionName: action
      });
    },

    async doPlayerActionMove(possibleDestinations) {
      try {
        const rooms = possibleDestinations.map(p => this.rooms.getByPosition(p));
        const room = await this.waitForRoomClick(rooms, {
          cancel: true
        });
        await this.ajaxAction('move', {
          lock: true,
          position: room.position
        });
      } catch (e) {
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerActionDiscardResource() {
      const card = (await this.waitForPlayerResource([this.players.getActive()])).card;
      await this.ajaxAction('discardResource', {
        lock: true,
        cardId: card.id
      });
    },

    async doPlayerActionGiveResource(sameRoomOnly = true) {
      this.gamedatas.gamestate.descriptionmyturn = _('You mush choose a card to give');
      this.updatePageTitle();
      this.removeActionButtons();

      try {
        const activePlayer = this.players.getActive();
        const card = (await this.waitForPlayerResource([activePlayer], {
          cancel: true
        })).card;
        this.gamedatas.gamestate.descriptionmyturn = _('You mush choose a player to give the card to');
        this.updatePageTitle();
        this.removeActionButtons();
        const targetPlayers = this.players.getInactive().filter(p => !sameRoomOnly || p.position === activePlayer.position);
        const player = (await this.waitForPlayerResource(targetPlayers, {
          cancel: true
        })).player;
        await this.ajaxAction('giveResourceToAnotherPlayer', {
          lock: true,
          cardId: card.id,
          playerId: player.id
        });
      } catch (e) {
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerActionTakeResource(sameRoomOnly = true) {
      this.gamedatas.gamestate.descriptionmyturn = _('You mush choose a card to take');
      this.updatePageTitle();
      this.removeActionButtons();

      try {
        const activePlayer = this.players.getActive();
        const targetPlayers = this.players.getInactive().filter(p => !sameRoomOnly || p.position === activePlayer.position);
        const card = (await this.waitForPlayerResource(targetPlayers, {
          cancel: true
        })).card;
        await this.ajaxAction('pickResourceFromAnotherPlayer', {
          lock: true,
          cardId: card.id
        });
      } catch (e) {
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerActionSwapResource() {
      this.gamedatas.gamestate.descriptionmyturn = _('Swap cards : You mush choose a card to give');
      this.updatePageTitle();
      this.removeActionButtons();

      try {
        const card1 = (await this.waitForPlayerResource([this.players.getActive()], {
          cancel: true
        })).card;
        this.gamedatas.gamestate.descriptionmyturn = _('Swap cards : You mush choose a card to exchange');
        this.updatePageTitle();
        this.removeActionButtons();
        const card2 = (await this.waitForPlayerResource(this.players.getInactive(), {
          cancel: true
        })).card;
        await this.ajaxAction('swapResourceWithAnotherPlayer', {
          lock: true,
          cardId: card1.id,
          card2Id: card2.id
        });
      } catch (e) {
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerActionRepair() {
      try {
        const card = (await this.waitForPlayerResource([this.players.getActive()], {
          cancel: true
        })).card;
        let resourceTypeId = null;

        if (card.type === 'universal') {
          resourceTypeId = (await this.waitForResourceType({
            cancel: true
          })).id;
        }

        await this.ajaxAction('selectResourceForRepair', {
          lock: true,
          cardId: card.id,
          resourceType: resourceTypeId
        });
      } catch (e) {
        console.error(e);
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerActionDivert() {
      try {
        const cards = await this.waitForPlayerResources({
          count: 3,
          cancel: true
        });
        await this.ajaxAction('selectResourcesForDivert', {
          lock: true,
          cardIds: cards.map(c => c.id).join(',')
        });
      } catch (e) {
        console.error(e);
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerRoomCargoHold(cards) {
      const selectedCards = await this.waitForResourceCardOrderFromDialog(cards, {
        title: _('Reorder the next resource cards.') + '<br/>' + 'The first ones you select will be on <b>top</b> of the deck.',
        cancel: false
      });
      await this.ajaxAction('putBackResourceCardsInDeck', {
        lock: true,
        cardIds: selectedCards.map(c => c.id).join(',')
      });
    },

    async doPlayerRoomBridge(cards) {
      const selectedCards = await this.waitForDamageCardOrderFromDialog(cards, {
        title: _('Reorder the next damage cards.') + '<br/>' + 'The first ones you select will be on <b>top</b> of the deck.',
        cancel: false
      });
      await this.ajaxAction('putBackDamageCardsInDeck', {
        lock: true,
        cardIds: selectedCards.map(c => c.id).join(',')
      });
    },

    async doPlayerRoomCrewQuarter() {
      try {
        const player = await this.waitForPlayerMeepleClick(this.players.players, {
          cancel: true
        });
        this.gamedatas.gamestate.descriptionmyturn = _('Crew Quarters: You mush choose a destination');
        this.updatePageTitle();
        this.removeActionButtons(); // Valid rooms are where there are meeples

        const validRooms = this.players.players.map(p => p.position).map(p => this.rooms.getByPosition(p));
        const room = await this.waitForRoomClick(validRooms, {
          cancel: true
        });
        await this.ajaxAction('moveMeepleToRoom', {
          lock: true,
          playerId: player.id,
          position: room.position
        });
      } catch (e) {
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerRoomEngineRoom(cards) {
      try {
        const card = await this.waitForResourceCardFromDialog(cards, {
          title: _('Select a card from the discard, to be swapped with one from your hand'),
          cancel: true
        });
        const card2 = (await this.waitForPlayerResource([this.players.getActive()], {
          cancel: true
        })).card;
        await this.ajaxAction('swapResourceFromDiscard', {
          lock: true,
          cardId: card.id,
          card2Id: card2.id
        });
      } catch (e) {
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerRoomRepairCentre() {
      try {
        const room = await this.waitForRoomClick(this.rooms.rooms, {
          cancel: true
        });
        this.gamedatas.gamestate.descriptionmyturn = dojo.string.substitute(_('Repair Centre: You mush choose a resource to repair: ${room}'), {
          room: room.name
        });
        this.updatePageTitle();
        this.removeActionButtons();
        const card = (await this.waitForPlayerResource([this.players.getActive()], {
          cancel: true
        })).card;
        let resourceTypeId = null;

        if (card.type === 'universal') {
          resourceTypeId = (await this.waitForResourceType({
            cancel: true
          })).id;
        }

        await this.ajaxAction('selectResourceForRepair', {
          lock: true,
          cardId: card.id,
          position: room.position,
          resourceType: resourceTypeId
        });
      } catch (e) {
        console.error(e);
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerRoomArmoury(tokensToPut) {
      const onCancel = [];

      try {
        const validRooms = this.rooms.rooms.filter(r => r.slug !== 'energy-core');
        const positions = [];

        for (let i = 0; i < tokensToPut; i++) {
          const room = await this.waitForRoomClick(validRooms, {
            cancel: true
          });
          positions.push(room.position);
          room.protection.push(this.players.getActive().id);
          room.updateProtectionTokens();
          onCancel.push(() => {
            room.protection.pop();
            room.updateProtectionTokens();
          });
        }

        await this.ajaxAction('putProtectionTokens', {
          lock: true,
          positions: positions.join(',')
        });
      } catch (e) {
        console.error(e);
        onCancel.forEach(f => f());
        await this.ajaxAction('cancel', {
          lock: true
        });
      }
    },

    async doPlayerPickResources(possibleSources) {
      const card = await this.waitForResourceFromDeck({
        cancel: false,
        deck: possibleSources.includes('deck'),
        table: possibleSources.includes('table')
      });
      await this.ajaxAction('pickResource', {
        lock: true,
        cardId: card.id
      });
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
              setupNotifications:
               In this method, you associate each of your game notifications with your local method to handle it.
               Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                    your solarstorm.game.php file.
           */
    setupNotifications: function () {
      dojo.subscribe('updateRooms', this, 'notif_updateRooms');
      dojo.subscribe('updateDamageDiscard', this, 'notif_updateDamageDiscard');
      dojo.subscribe('addResourcesCardsOnTable', this, 'notif_addResourcesCardsOnTable');
      dojo.subscribe('updatePlayerData', this, 'notif_updatePlayerData');
      dojo.subscribe('playerPickResource', this, 'notif_playerPickResource');
      dojo.subscribe('playerDiscardResource', this, 'notif_playerDiscardResource');
      dojo.subscribe('playerShareResource', this, 'notif_playerShareResource');
      dojo.subscribe('putBackResourcesCardInDeck', this, 'notif_putBackResourcesCardInDeck');
    },

    notif_updateRooms(notif) {
      console.log('notif_updateRooms', notif);
      notif.args.rooms.forEach(roomData => {
        const room = this.rooms.getBySlug(roomData.slug);
        room.setDamage(roomData.damage);
        room.setDiverted(roomData.diverted);
        room.setDestroyed(roomData.destroyed);
        room.setProtection(roomData.protection);

        if (roomData.shake) {
          room.shake();
        }
      });
    },

    notif_updateDamageDiscard(notif) {
      console.log('notif_updateDamageDiscard', notif);
      notif.args.cards.forEach(cardData => {
        this.damageDeck.addToStock(cardData.type);
      });
    },

    notif_addResourcesCardsOnTable(notif) {
      console.log('notif_addResourcesCardsOnTable', notif);
      notif.args.cards.forEach(cardData => {
        this.resourceDeck.addToStockWithId(cardData.type, cardData.id);
      });
      this.updateResourceCardsNbr(notif.args.resourceCardsNbr);
    },

    notif_playerPickResource(notif) {
      console.log('notif_playerPickResource', notif);
      const card = notif.args.card;
      this.resourceDeck.removeFromStockById(card.id);
      const player = this.players.getPlayerById(notif.args.player_id);
      player.stock.addToStockWithId(card.type, card.id);

      if (notif.args.resourceCardsNbr) {
        this.updateResourceCardsNbr(notif.args.resourceCardsNbr);
      } // TODO animation

    },

    notif_playerDiscardResource(notif) {
      console.log('notif_playerDiscardResource', notif);
      const cards = notif.args.cards ? notif.args.cards : [notif.args.card];
      const player = this.players.getPlayerById(notif.args.player_id);
      cards.forEach(card => player.stock.removeFromStockById(card.id)); // TODO animation
    },

    notif_playerShareResource(notif) {
      console.log('notif_playerShareResource', notif);
      const card = notif.args.card;
      const player = this.players.getPlayerById(notif.args.player_id);

      if (notif.args.shareAction === 'take') {
        const fromPlayer = this.players.getPlayerById(notif.args.from_player_id);
        fromPlayer.stock.removeFromStockById(card.id);
        player.stock.addToStockWithId(card.type, card.id);
      } else {
        const toPlayer = this.players.getPlayerById(notif.args.to_player_id);
        player.stock.removeFromStockById(card.id);
        toPlayer.stock.addToStockWithId(card.type, card.id);
      } // TODO animation

    },

    notif_putBackResourcesCardInDeck(notif) {
      console.log('notif_putBackResourcesCardInDeck', notif);
      const card = notif.args.card;
      this.reorderResourceDeck.removeFromStockById(card.id, $first('.ss-resource-deck__deck'));
    },

    notif_updatePlayerData(notif) {
      console.log('notif_updatePlayerData', notif);
      const player = this.players.getPlayerById(notif.args.player_id);
      player.setRoomPosition(notif.args.position);
      player.setActionsTokens(notif.args.actionsTokens);
    },

    /* This enable to inject translatable styled things to logs or action bar */

    /* @Override */
    format_string_recursive: function (log, args) {
      try {
        if (log && args && !args.processed) {
          args.processed = true; // Representation of a resource card type

          if (args.resourceType !== undefined) {
            const type = this.resourceTypes.find(r => r.id === args.resourceType);
            args.resourceType = dojo.string.substitute('<span class="ss-resource-card-icon ss-resource-card-icon--small ss-resource-card-icon--${resourceType}"></span>${resourceName}', {
              resourceType: type.id,
              resourceName: type.nametr
            });
          } // Representation of a resource card type (2)


          if (args.resourceType2 !== undefined) {
            const type = this.resourceTypes.find(r => r.id === args.resourceType2);
            args.resourceType2 = dojo.string.substitute('<span class="ss-resource-card-icon ss-resource-card-icon--small ss-resource-card-icon--${resourceType2}"></span>${resourceName}', {
              resourceType2: type.id,
              resourceName: type.nametr
            });
          } // Representation of a many resource card types


          if (args.resourceTypes !== undefined) {
            const str = args.resourceTypes.map(resourceType => {
              const type = this.resourceTypes.find(r => r.id === resourceType);
              return dojo.string.substitute('<span class="ss-resource-card-icon ss-resource-card-icon--small ss-resource-card-icon--${resourceType}"></span>${resourceName}', {
                resourceType: type.id,
                resourceName: type.nametr
              });
            });
            args.resourceTypes = str.join(', ');
          } // Representation of a room name


          if (args.roomName !== undefined) {
            const room = this.rooms.getBySlug(args.roomName);
            args.roomName = dojo.string.substitute('<span class="ss-room-name" data-room="${roomSlug}" style="color: ${roomColor}">${roomName}</span>', {
              roomName: room.name,
              roomColor: room.color,
              roomSlug: room.slug
            });
          } // Representation of room names


          if (args.roomNames !== undefined) {
            const str = args.roomNames.map(roomName => {
              const room = this.rooms.getBySlug(roomName);
              return dojo.string.substitute('<span class="ss-room-name" data-room="${roomSlug}" style="color: ${roomColor}">${roomName}</span>', {
                roomName: room.name,
                roomColor: room.color,
                roomSlug: room.slug
              });
            });
            args.roomNames = str.join(', ');
          }
        }
      } catch (e) {
        console.error(log, args, 'Exception thrown', e.stack);
      }

      return this.inherited(arguments);
    }
  });
});

class SSRooms {
  constructor() {
    _defineProperty(this, "rooms", []);
  }

  addRoom(room) {
    this.rooms.push(room);
  }

  getByEl(el) {
    return this.rooms.find(r => r.el === el);
  }

  getBySlug(slug) {
    return this.rooms.find(r => r.slug === slug);
  }

  getByPosition(position) {
    return this.rooms.find(r => r.position === position);
  }

  highlightPositions(positions) {
    this.rooms.forEach(room => {
      room.highlight(positions && positions.includes(room.position));
    });
  }

}

class SSRoom {
  constructor(gameObject, data) {
    _defineProperty(this, "gameObject", null);

    _defineProperty(this, "id", null);

    _defineProperty(this, "slug", null);

    _defineProperty(this, "name", null);

    _defineProperty(this, "color", null);

    _defineProperty(this, "description", null);

    _defineProperty(this, "position", null);

    _defineProperty(this, "damage", [false, false, false]);

    _defineProperty(this, "diverted", false);

    _defineProperty(this, "destroyed", false);

    _defineProperty(this, "protection", []);

    _defineProperty(this, "el", null);

    _defineProperty(this, "divertedTokenEl", null);

    _defineProperty(this, "shakeTimeout", null);

    this.gameObject = gameObject;
    this.id = data.id;
    this.slug = data.slug;
    this.name = data.name;
    this.color = data.color;
    this.description = data.description;
    this.position = data.position;
    this.assertEl();
    this.setDamage(data.damage);
    this.setDiverted(data.diverted);
    this.setDestroyed(data.destroyed);
    this.setProtection(data.protection);
  }

  assertEl() {
    let el = $first(`.ss-room--${this.id}`);

    if (el) {
      this.el = el;
      return;
    }

    const roomsEl = $first('.ss-rooms');
    el = dojo.create('div', {
      id: `ss-room--${this.id}`,
      class: `ss-room ss-room--pos-${this.position} ss-room--${this.id}`
    }, roomsEl);
    this.gameObject.addTooltipMarkdown(el, `<div class="ss-room ss-room-tooltip ss-room--${this.id}"></div>**<span class="ss-room-name" data-room="${this.slug}" style="color: ${this.color}">${this.name}</span>**\n----\n${this.description}`, {}, 1000);

    if (this.id !== 0) {
      for (let i = 0; i < 3; i++) {
        dojo.create('div', {
          class: `ss-room__damage ss-room__damage--${i}`
        }, el);
      }

      this.divertedTokenEl = dojo.create('div', {
        class: 'ss-room__diverted-token'
      }, el);
    }

    this.el = el;
  }

  setDamage(damage) {
    if (this.id === 0) {
      return;
    }

    damage.forEach((dmg, index) => {
      this.el.classList[dmg ? 'add' : 'remove'](`ss-room--damaged-${index}`);
    });
    this.damage = damage;
  }

  setDiverted(diverted) {
    if (this.id === 0) {
      return;
    }

    this.diverted = diverted;
    this.gameObject.setVisibleEl(this.divertedTokenEl, diverted);
  }

  setProtection(protection) {
    if (this.id === 0) {
      return;
    }

    if (!Array.isArray(protection)) {
      protection = [];
    } // Assert numbers, and sort


    protection = protection.map(p => +p).sort();
    const doUpdate = protection.toString() !== this.protection.toString();
    this.protection = protection;

    if (doUpdate) {
      this.updateProtectionTokens();
    }
  }

  updateProtectionTokens() {
    // Remove all
    dojo.query('.ss-protection-token', this.el).forEach(el => {
      el.remove();
      this.gameObject.removeTooltip(el.id);
    }); // Add them

    let index = 0;
    this.protection.forEach(playerId => {
      const id = `ss-protection-token--${this.position}-${index}`;
      const player = this.gameObject.players.getPlayerById(playerId);

      if (!player) {
        return;
      }

      const order = player.order;
      dojo.create('div', {
        class: `ss-protection-token ss-protection-token--${order}`,
        id
      }, this.el);
      const tooltip = dojo.string.substitute(_("Protection token put by ${player_name}.${newline}It will be removed when a damage is received on this room, or at the <b>start</b> of ${player_name}'s turn"), {
        player_name: player.name,
        newline: '<br/>'
      });
      this.gameObject.addTooltipHtml(id, tooltip, 250);
      index++;
    });
  }

  highlight(value) {
    this.gameObject.highlightEl(this.el, value);
  }

  highlightHover(value) {
    this.gameObject.highlightEl(this.el, value, 'ss-highlight-hover');
  }

  shake() {
    if (this.shakeTimeout) {
      clearTimeout(this.shakeTimeout);
    }

    this.el.classList.add('ss-shake');
    this.shakeTimeout = setTimeout(() => {
      this.el.classList.remove('ss-shake');
    }, 2000);
  }

  setDestroyed(destroyed) {
    this.destroyed = destroyed;
    this.el.classList[destroyed ? 'add' : 'remove']('ss-room-destroyed');
  }

}

class SSPlayers {
  constructor(gameObject) {
    _defineProperty(this, "gameObject", null);

    _defineProperty(this, "players", []);

    this.gameObject = gameObject;
  }

  addPlayer(player) {
    this.players.push(player);
  }

  getPlayerById(id) {
    return this.players.find(p => p.id === +id);
  } // Return active player


  getActive() {
    return this.getPlayerById(+this.gameObject.getActivePlayerId());
  } // Return inactive players (array)


  getInactive() {
    return this.players.filter(p => p.id !== +this.gameObject.getActivePlayerId());
  }

  getAtPosition(position) {
    return this.players.filter(p => p.position === +position);
  }

  assertPositions() {
    this.players.forEach(player => {
      player.setRoomPosition(player.position, true, false);
    });
  }

  highlightHands(ids) {
    this.players.forEach(player => {
      player.highlightHand(ids && ids.includes(player.id));
    });
  }

  highlightMeeples(ids) {
    this.players.forEach(player => {
      player.highlightMeeple(ids === 'all' || ids && ids.includes(player.id));
    });
  }

}

class SSPlayer {
  constructor(gameObject, id, name, color, order, position, actionsTokens) {
    _defineProperty(this, "gameObject", null);

    _defineProperty(this, "id", null);

    _defineProperty(this, "name", null);

    _defineProperty(this, "color", null);

    _defineProperty(this, "stock", null);

    _defineProperty(this, "order", null);

    _defineProperty(this, "position", null);

    _defineProperty(this, "actionsTokens", 0);

    _defineProperty(this, "boardEl", null);

    _defineProperty(this, "meepleEl", null);

    _defineProperty(this, "actionsTokensEl", null);

    _defineProperty(this, "actionsTokensNumberEl", null);

    this.gameObject = gameObject;
    this.id = +id;
    this.name = name;
    this.color = color;
    this.order = order;
    this.assertBoardEl();
    this.assertMeepleEl();
    this.createStock();
    this.setRoomPosition(position, false, true);
    this.setActionsTokens(actionsTokens);
  }

  isCurrent() {
    return this.gameObject.player_id == this.id;
  }

  isCurrentActive() {
    return this.gameObject.player_id == this.id && this.gameObject.getActivePlayerId() == this.id;
  }

  assertBoardEl() {
    let boardEl = $first(`.ss-players-board--id-${this.id}`);

    if (boardEl) {
      this.boardEl = boardEl;
      return;
    }

    const playersHandsEl = $first('.ss-players-hands');
    boardEl = dojo.create('div', {
      class: `ss-player-board ss-players-board--id-${this.id}`
    }, playersHandsEl);
    this.boardEl = boardEl;
    const handName = this.isCurrent() ? _('Your hand') : _('Hand of') + ` <span style="color: #${this.color}">${this.name}</span>`;
    const nameEl = dojo.create('div', {
      class: 'ss-player-board__name ss-section-title',
      innerHTML: `<span>${handName}</span>`
    }, boardEl);
    this.handEl = dojo.create('div', {
      class: 'ss-player-hand',
      id: `ss-player-hand--${this.id}`,
      style: {
        backgroundColor: `#${this.color}55`
      }
    }, this.boardEl);
    this.actionsTokensEl = dojo.create('div', {
      class: 'ss-player-board__action-tokens'
    }, boardEl);
    dojo.create('div', {
      class: 'ss-player-board__action-tokens__token ss-action-token'
    }, this.actionsTokensEl);
    this.actionsTokensNumberEl = dojo.create('div', {
      class: 'ss-player-board__action-tokens__number'
    }, this.actionsTokensEl);
    this.gameObject.addTooltipMarkdown(this.actionsTokensEl, _('Actions Tokens.\nAt any time during their turn, this player can use one action token to gain one action.\nThey can also use an action to gain a action token for later.\n----\n**Note** : there are only **8** action tokens available for all players.'), {}, 250);
  }

  assertMeepleEl() {
    let meepleEl = $first(`.ss-player-meeple--id-${this.id}`);

    if (meepleEl) {
      this.meepleEl = meepleEl;
      return;
    }

    const playersArea = $first('.ss-play-area');
    meepleEl = dojo.create('div', {
      id: `ss-player-meeple--id-${this.id}`,
      class: `ss-player-meeple ss-player-meeple--order-${this.order} ss-player-meeple--id-${this.id}`
    }, playersArea);
    this.gameObject.addTooltipHtml(meepleEl.id, _(`Player ${this.name}`), 250);
    this.meepleEl = meepleEl;
  }

  createStock() {
    this.stock = new ebg.stock();
    this.stock = this.gameObject.createResourceStock(this.handEl);
    this.stock.setOverlap(30, 5);
    this.gameObject.resourceTypes.forEach((type, index) => {
      this.stock.addItemType(type.id, index, g_gamethemeurl + 'img/resources.jpg', index);
    });
  }

  setRoomPosition(position, instant = false, moveOthers = true) {
    const previousPosition = this.position;
    this.position = position;

    if (position === null) {
      // this.meepleEl.style.display = 'none'
      return;
    }

    this.meepleEl.style.display = 'block';
    const roomEl = this.gameObject.rooms.getByPosition(position).el;
    const duration = instant ? 0 : 750;
    const roomPos = dojo.position(roomEl); // const index = this.gameObject.players
    // .getAtPosition(position)
    // .sort(p => p.order)
    // .findIndex(p => p.id === this.id)

    const index = this.order;
    const offsetX = index * 30 + 20;
    const offsetY = roomPos.h * 0.2;
    this.gameObject.slideToObjectPos(this.meepleEl, roomEl, offsetX, offsetY, duration, 0).play();

    if (moveOthers) {
      this.gameObject.players.getAtPosition(position).forEach(p => p.setRoomPosition(position, false, false));

      if (previousPosition !== position) {
        this.gameObject.players.getAtPosition(previousPosition).forEach(p => p.setRoomPosition(previousPosition, false, false));
      }
    }
  }

  highlightHand(value) {
    this.gameObject.highlightEl(this.boardEl, value);
  }

  highlightMeeple(value) {
    this.gameObject.highlightEl(this.meepleEl, value);
  }

  setActionsTokens(value) {
    this.actionsTokens = value;
    this.actionsTokensNumberEl.innerHTML = '×' + value;
    this.gameObject.setVisibleEl(this.actionsTokensEl, value > 0);
  }

}

const markdownSubstitute = (() => {
  /***   Regex Markdown Parser by chalarangelo   ***/
  // Replaces 'regex' with 'replacement' in 'str'
  // Curry function, usage: replaceRegex(regexVar, replacementVar) (strVar)
  const replaceRegex = function (regex, replacement) {
    return function (str) {
      return str.replace(regex, replacement);
    };
  }; // Regular expressions for Markdown (a bit strict, but they work)


  const codeBlockRegex = /((\n\t)(.*))+/g;
  const inlineCodeRegex = /(`)(.*?)\1/g;
  const imageRegex = /!\[([^\[]+)\]\(([^\)]+)\)/g;
  const linkRegex = /\[([^\[]+)\]\(([^\)]+)\)/g;
  const headingRegex = /\n(#+\s*)(.*)/g;
  const boldItalicsRegex = /(\*{1,2})(.*?)\1/g;
  const strikethroughRegex = /(\~\~)(.*?)\1/g;
  const blockquoteRegex = /\n(&gt;|\>)(.*)/g;
  const horizontalRuleRegex = /\n((\-{3,})|(={3,}))/g;
  const unorderedListRegex = /(\n\s*(\-|\+)\s.*)+/g;
  const orderedListRegex = /(\n\s*([0-9]+\.)\s.*)+/g;
  const paragraphRegex = /\n+(?!<pre>)(?!<h)(?!<ul>)(?!<blockquote)(?!<hr)(?!\t)([^\n]+)\n/g; // Replacer functions for Markdown

  const codeBlockReplacer = function (fullMatch) {
    return '\n<pre>' + fullMatch + '</pre>';
  };

  const inlineCodeReplacer = function (fullMatch, tagStart, tagContents) {
    return '<code>' + tagContents + '</code>';
  };

  const imageReplacer = function (fullMatch, tagTitle, tagURL) {
    return '<img src="' + tagURL + '" alt="' + tagTitle + '" />';
  };

  const linkReplacer = function (fullMatch, tagTitle, tagURL) {
    return '<a href="' + tagURL + '">' + tagTitle + '</a>';
  };

  const headingReplacer = function (fullMatch, tagStart, tagContents) {
    return '\n<h' + tagStart.trim().length + '>' + tagContents + '</h' + tagStart.trim().length + '>';
  };

  const boldItalicsReplacer = function (fullMatch, tagStart, tagContents) {
    return '<' + (tagStart.trim().length == 1 ? 'em' : 'strong') + '>' + tagContents + '</' + (tagStart.trim().length == 1 ? 'em' : 'strong') + '>';
  };

  const strikethroughReplacer = function (fullMatch, tagStart, tagContents) {
    return '<del>' + tagContents + '</del>';
  };

  const blockquoteReplacer = function (fullMatch, tagStart, tagContents) {
    return '\n<blockquote>' + tagContents + '</blockquote>';
  };

  const horizontalRuleReplacer = function (fullMatch) {
    return '\n<hr />';
  };

  const unorderedListReplacer = function (fullMatch) {
    let items = '';
    fullMatch.trim().split('\n').forEach(item => {
      items += '<li>' + item.substring(2) + '</li>';
    });
    return '\n<ul>' + items + '</ul>';
  };

  const orderedListReplacer = function (fullMatch) {
    let items = '';
    fullMatch.trim().split('\n').forEach(item => {
      items += '<li>' + item.substring(item.indexOf('.') + 2) + '</li>';
    });
    return '\n<ol>' + items + '</ol>';
  };

  const paragraphReplacer = function (fullMatch, tagContents) {
    return '<p>' + tagContents + '</p>';
  }; // Rules for Markdown parsing (use in order of appearance for best results)


  const replaceCodeBlocks = replaceRegex(codeBlockRegex, codeBlockReplacer);
  const replaceInlineCodes = replaceRegex(inlineCodeRegex, inlineCodeReplacer);
  const replaceImages = replaceRegex(imageRegex, imageReplacer);
  const replaceLinks = replaceRegex(linkRegex, linkReplacer);
  const replaceHeadings = replaceRegex(headingRegex, headingReplacer);
  const replaceBoldItalics = replaceRegex(boldItalicsRegex, boldItalicsReplacer);
  const replaceceStrikethrough = replaceRegex(strikethroughRegex, strikethroughReplacer);
  const replaceBlockquotes = replaceRegex(blockquoteRegex, blockquoteReplacer);
  const replaceHorizontalRules = replaceRegex(horizontalRuleRegex, horizontalRuleReplacer);
  const replaceUnorderedLists = replaceRegex(unorderedListRegex, unorderedListReplacer);
  const replaceOrderedLists = replaceRegex(orderedListRegex, orderedListReplacer);
  const replaceParagraphs = replaceRegex(paragraphRegex, paragraphReplacer); // Fix for tab-indexed code blocks

  const codeBlockFixRegex = /\n(<pre>)((\n|.)*)(<\/pre>)/g;

  const codeBlockFixer = function (fullMatch, tagStart, tagContents, lastMatch, tagEnd) {
    let lines = '';
    tagContents.split('\n').forEach(line => {
      lines += line.substring(1) + '\n';
    });
    return tagStart + lines + tagEnd;
  };

  const fixCodeBlocks = replaceRegex(codeBlockFixRegex, codeBlockFixer); // Replacement rule order function for Markdown
  // Do not use as-is, prefer parseMarkdown as seen below

  const replaceMarkdown = function (str) {
    return replaceParagraphs(replaceOrderedLists(replaceUnorderedLists(replaceHorizontalRules(replaceBlockquotes(replaceceStrikethrough(replaceBoldItalics(replaceHeadings(replaceLinks(replaceImages(replaceInlineCodes(replaceCodeBlocks(str))))))))))));
  }; // Parser for Markdown (fixes code, adds empty lines around for parsing)
  // Usage: parseMarkdown(strVar)


  const parseMarkdown = function (str) {
    return fixCodeBlocks(replaceMarkdown('\n' + str + '\n')).trim();
  };

  return (str, values) => {
    return parseMarkdown(dojo.string.substitute(str, values));
  };
})();

//# sourceMappingURL=solarstorm.js.map