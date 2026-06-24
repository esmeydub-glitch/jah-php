(function () {
  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function hit(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  }

  function start(root) {
    var hero = root.querySelector('#hero32');
    var shotsLayer = root.querySelector('#shots_layer');
    var scoreNode = root.querySelector('#score32');
    var energyNode = root.querySelector('#energy32');
    var messageNode = root.querySelector('#message32');
    var enemies = Array.prototype.slice.call(root.querySelectorAll('[data-enemy32="true"]'));

    if (!hero || !shotsLayer || !scoreNode || !energyNode || !messageNode) return;

    var keys = {};
    var score = 0;
    var energy = 100;
    var running = true;
    var cooldown = 0;
    var heroState = { x: 80, y: 220, w: 72, h: 48 };
    var shots = [];
    var enemyStates = enemies.map(function (node, index) {
      return {
        node: node,
        x: 880 + index * 190,
        y: 82 + (index * 71) % 300,
        w: 58,
        h: 42,
        speed: 1.6 + index * 0.4
      };
    });

    function place(node, state) {
      node.style.transform = 'translate3d(' + state.x + 'px,' + state.y + 'px,0)';
    }

    function setHud() {
      scoreNode.textContent = String(score);
      energyNode.textContent = String(Math.max(0, energy));
    }

    function shoot() {
      if (cooldown > 0) return;
      cooldown = 10;
      var shot = document.createElement('div');
      shot.className = 'shot32';
      shotsLayer.appendChild(shot);
      shots.push({
        node: shot,
        x: heroState.x + heroState.w - 8,
        y: heroState.y + 21,
        w: 22,
        h: 5,
        speed: 9
      });
    }

    function damage() {
      energy -= 20;
      hero.classList.add('hit32');
      window.setTimeout(function () { hero.classList.remove('hit32'); }, 140);
      setHud();
      if (energy <= 0) {
        running = false;
        messageNode.textContent = 'MISSION FAILED - recarga para reiniciar';
      }
    }

    document.addEventListener('keydown', function (event) {
      keys[event.key] = true;
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ', 'Control'].indexOf(event.key) !== -1) {
        event.preventDefault();
      }
    });

    document.addEventListener('keyup', function (event) {
      keys[event.key] = false;
    });

    function loop() {
      if (!running) return;

      var speed = keys.Shift ? 6 : 4;
      if (keys.ArrowLeft || keys.a) heroState.x -= speed;
      if (keys.ArrowRight || keys.d) heroState.x += speed;
      if (keys.ArrowUp || keys.w) heroState.y -= speed;
      if (keys.ArrowDown || keys.s) heroState.y += speed;
      if (keys[' '] || keys.Control) shoot();
      if (cooldown > 0) cooldown -= 1;

      heroState.x = clamp(heroState.x, 0, 800 - heroState.w);
      heroState.y = clamp(heroState.y, 64, 450 - heroState.h - 18);
      place(hero, heroState);

      shots = shots.filter(function (shot) {
        shot.x += shot.speed;
        shot.node.style.transform = 'translate3d(' + shot.x + 'px,' + shot.y + 'px,0)';
        if (shot.x > 840) {
          shot.node.remove();
          return false;
        }
        return true;
      });

      enemyStates.forEach(function (enemy) {
        enemy.x -= enemy.speed;
        if (enemy.x < -90) {
          enemy.x = 880 + Math.floor(Math.random() * 220);
          enemy.y = 78 + Math.floor(Math.random() * 305);
        }
        place(enemy.node, enemy);

        if (hit(heroState, enemy)) {
          enemy.x = 880;
          damage();
        }

        shots.forEach(function (shot) {
          if (hit(shot, enemy)) {
            score += 25;
            enemy.x = 900 + Math.floor(Math.random() * 260);
            enemy.y = 78 + Math.floor(Math.random() * 305);
            shot.x = 900;
            shot.node.remove();
            setHud();
          }
        });
      });

      window.requestAnimationFrame(loop);
    }

    setHud();
    place(hero, heroState);
    enemyStates.forEach(function (enemy) { place(enemy.node, enemy); });
    window.requestAnimationFrame(loop);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-asjah-game="bit32"]').forEach(start);
  });
}());
