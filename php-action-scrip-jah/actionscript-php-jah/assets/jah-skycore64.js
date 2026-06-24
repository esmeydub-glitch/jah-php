(function () {
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function hit(a, b) { return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y; }
  function el(cls) { var n = document.createElement('div'); n.className = cls; return n; }

  function start(root) {
    var configNode = root.querySelector('.game64-config');
    var config = JSON.parse(configNode ? configNode.textContent : '{}');
    var world = root.querySelector('.game64-world');
    var playerNode = root.querySelector('.player64');
    var enemiesLayer = root.querySelector('.enemies64');
    var shotsLayer = root.querySelector('.shots64');
    var orbsLayer = root.querySelector('.orbs64');
    var particlesLayer = root.querySelector('.particles64');
    var bossLayer = root.querySelector('.boss64');
    var start = root.querySelector('[data-game64-start]');
    var overlay = root.querySelector('.game64-start');
    var msg = root.querySelector('.game64-message');
    var hud = {
      level: root.querySelector('[data-hud64="level"]'),
      score: root.querySelector('[data-hud64="score"]'),
      energy: root.querySelector('[data-hud64="energy"]'),
      cores: root.querySelector('[data-hud64="cores"]'),
      charge: root.querySelector('[data-hud64="charge"]')
    };
    var keys = {};
    var level = 0;
    var score = 0;
    var cores = 0;
    var charge = 0;
    var cooldown = 0;
    var dashCooldown = 0;
    var active = false;
    var player = { x: 110, y: 290, w: 86, h: 52, energy: 160, speed: 6 };
    var enemies = [];
    var shots = [];
    var orbs = [];
    var particles = [];
    var boss = null;

    function place(node, o) {
      node.style.transform = 'translate3d(' + o.x + 'px,' + o.y + 'px,' + (o.z || 0) + 'px) scale(' + (o.scale || 1) + ')';
    }

    function hudUpdate() {
      hud.level.textContent = String(level + 1);
      hud.score.textContent = String(score);
      hud.energy.textContent = String(Math.max(0, player.energy));
      hud.cores.textContent = String(cores);
      hud.charge.textContent = String(Math.floor(charge));
    }

    function clear(node) {
      while (node.firstChild) node.removeChild(node.firstChild);
    }

    function spawnLevel() {
      var spec = config.levels[level] || config.levels[0];
      enemies = []; shots = []; orbs = []; particles = []; boss = null;
      [enemiesLayer, shotsLayer, orbsLayer, particlesLayer, bossLayer].forEach(clear);
      root.setAttribute('data-bg64', spec.background || 'quantum_blue');
      msg.textContent = 'Layer ' + (level + 1) + ': ' + spec.id + ' - ' + spec.boss;

      for (var i = 0; i < spec.particles; i++) {
        var p = el('particle64');
        particlesLayer.appendChild(p);
        particles.push({ node: p, x: Math.random() * config.width, y: 70 + Math.random() * (config.height - 140), z: -120 + Math.random() * 180, speed: .8 + Math.random() * 3, scale: .4 + Math.random() * 1.4 });
      }

      for (var e = 0; e < spec.enemies; e++) {
        var n = el('enemy64');
        enemiesLayer.appendChild(n);
        enemies.push({ node: n, x: config.width + 100 + e * 32, y: 82 + (e * 59) % (config.height - 160), z: -20 + (e % 5) * 12, w: 58, h: 44, hp: 2 + Math.floor(level / 2), speed: 2 + level * .32 + (e % 7) * .12, scale: .85 + (e % 4) * .08 });
      }

      for (var o = 0; o < 5; o++) {
        var orb = el('core64');
        orbsLayer.appendChild(orb);
        orbs.push({ node: orb, x: 260 + o * 130, y: 105 + (o % 2) * 235, w: 30, h: 30, scale: 1 });
      }

      var b = el('boss64-node');
      b.textContent = spec.boss || 'BOSS';
      bossLayer.appendChild(b);
      boss = { node: b, x: config.width + 260, y: 190, w: 190, h: 110, hp: 40 + level * 28, speed: .45 + level * .1, scale: 1 };
      hudUpdate();
    }

    function shoot(power) {
      var maxShots = (config.levels[level] || {}).shots || 80;
      if (cooldown > 0 || shots.length >= maxShots) return;
      cooldown = power ? 18 : 5;
      var s = el(power ? 'shot64 charged' : 'shot64');
      shotsLayer.appendChild(s);
      shots.push({ node: s, x: player.x + player.w - 4, y: player.y + 24, w: power ? 70 : 30, h: power ? 9 : 5, speed: power ? 13 : 11, power: power ? 6 : 1, scale: 1 });
      if (power) charge = 0;
    }

    function damage(amount) {
      player.energy -= amount;
      playerNode.classList.add('hit64');
      setTimeout(function () { playerNode.classList.remove('hit64'); }, 130);
      if (player.energy <= 0) {
        active = false;
        msg.textContent = 'CORE LOST - recarga para reiniciar';
      }
      hudUpdate();
    }

    function nextLevel() {
      level += 1;
      if (level >= config.levels.length) {
        active = false;
        msg.textContent = 'JAH SKYCORE 64 COMPLETADO - NEXUS PURGADO';
        return;
      }
      spawnLevel();
    }

    document.addEventListener('keydown', function (ev) {
      keys[ev.key] = true;
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ', 'Control'].indexOf(ev.key) !== -1) ev.preventDefault();
      if (ev.key === 'Enter') begin();
    });
    document.addEventListener('keyup', function (ev) { keys[ev.key] = false; });

    function begin() {
      if (active) return;
      active = true;
      if (overlay) overlay.style.display = 'none';
      spawnLevel();
      requestAnimationFrame(loop);
    }
    if (start) start.addEventListener('click', begin);

    function loop() {
      if (!active) return;
      var speed = keys.Shift ? player.speed + 2 : player.speed;
      if ((keys.e || keys.E) && dashCooldown <= 0) { player.x += 90; dashCooldown = 80; }
      if (keys.ArrowLeft || keys.a) player.x -= speed;
      if (keys.ArrowRight || keys.d) player.x += speed;
      if (keys.ArrowUp || keys.w) player.y -= speed;
      if (keys.ArrowDown || keys.s) player.y += speed;
      if (keys[' '] || keys.Control) shoot(false);
      if (keys.q || keys.Q) { if (charge >= 100) shoot(true); }
      if (cooldown > 0) cooldown -= 1;
      if (dashCooldown > 0) dashCooldown -= 1;
      charge = clamp(charge + .42, 0, 100);

      player.x = clamp(player.x, 0, config.width - player.w);
      player.y = clamp(player.y, 66, config.height - player.h - 20);
      place(playerNode, player);

      particles.forEach(function (p) {
        p.x -= p.speed;
        if (p.x < -12) p.x = config.width + 12;
        place(p.node, p);
      });

      orbs = orbs.filter(function (o) {
        place(o.node, o);
        if (hit(player, o)) {
          o.node.remove();
          cores += 1;
          score += 80;
          player.energy = clamp(player.energy + 10, 0, 160);
          hudUpdate();
          return false;
        }
        return true;
      });

      shots = shots.filter(function (s) {
        s.x += s.speed;
        place(s.node, s);
        if (s.x > config.width + 90) { s.node.remove(); return false; }
        return true;
      });

      enemies = enemies.filter(function (enemy) {
        enemy.x -= enemy.speed;
        enemy.y += Math.sin((Date.now() / 260) + enemy.x * .01) * .35;
        if (enemy.x < -90) { enemy.x = config.width + 120; enemy.y = 80 + Math.random() * (config.height - 165); }
        place(enemy.node, enemy);
        if (hit(player, enemy)) { enemy.node.remove(); damage(12 + level * 2); return false; }
        for (var i = 0; i < shots.length; i++) {
          if (hit(shots[i], enemy)) {
            enemy.hp -= shots[i].power;
            shots[i].node.remove();
            shots[i].x = config.width + 999;
            if (enemy.hp <= 0) { enemy.node.remove(); score += 35; hudUpdate(); return false; }
          }
        }
        return true;
      });

      if (boss) {
        boss.x -= boss.speed;
        boss.y += Math.sin(Date.now() / 300) * .8;
        place(boss.node, boss);
        if (hit(player, boss)) damage(28);
        shots.forEach(function (s) {
          if (hit(s, boss)) {
            boss.hp -= s.power;
            score += s.power;
            s.node.remove();
            s.x = config.width + 999;
            if (boss.hp <= 0) {
              boss.node.remove();
              boss = null;
              score += 500;
              nextLevel();
            }
          }
        });
      }

      hudUpdate();
      requestAnimationFrame(loop);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-asjah-game="skycore64"]').forEach(start);
  });
}());
