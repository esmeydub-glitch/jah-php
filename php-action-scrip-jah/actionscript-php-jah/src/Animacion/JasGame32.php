<?php

declare(strict_types=1);

namespace Jah\ActionScript\Animacion;

use Jah\Security\JahSalkToken;

final class JasGame32
{
    private int $width = 960;
    private int $height = 540;
    private int $fps = 60;
    private string $title = 'JAS Game32';
    private ?JasGame32Player $player = null;
    private array $levels = [];

    public function __construct(private string $id)
    {
    }

    public function size(int $width, int $height): self
    {
        $this->width = max(320, $width);
        $this->height = max(240, $height);
        return $this;
    }

    public function fps(int $fps): self
    {
        $this->fps = max(1, $fps);
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function player(string $id): JasGame32Player
    {
        $this->player = new JasGame32Player($id);
        return $this->player;
    }

    public function level(string $id): JasGame32Level
    {
        $level = new JasGame32Level($id);
        $this->levels[] = $level;
        return $level;
    }

    public function config(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'width' => $this->width,
            'height' => $this->height,
            'fps' => $this->fps,
            'player' => ($this->player ?? new JasGame32Player('salk01'))->config(),
            'levels' => array_map(static fn(JasGame32Level $level): array => $level->config(), $this->levels),
        ];
    }

    public function render(): string
    {
        $config = $this->config();
        $json = (string) json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = str_replace('</script', '<\/script', $json);
        $token = '';

        if (class_exists(JahSalkToken::class)) {
            $token = JahSalkToken::make([
                'purpose' => 'game32_manifest',
                'game' => $this->id,
                'payload_hash' => JahSalkToken::payloadHash($config),
                'payload' => $config,
            ]);
        }

        return '<main id="' . self::escape($this->id) . '" class="jas-game32" data-asjah-game="skycore32" data-salk-token="' . self::escape($token) . '" style="width:' . $this->width . 'px;height:' . $this->height . 'px;">' .
            '<script type="application/json" class="jas-game32-config">' . $json . '</script>' .
            '<section class="game32-bg layer-back"></section>' .
            '<section class="game32-bg layer-mid"></section>' .
            '<section class="game32-bg layer-front"></section>' .
            '<section class="game32-hud">' .
            '<strong class="game-title">' . self::escape($this->title) . '</strong>' .
            '<span>LEVEL <b data-hud="level">1</b></span>' .
            '<span>SCORE <b data-hud="score">0</b></span>' .
            '<span>ENERGY <b data-hud="energy">' . (int) $config['player']['energy'] . '</b></span>' .
            '<span>TOKENS <b data-hud="tokens">0</b></span>' .
            '<span>SHIELD <b data-hud="shield">OFF</b></span>' .
            '</section>' .
            '<section class="game32-world">' .
            '<div class="player32"></div>' .
            '<div class="shots32"></div>' .
            '<div class="tokens32"></div>' .
            '<div class="particles32"></div>' .
            '<div class="enemies32"></div>' .
            '<div class="boss32"></div>' .
            '</section>' .
            '<section class="game32-start"><button type="button" data-game-start>INICIAR MISION</button><span>WASD/Flechas mover · Espacio dispara</span></section>' .
            '<section class="game32-message">WASD/Flechas mover - Espacio dispara - toma tokens SALK</section>' .
            '</main>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
