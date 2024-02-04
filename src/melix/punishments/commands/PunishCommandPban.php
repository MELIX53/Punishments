<?php

namespace melix\punishments\commands;

use donatepr\melix\DonatePRManager;
use melix\joinmanager\JoinManager;
use melix\logger\AntiLoggerManager;
use melix\punishments\PunishManager;
use melix\vkmanager\VKManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\Server;
use ppnew\PpNewManager;
use Richen\Perms\PermsMain;

class PunishCommandPban extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if(!$sender->hasPermission("pban") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
            $prefix = PermsMain::getInstance()->searchPrefixToPermission("pban");
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде, команда доступна с привилегии {$prefix}");
            return;
        }

        if(!isset($args[2])){
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/pban ник время§7(в минутах) §bпричина");
            return;
        }

        $name = $args[0];

        if(!is_numeric($args[1])){
            $sender->sendMessage(PunishManager::PREFIX . "Время должно быть числом!");
            return;
        }

        if((int)$args[1] > PunishManager::MAX_BAN){
            $sender->sendMessage(PunishManager::PREFIX . "Максимальное время бана §b".PunishManager::MAX_BAN."§f минут");
            return;
        }

        $time = (int)$args[1] * 60;

        unset($args[0]);
        unset($args[1]);
        $reason = implode(" ", $args);

        if(strlen($reason) < 3){
            $sender->sendMessage(PunishManager::PREFIX . "Слишком короткая причина!");
            return;
        }

        $server = Server::getInstance();
        $opponent = $server->getPlayerByPrefix($name);

        if($sender instanceof Player){
            if(DonatePRManager::getInstance()->isPlayerDonatePR($sender->getLowerCaseName())){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете использовать данную команду с бесплатной привилегии!");
                return;
            }

            if($opponent?->isConnected() and $sender->getLowerCaseName() === $opponent->getLowerCaseName()){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете наказывать самого себя!");
                return;
            }
        }

        if($opponent !== null and $opponent->isConnected()){
            $name = $opponent->getName();
        }

        if(PunishManager::getInstance()->getBanOSData($name) !== null){
            $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$name}§f уже забанен по §gOS");
            return;
        }

        if(PunishManager::getInstance()->getBanData($name) !== null){
            $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$name}§f уже забанен");
            return;
        }

        if($opponent !== null and $opponent->isConnected()){
            $loginMessage = PunishManager::LOGIN_MESSAGE_BAN;
            $loginMessage = str_replace("{order}", $sender->getName(), $loginMessage);
            $loginMessage = str_replace("{reason}", $reason, $loginMessage);
            $loginMessage = str_replace("{time}", PunishManager::parseTime(time() + $time), $loginMessage);
            $loginMessage = str_replace("{donate}", JoinManager::DONATE, $loginMessage);
            $loginMessage = str_replace("{site}", PunishManager::getInstance()->site, $loginMessage);
            AntiLoggerManager::getInstance()->removeALogger($opponent->getLowerCaseName());
            $opponent->kick($loginMessage);
        }

        $broadcastMessage = PunishManager::BROADCAST_MESSAGE_BAN;
        $broadcastMessage = str_replace("{order}", $sender->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{opponent}", $name, $broadcastMessage);
        $broadcastMessage = str_replace("{reason}", $reason, $broadcastMessage);
        $broadcastMessage = str_replace("{time}", PunishManager::parseTime(time() + $time), $broadcastMessage);

        $server->broadcastMessage($broadcastMessage);

        $warnMessage = PunishManager::WARN_MESSAGE;
        $warnMessage = str_replace("{site}", PunishManager::getInstance()->site, $warnMessage);
        $sender->sendMessage(PunishManager::PREFIX . "Вы успешно забанили по нику игрока §b{$name}\n" . $warnMessage);

        $tableName = PunishManager::TABLE_NAME_BANS;
        $timeGenerated = date('Y-m-d H:i:s', time());
        $timeLocking = date('Y-m-d H:i:s', time() + $time);
        $set = PunishManager::getInstance()->mysqli->prepare(
            "INSERT INTO ".$tableName."(opponentName, punishedName, reason, pardoned, url, timeGenerated, timeLocking, confirmed, port) VALUES " .
            "('$name', '".$sender->getName()."', ?, null, null, '$timeGenerated', '$timeLocking', false, '".PunishManager::$PORT."');"
        );
        $set->bind_param("s", $reason);
        $set->execute();

        PpNewManager::getInstance()->config->set(strtolower($name), PpNewManager::BLOCK_TIME);
        VKManager::sendChatMessage("⚠ Ban ⚠ \nИгрок {$sender->getName()}\nзабанил игрока {$name} по нику\nПо причине: {$reason}\nРазбан: ".PunishManager::parseTime(time() + $time)."\n[Таблица {$tableName}]", JoinManager::CHAT_ADMINS);

    }

}