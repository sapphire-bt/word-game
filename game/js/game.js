class Game {
	settings = {
		node                : null,
		dictionary          : [],
		skipSpaces          : true,
		preventCtrl         : ["f", "o", "p"],
		wordAnimPrefix      : "phrase",
		wordAnimDirection   : "top-to-bottom",
		wordAnimCount       : 40,
		wordAnimVariance    : 15,
		wordInterval        : 1,
		wordMinDuration     : 4,
		wordMaxDuration     : 8,
		wordFadeOutTime     : 1,
		nextRoundStartDelay : 2,
		onBeginRound        : function() {},
		onEndRound          : function() {},
		onWordCompleted     : function() {},
		onGameCompleted     : function() {},
	}

	gameInProgress = false;

	currentRound = 0;

	totalRounds = 0;

	roundsData = [];

	constructor(settings) {
		// Merge settings
		this.settings = {...this.settings, ...settings};

		this.totalRounds = this.settings.dictionary.length; // Dictionary = array of arrays; one array per round.

		this.roundsData = new Array(this.totalRounds);

		// Populate rounds data array
		for (let i = 0; i < this.roundsData.length; i++) {
			const roundWordList = this.settings.dictionary[i];
			const roundData = {};

			roundData.score = 0;
			roundData.shown = 0;

			roundData.words = new Array(roundWordList.length);

			for (let j = 0; j < roundWordList.length; j++) {
				roundData.words[j] = new Word(this, roundWordList[j]);
			}

			this.roundsData[i] = roundData;
		}

		document.addEventListener("keydown", (e) => this.onKeyDown(e));
	}

	start() {
		this.generateCssAnimations(
			this.settings.wordAnimPrefix,
			this.settings.wordAnimDirection,
			this.settings.wordAnimCount,
			this.settings.wordAnimVariance
		);

		this.settings.node.focus();

		this.beginRound();

		this.gameInProgress = true;
	}

	beginRound() {
		console.log("beginRound");

		const data = this.currentRoundData;

		let i = 0;

		const addWord = function(settings) {
			const word = data.words[i++];

			settings.node.appendChild(word.node);

			if (i < data.words.length) {
				setTimeout(() => addWord(settings), settings.wordInterval * 1000);
			}
		}

		addWord(this.settings);

		this.settings.onBeginRound(this);
	}

	endRound() {
		console.log("endRound");

		this.currentRound++;

		if (this.currentRound === this.totalRounds) {
			this.endGame();
		} else {
			setTimeout(() => this.beginRound(), this.settings.nextRoundStartDelay * 1000);
		}

		this.settings.onEndRound(this);
	}

	endGame() {
		console.log("endGame");

		this.gameInProgress = false;

		this.settings.onGameCompleted(this);
	}

	onKeyDown(e) {
		if (this.gameInProgress) {
			const { settings } = this;

			if (e.ctrlKey && settings.preventCtrl) {
				// preventCtrl = true; prevent all
				if (typeof settings.preventCtrl === "boolean") {
					e.preventDefault();
				}

				// preventCtrl = array of keys; prevent only these
				else if (Array.isArray(settings.preventCtrl)) {
					if (settings.preventCtrl.includes(e.key.toLowerCase()) || settings.preventCtrl.includes(e.key.toUpperCase())) {
						e.preventDefault();
					}
				}
			}

			if (this.activeWord) {
				if (this.activeWord.currentLetter.textContent.startsWith(e.key)) {
					this.activeWord.moveToNextLetter();
				}
			}

			// No active word - check if the key press matches any other words
			else {
				for (const word of this.visibleWords) {
					if (word.text.startsWith(e.key)) {
						word.active = true;
						break;
					}
				}
			}
		}
	}

	generateCssAnimations(prefix, direction, count, variance) {
		const style = document.createElement("style");
		const animations = new Array(count);

		const animationName = this.wordAnimName;

		for (let i = 0; i < animations.length; i++) {
			const leftStart  = Math.min(95, Math.max(3, Math.random() * 100));
			const leftFinish = Math.min(98, Math.max(2, Math.random() < 0.5 ? leftStart + (Math.random() * variance) : leftStart - (Math.random() * variance)));

			animations[i] = `
				@keyframes ${animationName}-${i} {
					0% {
						left: ${leftStart}%;
						transform: translateX(-${leftStart}%);
						top: -5%;
					}
					100% {
						left: ${leftFinish}%;
						transform: translateX(-${leftFinish}%);
						top: 100%;
					}
				}
			`;
		}

		style.textContent = animations.join("\n");

		document.head.appendChild(style);
	}

	get activeWord() {
		return this.currentRoundData.words.filter(word => word.active)[0];
	}

	get visibleWords() {
		return this.currentRoundData.words.filter(word => word.visible);
	}

	get wordAnimName() {
		return this.settings.wordAnimPrefix + "-" + this.settings.wordAnimDirection;
	}

	get currentRoundData() {
		return this.roundsData[this.currentRound];
	}

	get currentRoundScore() {
		return this.currentRoundData.score;
	}

	set currentRoundScore(value) {
		this.roundsData[this.currentRound].score = value;
	}
}

class Word {
	isActive = false;
	visible  = true;
	onFail   = () => this.failed();

	constructor(game, word) {
		const { settings } = game;

		const div = document.createElement("div");
		const split = `<p>${word.split("").map(c => `<span>${c}</span>`).join("")}</p>`;

		div.classList.add("phrase");
		div.addEventListener("animationend", this.onFail);
		div.style.animationName = game.wordAnimName + "-" + Math.floor(Math.random() * settings.wordAnimCount);
		div.style.animationDuration = Math.floor(Math.random() * (settings.wordMaxDuration - settings.wordMinDuration + 1) + settings.wordMinDuration) + "s";
		div.innerHTML = split;

		this.node   = div;
		this.parent = game;
		this.text   = word;
	}

	moveToNextLetter() {
		if (this.nextLetter) {
			const nextLetter = this.nextLetter;
			this.currentLetter.classList.remove("current");
			nextLetter.classList.add("current");

			if (nextLetter.textContent === " " && this.parent.settings.skipSpaces) {
				this.moveToNextLetter();
			}
		} else {
			this.success();
		}
	}

	success() {
		console.log("success");

		this.node.removeEventListener("animationend", this.onFail);

		this.parent.currentRoundScore += 1;

		this.fadeOut();

		this.parent.settings.onWordCompleted(this.parent);

		this.expire();
	}

	failed() {
		console.log("failed");

		this.expire();
		this.node.remove();
	}

	expire() {
		this.active = false;
		this.visible = false;

		this.parent.currentRoundData.shown++;

		if (this.parent.currentRoundData.shown === this.parent.currentRoundData.words.length) {
			this.parent.endRound();
		}
	}

	fadeOut() {
		console.log("fading out");

		this.node.addEventListener("transitionend", () => this.node.remove());
		this.node.style.transition = `opacity ${this.parent.settings.wordFadeOutTime}s, font-size ${this.parent.settings.wordFadeOutTime}s`;
		this.node.style.opacity = 0;
	}

	get currentLetter() {
		return this.node.querySelector("span.current");
	}

	get nextLetter() {
		return this.currentLetter.nextElementSibling;
	}

	get active() {
		return this.isActive;
	}

	set active(value) {
		if (value) {
			this.isActive = true;
			this.node.classList.add("active");

			if (this.text.length === 1) {
				this.success();
			} else {
				this.node.querySelector("span:nth-child(2)").classList.add("current");
			}
		} else {
			this.isActive = false;
			this.node.classList.remove("active");
		}
	}
}