(function () {
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function hit(a, b) { return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y; }
  function make(cls) { var node = document.createElement('div'); node.className = cls; return node; }

  function readConfig(root) {
    var node = root.querySelector('.jas-game32-config');
    return JSON.parse(node ? node.textContent : '{}');
  }

  function start(root) {
    var config = readConfig(root);
    var world = root.querySelector('.game32-world');
    var enemiesLayer = root.querySelector('.enemies32');
    var shotsLayer = root.querySelector('.shots32');
    var tokensLayer = root.querySelector('.tokens32');
    var particlesLayer = root.querySelector('.particles32');
    var bossLayer = root.querySelector('.boss32');
    var playerNode = root.querySelector('.player32');
    var hud = {
      level: root.querySelector('[data-hud="level"]'),
      score: root.querySelector('[data-hud="score"]'),
      energy: root.querySelector('[data-hud="energy"]'),
      tokens: root.querySelector('[data-hud="tokens"]'),
      shield: root.querySelector('[data-hud="shield"]')
    };
    var message = root.querySelector('.game32-message');
    var startOverlay = root.querySelector('.game32-start');
    var startButton = root.querySelector('[data-game-start]');
    var keys = {};
    var levelIndex = 0;
    var score = 0;
    var tokens = 0;
    var shield = 0;
    var cooldown = 0;
    var running = true;
    var player = {
      x: config.player.x || 80,
      y: config.player.y || 260,
      w: 76,
      h: 42,
      speed: config.player.speed || 6,
      energy: config.player.energy || 100
    };
    var enemies = [];
    var shots = [];
    var tokenNodes = [];
    var particles = [];
    var boss = null;
    var started = false;

    function place(node, obj) {
      node.style.transform = 'translate3d(' + obj.x + 'px,' + obj.y + 'px,0)';
    }

    function setHud() {
      hud.level.textContent = String(levelIndex + 1);
      hud.score.textContent = String(score);
      hud.energy.textContent = String(Math.max(0, player.energy));
      hud.tokens.textContent = String(tokens);
      hud.shield.textContent = shield > 0 ? 'ON' : 'OFF';
    }

    function clearLayer(layer) {
      while (layer.firstChild) layer.removeChild(layer.firstChild);
    }

    function backgroundClass(name) {
      root.setAttribute('data-bg', name || 'grid_blue');
    }

    function spawnLevel(index) {
      var level = config.levels[index];
      enemies = [];
      shots = [];
      tokenNodes = [];
      particles = [];
      boss = null;
      clearLayer(enemiesLayer);
      clearLayer(shotsLayer);
      clearLayer(tokensLayer);
      clearLayer(particlesLayer);
      clearLayer(bossLayer);
      backgroundClass(level.background);
      message.textContent = 'Nivel ' + (index + 1) + ' - ' + level.id + ' - Boss: ' + (level.boss || 'none');

      for (var i = 0; i < level.particles; i++) {
        var p = make('particle32');
        particlesLayer.appendChild(p);
        particles.push({ node: p, x: Math.random() * config.width, y: 62 + Math.random() * (config.height - 110), speed: 0.4 + Math.random() * 1.8 });
      }

      for (var e = 0; e < level.enemies; e++) {
        var enemy = make('sky-enemy32');
        enemiesLayer.appendChild(enemy);
        enemies.push({
          node: enemy,
          x: config.width + 60 + e * 38,
          y: 72 + (e * 53) % (config.height - 140),
          w: 46,
          h: 34,
          hp: 1 + Math.floor(index / 2),
          speed: 1.4 + index * 0.35 + (e % 5) * 0.18
        });
      }

      for (var t = 0; t < 4; t++) {
        var token = make('salk-token32');
        tokensLayer.appendChild(token);
        tokenNodes.push({ node: token, x: 260 + t * 145, y: 100 + (t % 2) * 190, w: 28, h: 28 });
      }

      var bossNode = make('sky-boss32');
      bossNode.textContent = (level.boss || 'BOSS').replace('_', ' ').toUpperCase();
      bossLayer.appendChild(bossNode);
      boss = { node: bossNode, x: config.width + 220, y: 170, w: 140, h: 82, hp: 20 + index * 18, speed: 0.55 + index * 0.12 };
      setHud();
    }

    function shoot() {
      var maxShots = config.levels[levelIndex].shots || 40;
      if (cooldown > 0 || shots.length >= maxShots) return;
      cooldown = 7;
      var node = make('sky-shot32');
      shotsLayer.appendChild(node);
      shots.push({ node: node, x: player.x + player.w - 4, y: player.y + 18, w: 24, h: 5, speed: 10 });
    }

    function damage(amount) {
      if (shield > 0) return;
      player.energy -= amount;
      playerNode.classList.add('hit32');
      setTimeout(function () { playerNode.classList.remove('hit32'); }, 120);
      if (player.energy <= 0) {
        running = false;
        message.textContent = 'NEXUS-NULL gano. Recarga para reintentar.';
      }
      setHud();
    }

    function nextLevel() {
      levelIndex += 1;
      if (levelIndex >= config.levels.length) {
        running = false;
        message.textContent = 'SENAL RESTAURADA - JAH SKYCORE COMPLETADO';
        return;
      }
      spawnLevel(levelIndex);
    }

    document.addEventListener('keydown', function (event) {
      keys[event.key] = true;
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ', 'Control'].indexOf(event.key) !== -1) event.preventDefault();
    });
    document.addEventListener('keyup', function (event) { keys[event.key] = false; });

    function begin() {
      if (started) return;
      started = true;
      if (startOverlay) startOverlay.style.display = 'none';
      message.textContent = 'Mision activa - destruye el boss para avanzar';
      requestAnimationFrame(loop);
    }

    if (startButton) {
      startButton.addEventListener('click', begin);
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') begin();
    });

    function loop() {
      if (!running) return;
      var speed = keys.Shift ? player.speed + 2 : player.speed;
      if (keys.ArrowLeft || keys.a) player.x -= speed;
      if (keys.ArrowRight || keys.d) player.x += speed;
      if (keys.ArrowUp || keys.w) player.y -= speed;
      if (keys.ArrowDown || keys.s) player.y += speed;
      if (keys[' '] || keys.Control) shoot();
      if (cooldown > 0) cooldown -= 1;
      if (shield > 0) shield -= 1;

      player.x = clamp(player.x, 0, config.width - player.w);
      player.y = clamp(player.y, 62, config.height - player.h - 22);
      place(playerNode, player);

      particles.forEach(function (p) {
        p.x -= p.speed;
        if (p.x < -8) p.x = config.width + 8;
        place(p.node, p);
      });

      tokenNodes = tokenNodes.filter(function (token) {
        place(token.node, token);
        if (hit(player, token)) {
          token.node.remove();
          tokens += 1;
          shield = 180;
          score += 50;
          setHud();
          return false;
        }
        return true;
      });

      shots = shots.filter(function (shot) {
        shot.x += shot.speed;
        place(shot.node, shot);
        if (shot.x > config.width + 40) {
          shot.node.remove();
          return false;
        }
        return true;
      });

      enemies = enemies.filter(function (enemy) {
        enemy.x -= enemy.speed;
        if (enemy.x < -80) {
          enemy.x = config.width + 120;
          enemy.y = 72 + Math.random() * (config.height - 150);
        }
        place(enemy.node, enemy);
        if (hit(player, enemy)) {
          enemy.node.remove();
          damage(10 + levelIndex * 2);
          return false;
        }
        for (var i = 0; i < shots.length; i++) {
          if (hit(shots[i], enemy)) {
            shots[i].node.remove();
            shots[i].x = config.width + 100;
            enemy.hp -= 1;
            if (enemy.hp <= 0) {
              enemy.node.remove();
              score += 25;
              setHud();
              return false;
            }
          }
        }
        return true;
      });

      if (boss) {
        boss.x -= boss.speed;
        boss.y += Math.sin(Date.now() / 240) * 0.7;
        place(boss.node, boss);
        if (hit(player, boss)) damage(20);
        shots.forEach(function (shot) {
          if (hit(shot, boss)) {
            shot.node.remove();
            shot.x = config.width + 100;
            boss.hp -= 1;
            score += 5;
            if (boss.hp <= 0) {
              boss.node.remove();
              boss = null;
              score += 250;
              nextLevel();
            }
            setHud();
          }
        });
      }

      setHud();
      requestAnimationFrame(loop);
    }

    spawnLevel(0);
    place(playerNode, player);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-asjah-game="skycore32"]').forEach(start);
  });
}());
