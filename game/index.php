<?php
	$dictionaryJson    = file_get_contents('./dictionary/data.json');
	$dictionaryDecoded = json_decode($dictionaryJson, true);
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<link rel="stylesheet" href="./css/styles.css?v=<?= filemtime('./css/styles.css'); ?>" />
		<title>Word Game</title>
	</head>
	<body>
		<div class="particle-wrap"></div>
		<div class="phrase-wrap"></div>

		<button id="start-game">Start</button>

		<div id="game-over" class="hide">Game Over</div>

		<aside>
			<div class="config-wrap">
				<h2>Config</h2>

				<section class="speed">
					<p>Speed:</p>

					<label>
						<input type="radio" name="speed" value="slow" autocomplete="on" />
						<span>Slow</span>
					</label>

					<label>
						<input type="radio" name="speed" value="normal" autocomplete="on" checked />
						<span>Normal</span>
					</label>

					<label>
						<input type="radio" name="speed" value="fast" autocomplete="on" />
						<span>Fast</span>
					</label>

					<label>
						<input type="radio" name="speed" value="very-fast" autocomplete="on" />
						<span>Very Fast</span>
					</label>
				</section>

				<section class="words-per-round">
					<p>Words per round:</p>

					<label>
						<input type="radio" name="words-per-round" value="5" autocomplete="on" />
						<span>5</span>
					</label>

					<label>
						<input type="radio" name="words-per-round" value="10" autocomplete="on" checked />
						<span>10</span>
					</label>

					<label>
						<input type="radio" name="words-per-round" value="20" autocomplete="on" />
						<span>20</span>
					</label>
				</section>

				<section>
					<label>
						<p>Dictionary:</p>
						<select id="dictionary-select" autocomplete="on">
							<?php foreach ($dictionaryDecoded as $i => $dict) { ?>
								<option value="<?= $i; ?>" <?php if ($i === 0) { echo 'selected'; } ?>><?= $dict['name']; ?></option>
							<?php } ?>
						</select>
					</label>
				</section>

				<section>
					<label>
						<input type="checkbox" id="enable-background" autocomplete="on" checked />
						<span>Enable background</span>
					</label>
				</section>
			</div>

			<div class="stats-wrap">
				<h2>Stats</h2>
			</div>
		</aside>

		<script src="./js/game.js?v=<?= filemtime('./js/game.js'); ?>"></script>
		<script>
			initalise();

			function initalise() {
				// Conveniences
				Array.prototype.shuffle = function() {
					for (let i = this.length - 1; i > 0; i--) {
						const j = Math.floor(Math.random() * (i + 1));
						[this[i], this[j]] = [this[j], this[i]];
					}

					return this;
				}

				const $ = (selector) => {
					const el = document.querySelectorAll(selector);

					if (el.length === 1) {
						return el[0];
					}

					if (el.length > 1) {
						return el;
					}

					return null;
				}

				// Elements
				const particleWrap     = $(".particle-wrap");
				const phraseWrap       = $(".phrase-wrap");
				const statsSideBar     = $("aside .stats-wrap");
				const backgroundToggle = $("#enable-background");
				const startButton      = $("#start-game");
				const gameOver         = $("#game-over");

				// Dictionary / word list
				const dictionary = getWordList();

				// Fade in start button / background
				if (window.NO_ANIM) {
					particleWrap.style.transition = "unset";
					particleWrap.style.opacity = 1;
					startButton.style.transition = "unset";
					startButton.style.opacity = 1;
				} else {
					addParticles();

					setTimeout(function() {
						particleWrap.style.opacity = 1;
						startButton.style.opacity = 1;
					}, 2000);
				}

				// "Start" button - begin game
				startButton.addEventListener("click", function(e) {
					e.preventDefault();

					this.disabled = true;

					// Config values - used in game settings below
					const gameSpeed = $(`input[name="speed"]:checked`).value;
					const wordsPerRound = parseInt($(`input[name="words-per-round"]:checked`).value);
					const chosenDictionary = parseInt($("#dictionary-select").value);

					// Update sidebar with initial stats
					for (let i = 0; i < dictionary[chosenDictionary].words.length; i++) {
						const section = document.createElement("section");

						section.classList.add("score-wrap", `round-${i}`);

						section.innerHTML = `
							<h3>Round ${i+1}</h3>
							<p class="score"><span class="current-score">0</span>/<span>${wordsPerRound}</span></p>
						`;

						statsSideBar.appendChild(section);
					}

					// Prepare game settings
					const settings = {};

					if (gameSpeed === "slow") {
						settings.wordInterval    = 3;
						settings.wordMinDuration = 6;
						settings.wordMaxDuration = 10;
					} else if (gameSpeed === "normal") {
						settings.wordInterval    = 1.5;
						settings.wordMinDuration = 4;
						settings.wordMaxDuration = 8;
					} else if (gameSpeed === "fast") {
						settings.wordInterval    = 1;
						settings.wordMinDuration = 3;
						settings.wordMaxDuration = 6;
					} else {
						settings.wordInterval    = 0.5;
						settings.wordMinDuration = 2;
						settings.wordMaxDuration = 5;
					}

					// Shuffle / slice word list
					dictionary[chosenDictionary].words.forEach(wordList => wordList.shuffle());

					settings.dictionary = dictionary[chosenDictionary].words.map(wordList => wordList.slice(0, wordsPerRound));

					// Assign callbacks to update stats sidebar
					settings.onBeginRound = function(game) {
						for (const el of $("aside .score-wrap")) {
							el.classList.remove("active");
						}

						const sideBarRoundStats = $(`aside .score-wrap.round-${game.currentRound}`);

						sideBarRoundStats.classList.add("active");
					}

					settings.onWordCompleted = function(game) {
						const sideBarScore = $(`aside .score-wrap.round-${game.currentRound} .current-score`);
						sideBarScore.textContent = game.currentRoundData.score;
					}

					settings.onGameCompleted = function(game) {
						gameOver.classList.remove("hide");

						setTimeout(function() {
							gameOver.style.opacity = 1;
						}, 0);
					}

					settings.node = phraseWrap;

					// Create game
					const game = new Game(settings);

					fadeOut(this, window.NO_ANIM ? 1 : 1000, function() {
						game.start();
					})
				})

				// Toggle animated background
				backgroundToggle.addEventListener("input", function() {
					document.body.classList.toggle("no-background", !this.checked);
				})

				backgroundToggle.dispatchEvent(new Event("input", { bubbles: true }));

				function addParticles() {
					const totalAnims = 20;
					const totalParticles = 40;

					// Create randomised CSS animation <style> rules
					createAnimations({
						name      : "particle",
						direction : "top-to-bottom",
						count     : 20,
						variance  : 15,
					})

					// Create particles
					for (let i = 0; i < totalParticles; i++) {
						particleWrap.appendChild(document.createElement("div"));
					}

					// Apply random animation, and randomise size/duration
					for (const particle of $(".particle-wrap div")) {
						particle.addEventListener("animationend", function() {
							randomiseParticle(particle);
						})

						randomiseParticle(particle);
					}

					function randomiseParticle(particle) {
						const duration = Math.random() * 5 + 1;
						const size = Math.floor(Math.random() * 8) + 2;

						particle.style.animationDuration = `${duration}s`;
						particle.style.animationName     = `particle-top-to-bottom-${Math.floor(Math.random() * totalAnims)}`;
						particle.style.height            = `${size}px`;
						particle.style.width             = `${size}px`;
					}
				}

				function createAnimations(options) {
					const style = document.createElement("style");
					const animations = new Array(options.count);

					const animationName = `${options.name}-${options.direction}`;

					for (let i = 0; i < animations.length; i++) {
						const leftStart  = Math.random() * 100;
						const leftFinish = Math.random() < 0.5 ? leftStart + (Math.random() * options.variance) : leftStart - (Math.random() * options.variance);

						animations[i] = `
							@keyframes ${animationName}-${i} {
								0% {
									left: ${leftStart}%;
									top: -10%;
								}
								100% {
									left: ${leftFinish}%;
									top: 100%;
								}
							}
						`;
					}

					style.textContent = animations.join("\n");

					document.head.appendChild(style);
				}

				function fadeOut(element, duration, callback) {
					element.addEventListener("transitionend", function() {
						element.style.display = "none";
						element.style.transition = "";
						element.style.opacity = "";

						if (typeof callback === "function") {
							callback();
						}
					})

					element.style.transition = `opacity ${duration / 1000}s`;
					element.style.opacity = 0;
				}

				function getWordList() {
					return <?= $dictionaryJson; ?>;
				}
			}
		</script>
	</body>
</html>