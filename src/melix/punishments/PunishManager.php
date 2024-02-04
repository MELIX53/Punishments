<?php

namespace melix\punishments;

use melix\joinmanager\JoinManager;
use melix\punishments\commands\PunishCommandFire;
use melix\punishments\commands\PunishCommandMute;
use melix\punishments\commands\PunishCommandPban;
use melix\punishments\commands\PunishCommandPbanOS;
use melix\punishments\commands\PunishCommandPkick;
use melix\punishments\commands\PunishCommandUnBan;
use melix\punishments\commands\PunishCommandUnBanAll;
use melix\punishments\commands\PunishCommandUnBanOS;
use melix\punishments\commands\PunishCommandUnMute;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class PunishManager extends PluginBase
{

    const SERVER_NAME = "localhost:3306";
    const LOGIN = "";
    const PASSWORD = "";
    const DATA_BASE_NAME = "";
    const KEY_REMOVE = '';
    const URL_REMOVE = 'https://banlist.dygers.fun/api/remove_post';

    const TABLE_NAME_BANS = "table_bans";
    const TABLE_NAME_BANS_OS = "table_bans_os";
    const TABLE_NAME_MUTES = "table_mutes";
    const TABLE_NAME_KICKS = "table_kicks";

    const PREFIX = "§c§lНаказания §r§7× §f";
    const TITLE = "§c§lНаказания";

    const LOGIN_MESSAGE_BAN = "§g* §fВы забанены по нику игроком §g{order}\n§g* §fПричина: §g{reason}\n§g* §fРазбан через: §g{time}\n§g* §fКупить разбан: §b{donate}\n§g* §fАрхив Доказательств: §b{site}";
    const LOGIN_MESSAGE_BAN_OS = "§g* §fВы забанены по §gOS§f игроком §g{order}\n§g* §fПричина: §g{reason}\n§g* §fРазбан через: §g{time}\n§g* §fКупить разбан: §b{donate}\n§g* §fАрхив Доказательств: §b{site}";
    const LOGIN_MESSAGE_KICK = "§g* §fВас кикнул игрок §g{order}\n§g* §fПричина: §g{reason}\n§g* §fАрхив Доказательств: §b{site}";
    const BROADCAST_MESSAGE_BAN = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f забанил по нику игрока §g{opponent}§f\n §g* §fПричина: §g{reason}\n§g* §fРазбан через: §g{time}";
    const BROADCAST_MESSAGE_BAN_OS = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f забанил по §gOS§f игрока §g{opponent}§f\n §g* §fПричина: §g{reason}\n§g* §fРазбан через: §g{time}";
    const BROADCAST_MESSAGE_MUTE = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f замутил игрока §g{opponent}§f\n§g* §fПричина: §g{reason}\n§g* §fРазмут через: §g{time}";
    const BROADCAST_MESSAGE_KICK = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f кикнул игрока §g{opponent}§f\n§g* §fПричина: §g{reason}";
    const BROADCAST_MESSAGE_FIRE = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f поджег игрока §g{opponent}§f\n§g* §fПричина: §g{reason}";
    const BROADCAST_MESSAGE_UNMUTE = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f снял мут с игрока §g{opponent}";
    const BROADCAST_MESSAGE_UNBAN = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f разбанил игрока §g{opponent}§f по нику";
    const BROADCAST_MESSAGE_UNBAN_OS = "§g* §c§lНаказания§r §g*\n§g* §fИгрок §g{order}§f разбанил игрока §g{opponent}§f по §gOS";
    const MESSAGE_MUTE = "§g* §fУ вас нет доступа к §bЧату§f!\n§g* §fНаложил мут §g{order}\n§g* §fПричина: §g{reason}\n§g* §fРазмут через: §g{time}\n§g* §fАрхив Доказательств: §b{site}";
    const WARN_MESSAGE = "§fНе забывайте кидать доказательства в на сайт §g{site}§f в противном случае вы рискуете потерять свою§d привилегию";

    const DEVICE_ID = "DeviceId";

    const MAX_BAN = 10080;
    const MAX_MUTE = 1440;

    public \mysqli $mysqli;

    public array $mutes = [];

    public static int $PORT = 0;//Автоматически меняется на порт сервера (ид сервера - порт сервера)
    public string $site = "";

    public static PunishManager $plugin;

    public function onEnable(): void
    {
        date_default_timezone_set("Europe/Moscow");
        self::$plugin = $this;

        $this->site = "banlist." . JoinManager::DONATE;
        self::$PORT = $this->getServer()->getPort();

        $this->mysqli = new \mysqli(self::SERVER_NAME, self::LOGIN, self::PASSWORD, self::DATA_BASE_NAME);

        $this->getServer()->getLogger()->info("Плагин успешно запущен");

        $this->getServer()->getPluginManager()->registerEvents(new PunishListener(), $this);

        $this->getServer()->getCommandMap()->register("pban", new PunishCommandPban("pban", "§bЗаблокировать игрока по нику"));
        $this->getServer()->getCommandMap()->register("pbanos", new PunishCommandPbanOS("pbanos", "§bЗаблокировать игрока по OS", null, ["pbancid"]));
        $this->getServer()->getCommandMap()->register("mute", new PunishCommandMute("mute", "§bЗаблокировать игроку чат"));
        $this->getServer()->getCommandMap()->register("pkick", new PunishCommandPkick("pkick", "§bВыгнать игрока"));
        $this->getServer()->getCommandMap()->register("fire", new PunishCommandFire("fire", "§bПоджечь игрока"));

        $this->getServer()->getCommandMap()->register("punban", new PunishCommandUnBan("punban", "§bРазблокировать игрока по нику"));
        $this->getServer()->getCommandMap()->register("punbanos", new PunishCommandUnBanOS("punbanos", "§bРазблокировать игрока по OS"));
        $this->getServer()->getCommandMap()->register("unmute", new PunishCommandUnMute("unmute", "§bРазблокировать чат в игрока"));
        $this->getServer()->getCommandMap()->register("punbanall", new PunishCommandUnBanAll("punbanall", "§bСнять все наказания в игрока"));

        $this->isValidPunishments();
    }

    public static function getInstance(): PunishManager
    {
        return self::$plugin;
    }

    public function getMuteCache(string $name): ?array
    {
        if(!isset($this->mutes[$name])) return null;
        return ($this->mutes[$name]['timeLocking'] > time() ? $this->mutes[$name] : null);
    }

    public function setMuteCache(string $name, array $data): void
    {
        $timeLocking = strtotime($data['timeLocking']);
        $data['timeLocking'] = $timeLocking;
        $this->mutes[$name] = $data;
    }

    public function remMuteCache(string $name): void
    {
        if (isset($this->mutes[$name])) unset($this->mutes[$name]);
    }

    public function unAll(string $name, string $pardoned): ?string
    {
        $isMute = $this->unMute($name, $pardoned);
        $isBanOs = $this->unBanOs($name, $pardoned);
        $isBan = $this->unBan($name, $pardoned);
        if (!$isMute and !$isBanOs and !$isBan) return null;
        return
            "Игрок §b{$name}§f получил помилование:\n" .
            "§g* §fСписок помилований:\n" .
            "§g* §fБан по аккаунту: " . ($isBan ? "§aСнят бан" : "§cНе был наказан") . "\n" .
            "§g* §fБлокировка чата: " . ($isMute ? "§aСнят бан" : "§cНе был наказан") . "\n" .
            "§g* §fБан по OS: " . ($isBanOs ? "§aСнят бан" : "§cНе был наказан");
    }

    public function unMute(string $name, string $pardoned): bool
    {
        $muteData = $this->getMuteData($name);
        if ($muteData == null) return false;
        $id = $muteData["id"];
        $tableName = self::TABLE_NAME_MUTES;
        $this->mysqli->query("UPDATE " . $tableName . " SET pardoned = '$pardoned' WHERE id = '$id' AND port = " . self::$PORT . " AND pardoned IS NULL");
        return true;
    }

    public function unBanOs(string $name, string $pardoned): bool
    {
        $banData = $this->getBanOSData($name);
        if ($banData == null) return false;
        $id = $banData["id"];
        $tableName = self::TABLE_NAME_BANS_OS;
        $this->mysqli->query("UPDATE " . $tableName . " SET pardoned = '$pardoned' WHERE id = '$id' AND port = " . self::$PORT . " AND pardoned IS NULL");
        return true;
    }

    public function unBan(string $name, string $pardoned): bool
    {
        $banData = $this->getBanData($name);
        if ($banData == null) return false;
        $id = $banData["id"];
        $tableName = self::TABLE_NAME_BANS;
        $this->mysqli->query("UPDATE " . $tableName . " SET pardoned = '$pardoned' WHERE id = '$id' AND port = " . self::$PORT . " AND pardoned IS NULL");
        return true;
    }

    public function getMuteData(string $name): ?array
    {
        $prepare = $this->mysqli->prepare
        (
            "SELECT * FROM " . self::TABLE_NAME_MUTES . " " .
            "WHERE LOWER(opponentName) = LOWER(?) AND " .
            "port = " . self::$PORT . " AND " .
            "pardoned IS NULL AND " .
            "timeLocking >= NOW()"
        );

        $prepare->bind_param("s", $name);
        $prepare->execute();
        $request = $prepare->get_result();

        return (!$request ? null : $request->fetch_assoc());
    }

    public function getBanOSData(?string $name, ?string $cid = null): ?array
    {
        $where =
            " WHERE (LOWER(opponentName) = LOWER(?) OR clientId = ?) AND " .
            "port = " . self::$PORT . " AND " .
            "pardoned IS NULL AND " .
            "timeLocking >= NOW()";
        $prepare = $this->mysqli->prepare("SELECT * FROM " . self::TABLE_NAME_BANS_OS . $where);
        $prepare->bind_param("ss", $name, $cid);
        $prepare->execute();
        $request = $prepare->get_result();

        return (!$request ? null : $request->fetch_assoc());
    }

    public function getBanData(string $name): ?array
    {
        $prepare = $this->mysqli->prepare(
            "SELECT * FROM " . self::TABLE_NAME_BANS . " " .
            "WHERE LOWER(opponentName) = LOWER(?) AND " .
            "port = " . self::$PORT . " AND " .
            "pardoned IS NULL AND " .
            "timeLocking >= NOW()"
        );
        $prepare->bind_param("s", $name);
        $prepare->execute();
        $request = $prepare->get_result();

        return (!$request ? null : $request->fetch_assoc());
    }

    public static function parseTime($time): string
    {
        $now = time();
        $left = ($time - $now);
        $seconds = $left % 60;
        $minutes = (int)($left / 60);
        if ($minutes >= 60) {
            $hours = (int)($minutes / 60);
            $minutes = $minutes % 60;
        }
        if (@$hours >= 24) {
            $days = (int)($hours / 24);
            $hours = $hours % 24;
        }
        $timeLeft = $seconds . "с.";
        $timeLeft = $minutes . "м. " . $timeLeft;
        if (isset($hours))
            $timeLeft = $hours . "ч. " . $timeLeft;
        if (isset($days))
            $timeLeft = $days . "д. " . $timeLeft;
        return $timeLeft;
    }

    public function isValidPunishments(): void
    {
        $secSevDay = 604800;//7 дней в секундах

        $tables = [
            self::TABLE_NAME_BANS => "id, timeGenerated, port",
            self::TABLE_NAME_BANS_OS => "id, timeGenerated, port",
            self::TABLE_NAME_MUTES => "id, timeGenerated, port",
            self::TABLE_NAME_KICKS => "id, timeGenerated, port"
        ];

        $parseTypeLock = [
            'table_bans' => 'lock_nick',
            'table_bans_os' => 'lock_os',
            'table_kicks' => 'kick',
            'table_mutes' => 'lock_chat'
        ];

        foreach ($tables as $tableName => $columns) {
            if ($request = $this->mysqli->query(
                "SELECT " . $columns . " FROM " . $tableName . " WHERE " .
                "port = " . self::$PORT . " AND " .
                "timeGenerated + INTERVAL $secSevDay SECOND <= NOW()"
            )) {
                while ($data = $request->fetch_assoc()) {
                    $removeUrl = self::URL_REMOVE;

                    $removeData = [
                        'postId' => $data['id'],
                        'typeLock' => $parseTypeLock[$tableName] ?? "none",
                        'removeKey' => self::KEY_REMOVE
                    ];

                    $ch = curl_init($removeUrl);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $removeData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);
                    var_dump(json_decode($response, true));//TODO REMOVE
                    curl_close($ch);
                }
            }
        }
    }

    public function onDisable(): void
    {
        $this->mysqli->close();
    }

}