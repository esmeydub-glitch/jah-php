(function () {
  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function rectOf(node, state) {
    return {
      x: state.x,
      y: state.y,
      w: node.offsetWidth,
      h: node.offsetHeight
    };
  }

  function intersects(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  }

  function startGame(root) {
    var hero = root.querySelector('#hero_ship');
    var coin = root.querySelector('#coin');
    var enemies = Array.prototype.slice.call(root.querySelectorAll('[data-enemy="true"]'));
    var scoreNode = root.querySelector('#score_value');
    var livesNode = root.querySelector('#lives_value');
    var messageNode = root.querySelector('#game_message');

    if (!hero || !coin || !scoreNode || !livesNode || !messageNode) {
      return;
    }

    var bounds = { w: 800, h: 450 };
    var keys = {};
    var score = 0;
    var lives = 3;
    var running = true;
    var heroState = { x: 80, y: 210 };
    var coinState = { x: 620, y: 120 };
    var enemyStates = enemies.map(function (enemy, index) {
      return {
        node: enemy,
        x: 850 + index * 220,
        y: 90 + (index * 83) % 270,
        speed: 1.5 + index * 0.55
      };
    });

    function place(node, state) {
      node.style.transform = 'translate(' + state.x + 'px,' + state.y + 'px)';
    }

    function randomCoin() {
      coinState.x = 120 + Math.floor(Math.random() * 620);
      coinState.y = 70 + Math.floor(Math.random() * 290);
      place(coin, coinState);
    }

    function damage() {
      lives -= 1;
      livesNode.textContent = String(lives);
      hero.classList.add('hit');
      window.setTimeout(function () { hero.classList.remove('hit'); }, 160);

      if (lives <= 0) {
        running = false;
        messageNode.textContent = 'GAME OVER - recarga para reiniciar';
      }
    }

    document.addEventListener('keydown', function (event) {
      keys[event.key] = true;
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' '].indexOf(event.key) !== -1) {
        event.preventDefault();
      }
    });

    document.addEventListener('keyup', function (event) {
      keys[event.key] = false;
    });

    randomCoin();

    function frame() {
      if (!running) {
        return;
      }

      var speed = keys.Shift ? 6 : 4;
      if (keys.ArrowLeft || keys.a) heroState.x -= speed;
      if (keys.ArrowRight || keys.d) heroState.x += speed;
      if (keys.ArrowUp || keys.w) heroState.y -= speed;
      if (keys.ArrowDown || keys.s) heroState.y += speed;

      heroState.x = clamp(heroState.x, 0, bounds.w - hero.offsetWidth);
      heroState.y = clamp(heroState.y, 56, bounds.h - hero.offsetHeight - 40);
      place(hero, heroState);

      var heroRect = rectOf(hero, heroState);
      if (intersects(heroRect, rectOf(coin, coinState))) {
        score += 10;
        scoreNode.textContent = String(score);
        randomCoin();
      }

      enemyStates.forEach(function (enemy) {
        enemy.x -= enemy.speed;
        if (enemy.x < -80) {
          enemy.x = 850 + Math.floor(Math.random() * 220);
          enemy.y = 70 + Math.floor(Math.random() * 310);
        }
        place(enemy.node, enemy);

        if (intersects(heroRect, rectOf(enemy.node, enemy))) {
          enemy.x = 860;
          damage();
        }
      });

      window.requestAnimationFrame(frame);
    }

    place(hero, heroState);
    enemyStates.forEach(function (enemy) { place(enemy.node, enemy); });
    window.requestAnimationFrame(frame);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-asjah-game="bit16"]').forEach(startGame);
  });
}());
