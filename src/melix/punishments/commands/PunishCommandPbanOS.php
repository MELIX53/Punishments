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

class PunishCommandPbanOS extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if (!$sender->hasPermission("pbancid") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
            $prefix = PermsMain::getInstance()->searchPrefixToPermission("pbancid");
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде, команда доступна с привилегии {$prefix}");
            return;
        }

        if (!isset($args[2])) {
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/pbanos ник время§7(в минутах) §bпричина");
            return;
        }

        $server = Server::getInstance();
        $name = $args[0];

        $opponent = $server->getPlayerByPrefix($name);

        if ($opponent == null or !$opponent->isConnected()) {
            $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$name}§f не онлайн!");
            return;
        }

        $deviceId = $opponent->getNetworkSession()->getPlayerInfo()->getExtraData()[PunishManager::DEVICE_ID];

        if (!is_numeric($args[1])) {
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

        if($sender instanceof Player){
            if(DonatePRManager::getInstance()->isPlayerDonatePR($sender->getLowerCaseName())){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете использовать данную команду с бесплатной привилегии!");
                return;
            }

            if($sender->getLowerCaseName() === $opponent->getLowerCaseName()){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете наказывать самого себя!");
                return;
            }
        }

        $loginMessage = PunishManager::LOGIN_MESSAGE_BAN_OS;
        $loginMessage = str_replace("{order}", $sender->getName(), $loginMessage);
        $loginMessage = str_replace("{reason}", $reason, $loginMessage);
        $loginMessage = str_replace("{time}", PunishManager::parseTime(time() + $time), $loginMessage);
        $loginMessage = str_replace("{donate}", JoinManager::DONATE, $loginMessage);
        $loginMessage = str_replace("{site}", PunishManager::getInstance()->site, $loginMessage);
        AntiLoggerManager::getInstance()->removeALogger($opponent->getLowerCaseName());

        $broadcastMessage = PunishManager::BROADCAST_MESSAGE_BAN_OS;
        $broadcastMessage = str_replace("{order}", $sender->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{opponent}", $name, $broadcastMessage);
        $broadcastMessage = str_replace("{reason}", $reason, $broadcastMessage);
        $broadcastMessage = str_replace("{time}", PunishManager::parseTime(time() + $time), $broadcastMessage);

        $server->broadcastMessage($broadcastMessage);

        $warnMessage = PunishManager::WARN_MESSAGE;
        $warnMessage = str_replace("{site}", PunishManager::getInstance()->site, $warnMessage);
        $sender->sendMessage(PunishManager::PREFIX . "Вы успешно забанили по §gOS§f игрока §b{$name}\n" . $warnMessage);

        $tableName = PunishManager::TABLE_NAME_BANS_OS;
        $timeGenerated = date('Y-m-d H:i:s', time());
        $timeLocking = date('Y-m-d H:i:s', time() + $time);
        $set = PunishManager::getInstance()->mysqli->prepare(
            "INSERT INTO ".$tableName."(opponentName, clientID, punishedName, pardoned, reason, url, timeGenerated, timeLocking, confirmed, port) VALUES " .
            "('$name', '$deviceId', '".$sender->getName()."', null, ?, null, '$timeGenerated', '$timeLocking', false, '".PunishManager::$PORT."');"
        );
        $set->bind_param("s", $reason);
        $set->execute();

        PpNewManager::getInstance()->config->set($opponent->getLowerCaseName(), PpNewManager::BLOCK_TIME);
        VKManager::sendChatMessage("⚠ Ban-OS ⚠ \nИгрок {$sender->getName()}\nзабанил игрока {$opponent->getName()} по OS\nПо причине: {$reason}\nРазбан: ".PunishManager::parseTime(time() + $time)."\n[Таблица {$tableName}]", JoinManager::CHAT_ADMINS);
        $opponent->kick($loginMessage);
    }

}